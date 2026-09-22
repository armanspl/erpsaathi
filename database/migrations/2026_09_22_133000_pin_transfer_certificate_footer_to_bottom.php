<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Checked By / Sign of Principal & Seal" was sitting right under field 23 instead of at
 * the physical bottom of the page. Two earlier attempts (position:fixed nested inside
 * .tc-page, and a flex/percentage-height table) failed silently or didn't move it, because
 * the render wrapper forces `.tc-page { overflow: hidden !important }` — any fixed-position
 * child nested inside it gets clipped once it renders below .tc-page's own (short, auto-height)
 * box, and Dompdf doesn't reliably resolve percentage/flex heights on tables either.
 *
 * The fix: move the footer to be a sibling *after* `.tc-page` closes, so it's outside that
 * clipping box, and give it `position: fixed; bottom: 4mm;` — Dompdf's well-supported pattern
 * for content pinned to the page edge. Content is otherwise unchanged from the last polish
 * pass (same fonts, gaps, borders removed, single-page fit).
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('templates')
            ->where('category', 'certificate')
            ->where('raw_html', 'like', '%tc-page%')
            ->get(['id']);

        foreach ($rows as $row) {
            DB::table('templates')->where('id', $row->id)->update([
                'raw_html' => $this->html(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Layout polish — not reverted.
    }

    private function html(): string
    {
        return <<<'HTML'
<style>
.tc-page {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    margin: 0 auto;
    padding: 0 6mm;   /* 👈 left-right padding add kiya, top-bottom 0 rakha */
    font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
    color: #111827;
}

/* generic fake-table replacement — real tables, fixed layout */
.tc-row-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
}
.tc-row-table td {
    vertical-align: middle;
    box-sizing: border-box;
}

/* TOP LINE */
.tc-topline-row td {
    font-size: 8.5pt;
    font-weight: bold;
    padding-bottom: 2mm;
}
.tc-topline-row td:first-child { text-align: left; width: 50%; }
.tc-topline-row td:last-child  { text-align: right; width: 50%; }

/* SCHOOL HEADER */
.tc-logo-row td { vertical-align: middle; }
.tc-logo-row .tc-logo-cell        { width: 26mm; }
.tc-logo-row .tc-logo-cell-right  { width: 18mm; text-align: right; }
.tc-logo-row .tc-head             { text-align: center; padding: 0 2mm; }

.tc-logo { width: 23mm; height: 23mm; object-fit: contain; }

.tc-school { font-size: 23pt; font-weight: bold; color: #1e3a8a; letter-spacing: 0.3px; margin: 0; white-space: nowrap; }
.tc-address { font-size: 8pt; font-weight: bold; margin-top: 1mm; white-space: nowrap; }
.tc-phone { font-size: 8pt; font-weight: bold; margin-top: 0.6mm; }

/* TITLE BAR */
.tc-titlebar {
    width: 100%;
    box-sizing: border-box;
    border: 0.35mm solid #1f2937;
    background: #d1d5db;
    text-align: center;
    font-weight: bold;
    font-size: 11.5pt;
    letter-spacing: 2.5px;
    text-decoration: underline;
    padding: 1.4mm 0;
    margin: 1.5mm 0;
}

/* REFERENCE INFORMATION ROW */
.tc-refrow-row td {
    font-size: 8.5pt;
    font-weight: bold;
    padding-bottom: 1.5mm;
    padding-right: 1mm;
}
.tc-refrow-row td:nth-child(1) { width: 14%; }
.tc-refrow-row td:nth-child(2) { width: 19%; }
.tc-refrow-row td:nth-child(3) { width: 19%; }
.tc-refrow-row td:nth-child(4) { width: 19%; }
.tc-refrow-row td:nth-child(5) { width: 29%; padding-right: 0; }

/* MAIN DETAILS TABLE — unchanged, already a real table */
.tc-list, .tc-list tr, .tc-list td { border: none !important; outline: none; }
.tc-list {
    width: 100%;
    max-width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 10pt;
    line-height: 1.15;
    box-sizing: border-box;
}
.tc-list td { padding: 0.55mm 0; vertical-align: top; border: none !important; outline: none; box-sizing: border-box; overflow-wrap: anywhere; word-wrap: break-word; }
.tc-list .num { width: 5%; padding-right: 2mm; }
.tc-list .label { width: 60%; padding-right: 2mm; }
.tc-list .colon { width: 5%; text-align: center; padding: 0 1.5mm; }
.tc-list .value { width: 30%; font-weight: bold; padding-left: 2mm; }

/* FOOTER — pinned to the physical bottom of the page, outside .tc-page so its own
   overflow:hidden (forced by the render wrapper) never clips it. */
.tc-footer-fixed {
    position: fixed;
    bottom: 4mm;
    left: 6mm;
    right: 6mm;
}
.tc-footer-row td {
    font-size: 8.5pt;
    font-weight: bold;
    vertical-align: bottom;
}
.tc-footer-row .tc-footer-left    { text-align: left; width: 42%; }
.tc-footer-row .tc-footer-spacer  { width: 16%; }
.tc-footer-row .tc-footer-right   { border: none !important; outline: none; text-align: right; width: 42%; }
.tc-sig-img { max-height: 11mm; max-width: 36mm; margin-bottom: 1mm; }
.tc-sig-wrap { display: inline-block; text-align: center; border: none !important; outline: none; box-shadow: none; }
.tc-checked-date { display: block; padding-top: 4mm; margin: 0; line-height: 1.2; }
</style>

<div class="tc-page">

    <table class="tc-row-table tc-topline-row">
        <tr>
            <td>Reg No.: {{registration_no}}</td>
            <td>U-DISE CODE: {{udise_code}}</td>
        </tr>
    </table>

    <table class="tc-row-table tc-logo-row">
        <tr>
            <td class="tc-logo-cell"><img class="tc-logo" src="{{school_logo}}" alt="" /></td>
            <td class="tc-head">
                <div class="tc-school">{{school_name}}</div>
                <div class="tc-address">{{school_address}}</div>
                <div class="tc-phone">MOB. {{school_phone}}</div>
            </td>
            <td class="tc-logo-cell-right"></td>
        </tr>
    </table>

    <div class="tc-titlebar">TRANSFER CERTIFICATE</div>

    <table class="tc-row-table tc-refrow-row">
        <tr>
            <td>Book No. <b>{{book_no}}</b></td>
            <td>SR. No : <b>{{sr_no}}</b></td>
            <td>Admission No : <b>{{admission_id}}</b></td>
            <td>PEN No : <b>{{pen_no}}</b></td>
            <td>Aadhar No : <b>{{aadhar_no}}</b></td>
        </tr>
    </table>


    <!-- =========================
         STUDENT DETAILS
         ========================= -->

    <table class="tc-list">

        <tr>
            <td class="num">1.</td>
            <td class="label">Name of the Student</td>
            <td class="colon">:-</td>
            <td class="value">{{recipient_name}}</td>
        </tr>

        <tr>
            <td class="num">2.</td>
            <td class="label">Mother's Name</td>
            <td class="colon">:-</td>
            <td class="value">{{mother_name}}</td>
        </tr>

        <tr>
            <td class="num">3.</td>
            <td class="label">Father's Name</td>
            <td class="colon">:-</td>
            <td class="value">{{father_name}}</td>
        </tr>

        <tr>
            <td class="num">4.</td>
            <td class="label">
                Date of Birth (in Christian Era) according to the Admission &amp; withdrawal Register (in figures)
            </td>
            <td class="colon">:-</td>
            <td class="value">{{dob_numeric}}</td>
        </tr>

        <tr>
            <td class="num">5.</td>
            <td class="label">Nationality</td>
            <td class="colon">:-</td>
            <td class="value">{{nationality}}</td>
        </tr>

        <tr>
            <td class="num">6.</td>
            <td class="label">
                Whether the pupil belongs to SC/ST/OBC Category
            </td>
            <td class="colon">:-</td>
            <td class="value">{{category}}</td>
        </tr>

        <tr>
            <td class="num">7.</td>
            <td class="label">
                Date of first admission in the school with class
            </td>
            <td class="colon">:-</td>
            <td class="value">{{admission_date}}</td>
        </tr>

        <tr>
            <td class="num">8.</td>
            <td class="label">
                Class in which the pupil last studied (in figure)
            </td>
            <td class="colon">:-</td>
            <td class="value">{{class}}</td>
        </tr>

        <tr>
            <td class="num">9.</td>
            <td class="label">
                School/ Board Annual examination last taken with result
            </td>
            <td class="colon">:-</td>
            <td class="value">{{last_exam_result}}</td>
        </tr>

        <tr>
            <td class="num">10.</td>
            <td class="label">
                Whether failed, if so once/twice in the same class
            </td>
            <td class="colon">:-</td>
            <td class="value">{{failed_status}}</td>
        </tr>

        <tr>
            <td class="num">11.</td>
            <td class="label">Subject Studied</td>
            <td class="colon">:-</td>
            <td class="value">{{subjects_studied}}</td>
        </tr>

        <tr>
            <td class="num">12.</td>
            <td class="label">
                Whether qualified for promotion to the higher class
            </td>
            <td class="colon">:-</td>
            <td class="value">{{promotion_status}}</td>
        </tr>

        <tr>
            <td class="num"></td>
            <td class="label">
                if so, to which class (in Figures)
            </td>
            <td class="colon">:</td>
            <td class="value">{{promoted_class}}</td>
        </tr>

        <tr>
            <td class="num">13.</td>
            <td class="label">
                Total no. of working days in the academic session
            </td>
            <td class="colon">:-</td>
            <td class="value">{{working_days}}</td>
        </tr>

        <tr>
            <td class="num">14.</td>
            <td class="label">
                Total no. of presence in the academic session
            </td>
            <td class="colon">:-</td>
            <td class="value">{{presence_days}}</td>
        </tr>

        <tr>
            <td class="num">15.</td>
            <td class="label">
                Month upto which the pupil has paid school dues
            </td>
            <td class="colon">:-</td>
            <td class="value">{{fee_paid_upto}}</td>
        </tr>

        <tr>
            <td class="num">16.</td>
            <td class="label">
                Any fee concession availed of, if so, the nature of such concession
            </td>
            <td class="colon">:-</td>
            <td class="value">{{fee_concession}}</td>
        </tr>

        <tr>
            <td class="num">17.</td>
            <td class="label">
                Whether NCC Cadet/ Boy Scout/ Girl Guide (give details)
            </td>
            <td class="colon">:-</td>
            <td class="value">{{ncc_activities}}</td>
        </tr>

        <tr>
            <td class="num">18.</td>
            <td class="label">
                Games played on extra curricular activities in which the pupil usually took part (mention achievement level therein)
            </td>
            <td class="colon">:-</td>
            <td class="value">{{games_activities}}</td>
        </tr>

        <tr>
            <td class="num">19.</td>
            <td class="label">General conduct</td>
            <td class="colon">:-</td>
            <td class="value">{{general_conduct}}</td>
        </tr>

        <tr>
            <td class="num">20.</td>
            <td class="label">
                Date of application for certificate
            </td>
            <td class="colon">:-</td>
            <td class="value">{{application_date}}</td>
        </tr>

        <tr>
            <td class="num">21.</td>
            <td class="label">
                Date of issue of certificate
            </td>
            <td class="colon">:-</td>
            <td class="value">{{issue_date_numeric}}</td>
        </tr>

        <tr>
            <td class="num">22.</td>
            <td class="label">
                Reason for leaving the School
            </td>
            <td class="colon">:-</td>
            <td class="value">{{purpose}}</td>
        </tr>

        <tr>
            <td class="num">23.</td>
            <td class="label">
                Any other remarks
            </td>
            <td class="colon">:-</td>
            <td class="value">{{remarks}}</td>
        </tr>

    </table>

</div>

<!-- =========================
     FOOTER — pinned to the physical bottom of the page
     ========================= -->

<div class="tc-footer-fixed">
     <table class="tc-row-table tc-footer-row">
        <tr>
            <td class="tc-footer-left">Checked By<br /><div class="tc-checked-date">Date: {{issue_date_numeric}}</div></td>
            <td class="tc-footer-spacer"></td>
            <td class="tc-footer-right"><div class="tc-sig-wrap"><img src="{{principal_signature_image}}" class="tc-sig-img" alt="" /><br />Sign of Principal &amp; Seal</div></td>
        </tr>
    </table>
</div>
HTML;
    }
};
