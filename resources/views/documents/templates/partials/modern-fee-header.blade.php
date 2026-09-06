{{-- Shared compact header for modern Fee Receipt / Fee Due Receipt.
     Title + school + address stack in the center cell (DomPDF-safe). Logos unchanged. --}}
@php
    $titleBg = $title_bg ?? '#4f46e5';
    $titleFg = $title_fg ?? '#ffffff';
@endphp
<style>
    .fee-hdr { width: 100%; border-collapse: collapse; margin: 0; line-height: 1; }
    .fee-hdr td { border: none; padding: 0; margin: 0; vertical-align: top; line-height: 1; }
    .fee-hdr-logo { width: 22mm; }
    .fee-hdr-logo .doc-logo,
    .fee-hdr-logo-right .doc-logo {
        width: 20mm;
        height: 20mm;
        object-fit: contain;
        display: block;
        margin: 0;
        padding: 0;
    }
    .fee-hdr-logo-right { width: 22mm; text-align: right; }
    .fee-hdr-logo-right .doc-logo { margin-left: auto; }
    .fee-hdr-center { text-align: center; padding-top: 0.5mm !important; }
    .fee-hdr-title {
        display: inline-block;
        margin: 0 0 0.4mm;
        padding: 0.6mm 4mm;
        background: {{ $titleBg }};
        color: {{ $titleFg }};
        font-size: 9pt;
        font-weight: bold;
        letter-spacing: 0.3pt;
        text-transform: uppercase;
        line-height: 1;
        border-radius: 1mm;
    }
    .fee-hdr-school {
        display: block;
        text-align: center;
        font-size: 13pt;
        font-weight: bold;
        color: #111827;
        margin: 0 0 0.3mm;
        padding: 0;
        line-height: 1;
    }
    .fee-hdr-addr {
        display: block;
        text-align: center;
        font-size: 7.5pt;
        color: #6b7280;
        margin: 0;
        padding: 0;
        line-height: 1;
    }
</style>
<table class="fee-hdr">
    <tr>
        <td class="fee-hdr-logo"><img class="doc-logo" src="{{ $school_logo }}" alt=""></td>
        <td class="fee-hdr-center">
            <div class="fee-hdr-title">{{ $receipt_title }}</div>
            <div class="fee-hdr-school">{{ $school_name }}</div>
            <div class="fee-hdr-addr">Address : {{ $school_address }}</div>
        </td>
        <td class="fee-hdr-logo-right"><img class="doc-logo" src="{{ $school_logo }}" alt=""></td>
    </tr>
</table>
