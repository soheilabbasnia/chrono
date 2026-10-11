<template>
    <BaseModal :dismissable="false" @submit="save" @close="() => {}">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-xl bg-emerald-glow/10 border border-emerald-glow/30 flex items-center justify-center shadow-glow">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-glow" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            </div>
            <div>
                <h2 class="text-base sm:text-lg font-bold text-main-color">تغییر اجباری رمز عبور</h2>
                <p class="text-xs text-soft-color mt-1">برای ادامه، لطفاً یک رمز عبور جدید و امن انتخاب کنید.</p>
            </div>
        </div>
        <div class="space-y-4 mb-6 text-xs sm:text-sm">
            <div>
                <label class="block text-xs text-soft-color mb-2">رمز عبور فعلی (موقت)</label>
                <input v-model="oldPass" type="password" dir="ltr" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-left focus:outline-none focus:border-emerald-glow">
            </div>
            <div>
                <label class="block text-xs text-soft-color mb-2">رمز عبور جدید (حداقل ۱۰ کاراکتر، شامل حرف و عدد)</label>
                <input v-model="newPass" type="password" dir="ltr" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-left focus:outline-none focus:border-emerald-glow">
            </div>
        </div>
        <button :disabled="loading" class="w-full h-11 rounded-xl bg-emerald-glow text-bg-main font-bold text-sm shadow-glow hover:opacity-90 active:scale-95 transition-all disabled:opacity-60" @click="save">{{ loading ? 'در حال ذخیره...' : 'ذخیره و ادامه' }}</button>
    </BaseModal>
</template>

<script setup>
import { ref } from 'vue';
import BaseModal from './BaseModal.vue';
import { apiPost } from '../api.js';
import { auth } from '../store.js';
import { showToast } from '../toast.js';
import { validatePasswordClient } from '../utils/format.js';

const oldPass = ref('');
const newPass = ref('');
const loading = ref(false);

const save = async () => {
    const err = validatePasswordClient(newPass.value);
    if (err) { showToast(err, 'error'); return; }
    loading.value = true;
    try {
        const { ok, data } = await apiPost('change_password', { user_id: auth.user.id, old_password: oldPass.value, new_password: newPass.value });
        if (ok) {
            showToast('رمز عبور با موفقیت تغییر یافت', 'success');
            setTimeout(() => window.location.reload(), 900);
        } else {
            showToast(data.error || 'خطا در تغییر رمز عبور', 'error');
            loading.value = false;
        }
    } catch (e) {
        showToast('خطا در برقراری ارتباط با سرور', 'error');
        loading.value = false;
    }
};
</script>
