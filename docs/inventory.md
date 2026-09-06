# Inventory

See [README.md](README.md) for cross-cutting architecture (routing, permission model, generic-page mechanism, audit logging, and the "computed, not stored" calculator-service pattern) — only module-specific detail is repeated here.

## 1. Module Overview

Inventory is a small, fully-real module (every one of its 6 menu leaves is backed by a real controller/table — there are no generic placeholders in this module) covering basic stock-room bookkeeping for a school: a `Product` catalog with a running `current_stock` counter, `Supplier` contacts, `Purchase` orders that receive stock in, and an append-only `StockAdjustment` ledger for manual corrections (damage, loss, recount). `Low Stock Alerts` and `Inventory Reports` are both lightweight, live-computed read views over the same `products`/`purchases` data — neither is a stored table.

There is no barcode/warehouse-location tracking, no multi-warehouse support, and no goods-return/purchase-return flow. Stock only ever moves through two paths: a `Purchase` (always additive) or a `StockController::adjust` entry (`Addition` or `Reduction`).

## 2. Menu Structure

Menu group `Inventory` (`resources/js/erp/data/menu.js`), 6 children — **all real**:

| Menu Label | Path | Status | Real backend |
|---|---|---|---|
| Products | `/inventory/products` | **Real** | `ProductController` |
| Purchase | `/inventory/purchase` | **Real** | `PurchaseController` |
| Stock | `/inventory/stock` | **Real** | `StockController` |
| Supplier | `/inventory/supplier` | **Real** | `SupplierController` |
| Low Stock Alerts | `/inventory/low-stock-alerts` | **Real** | `LowStockAlertController` (live-computed, no dedicated table) |
| Inventory Reports | `/inventory/inventory-reports` | **Real** | `InventoryReportController` (live-computed via `InventoryReportCalculator`) |

Confirmed against `FLAGSHIP_ROUTES` in `resources/js/erp/router/index.js` (all 6 `/inventory/*` paths are mapped to real Vue components) and `routes/erp_api.php`'s `Route::prefix('inventory')` group (lines 450–474).

---

## 3–9. Pages

Backend routes live under `Route::prefix('inventory')->name('inventory.')` in `routes/erp_api.php`. Reads are open to any authenticated `erp` user; every write route is wrapped in `Route::middleware('erp.permission:inventory.manage')`. The permission key `inventory.manage` is registered in `resources/js/erp/pages/settings/Roles.vue`'s `availablePermissions` with label "Inventory".

### 3.1 Products

- **Frontend**: `/inventory/products` → `resources/js/erp/pages/inventory/Products.vue`
- **Backend**: `GET|POST /erp/api/inventory/products`, `PUT|DELETE /erp/api/inventory/products/{product}`
- **Controller**: `App\Http\Controllers\Erp\Inventory\ProductController`
- **Permissions**: index open; store/update/destroy require `inventory.manage`.

**Validation — store**:
```
name           => required|string|max:255
sku            => required|string|max:100|unique:products,sku
category       => nullable|string|max:100
unit           => required|string|max:20
cost_price     => required|numeric|min:0
reorder_level  => required|integer|min:0
opening_stock  => nullable|integer|min:0
```
**Validation — update** (note: does **not** include `opening_stock`; matches the UI, which hides that field once editing):
```
name           => required|string|max:255
sku            => required|string|max:100|unique:products,sku,{product->id}
category       => nullable|string|max:100
unit           => required|string|max:20
cost_price     => required|numeric|min:0
reorder_level  => required|integer|min:0
```

**Form Layout**: slide-over — Name, two-column (SKU / Category), two-column (Unit, placeholder "pcs, box, kg…" / Cost Price), two-column (Reorder Level / Opening Stock — Opening Stock only shown when adding, not editing).

