<template>
    <BaseModal max-width="max-w-xl" @submit="save" @close="$emit('close')">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-base sm:text-lg font-bold text-main-color">افزودن همکار جدید</h2>
            <button class="text-soft-color hover:text-main-color" @click="$emit('close')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="space-y-4 mb-6 text-xs sm:text-sm">
            <div>
                <label class="block text-xs text-soft-color mb-2">نام و نام خانوادگی</label>
                <input v-model="fullName" type="text" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color focus:outline-none focus:border-emerald-glow" placeholder="مثال: علی رضایی">
            </div>
            <div>
                <label class="block text-xs text-soft-color mb-2">نام کاربری</label>
                <input v-model="username" type="text" dir="ltr" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-left focus:outline-none focus:border-emerald-glow" placeholder="alirezai">
            </div>
            <div>
                <label class="block text-xs text-soft-color mb-2">رمز عبور</label>
                <input v-model="password" type="password" dir="ltr" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-left focus:outline-none focus:border-emerald-glow">
            </div>
            <div>
                <label class="block text-xs text-soft-color mb-2">نقش کاربری</label>
                <select v-model="role" class="custom-select w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color focus:outline-none focus:border-emerald-glow">
                    <option value="partner">همکار</option>
                    <option value="manager">مدیر</option>
                </select>
            </div>
        </div>
        <div class="flex gap-3">
            <button :disabled="loading" class="h-11 flex-1 rounded-xl bg-emerald-glow text-bg-main font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center disabled:opacity-60" @click="save">{{ loading ? 'در حال ثبت...' : 'ایجاد کاربر' }}</button>
            <button class="h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center" @click="$emit('close')">انصراف</button>
        </div>
    </BaseModal>
</template>

<script setup>
import { ref } from 'vue';
import BaseModal from './BaseModal.vue';
import { apiPost } from '../api.js';
import { showToast } from '../toast.js';
import { validatePasswordClient } from '../utils/format.js';

const emit = defineEmits(['close', 'saved']);
const fullName = ref('');
const username = ref('');
const password = ref('');
const role = ref('partner');
const loading = ref(false);

const save = async () => {
    if (!fullName.value.trim() || !username.value.trim() || !password.value) { showToast('تمام فیلدها الزامی هستند', 'error'); return; }
    const err = validatePasswordClient(password.value);
    if (err) { showToast(err, 'error'); return; }
    loading.value = true;
    try {
        const { ok, data } = await apiPost('admin_create_user', { full_name: fullName.value.trim(), username: username.value.trim(), password: password.value, role: role.value });
        if (ok) { emit('saved'); emit('close'); }
        else { showToast(data.error || 'خطا در ثبت کاربر', 'error'); loading.value = false; }
    } catch (e) { showToast('خطا در برقراری ارتباط با سرور', 'error'); loading.value = false; }
};
</script>
