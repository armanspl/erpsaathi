{{-- Shared compact header for classic Fee Receipt / Fee Due Receipt (DomPDF-safe).
     Title + school + address stack in the center cell so DomPDF does not leave a
     large gap under a logo-tall row. Logos stay at current size/position.
     Expects: $receipt_title, $school_logo, $school_name, $school_address
     Optional: $school_phone, $show_phone (bool), $title_bg (hex, default green) --}}
@php
    $titleBg = $title_bg ?? '#15803d';
    $showPhone = ! empty($show_phone);
@endphp
<style>
    .doc-header { text-align: center; margin: 0; padding: 0; line-height: 1; }
    .doc-header-top { width: 100%; border-collapse: collapse; margin: 0; padding: 0; }
    .doc-header-top td { border: none; padding: 0; margin: 0; vertical-align: top; line-height: 1; }
    .doc-logo { width: 20mm; height: 20mm; object-fit: contain; display: block; margin: 0; padding: 0; }
    .doc-logo-cell { width: 22mm; }
    .doc-logo-cell-right { width: 22mm; text-align: right; }
    .doc-logo-cell-right .doc-logo { margin-left: auto; }
    .doc-header-center { text-align: center; padding-top: 0.5mm !important; }
    .doc-title {
        display: inline-block;
        margin: 0 0 0.4mm;
        padding: 0.6mm 5.5mm;
        background: {{ $titleBg }};
        border-top: 0.5mm solid #1e3a8a;
        border-bottom: 0.5mm solid #1e3a8a;
        color: #ffffff;
        font-size: 10pt;
        font-weight: bold;
        letter-spacing: 0.3pt;
        line-height: 1;
    }
    .doc-school-name {
        display: block;
        font-size: 16pt;
        font-weight: bold;
        color: #1d4ed8;
        text-align: center;
        margin: 0 0 0.3mm;
        padding: 0;
        line-height: 1;
    }
    .doc-school-sub {
        display: block;
        font-size: 8pt;
        color: #334155;
        text-align: center;
        margin: 0;
        padding: 0;
        line-height: 1;
    }
    .doc-school-sub.bold { font-weight: bold; margin-top: 0.2mm; }
</style>
<div class="doc-header">
    <table class="doc-header-top">
        <tr>
            <td class="doc-logo-cell"><img class="doc-logo" src="{{ $school_logo }}" alt=""></td>
            <td class="doc-header-center">
                <div class="doc-title">{{ $receipt_title }}</div>
                <div class="doc-school-name">{{ $school_name }}</div>
                <div class="doc-school-sub">Address : {{ $school_address }}</div>
                @if($showPhone && ! empty($school_phone))
                    <div class="doc-school-sub bold">Contact No. {{ $school_phone }}</div>
                @endif
            </td>
            <td class="doc-logo-cell-right"><img class="doc-logo" src="{{ $school_logo }}" alt=""></td>
        </tr>
    </table>
</div>
