import { reactive } from 'vue';

export const toastState = reactive({ items: [] });

let seq = 0;

export const showToast = (message, type = 'info') => {
    const id = ++seq;
    toastState.items.push({ id, message, type });
    setTimeout(() => removeToast(id), 3800);
    return id;
};

export const removeToast = (id) => {
    const idx = toastState.items.findIndex(t => t.id === id);
    if (idx !== -1) toastState.items.splice(idx, 1);
};
