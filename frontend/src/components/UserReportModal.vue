<template>
    <BaseModal max-width="max-w-4xl" :enter-submits="false" @submit="() => {}" @close="$emit('close')">
        <div class="flex justify-between items-center mb-6 border-b border-border-main/40 pb-4">
            <div>
                <h2 class="text-base sm:text-xl font-bold text-main-color">گزارش کامل فعالیت: {{ target.full_name }}</h2>
                <p class="text-xs text-soft-color mt-1">{{ target.username }} ({{ target.role === 'manager' ? 'مدیر' : 'همکار' }})</p>
            </div>
            <button class="text-soft-color hover:text-main-color" @click="$emit('close')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2 mb-6">
            <template v-if="auth.isManager">
                <button class="px-3 py-1.5 sm:py-2 rounded-xl bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-xs font-medium" @click="showChangePass = true">تغییر رمز کاربر</button>
                <button class="px-3 py-1.5 sm:py-2 rounded-xl bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-xs font-medium" @click="exportJson">پشتیبان JSON</button>
                <button class="px-3 py-1.5 sm:py-2 rounded-xl bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-xs font-medium" @click="fileInput.click()">بازیابی JSON</button>
            </template>
            <button class="px-3 py-1.5 sm:py-2 rounded-xl bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-xs font-medium" @click="exportCsv">خروجی CSV</button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <div class="bg-bg-soft/40 border border-border-main rounded-2xl p-3 sm:p-4"><span class="text-xs text-soft-color">کل ساعت‌ها:</span><h4 class="text-sm sm:text-lg font-black text-emerald-glow mt-1">{{ toFa(formatDuration(stats.totalMs)) }}</h4><span class="text-[10px] sm:text-[11px] text-muted-color">{{ toFa(stats.totalTasks) }} تسک</span></div>
            <div class="bg-bg-soft/40 border border-border-main rounded-2xl p-3 sm:p-4"><span class="text-xs text-soft-color">این هفته:</span><h4 class="text-sm sm:text-lg font-black text-main-color mt-1">{{ toFa(formatDuration(stats.weekMs)) }}</h4><span class="text-[10px] sm:text-[11px] text-muted-color">{{ toFa(stats.weekTasks) }} تسک</span></div>
            <div class="bg-bg-soft/40 border border-border-main rounded-2xl p-3 sm:p-4"><span class="text-xs text-soft-color">میانگین روزانه:</span><h4 class="text-sm sm:text-lg font-black text-main-color mt-1">{{ toFa(formatDuration(stats.avgMs)) }}</h4><span class="text-[10px] sm:text-[11px] text-muted-color">{{ toFa(stats.avgTasks) }} تسک</span></div>
            <div class="bg-bg-soft/40 border border-border-main rounded-2xl p-3 sm:p-4"><span class="text-xs text-soft-color">امروز:</span><h4 class="text-sm sm:text-lg font-black text-main-color mt-1">{{ toFa(formatDuration(stats.todayMs)) }}</h4><span class="text-[10px] sm:text-[11px] text-muted-color">{{ toFa(stats.todayTasks) }} تسک</span></div>
        </div>

        <h3 class="text-sm font-bold text-main-color mb-3">نوبت‌های کاری</h3>
        <div class="max-h-72 sm:max-h-80 overflow-y-auto pr-1 mb-6"><DailyBreakdown :daily-groups="stats.dailyGroups" /></div>

        <div class="flex">
            <button class="h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center" @click="$emit('close')">بستن</button>
        </div>

        <input ref="fileInput" type="file" accept=".json" class="hidden" @change="importJson">
        <ChangePasswordModal v-if="showChangePass" :user-id="target.id" :user-name="target.full_name" :for-other="true" @close="showChangePass = false" />
    </BaseModal>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import BaseModal from './BaseModal.vue';
import { auth } from '../store.js';
import { apiGet, apiPost } from '../api.js';
import { showToast } from '../toast.js';
import { toFa, formatDuration } from '../utils/format.js';
import { computeStats, downloadCsv, downloadJson } from '../utils/report.js';
import DailyBreakdown from './DailyBreakdown.vue';
import ChangePasswordModal from './ChangePasswordModal.vue';

const props = defineProps({ target: { type: Object, required: true } });
const emit = defineEmits(['close', 'changed']);

const sessionsData = ref([]);
const showChangePass = ref(false);
const fileInput = ref(null);
const stats = computed(() => computeStats(sessionsData.value));

const load = async () => {
    const { ok, data } = await apiGet('get_user_report', { target_id: props.target.id });
    if (ok) sessionsData.value = data.sessions || [];
    else showToast('خطا در بارگذاری گزارش', 'error');
};

const exportCsv = () => downloadCsv(sessionsData.value, `chrono-${props.target.username}-${Date.now()}.csv`);
const exportJson = async () => {
    const { ok, data } = await apiGet('export_user_json', { user_id: props.target.id });
    if (ok) downloadJson(data, `backup-${props.target.username}-${Date.now()}.json`);
    else showToast('خطا در پشتیبان‌گیری', 'error');
};
const importJson = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async (ev) => {
        try {
            const parsed = JSON.parse(ev.target.result);
            const { ok } = await apiPost('import_user_json', { user_id: props.target.id, sessions: parsed.sessions || [] });
            if (ok) { showToast('بازیابی اطلاعات با موفقیت انجام شد', 'success'); await load(); emit('changed'); }
            else showToast('خطا در بازیابی داده‌ها', 'error');
        } catch (err) { showToast('فایل نامعتبر است', 'error'); }
        e.target.value = '';
    };
    reader.readAsText(file);
};

onMounted(load);
</script>
