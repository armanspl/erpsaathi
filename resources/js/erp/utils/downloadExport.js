import client from '../api/client';

/** Turns an axios blob response into an actual browser file download. */
function triggerBlobDownload(response, fallbackFilename) {
    const disposition = response.headers['content-disposition'] || '';
    const match = disposition.match(/filename="?([^";]+)"?/i);
    const filename = match ? match[1] : fallbackFilename;

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
}

/**
 * Download an ERP export blob.
 * @param {string} entity
 * @param {string} format  xlsx | csv | pdf
 * @param {{ status?: string, sessions?: string[], columns?: string[] }} [filters]
 */
export async function downloadExport(entity, format = 'xlsx', filters = {}) {
    const params = { format };

    if (filters.status && filters.status !== 'All') {
        params.status = filters.status;
    }

    if (Array.isArray(filters.sessions) && filters.sessions.length && !filters.sessions.includes('All')) {
        params.sessions = filters.sessions;
    }

    if (Array.isArray(filters.columns) && filters.columns.length && !filters.columns.includes('All')) {
        params.columns = filters.columns;
    }

    const response = await client.get(`/import-export/export/${entity}`, {
        params,
        responseType: 'blob',
        // Axios serializes array params as sessions[]=a&sessions[]=b by default — fine for Laravel.
        paramsSerializer: {
            serialize: (p) => {
                const parts = [];
                Object.entries(p).forEach(([key, val]) => {
                    if (Array.isArray(val)) {
                        val.forEach((v) => parts.push(`${encodeURIComponent(key)}[]=${encodeURIComponent(v)}`));
                    } else if (val !== undefined && val !== null && val !== '') {
                        parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(val)}`);
                    }
                });
                return parts.join('&');
            },
        },
    });

    triggerBlobDownload(response, `${entity}-export.${format}`);
}

/** Download one of a student's uploaded documents (photo, Aadhaar, PAN, etc.) — see StudentController::downloadDocument(). */
export async function downloadStudentDocument(studentId, type, fallbackFilename) {
    const response = await client.get(`/people/students/${studentId}/documents/${type}`, {
        responseType: 'blob',
    });

    triggerBlobDownload(response, fallbackFilename);
}
