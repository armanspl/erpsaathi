<style>
    * { box-sizing: border-box; }
    /* Fixed size inside the 85.6 x 54 mm card page (1.5 mm page margin) so it always stays on one page. */
    .idc { position: relative; width: 100%; height: 50.6mm; overflow: hidden; border: 0.35mm solid #1e3a5f; border-radius: 2.2mm; background: #ffffff; font-family: 'DejaVu Sans', Helvetica, sans-serif; color: #1f2937; }
    .idc table { border-collapse: collapse; }
    .idc td { padding: 0; vertical-align: middle; }
    .hd { background: #1e3a5f; height: 10.5mm; padding: 1.2mm 1.8mm; }
    .hd table { width: 100%; }
    .logo-wrap { width: 8.4mm; height: 8.4mm; background: #ffffff; border-radius: 4.2mm; text-align: center; overflow: hidden; }
    .logo-wrap img { width: 7.6mm; height: 7.6mm; margin-top: 0.4mm; }
    .school { color: #ffffff; font-size: 6.6pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.15pt; line-height: 1.15; text-align: center; }
    .school-sub { color: #cbd5e1; font-size: 4.1pt; line-height: 1.2; text-align: center; margin-top: 0.3mm; }
    .rib { background: #f59e0b; color: #1e3a5f; font-size: 4.9pt; font-weight: bold; letter-spacing: 0.6pt; text-align: center; height: 3.1mm; line-height: 1; padding-top: 0.75mm; text-transform: uppercase; }
    .bd { padding: 1.4mm 1.8mm 0; }
    .bd > table { width: 100%; }
    .bd td { vertical-align: top; }
    .ph { position: relative; width: 16.5mm; height: 20mm; border: 0.35mm solid #1e3a5f; background: #f1f5f9; overflow: hidden; }
    .ph-txt { position: absolute; left: 0; top: 8mm; width: 100%; text-align: center; font-size: 4.5pt; color: #94a3b8; letter-spacing: 0.4pt; }
    .ph img { position: absolute; left: 0; top: 0; width: 16.5mm; height: 20mm; }
    .cardno { font-size: 4.2pt; color: #475569; text-align: center; margin-top: 0.6mm; width: 16.5mm; }
    .name { font-size: 7.6pt; font-weight: bold; color: #0f172a; text-transform: uppercase; line-height: 1.1; margin-bottom: 0.8mm; }
    .rows td { font-size: 4.9pt; line-height: 1.3; padding: 0.05mm 0; vertical-align: top; }
    .rows .l { color: #64748b; width: 12.5mm; }
    .rows .c { color: #64748b; width: 1.6mm; }
    .rows .v { font-weight: bold; color: #1f2937; }
    .addr { display: block; max-height: 4.4mm; overflow: hidden; }
    .sig { position: absolute; right: 2mm; bottom: 6.6mm; text-align: center; width: 15mm; }
    .sig img { height: 3.6mm; }
    .sig-lbl { font-size: 3.9pt; color: #475569; border-top: 0.2mm solid #94a3b8; padding-top: 0.3mm; }
    .ft { position: absolute; left: 0; bottom: 0; width: 100%; height: 5.4mm; background: #1e3a5f; color: #ffffff; font-size: 4.5pt; }
    .ft table { width: 100%; }
    .ft td { color: #ffffff; height: 5.4mm; vertical-align: middle; padding: 0 1.8mm; }
</style>
<div class="doc">
    <div class="idc">
        <div class="hd">
            <table><tr>
                <td style="width: 9mm;"><div class="logo-wrap"><img src="{{ $school_logo }}" alt=""></div></td>
                <td>
                    <div class="school">{{ $school_name }}</div>
                    <div class="school-sub">{{ $school_address }}</div>
                </td>
                <td style="width: 9mm;"></td>
            </tr></table>
        </div>
        <div class="rib">{{ $role_type }} {{ $card_title }}</div>

        <div class="bd">
            <table><tr>
                <td style="width: 18.5mm;">
                    <div class="ph">
                        <div class="ph-txt">PHOTO</div>
                        <img src="{{ $photo }}" alt="">
                    </div>
                    <div class="cardno">{{ $id_number }}</div>
                </td>
                <td style="padding-left: 1.6mm;">
                    <div class="name">{{ $holder_name }}</div>
                    <table class="rows">
                        <tr><td class="l">Class</td><td class="c">:</td><td class="v">{{ $class_section }}</td></tr>
                        <tr><td class="l">Adm. No.</td><td class="c">:</td><td class="v">{{ $admission_no }}</td></tr>
                        <tr><td class="l">Father</td><td class="c">:</td><td class="v">{{ $father_name }}</td></tr>
                        <tr><td class="l">D.O.B.</td><td class="c">:</td><td class="v">{{ $dob }}</td></tr>
                        <tr><td class="l">Blood Gr.</td><td class="c">:</td><td class="v">{{ $blood_group }}</td></tr>
                        <tr><td class="l">Mobile</td><td class="c">:</td><td class="v">{{ $contact_no }}</td></tr>
                        <tr><td class="l">Address</td><td class="c">:</td><td class="v"><span class="addr">{{ $address }}</span></td></tr>
                    </table>
                </td>
            </tr></table>
        </div>

        <div class="sig">
            <img src="{{ $principal_signature_image }}" alt="">
            <div class="sig-lbl">{{ $signature }}</div>
        </div>

        <div class="ft">
            <table><tr>
                <td>Valid till: <b>{{ $valid_until }}</b></td>
                <td style="text-align: right;">{{ $school_phone }}</td>
            </tr></table>
        </div>
    </div>
</div>
