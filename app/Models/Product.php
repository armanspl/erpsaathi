<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['name', 'sku', 'category', 'unit', 'cost_price', 'reorder_level', 'current_stock'];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
        ];
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }
}
