<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-bg-main">
        <div class="w-full max-w-md bg-panel border border-border-main rounded-2xl p-6 sm:p-8 shadow-2xl">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-emerald-glow/10 border border-emerald-glow/30 flex items-center justify-center shadow-glow">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-glow" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-main-color">ورود به کرونو</h2>
                    <p class="text-xs text-soft-color">برای دسترسی اطلاعات حساب خود را وارد کنید</p>
                </div>
            </div>
            <div v-if="error" class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-xs">{{ error }}</div>
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-xs text-soft-color mb-2">نام کاربری</label>
                    <input v-model="username" type="text" dir="ltr" required class="w-full h-11 bg-bg-main border border-border-main rounded-xl px-3 text-main-color text-sm text-left focus:outline-none focus:border-emerald-glow">
                </div>
                <div>
                    <label class="block text-xs text-soft-color mb-2">رمز عبور</label>
                    <input v-model="password" type="password" dir="ltr" required class="w-full h-11 bg-bg-main border border-border-main rounded-xl px-3 text-main-color text-sm text-left focus:outline-none focus:border-emerald-glow">
                </div>
                <button type="submit" :disabled="loading" class="w-full h-11 rounded-xl bg-emerald-glow text-bg-main font-bold text-sm shadow-glow hover:opacity-90 active:scale-95 transition-all disabled:opacity-60">{{ loading ? 'در حال ورود...' : 'ورود' }}</button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { login, refreshAuth } from '../store.js';

const username = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

const submit = async () => {
    error.value = '';
    loading.value = true;
    try {
        const { ok, data } = await login(username.value.trim(), password.value);
        if (ok) await refreshAuth();
        else error.value = data.error || 'خطا در ورود';
    } catch (e) {
        error.value = 'خطا در برقراری ارتباط با سرور';
    }
    loading.value = false;
};
</script>
