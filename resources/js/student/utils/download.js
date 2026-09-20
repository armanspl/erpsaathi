import client from '../api/client';

/**
 * Streams a PDF/binary endpoint and triggers a browser download.
 * Failures are already toasted by client.js's response interceptor (it extracts
 * the real message out of a Blob error body) — this just needs to not double-toast.
 */
export async function downloadFile(url, filename) {
    try {
        const response = await client.get(url, { responseType: 'blob' });
        const blobUrl = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(blobUrl);
    } catch {
        // client.js already surfaced a toast for this failure.
    }
}
