import axios from 'axios';
import { pushToast } from '../utils/toast';

const client = axios.create({
    baseURL: '/erp/api',
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        Accept: 'application/json',
    },
});

// Header session picker — read from localStorage to avoid a circular import with store.js.
client.interceptors.request.use((config) => {
    const session = localStorage.getItem('erp_current_session') || '';
    config.headers['X-Academic-Session'] = !session || session === 'All Sessions' || session === 'All' ? 'all' : session;
    return config;
});

// A failed request made with responseType: 'blob' (PDF/ZIP downloads) still returns its error
// body as a Blob, not parsed JSON — so error.response.data.message is always undefined for
// those, and every such failure silently fell back to a generic, undiagnosable message. Read
// the blob back out as text/JSON before falling back, so the real server message surfaces.
async function extractErrorMessage(error) {
    const data = error.response?.data;
    if (data instanceof Blob) {
        try {
            const text = await data.text();
            const parsed = JSON.parse(text);
            if (parsed?.message) return parsed.message;
        } catch {
            // Not JSON (e.g. an HTML error page) — fall through to the generic messages below.
        }
        return null;
    }
    return data?.message || null;
}

client.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (error.response?.status === 401) {
            window.location.href = '/erp/login';
            return Promise.reject(error);
        }

        let message = await extractErrorMessage(error);
        if (!message) {
            if (error.code === 'ECONNABORTED' || /timeout/i.test(error.message || '')) {
                message = 'Import timed out. The workbook is large — please try again (only INCOME/EXPENSES sheets are read now).';
            } else if (!error.response) {
                message = 'Server stopped responding during import (often a PHP timeout). Please try again.';
            } else {
                message = 'Something went wrong. Please try again.';
            }
        }
        pushToast(message, 'error');
        return Promise.reject(error);
    },
);

export default client;
