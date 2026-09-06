import { reactive } from 'vue';

export const toasts = reactive([]);
let uid = 0;

export function pushToast(message, type = 'info') {
    const id = ++uid;
    toasts.push({ id, message, type });
    setTimeout(() => dismissToast(id), 3500);
}

export function dismissToast(id) {
    const idx = toasts.findIndex((t) => t.id === id);
    if (idx !== -1) toasts.splice(idx, 1);
}
