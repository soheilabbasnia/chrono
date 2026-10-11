import { reactive } from 'vue';

const THEME_KEY = 'chronos_theme';

export const theme = reactive({ value: localStorage.getItem(THEME_KEY) || 'dark' });

export const applyTheme = (t) => {
    theme.value = t;
    if (t === 'light') document.documentElement.classList.add('light');
    else document.documentElement.classList.remove('light');
    localStorage.setItem(THEME_KEY, t);
};

export const toggleTheme = () => applyTheme(theme.value === 'light' ? 'dark' : 'light');

applyTheme(theme.value);
