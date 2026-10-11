<template>
    <BaseModal max-width="max-w-xl" @submit="apply" @close="$emit('close')">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-base sm:text-xl font-bold text-main-color">{{ title }}</h2>
            <button class="text-soft-color hover:text-main-color" @click="$emit('close')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 mb-6">
            <div>
                <label class="block text-xs text-soft-color mb-2">از تاریخ (YYYY/MM/DD)</label>
                <input v-model="fromVal" type="text" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-xs sm:text-sm focus:outline-none focus:border-emerald-glow" placeholder="۱۴۰۵/۰۱/۰۱">
            </div>
            <div>
                <label class="block text-xs text-soft-color mb-2">تا تاریخ (YYYY/MM/DD)</label>
                <input v-model="toVal" type="text" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-xs sm:text-sm focus:outline-none focus:border-emerald-glow" placeholder="۱۴۰۵/۰۱/۳۱">
            </div>
        </div>
        <div class="flex gap-3">
            <button class="h-11 flex-1 rounded-xl bg-emerald-glow text-bg-main font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center" @click="apply">اعمال فیلتر</button>
            <button class="h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center" @click="$emit('close')">انصراف</button>
        </div>
    </BaseModal>
</template>

<script setup>
import { ref } from 'vue';
import BaseModal from './BaseModal.vue';
import { showToast } from '../toast.js';
import { toEn, jalaaliToGregorian } from '../utils/format.js';

const props = defineProps({ title: { type: String, default: 'فیلتر بازه تاریخی' } });
const emit = defineEmits(['close', 'apply']);

const fromVal = ref('');
const toVal = ref('');

const apply = () => {
    try {
        let from = null, to = null;
        if (fromVal.value.trim()) {
            const [jy, jm, jd] = toEn(fromVal.value.trim()).split('/').map(Number);
            from = jalaaliToGregorian(jy, jm, jd).getTime();
        }
        if (toVal.value.trim()) {
            const [jy, jm, jd] = toEn(toVal.value.trim()).split('/').map(Number);
            const g = jalaaliToGregorian(jy, jm, jd);
            g.setHours(23, 59, 59, 999);
            to = g.getTime();
        }
        emit('apply', { from, to });
        emit('close');
    } catch (e) {
        showToast('فرمت تاریخ نامعتبر است', 'error');
    }
};
</script>
