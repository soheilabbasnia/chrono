const stack = [];
let listening = false;

const ensureKeyListener = () => {
    if (listening) return;
    listening = true;
    document.addEventListener('keydown', (e) => {
        const top = stack[stack.length - 1];
        if (!top) return;
        if (e.key === 'Escape') {
            if (top.dismissable) { e.preventDefault(); top.close(); }
            return;
        }
        if (e.key === 'Enter') {
            const el = document.activeElement;
            if (el && el.tagName === 'TEXTAREA') return;
            if (top.enterSubmits) { e.preventDefault(); top.submit(); }
        }
    });
};

export const registerModal = (api) => {
    ensureKeyListener();
    stack.push(api);
    return stack.length;
};

export const unregisterModal = (api) => {
    const i = stack.indexOf(api);
    if (i !== -1) stack.splice(i, 1);
};
