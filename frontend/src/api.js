let csrfToken = '';

export const setCsrfToken = (token) => { if (token) csrfToken = token; };
export const getCsrfToken = () => csrfToken;

export const apiGet = async (action, params = {}) => {
    const qs = new URLSearchParams({ action, ...params }).toString();
    const res = await fetch(`api.php?${qs}`);
    let data = {};
    try { data = await res.json(); } catch (e) { data = {}; }
    return { ok: res.ok, status: res.status, data };
};

export const apiPost = async (action, body = {}) => {
    const res = await fetch(`api.php?action=${encodeURIComponent(action)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify(body)
    });
    let data = {};
    try { data = await res.json(); } catch (e) { data = {}; }
    return { ok: res.ok, status: res.status, data };
};
