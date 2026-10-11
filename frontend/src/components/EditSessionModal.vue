<template>
    <BaseModal max-width="max-w-xl" @submit="save" @close="$emit('close')">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-base sm:text-xl font-bold text-main-color">ویرایش نوبت کاری</h2>
            <button class="text-soft-color hover:text-main-color" @click="$emit('close')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="space-y-4 mb-6 text-xs sm:text-sm">
            <div>
                <label class="block text-xs text-soft-color mb-2">تاریخ (YYYY/MM/DD)</label>
                <input v-model="dateStr" type="text" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color focus:outline-none focus:border-emerald-glow">
            </div>
            <div class="grid grid-cols-2 gap-3 sm:gap-4">
                <div>
                    <label class="block text-xs text-soft-color mb-2">ساعت شروع (HH:MM)</label>
                    <input v-model="startStr" type="text" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color focus:outline-none focus:border-emerald-glow">
                </div>
                <div>
                    <label class="block text-xs text-soft-color mb-2">ساعت پایان (HH:MM)</label>
                    <input v-model="endStr" type="text" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color focus:outline-none focus:border-emerald-glow">
                </div>
            </div>
            <div>
                <label class="block text-xs text-soft-color mb-2">تسک‌ها / توضیحات</label>
                <textarea v-model="task" rows="3" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color resize-none focus:outline-none focus:border-emerald-glow"></textarea>
            </div>
        </div>
        <div class="flex gap-3">
            <button :disabled="loading" class="h-11 flex-1 rounded-xl bg-emerald-glow text-bg-main font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center disabled:opacity-60" @click="save">{{ loading ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}</button>
            <button class="h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center" @click="$emit('close')">انصراف</button>
        </div>
    </BaseModal>
</template>

<script setup>
import { ref } from 'vue';
import BaseModal from './BaseModal.vue';
import { apiPost } from '../api.js';
import { showToast } from '../toast.js';
import { toFa, toEn, jalaaliToGregorian } from '../utils/format.js';

const props = defineProps({ session: { type: Object, required: true } });
const emit = defineEmits(['close', 'saved']);

const startDate = new Date(Number(props.session.startTime));
const endDate = new Date(Number(props.session.endTime));
const parts = new Intl.DateTimeFormat('en-u-ca-persian', { year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(startDate);
let jy = '', jm = '', jd = '';
parts.forEach(p => { if (p.type === 'year') jy = p.value; if (p.type === 'month') jm = p.value; if (p.type === 'day') jd = p.value; });
const pad = (n) => String(n).padStart(2, '0');

const dateStr = ref(toFa(`${jy}/${jm}/${jd}`));
const startStr = ref(toFa(`${pad(startDate.getHours())}:${pad(startDate.getMinutes())}`));
const endStr = ref(toFa(`${pad(endDate.getHours())}:${pad(endDate.getMinutes())}`));
const task = ref(toFa(props.session.task || ''));
const loading = ref(false);

const save = async () => {
    try {
        const dp = toEn(dateStr.value.trim()).split('/').map(Number);
        const g = jalaaliToGregorian(dp[0], dp[1], dp[2]);
        const sp = toEn(startStr.value.trim()).split(':').map(Number);
        const ep = toEn(endStr.value.trim()).split(':').map(Number);
        const newStart = new Date(g.getFullYear(), g.getMonth(), g.getDate(), sp[0] || 0, sp[1] || 0).getTime();
        const newEnd = new Date(g.getFullYear(), g.getMonth(), g.getDate(), ep[0] || 0, ep[1] || 0).getTime();
        if (newEnd <= newStart) throw new Error('زمان پایان باید بعد از شروع باشد');
        loading.value = true;
        const { ok } = await apiPost('update_session', { id: props.session.id, startTime: newStart, endTime: newEnd, task: toFa(task.value.trim()) });
        if (ok) { emit('saved'); emit('close'); }
        else { showToast('خطا در ذخیره تغییرات', 'error'); loading.value = false; }
    } catch (err) {
        showToast('خطا: ' + err.message, 'error');
        loading.value = false;
    }
};
</script>