**Fields**:

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Name | `name` | `products` | `string` | Required | `required\|string\|max:255` | — | — | — |
| SKU | `sku` | `products` | `string`, unique | Required | `required\|string\|max:100\|unique` | — | — | — |
| Category | `category` | `products` | `string`, nullable | Optional | `nullable\|string\|max:100` | `null` | — | — |
| Unit | `unit` | `products` | `string` | Required | `required\|string\|max:20` | `pcs` (both column default and form default) | — | — |
| Cost Price | `cost_price` | `products` | `decimal(10,2)` | Required | `required\|numeric\|min:0` | `0` | — | — |
| Reorder Level | `reorder_level` | `products` | `unsignedInteger` | Required | `required\|integer\|min:0` | `0` | — | — |
| Opening Stock *(create-only)* | maps to `current_stock` at insert | `products` | `unsignedInteger` | Optional | `nullable\|integer\|min:0` | `0` | — | — |
| Current Stock *(not directly form-editable after create)* | `current_stock` | `products` | `unsignedInteger` | n/a on update | not in update's validated array | `0` | — | — |

**Database Relationships** (`app/Models/Product.php`): `stockAdjustments()` → `hasMany(StockAdjustment::class)`. Also referenced by `purchases.items[].product_id` (a JSON array element, not a formal FK — see §10.3) and `stock_adjustments.product_id` (a real FK).

**Create Flow**: `ProductController::store()` builds the insert array explicitly, mapping the form's `opening_stock` input onto the `current_stock` column (`'current_stock' => $data['opening_stock'] ?? 0`) — single insert into `products`, no side effects on other tables.

**Edit Flow**: `Product::update()` with the 6 validated fields; `current_stock` is untouched by this endpoint — it can only change via a `Purchase` (§3.3) or a `StockController::adjust` call (§3.4), never directly through the Products form once a product exists. This is intentional: stock quantity is meant to move only through an auditable transaction (purchase or adjustment), not a silent edit.

**Delete Flow**: hard delete (no `SoftDeletes`, no `deleted_at` column). `stock_adjustments.product_id` is `restrictOnDelete()` — a product with any adjustment history cannot be deleted (fails at the DB level with a foreign-key error, no pre-check in the controller). Purchases reference products only inside the `items` JSON column, so a product can still be deleted even if it appears in past purchase line items — that history becomes orphaned/unresolvable by id only within the JSON blob (the `product_name` snapshot, per §10.3, keeps the purchase row readable regardless).

### 3.2 Supplier

- **Frontend**: `/inventory/supplier` → `resources/js/erp/pages/inventory/Supplier.vue`
- **Backend**: `GET|POST /erp/api/inventory/suppliers`, `PUT|DELETE /erp/api/inventory/suppliers/{supplier}`
- **Controller**: `App\Http\Controllers\Erp\Inventory\SupplierController`
- **Permissions**: index open; store/update/destroy require `inventory.manage`.

**Validation — store & update** (identical):
```
name    => required|string|max:255
contact => nullable|string|max:50
email   => nullable|email|max:255
address => nullable|string|max:255
```

**Form Layout**: slide-over — Name, Contact, Email, Address (all single-column, stacked).

**Fields**:

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Name | `name` | `suppliers` | `string` | Required | `required\|string\|max:255` | — | — | — |
| Contact | `contact` | `suppliers` | `string`, nullable | Optional | `nullable\|string\|max:50` | `null` | — | — |
| Email | `email` | `suppliers` | `string`, nullable | Optional | `nullable\|email\|max:255` | `null` | — | — |
| Address | `address` | `suppliers` | `string`, nullable | Optional | `nullable\|string\|max:255` | `null` | — | — |

**Database Relationships**: none declared on `Supplier` itself; `Purchase::supplier()` → `belongsTo(Supplier::class)`.

**Create/Edit Flow**: straightforward single-table insert/update, no side effects.

**Delete Flow**: hard delete. `purchases.supplier_id` is `restrictOnDelete()` — a supplier with any purchase history cannot be deleted (DB-level FK error, no pre-check).

### 3.3 Purchase

