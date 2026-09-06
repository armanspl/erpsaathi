<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TEMPLATE_NAME = 'Transfer Certificate (Official Format)';

    public function up(): void
    {
        $templateId = DB::table('templates')->insertGetId([
            'category' => 'certificate',
            'render_mode' => 'html',
            'name' => self::TEMPLATE_NAME,
            'is_default' => false,
            'is_favorite' => false,
            'page_width_mm' => 210,
            'page_height_mm' => 297,
            'background_color' => '#ffffff',
            'elements' => json_encode([]),
            'raw_html' => $this->rawHtml(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $type = DB::table('certificate_types')->where('label', 'Transfer Certificate')->first();

        if ($type) {
            DB::table('certificate_types')->where('id', $type->id)->update(['template_id' => $templateId]);
        } else {
            DB::table('certificate_types')->insert([
                'label' => 'Transfer Certificate',
                'prefix' => 'TC',
                'roles' => json_encode(['student']),
                'custom_fields' => json_encode([]),
                'is_system' => true,
                'template_id' => $templateId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $template = DB::table('templates')->where('category', 'certificate')->where('name', self::TEMPLATE_NAME)->first();

        if (! $template) {
            return;
        }

        DB::table('certificate_types')->where('template_id', $template->id)->update(['template_id' => null]);
        DB::table('templates')->where('id', $template->id)->delete();
    }

    private function rawHtml(): string
    {
        return <<<'HTML'
<style>
.tc-page{ width:210mm; box-sizing:border-box; padding:6mm; font-family:'DejaVu Sans', sans-serif; color:#111827; }
.tc-frame{ border:1mm solid #1f2937; padding:4mm 6mm 5mm; position:relative; box-sizing:border-box; }
.tc-topline{ width:100%; font-size:8pt; font-weight:bold; border-collapse:collapse; margin-bottom:3mm; }
.tc-topline td{ padding:0; }
.tc-logo{ position:absolute; left:8mm; top:8mm; width:16mm; height:16mm; object-fit:contain; }
.tc-photo{ position:absolute; right:8mm; top:8mm; width:20mm; height:24mm; border:0.3mm solid #94a3b8; object-fit:cover; }
.tc-head{ text-align:center; margin-bottom:0.5mm; }
.tc-school{ font-size:20pt; font-weight:bold; color:#7c2d12; margin:0 24mm; letter-spacing:0.3px; }
.tc-address{ font-size:8.5pt; font-weight:bold; margin-top:1mm; }
.tc-phone{ font-size:8pt; font-weight:bold; margin-top:0.6mm; }
.tc-titlebar{ border:0.35mm solid #1f2937; background:#e2e8f0; text-align:center; font-weight:bold; font-size:11.5pt; letter-spacing:2.5px; padding:1.3mm 0; margin:2.5mm 0 2mm; }
.tc-refrow{ border:0.3mm solid #1f2937; width:100%; font-size:8pt; font-weight:bold; border-collapse:collapse; margin-bottom:2.5mm; }
.tc-refrow td{ padding:1.2mm 1.5mm; overflow-wrap:break-word; }
.tc-list{ width:100%; font-size:8.2pt; line-height:1.3; border-collapse:collapse; }
.tc-list td{ padding:0.5mm 0; vertical-align:top; overflow-wrap:break-word; }
.tc-list .num{ width:6mm; }
.tc-list .label{ width:112mm; }
.tc-list .colon{ width:4mm; }
.tc-list .value{ width:62mm; font-weight:bold; }
.tc-footer{ width:100%; margin-top:6mm; font-size:8.5pt; font-weight:bold; border-collapse:collapse; }
.tc-footer td{ vertical-align:bottom; }
</style>
<div class="tc-page">
    <div class="tc-frame">
        <img class="tc-logo" src="{{school_logo}}" alt="" />
        <img class="tc-photo" src="{{photo}}" alt="" />
        <table class="tc-topline"><tr>
            <td style="text-align:left;">Reg No.: {{registration_no}}</td>
            <td style="text-align:right;">U-DISE CODE: {{udise_code}}</td>
        </tr></table>
        <div class="tc-head">
            <div class="tc-school">{{school_name}}</div>
            <div class="tc-address">{{school_address}}</div>
            <div class="tc-phone">MOB. {{school_phone}}</div>
        </div>
        <div class="tc-titlebar">TRANSFER CERTIFICATE</div>
        <table class="tc-refrow"><tr>
            <td>Book No. <b>{{book_no}}</b></td>
            <td>SR. No : <b>{{sr_no}}</b></td>
            <td>Admission No : <b>{{admission_id}}</b></td>
            <td>PEN No : <b>{{pen_no}}</b></td>
        </tr></table>
        <table class="tc-list">
            <tr><td class="num">1.</td><td class="label">Name of the Student</td><td class="colon">:-</td><td class="value">{{recipient_name}}</td></tr>
            <tr><td class="num">2.</td><td class="label">Mother's Name</td><td class="colon">:-</td><td class="value">{{mother_name}}</td></tr>
            <tr><td class="num">3.</td><td class="label">Father's Name</td><td class="colon">:-</td><td class="value">{{father_name}}</td></tr>
            <tr><td class="num">4.</td><td class="label">Date of Birth (in Christian Era) according to the Admission &amp; withdrawal Register (in figures)</td><td class="colon">:-</td><td class="value">{{dob_numeric}}</td></tr>
            <tr><td class="num">5.</td><td class="label">Nationality</td><td class="colon">:-</td><td class="value">{{nationality}}</td></tr>
            <tr><td class="num">6.</td><td class="label">Whether the pupil belongs to SC/ST/OBC Category</td><td class="colon">:-</td><td class="value">{{category}}</td></tr>
            <tr><td class="num">7.</td><td class="label">Date of first admission in the school with class</td><td class="colon">:-</td><td class="value">{{admission_date}}</td></tr>
            <tr><td class="num">8.</td><td class="label">Class in which the pupil last studied (in figure)</td><td class="colon">:-</td><td class="value">{{class}}</td></tr>
            <tr><td class="num">9.</td><td class="label">School/ Board Annual examination last taken with result</td><td class="colon">:-</td><td class="value">{{last_exam_result}}</td></tr>
            <tr><td class="num">10.</td><td class="label">Whether failed, if so once/twice in the same class</td><td class="colon">:-</td><td class="value">{{failed_status}}</td></tr>
            <tr><td class="num">11.</td><td class="label">Subject Studied</td><td class="colon">:-</td><td class="value">{{subjects_studied}}</td></tr>
            <tr><td class="num">12.</td><td class="label">Whether qualified for promotion to the higher class, if so, to which class (in Figures)</td><td class="colon">:-</td><td class="value">{{promotion_status}} {{promoted_class}}</td></tr>
            <tr><td class="num">13.</td><td class="label">Total no. of working days in the academic session</td><td class="colon">:-</td><td class="value">{{working_days}}</td></tr>
            <tr><td class="num">14.</td><td class="label">Total no. of presence in the academic session</td><td class="colon">:-</td><td class="value">{{presence_days}}</td></tr>
            <tr><td class="num">15.</td><td class="label">Month upto which the pupil has paid school dues</td><td class="colon">:-</td><td class="value">{{fee_paid_upto}}</td></tr>
            <tr><td class="num">16.</td><td class="label">Any fee concession availed of, if so, the nature of such concession</td><td class="colon">:-</td><td class="value">{{fee_concession}}</td></tr>
            <tr><td class="num">17.</td><td class="label">Whether NCC Cadet/ Boy Scout/ Girl Guide (give details)</td><td class="colon">:-</td><td class="value">{{ncc_activities}}</td></tr>
            <tr><td class="num">18.</td><td class="label">Games played on extra curricular activities in which the pupil usually took part (mention achievement level therein)</td><td class="colon">:-</td><td class="value">{{games_activities}}</td></tr>
            <tr><td class="num">19.</td><td class="label">General conduct</td><td class="colon">:-</td><td class="value">{{general_conduct}}</td></tr>
            <tr><td class="num">20.</td><td class="label">Date of application for certificate</td><td class="colon">:-</td><td class="value">{{application_date}}</td></tr>
            <tr><td class="num">21.</td><td class="label">Date of issue of certificate</td><td class="colon">:-</td><td class="value">{{issue_date_numeric}}</td></tr>
            <tr><td class="num">22.</td><td class="label">Reason for leaving the School</td><td class="colon">:-</td><td class="value">{{purpose}}</td></tr>
            <tr><td class="num">23.</td><td class="label">Any other remarks</td><td class="colon">:-</td><td class="value">{{remarks}}</td></tr>
        </table>
        <table class="tc-footer"><tr>
            <td style="text-align:left;">Checked By<br/>Date: {{issue_date_numeric}}</td>
            <td style="text-align:right;">Sign of Principal &amp; Seal</td>
        </tr></table>
    </div>
</div>
HTML;
    }
};
