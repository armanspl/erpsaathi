<style>
    * { box-sizing: border-box; }
    .doc { width: 100%; margin: 0; padding: 0; font-family: 'DejaVu Serif', Georgia, serif; color: #1f2937; background: #fff; }
    .frame { border: 0.55mm solid #b8860b; padding: 2mm; height: 100%; }
    .top { text-align: center; border-bottom: 0.3mm solid #d6cbb0; padding-bottom: 1.2mm; margin-bottom: 1.5mm; }
    .logo { width: 7mm; height: 7mm; object-fit: cover; border-radius: 50%; vertical-align: middle; margin-right: 1mm; }
    .school { font-size: 7pt; font-weight: bold; color: #1e3a5f; vertical-align: middle; }
    .title { font-size: 5.5pt; color: #7c5c00; text-transform: uppercase; letter-spacing: 0.35pt; margin-top: 0.6mm; }
    .body { width: 100%; border-collapse: collapse; }
    .body td { border: none; vertical-align: top; padding: 0; }
    .photo { width: 16mm; height: 19mm; object-fit: cover; border: 0.3mm solid #b8860b; }
    .name { font-size: 8pt; font-weight: bold; margin: 0 0 0.6mm; color: #0f172a; }
    .id { font-size: 6pt; color: #64748b; margin-bottom: 1.2mm; }
    .row { font-size: 5.8pt; margin: 0.4mm 0; }
    .lbl { color: #64748b; }
    .val { font-weight: bold; color: #1f2937; }
    .foot { text-align: center; font-size: 5pt; color: #64748b; margin-top: 1.5mm; border-top: 0.25mm solid #e5e7eb; padding-top: 1mm; }
    .sig { text-align: center; margin-top: 0.8mm; }
    .sig img { height: 4.5mm; }
    .sig-lbl { font-size: 4.5pt; color: #64748b; }
</style>
<div class="doc">
    <div class="frame">
        <div class="top">
            <img class="logo" src="{{ $school_logo }}" alt=""><span class="school">{{ $school_name }}</span>
            <div class="title">{{ $card_title }}</div>
        </div>
        <table class="body"><tr>
            <td style="width:18mm"><img class="photo" src="{{ $photo }}" alt=""></td>
            <td style="padding-left:2mm">
                <div class="name">{{ $holder_name }}</div>
                <div class="id">{{ $id_number }} · {{ $role_type }}</div>
                <div class="row"><span class="lbl">Class:</span> <span class="val">{{ $class_section }}</span></div>
                <div class="row"><span class="lbl">Blood:</span> <span class="val">{{ $blood_group }}</span>
                    &nbsp;&nbsp;<span class="lbl">Contact:</span> <span class="val">{{ $contact_no }}</span></div>
                <div class="row"><span class="lbl">Valid until:</span> <span class="val">{{ $valid_until }}</span></div>
            </td>
        </tr></table>
        <div class="foot">Code: {{ $barcode }}</div>
        <div class="sig">
            <img src="{{ $principal_signature_image }}" alt="">
            <div class="sig-lbl">{{ $signature }}</div>
        </div>
    </div>
</div>
