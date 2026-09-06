<style>
    * { box-sizing: border-box; }
    .doc { width: 100%; margin: 0; padding: 0; font-family: 'DejaVu Serif', Georgia, serif; color: #1f2937; background: #fff; }
    .frame { border: 0.55mm solid #0f766e; padding: 2mm; }
    .top { text-align: center; border-bottom: 0.3mm solid #99f6e4; padding-bottom: 1.2mm; margin-bottom: 1.5mm; }
    .logo { width: 7mm; height: 7mm; object-fit: cover; border-radius: 50%; vertical-align: middle; margin-right: 1mm; }
    .school { font-size: 7pt; font-weight: bold; color: #134e4a; vertical-align: middle; }
    .title { font-size: 5.5pt; color: #0f766e; text-transform: uppercase; letter-spacing: 0.35pt; margin-top: 0.6mm; font-weight: bold; }
    .body { width: 100%; border-collapse: collapse; }
    .body td { border: none; vertical-align: top; padding: 0; }
    .photo { width: 16mm; height: 19mm; object-fit: cover; border: 0.3mm solid #0f766e; }
    .name { font-size: 8pt; font-weight: bold; margin: 0 0 0.6mm; color: #0f172a; }
    .id { font-size: 6pt; color: #64748b; margin-bottom: 1.2mm; }
    .row { font-size: 5.8pt; margin: 0.45mm 0; }
    .lbl { color: #64748b; }
    .val { font-weight: bold; color: #1f2937; }
    .route {
        margin-top: 1.2mm; padding: 1mm 1.5mm; background: #f0fdfa; border: 0.25mm solid #99f6e4;
        font-size: 5.8pt;
    }
    .foot { text-align: center; font-size: 5pt; color: #64748b; margin-top: 1.5mm; border-top: 0.25mm solid #e5e7eb; padding-top: 1mm; }
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
                <div class="name">{{ $student_name }}</div>
                <div class="id">{{ $admission_id }} · {{ $class_section }}</div>
                <div class="route">
                    <div class="row"><span class="lbl">Route:</span> <span class="val">{{ $route_name }}</span></div>
                    <div class="row"><span class="lbl">Stop:</span> <span class="val">{{ $pickup_stop }}</span></div>
                </div>
                <div class="row" style="margin-top:1mm"><span class="lbl">Driver:</span> <span class="val">{{ $driver_name }}</span>
                    &nbsp;&nbsp;<span class="lbl">Vehicle:</span> <span class="val">{{ $vehicle_no }}</span></div>
            </td>
        </tr></table>
        <div class="foot">Valid until {{ $valid_until }}</div>
    </div>
</div>