- **Frontend**: `/inventory/purchase` → `resources/js/erp/pages/inventory/Purchase.vue`
- **Backend**: `GET /erp/api/inventory/purchases`, `POST /erp/api/inventory/purchases`
- **Controller**: `App\Http\Controllers\Erp\Inventory\PurchaseController` — **only `index` and `store` exist.**
- **Permissions**: index open; store requires `inventory.manage`.
- **KNOWN LIMITATION**: `routes/erp_api.php` defines **no `PUT`/update and no `DELETE` route for Purchase at all** (contrast with Products/Suppliers, which have full CRUD). A purchase, once recorded, is permanent — there is no way to edit its items or void it through this module. This is a current limitation of the implementation, not a bug being fixed by this documentation. (Reversing a bad purchase's stock effect requires a manual `StockController::adjust` "Reduction" entry — there is no purpose-built reversal flow.)

**Validation — store**:
```
supplier_id           => required|exists:suppliers,id
purchase_date         => required|date
items                 => required|array|min:1
items.*.product_id    => required|exists:products,id
items.*.quantity      => required|integer|min:1
items.*.unit_cost     => required|numeric|min:0
remarks               => nullable|string|max:255
```
`voucher_no`, `total_amount`, and `purchased_by_id` are server-derived — not client-suppliable.

**Form Layout**: slide-over — Supplier (select), Purchase Date, a repeatable "Items" list (each row: Product select, Quantity number, Unit Cost number, remove button; "+ Add Item" appends a blank row), a read-only computed Total, Remarks.

**Fields**:

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Supplier | `supplier_id` | `purchases` | `unsignedBigInteger` | Required | `required\|exists:suppliers,id` | — | `GET /inventory/suppliers` | `suppliers.id`, `restrictOnDelete` |
| Purchase Date | `purchase_date` | `purchases` | `date` | Required | `required\|date` | today | — | — |
| Items (line item: Product) | `items[].product_id` (inside JSON) | `purchases` | JSON element | Required | `required\|exists:products,id` (per item) | — | `GET /inventory/products` | logical reference into `products.id` — **not** a DB-level FK (JSON column, see §10.3) |
| Items (line item: Quantity) | `items[].quantity` | `purchases` | JSON element | Required | `required\|integer\|min:1` (per item) | `1` | — | — |
| Items (line item: Unit Cost) | `items[].unit_cost` | `purchases` | JSON element | Required | `required\|numeric\|min:0` (per item) | `0` | — | — |
| Remarks | `remarks` | `purchases` | `string`, nullable | Optional | `nullable\|string\|max:255` | `null` | — | — |
| Voucher No *(server-derived)* | `voucher_no` | `purchases` | `string`, unique | n/a | — | see §10.1 | — | — |
| Total Amount *(server-derived)* | `total_amount` | `purchases` | `decimal(10,2)` | n/a | — | `Σ(quantity × unit_cost)` across items | — | — |
| Purchased By *(server-derived)* | `purchased_by_id` | `purchases` | `unsignedBigInteger`, nullable | n/a | — | `Auth::guard('erp')->id()` | — | `erp_users.id`, `nullOnDelete` |

**Database Relationships** (`app/Models/Purchase.php`): `supplier()` → `belongsTo(Supplier::class)`; `purchasedBy()` → `belongsTo(ErpUser::class, 'purchased_by_id')`. `items` is cast `'array'` — Eloquent (de)serializes it to/from the `json` column automatically, but there is no `PurchaseItem` child model or table.

**Create Flow** (`PurchaseController::store()`, wrapped in `DB::transaction`):
1. Look up product names for every `product_id` in the submitted items (`Product::whereIn('id', ...)->pluck('name', 'id')`).
2. Build the `items` array server-side — each element gets `product_id`, a **snapshotted** `product_name`, `quantity`, `unit_cost`, and a computed `total` (`quantity * unit_cost`, rounded to 2 decimals). The client's raw item objects are never stored verbatim; the server recomputes `total` and injects `product_name`.
3. Insert one `Purchase` row: `voucher_no` (§10.1), `supplier_id`, `purchase_date`, the built `items` array (stored as JSON), `total_amount` (sum of item totals), `remarks`, `purchased_by_id`.
4. For every item, `Product::where('id', $item['product_id'])->increment('current_stock', $item['quantity'])` — this is the **only** place a `Purchase` touches the `products` table, and it always adds.
5. All of steps 3–4 happen inside a single DB transaction, so a partial failure (e.g. a product deleted mid-request) rolls back the whole purchase rather than leaving stock incremented without a purchase row or vice versa.

**Edit Flow**: **not available** — no update route exists (see limitation above).

**Delete Flow**: **not available** — no delete route exists. Stock added by a purchase is never automatically reversible; correcting it requires a manual Stock Adjustment (§3.4) "Reduction" entry.

### 3.4 Stock

- **Frontend**: `/inventory/stock` → `resources/js/erp/pages/inventory/Stock.vue`
- **Backend**: `GET /erp/api/inventory/stock` (same payload as Products' index — every product with its current stock), `GET /erp/api/inventory/stock/adjustments` (history for one product), `POST /erp/api/inventory/stock/adjust`
- **Controller**: `App\Http\Controllers\Erp\Inventory\StockController` (`index`, `adjustments`, `adjust`) — **there is no plain CRUD here at all.** No `store`/`update`/`destroy` in the REST sense exist for stock; the only mutation is `adjust`, which is additive/subtractive by design, not a form that edits a stored quantity directly.
- **Permissions**: `index`/`adjustments` open; `adjust` requires `inventory.manage`.

**IMPORTANT — append-only ledger, by design**: every stock change (other than a Purchase, §3.3) goes through `POST stock/adjust`, which **inserts a new `StockAdjustment` row and increments/decrements `Product.current_stock`** — it never updates an existing adjustment or lets `current_stock` be set directly to an arbitrary value. There is no `PUT`/`DELETE` route for `StockAdjustment` in `routes/erp_api.php`. This is intentional business design (an audit trail of every correction, not a gap): if a past adjustment was wrong, the fix is to record a new, opposite adjustment, not to edit history.

**Validation — `GET stock/adjustments`**: `product_id => required|exists:products,id` (query param).

**Validation — `POST stock/adjust`**:
```
product_id => required|exists:products,id
type       => required|in:Addition,Reduction
quantity   => required|integer|min:1
reason     => nullable|string|max:255
date       => required|date
```
Additional manual guard (not a Laravel validation rule): if `type === 'Reduction'` and `quantity > $product->current_stock`, the controller throws `ValidationException::withMessages(['quantity' => "Cannot reduce stock by more than the current stock ({$product->current_stock})."])` — stock is never allowed to go negative through this endpoint.

**Form Layout**: product list (same columns as Products, plus a Status badge derived from `current_stock <= reorder_level`) with a per-row "Adjust" button opening a slide-over — Type (select: Addition/Reduction), two-column (Quantity / Date), Reason (text, placeholder "Damaged, lost, correction…").

**Fields**:

| Label | DB Column | Table | Data Type | Required | Validation | Default | Dropdown Source | FK |
|---|---|---|---|---|---|---|---|---|
| Product | `product_id` | `stock_adjustments` | `unsignedBigInteger` | Required | `required\|exists:products,id` | — | implicit (row context, "Adjust" button on a specific product) | `products.id`, `restrictOnDelete` |
| Type | `type` | `stock_adjustments` | `enum(Addition,Reduction)` | Required | `required\|in:Addition,Reduction` | `Addition` | fixed 2-option select | — |
| Quantity | `quantity` | `stock_adjustments` | `unsignedInteger` | Required | `required\|integer\|min:1` + manual "not more than current stock" check on Reduction | `1` | — | — |
| Reason | `reason` | `stock_adjustments` | `string`, nullable | Optional | `nullable\|string\|max:255` | `null` | — | — |
| Date | `date` | `stock_adjustments` | `date` | Required | `required\|date` | today | — | — |
| Adjusted By *(server-derived)* | `adjusted_by_id` | `stock_adjustments` | `unsignedBigInteger`, nullable | n/a | — | `Auth::guard('erp')->id()` | — | `erp_users.id`, `nullOnDelete` |

**Database Relationships** (`app/Models/StockAdjustment.php`): `product()` → `belongsTo(Product::class)`; `adjustedBy()` → `belongsTo(ErpUser::class, 'adjusted_by_id')`.

**Create Flow** (`StockController::adjust()`, wrapped in `DB::transaction`): insert one `StockAdjustment` row, then `Product::increment/decrement('current_stock', ...)` on the same request — both happen atomically inside the transaction.

**Edit Flow**: **not available** by design — see the append-only note above.

**Delete Flow**: **not available** — no delete route. A wrong adjustment is corrected with an opposite adjustment, never removed.

### 3.5 Low Stock Alerts

- **Frontend**: `/inventory/low-stock-alerts` → `resources/js/erp/pages/inventory/LowStockAlerts.vue`
- **Backend**: `GET /erp/api/inventory/low-stock-alerts`
- **Controller**: `App\Http\Controllers\Erp\Inventory\LowStockAlertController::index`
- **Permissions**: open to any authenticated user.

Read-only, computed view — no form, no create/edit/delete, no dedicated table. Query: `Product::whereColumn('current_stock', '<=', 'reorder_level')->orderBy('current_stock')->get()`. The UI additionally computes a per-row "Shortfall" (`reorder_level - current_stock`) client-side. See §10.4 for the threshold rule.

### 3.6 Inventory Reports

- **Frontend**: `/inventory/inventory-reports` → `resources/js/erp/pages/inventory/InventoryReports.vue`
- **Backend**: `GET /erp/api/inventory/reports`
- **Controller**: `App\Http\Controllers\Erp\Inventory\InventoryReportController::index`, delegating entirely to `App\Services\InventoryReportCalculator::summary()`
- **Permissions**: open to any authenticated user.

Read-only, computed page — no form, no create/edit/delete; not a stored table. See §12 for the calculation.

---

## 10. Business Rules

### 10.1 Purchase voucher numbering (server-generated, not user-suppliable)

`PurchaseController::nextVoucherNo()`: `sprintf('PO-%s-%04d', $year, $count)` where `$year = now()->format('Y')` and `$count = Purchase::where('voucher_no', 'like', "PO-{$year}-%")->count() + 1`. Example: `PO-2026-0001`. Same `COUNT + 1`-per-year pattern as `Expense`/`Income` in Finance & Payroll (see `finance-payroll.md` §10.1) — not concurrency-safe against a true race, but consistent across the app.

### 10.2 Stock only ever moves through two auditable paths

1. **Purchase** (§3.3) — always additive (`increment('current_stock', $item['quantity'])`), one entry per purchase line item, never subtracts.
2. **Stock Adjustment** (§3.4) — `Addition` (increment) or `Reduction` (decrement, blocked from taking stock negative). This is the only path for corrections, initial-stock fixes, damage/loss write-offs, or manual recounts.

There is **no third path**: `Product::current_stock` cannot be set to an arbitrary value once the product exists (the Products edit form's validated fields don't include `current_stock`/`opening_stock` — see §3.1). `opening_stock` only applies once, at product creation.

### 10.3 `Purchase.items` is a JSON column, not normalized child rows

`purchases.items` is a single `json` column (migration comment: `// [{ product_id, product_name, quantity, unit_cost, total }]`), cast to `array` on the `Purchase` model — there is no `purchase_items` table and no `PurchaseItem` model. This mirrors the same pattern used by `FeePayment.items` in Fee Management (see README "Computed, not stored" / cross-module note): a purchase's line items are a fixed, self-contained snapshot of what was bought, at what price, from whom, on that date — they don't need to be independently queried, joined, or updated after the fact (since there's no edit route at all, §3.3), so a normalized child table would add relational overhead for zero benefit. The trade-off: you cannot run a SQL-level `WHERE` across individual purchase line items (e.g., "all purchases containing product X") without decoding the JSON in PHP or using MySQL JSON functions — `InventoryReportCalculator::summary()` handles this by loading every `Purchase` and flat-mapping `items` in PHP (`Purchase::get()->flatMap(fn ($p) => $p->items)`) rather than querying at the SQL level.

### 10.4 Low-stock threshold logic

A product is "low stock" whenever `current_stock <= reorder_level` — a simple, per-product, statically-configured threshold (`reorder_level`, set at product creation/edit, §3.1). This exact comparison is used in three independent places, all reading live from `products` with no caching or stored "is_low_stock" flag:
- `LowStockAlertController::index()` — `Product::whereColumn('current_stock', '<=', 'reorder_level')`.
- `InventoryReportCalculator::summary()` — `$products->filter(fn ($p) => $p->current_stock <= $p->reorder_level)` (in-PHP, same condition).
- `Products.vue` and `Stock.vue` client-side (`p.current_stock <= p.reorder_level`) for the red/amber status badge and the "Low Stock" stat card — purely a display computation, redundant with (but consistent with) the two backend implementations.

There's no severity tiering (e.g., "critical" vs "warning") and no configurable per-product alert channel — it's a binary flag purely from the stock-vs-reorder-level comparison.

---

## 11. Import & Export

Inventory has **neither Import nor Export for any page**. `App\Http\Controllers\Erp\ImportExport\ExportController::ENTITIES` is `['student', 'fee', 'expense', 'salary', 'bank', 'fuel-log', 'route', 'marks', 'global']` — no `product`, `supplier`, `purchase`, or `stock` entity exists, so none of Products/Purchase/Stock/Supplier/Low Stock Alerts/Inventory Reports appear in the Global Workbook Export either. There is no importer for any Inventory entity (the only real importer in the whole app is Student — see README §"Import & Export"). The Import & Export module's own menu (`Global Workbook Import/Export`, `Student/Fee/Expense/Salary/Bank/Fuel Log/Route/Marks Import/Export`) has no Inventory-specific leaves at all.

---

## 12. Reports

- **Inventory Reports** (§3.6) is the module's one dedicated report page: `InventoryReportCalculator::summary()` returns `total_products`, `total_stock_units` (Σ `current_stock`), `total_stock_value` (Σ `current_stock × cost_price`), `low_stock_count`, `total_suppliers`, `purchases_this_month` (count, filtered `purchase_date` between start-of-month and today), `purchase_value_this_month` (Σ `total_amount` for the same range), `low_stock_list` (same shape as Low Stock Alerts), and `top_purchased` (top 5 products by total quantity purchased all-time, computed by flat-mapping every `Purchase.items` in PHP — §10.3).
- **Low Stock Alerts** (§3.5) is a lightweight computed alert view, not a full report — a single filtered product list with no aggregation beyond the count shown in its stat card.
- Inventory data does **not** feed the cross-module Reports module (`/reports/*`) — there is no "Inventory Reports" leaf under the Reports module's own menu group, and `FinanceReportCalculator`/other Reports-module calculators never reference `Product`, `Purchase`, `Supplier`, or `StockAdjustment`. Inventory's reporting is entirely self-contained within its own menu group.

---

## 13. APIs

Base path: `/erp/api/inventory/*`. All require the `erp.auth` session guard; writes additionally require `erp.permission:inventory.manage`.

| Method | Path | Controller@method | Permission |
|---|---|---|---|
| GET | `products` | `ProductController@index` | any authenticated user |
| POST | `products` | `ProductController@store` | `inventory.manage` |
| PUT | `products/{product}` | `ProductController@update` | `inventory.manage` |
| DELETE | `products/{product}` | `ProductController@destroy` | `inventory.manage` |
| GET | `suppliers` | `SupplierController@index` | any authenticated user |
| POST | `suppliers` | `SupplierController@store` | `inventory.manage` |
| PUT | `suppliers/{supplier}` | `SupplierController@update` | `inventory.manage` |
| DELETE | `suppliers/{supplier}` | `SupplierController@destroy` | `inventory.manage` |
| GET | `purchases` | `PurchaseController@index` | any authenticated user |
| POST | `purchases` | `PurchaseController@store` | `inventory.manage` (no update/delete route exists) |
| GET | `stock` | `StockController@index` | any authenticated user |
| GET | `stock/adjustments?product_id=` | `StockController@adjustments` | any authenticated user |
| POST | `stock/adjust` | `StockController@adjust` | `inventory.manage` (no update/delete route exists) |
| GET | `low-stock-alerts` | `LowStockAlertController@index` | any authenticated user |
| GET | `reports` | `InventoryReportController@index` | any authenticated user |

**Example — record a purchase**

Request: `POST /erp/api/inventory/purchases`
```json
{
  "supplier_id": 2,
  "purchase_date": "2026-07-30",
  "items": [
    { "product_id": 5, "quantity": 20, "unit_cost": 45.00 },
    { "product_id": 8, "quantity": 10, "unit_cost": 120.00 }
  ],
  "remarks": "Term restock"
}
```
Response `201`:
```json
{
  "id": 14,
  "voucher_no": "PO-2026-0014",
  "supplier_id": 2,
  "purchase_date": "2026-07-30",
  "items": [
    { "product_id": 5, "product_name": "A4 Paper Ream", "quantity": 20, "unit_cost": 45.0, "total": 900.0 },
    { "product_id": 8, "product_name": "Whiteboard Marker", "quantity": 10, "unit_cost": 120.0, "total": 1200.0 }
  ],
  "total_amount": "2100.00",
  "remarks": "Term restock",
  "purchased_by_id": 7,
  "supplier": { "id": 2, "name": "ABC Stationers" },
  "purchased_by": { "id": 7, "name": "Admin User" }
}
```
Side effect: `products.current_stock` for products `5` and `8` are incremented by `20` and `10` respectively.

**Example — adjust stock down**

Request: `POST /erp/api/inventory/stock/adjust`
```json
{ "product_id": 5, "type": "Reduction", "quantity": 3, "reason": "Damaged in storage", "date": "2026-07-31" }
```
Response `201`:
```json
{
  "id": 9,
  "product_id": 5,
  "type": "Reduction",
  "quantity": 3,
  "reason": "Damaged in storage",
  "date": "2026-07-31",
  "adjusted_by_id": 7,
  "product": { "id": 5, "name": "A4 Paper Ream", "current_stock": 17 }
}
```
If `quantity` (3) had exceeded the product's `current_stock`, the request would instead return a `422` with `{"message": "...", "errors": {"quantity": ["Cannot reduce stock by more than the current stock (X)."]}}`.

---

## 14. Database Tables

| Table | PK | FKs | Unique | Notes |
|---|---|---|---|---|
| `products` | `id` | — | `sku` | `unit` default `'pcs'`, `cost_price`/`reorder_level`/`current_stock` default `0` |
| `suppliers` | `id` | — | — | — |
| `purchases` | `id` | `supplier_id → suppliers.id` (restrict), `purchased_by_id → erp_users.id` (null on delete) | `voucher_no` | `items` is `json`, not normalized (§10.3) |
| `stock_adjustments` | `id` | `product_id → products.id` (restrict), `adjusted_by_id → erp_users.id` (null on delete) | — | append-only, no update/delete route (§10.2, §3.4) |

No table in this module has a `deleted_at` column or the `SoftDeletes` trait — every delete listed in this document is a hard delete.

---

## 15. File Storage

None. No controller in this module reads or writes to any disk (`Storage::`) — no product images, purchase invoices/receipts, or supplier documents are uploaded anywhere in Inventory.

---

## 16. Background Jobs

None. Purchase recording and stock adjustment both run synchronously inside their request (each wrapped in a `DB::transaction`, not a queued job); nothing in this module implements `ShouldQueue` or is dispatched to a queue.

---

## 17. Notifications

None. Neither crossing the low-stock threshold nor recording a purchase/adjustment sends an email, SMS, push notification, or in-app notification to anyone. `Low Stock Alerts` (§3.5) is a page a user has to actively visit — there is no proactive alert, badge, or scheduled digest tied to it.

---

## 18. Module Dependencies

- Inventory depends on **no other ERP module**. `Product`, `Supplier`, `Purchase`, and `StockAdjustment` reference only each other and `ErpUser` (for `purchased_by_id`/`adjusted_by_id`, both `nullOnDelete`, purely attribution — not a functional dependency on the People module's own entities like `Teacher`/`Staff`/`Student`).
- Inventory feeds **only its own Reports leaf** (`InventoryReportCalculator`, §12) — no other module's report or calculator reads `products`, `suppliers`, `purchases`, or `stock_adjustments`. It is not referenced by `FinanceReportCalculator`, the Reports module, or Import & Export (§11).
- Nothing in Academics, People, Fee Management, or any other module writes to or reads from Inventory's tables.

---

## 19. Screenshots

Not available — generated from source code.

---

## 20. Final Deliverable

Inventory is a small, fully-real, self-contained module: a product catalog with a running stock counter, supplier contacts, purchases that always add stock (server-computed voucher numbers and totals, product names snapshotted into a JSON `items` column rather than a normalized child table), and an append-only stock-adjustment ledger that is the only path for corrections — by design, never a directly-editable `current_stock` field. Low Stock Alerts and Inventory Reports are both pure read-time computations over `products`/`purchases`, with no stored aggregate and no proactive notification when a product crosses its reorder threshold. The module has no Import/Export support at all (unlike Finance & Payroll's Expense/Salary/Bank exports) and two deliberate, documented CRUD gaps: Purchases can be created and read but never edited or deleted, and Stock Adjustments can be created and read but never edited or deleted — both are intentional consequences of treating purchases and adjustments as an immutable transaction history rather than editable records.
