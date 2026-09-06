import client from '../api/client';

/**
 * Open a PDF in a new tab for viewing only (no download).
 * Pass an existingTab opened synchronously from the click handler to avoid popup blockers.
 */
export function openPdfBlob(blob, existingTab = null) {
    const pdfBlob = blob.type === 'application/pdf'
        ? blob
        : new Blob([blob], { type: 'application/pdf' });
    const url = URL.createObjectURL(pdfBlob);

    if (existingTab && !existingTab.closed) {
        existingTab.location.href = url;
    } else {
        window.open(url, '_blank', 'noopener');
    }

    setTimeout(() => URL.revokeObjectURL(url), 60_000);

    return url;
}

/**
 * Open a PDF in a new tab and trigger a file download.
 * Opens a blank tab synchronously (before await) so browsers don't block the popup.
 */
export function openAndDownloadPdfBlob(blob, filename = 'document.pdf', existingTab = null) {
    const pdfBlob = blob.type === 'application/pdf'
        ? blob
        : new Blob([blob], { type: 'application/pdf' });
    const url = URL.createObjectURL(pdfBlob);
    const name = filename.endsWith('.pdf') ? filename : `${filename}.pdf`;

    if (existingTab && !existingTab.closed) {
        existingTab.location.href = url;
    } else {
        window.open(url, '_blank', 'noopener');
    }

    const a = document.createElement('a');
    a.href = url;
    a.download = name;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();

    // Keep the blob URL alive long enough for the new tab to load
    setTimeout(() => URL.revokeObjectURL(url), 60_000);

    return url;
}

/** Fetch a PDF from an ERP API path, open it in a new tab, and download it. */
export async function openPdf(path, params = {}, filename = 'document.pdf') {
    // Must open synchronously from the click handler before any await.
    const tab = window.open('about:blank', '_blank');
    if (tab) {
        try {
            tab.document.title = 'Loading PDF...';
        } catch {
            // Cross-origin / opaque about:blank — ignore.
        }
    }

    try {
        const response = await client.get(path, { params, responseType: 'blob' });
        return openAndDownloadPdfBlob(response.data, filename, tab);
    } catch (error) {
        if (tab && !tab.closed) {
            tab.close();
        }
        throw error;
    }
}

/** Same as openPdf — kept for call sites that used download-only previously. */
export async function downloadPdf(path, filename = 'document.pdf', params = {}) {
    return openPdf(path, params, filename);
}

/**
 * For call sites that already fetched the blob: open a tab now (sync) then fill it.
 * Prefer downloadPdf/openPdf when you still need to fetch.
 */
export function openAndDownloadPdfBlobFromClick(blob, filename = 'document.pdf') {
    return openAndDownloadPdfBlob(blob, filename, null);
}

/** Download-only helper for non-PDF blobs (ZIP, CSV, JSON). */
export function triggerBlobDownload(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 5_000);
}
