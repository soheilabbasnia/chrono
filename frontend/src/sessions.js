import { reactive } from 'vue';
import { apiGet, apiPost } from './api.js';

export const sessions = reactive({ list: [], active: null });

export const loadSessions = async () => {
    const { ok, data } = await apiGet('get_sessions');
    if (ok) {
        sessions.list = data.sessions || [];
        sessions.active = data.activeSession || null;
    }
    return ok;
};

export const startSession = async (task) => {
    const now = Date.now();
    const { ok } = await apiPost('start_session', { id: now, startTime: now, task });
    if (ok) await loadSessions();
    return ok;
};

export const stopSession = async () => {
    const { ok } = await apiPost('stop_session', { endTime: Date.now() });
    if (ok) await loadSessions();
    return ok;
};
