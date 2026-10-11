<template>
    <BaseModal max-width="max-w-xl" @submit="save" @close="$emit('close')">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-base sm:text-lg font-bold text-main-color">تغییر رمز عبور <span v-if="forOther">«{{ userName }}»</span></h2>
            <button class="text-soft-color hover:text-main-color" @click="$emit('close')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="space-y-4 mb-6 text-xs sm:text-sm">
            <div v-if="showOld">
                <label class="block text-xs text-soft-color mb-2">رمز عبور فعلی</label>
                <input v-model="oldPass" type="password" dir="ltr" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-left focus:outline-none focus:border-emerald-glow">
            </div>
            <div>
                <label class="block text-xs text-soft-color mb-2">رمز عبور جدید</label>
                <input v-model="newPass" type="password" dir="ltr" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-left focus:outline-none focus:border-emerald-glow">
            </div>
        </div>
        <div class="flex gap-3">
            <button :disabled="loading" class="h-11 flex-1 rounded-xl bg-emerald-glow text-bg-main font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center disabled:opacity-60" @click="save">{{ loading ? 'در حال ذخیره...' : 'ذخیره رمز جدید' }}</button>
            <button class="h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center" @click="$emit('close')">انصراف</button>
        </div>
    </BaseModal>
</template>

<script setup>
import { ref, computed } from 'vue';
import BaseModal from './BaseModal.vue';
import { apiPost } from '../api.js';
import { auth } from '../store.js';
import { showToast } from '../toast.js';
import { validatePasswordClient } from '../utils/format.js';

const props = defineProps({
    userId: { type: Number, required: true },
    userName: { type: String, default: '' },
    forOther: { type: Boolean, default: false }
});
const emit = defineEmits(['close', 'saved']);

const oldPass = ref('');
const newPass = ref('');
const loading = ref(false);
const showOld = computed(() => !auth.isManager || !props.forOther);

const save = async () => {
    const err = validatePasswordClient(newPass.value);
    if (err) { showToast(err, 'error'); return; }
    loading.value = true;
    try {
        const { ok, data } = await apiPost('change_password', { user_id: props.userId, old_password: oldPass.value, new_password: newPass.value });
        if (ok) {
            showToast('رمز عبور با موفقیت تغییر یافت', 'success');
            emit('saved');
            emit('close');
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
