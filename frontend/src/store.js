import { reactive } from 'vue';
import { apiGet, apiPost, setCsrfToken } from './api.js';

export const auth = reactive({
    ready: false,
    user: null,
    isManager: false,
    canViewUsers: false,
    mustChange: false
});

export const refreshAuth = async () => {
    const { ok, data } = await apiGet('me');
    if (ok && data.user) {
        setCsrfToken(data.csrf_token);
        auth.user = data.user;
        auth.isManager = data.user.role === 'manager';
        auth.canViewUsers = !!data.can_view_users;
        auth.mustChange = !!data.user.must_change_password;
    } else {
        auth.user = null;
        auth.isManager = false;
        auth.canViewUsers = false;
        auth.mustChange = false;
    }
    auth.ready = true;
    return auth;
};

export const login = async (username, password) => {
    const { ok, data } = await apiPost('login', { username, password });
    if (ok) setCsrfToken(data.csrf_token);
    return { ok, data };
};

export const logout = async () => {
    await apiPost('logout', {});
    auth.user = null;
    auth.isManager = false;
    auth.canViewUsers = false;
    auth.mustChange = false;
};
