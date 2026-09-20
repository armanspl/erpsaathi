import axios from 'axios';
import { pushToast } from '../../erp/utils/toast';

const client = axios.create({
    baseURL: '/student/api',
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        Accept: 'application/json',
    },
});

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
            window.location.href = '/student/login';
            return Promise.reject(error);
        }

        let message = await extractErrorMessage(error);
        if (!message) {
            message = error.response?.status === 403
                ? 'This section is not available to you right now.'
                : 'Something went wrong. Please try again.';
        }
        pushToast(message, 'error');
        return Promise.reject(error);
    },
);

export default client;
