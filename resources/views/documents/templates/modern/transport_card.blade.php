<style>
    * { box-sizing: border-box; }
    .doc { width: 100%; margin: 0; padding: 0 0 2mm; font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; color: #111827; background: #fff; }
    .accent { height: 2.2mm; margin: 0 0 2mm; background: #0f766e; }
    .top { text-align: center; margin-bottom: 1.5mm; }
    .logo { width: 7mm; height: 7mm; object-fit: cover; border-radius: 1mm; vertical-align: middle; margin-right: 1mm; }
    .school { font-size: 7pt; font-weight: bold; color: #111827; vertical-align: middle; }
    .title {
        display: inline-block; margin-top: 0.8mm; padding: 0.6mm 2.5mm; border-radius: 1mm;
        background: #f0fdfa; color: #0f766e; font-size: 5.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3pt;
    }
    .body { width: 100%; border-collapse: collapse; }
    .body td { border: none; vertical-align: top; padding: 0; }
    .photo { width: 16mm; height: 19mm; object-fit: cover; border: 0.3mm solid #99f6e4; border-radius: 1.5mm; }
    .name { font-size: 8pt; font-weight: bold; margin: 0 0 0.6mm; }
    .id { font-size: 6pt; color: #6b7280; margin-bottom: 1.2mm; }
    .row { font-size: 5.8pt; margin: 0.45mm 0; }
    .lbl { color: #9ca3af; }
    .val { font-weight: bold; color: #111827; }
    .route {
        margin-top: 1.2mm; padding: 1mm 1.5mm; background: #f0fdfa; border: 0.25mm solid #99f6e4; border-radius: 1mm;
        font-size: 5.8pt;
    }
    .foot { text-align: center; font-size: 5pt; color: #9ca3af; margin-top: 1.5mm; border-top: 0.25mm solid #e5e7eb; padding-top: 1mm; }
</style>
<div class="doc">
    <div class="accent"></div>
    <div class="top">
        <img class="logo" src="{{ $school_logo }}" alt=""><span class="school">{{ $school_name }}</span>
        <div><span class="title">{{ $card_title }}</span></div>
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
