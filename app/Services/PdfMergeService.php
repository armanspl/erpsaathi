<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * Concatenate Dompdf PDFs for batch print.
 * Uses FPDI's recommended size handling (no forced rescale) so print matches
 * single-card downloads (same approach as test-school individual PDFs).
 */
class PdfMergeService
{
    private const BATCH_SIZE = 25;

    /**
     * @param  list<string>  $paths  Absolute paths to PDF files on disk
     */
    public function mergeFiles(array $paths): string
    {
        abort_if($paths === [], 422, 'No PDF files to merge.');

        if (count($paths) === 1) {
            $binary = file_get_contents($paths[0]);
            abort_unless($binary !== false && $binary !== '', 500, 'Could not read generated PDF.');

            return $binary;
        }

        if (count($paths) <= self::BATCH_SIZE) {
            return $this->mergeBatch($paths);
        }

        $intermediates = [];
        try {
            foreach (array_chunk($paths, self::BATCH_SIZE) as $chunk) {
                $tmp = tempnam(sys_get_temp_dir(), 'pdf-batch-');
                file_put_contents($tmp, $this->mergeBatch($chunk));
                $intermediates[] = $tmp;
            }

            return $this->mergeFiles($intermediates);
        } finally {
            foreach ($intermediates as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function mergeBatch(array $paths): string
    {
        $pdf = new Fpdi('P', 'mm');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        try {
            foreach ($paths as $file) {
                $pageCount = $pdf->setSourceFile($file);
                for ($page = 1; $page <= $pageCount; $page++) {
                    $tpl = $pdf->importPage($page);
                    $size = $pdf->getTemplateSize($tpl);
                    // Pass the full size array — do not rescale; prevents left-shrink / right blank.
                    $pdf->AddPage($size['orientation'], $size);
                    $pdf->useTemplate($tpl);
                }
            }

            return $pdf->Output('S');
        } catch (Throwable $e) {
            abort(500, 'Could not merge admit card PDFs for printing: '.$e->getMessage());
        }
    }
}
