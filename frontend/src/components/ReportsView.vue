<template>
    <main id="reports-view" class="flex-1 flex flex-col min-h-0 overflow-y-auto pr-1 space-y-4 sm:space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 flex-shrink-0">
            <div class="lg:col-span-6 print-card bg-panel border border-border-main rounded-2xl p-4 sm:p-6 shadow-xl flex flex-col justify-between relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-emerald-glow/5 to-transparent pointer-events-none"></div>
                <div class="relative z-10 flex flex-col h-full justify-between gap-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border-main/40 pb-3">
                        <span class="px-3 py-1 rounded-lg bg-emerald-glow/15 text-emerald-glow text-xs font-bold w-fit">اطلاعات کاربری و حساب</span>
                        <div class="flex flex-wrap items-center gap-1.5 no-print">
                            <button class="px-2.5 py-1.5 rounded-lg bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-xs font-medium" @click="showChangePass = true">تغییر رمز</button>
                            <button v-if="auth.isManager" class="px-2.5 py-1.5 rounded-lg bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-xs font-medium" @click="exportJson">پشتیبان JSON</button>
                            <button v-if="auth.isManager" class="px-2.5 py-1.5 rounded-lg bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-xs font-medium" @click="fileInput.click()">بازیابی JSON</button>
                            <button class="px-2.5 py-1.5 rounded-lg bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-xs font-medium" @click="exportCsv">خروجی CSV</button>
                            <button class="px-2.5 py-1.5 rounded-lg bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-xs font-medium" @click="print">چاپ / PDF</button>
                        </div>
                    </div>
                    <div class="space-y-2.5 sm:space-y-3.5">
                        <div class="flex justify-between items-center border-b border-border-main/40 pb-2"><span class="text-xs text-soft-color">نام و نام خانوادگی</span><span class="text-xs sm:text-sm font-bold text-main-color">{{ auth.user.full_name }}</span></div>
                        <div class="flex justify-between items-center border-b border-border-main/40 pb-2"><span class="text-xs text-soft-color">نام کاربری</span><span class="text-xs sm:text-sm font-medium text-main-color">{{ auth.user.username }}</span></div>
                        <div class="flex justify-between items-center border-b border-border-main/40 pb-2"><span class="text-xs text-soft-color">سطح دسترسی</span><span class="text-xs sm:text-sm font-medium text-emerald-glow">{{ auth.isManager ? 'مدیر سیستم' : 'همکار' }}</span></div>
                        <div class="flex justify-between items-center"><span class="text-xs text-soft-color">تاریخ عضویت</span><span class="text-xs sm:text-sm font-medium text-soft-color">{{ auth.user.created_at ? formatSolarDate(auth.user.created_at * 1000) : '-' }}</span></div>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-6 grid grid-cols-2 gap-3 sm:gap-4">
                <StatCard label="کل ساعت‌ها" :value="toFa(formatDuration(stats.totalMs))" :sub="`${toFa(stats.totalTasks)} تسک ثبت شده`" accent />
                <StatCard label="این هفته" :value="toFa(formatDuration(stats.weekMs))" :sub="`${toFa(stats.weekTasks)} تسک ثبت شده`" />
                <StatCard label="میانگین روزانه" :value="toFa(formatDuration(stats.avgMs))" :sub="`${toFa(stats.avgTasks)} تسک در روز`" />
                <StatCard label="امروز" :value="toFa(formatDuration(stats.todayMs))" :sub="`${toFa(stats.todayTasks)} تسک ثبت شده`" />
            </div>
        </div>

        <div class="print-card bg-panel border border-border-main rounded-[20px] shadow-xl flex-shrink-0 overflow-hidden">
            <div class="p-3 sm:p-4 flex flex-col sm:flex-row gap-2 sm:gap-4 border-b border-border-main bg-bg-soft/30 flex-shrink-0 no-print">
                <input v-model="search" type="search" autocomplete="new-password" class="search-field flex-1 bg-bg-main border border-border-main rounded-xl px-4 text-main-color text-sm focus:outline-none focus:border-emerald-glow" placeholder="جستجوی در تسک‌های گزارش (حداقل ۳ حرف)...">
                <div class="flex gap-2">
                    <button class="flex-1 sm:flex-none h-12 px-4 rounded-xl bg-bg-main border border-border-main text-soft-color hover:border-emerald-glow hover:text-emerald-glow transition-all text-xs sm:text-sm font-medium whitespace-nowrap" @click="showDateFilter = true">فیلتر بازه تاریخی</button>
                    <button v-if="hasFilter" class="h-12 px-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500/20 transition-all text-xs sm:text-sm font-medium whitespace-nowrap" @click="clearFilter">حذف فیلترها</button>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                <h3 class="text-sm sm:text-lg font-bold mb-4 sm:mb-6 text-main-color">گزارش روزانه نوبت‌ها</h3>
                <DailyBreakdown :daily-groups="stats.dailyGroups" />
            </div>
        </div>

        <input ref="fileInput" type="file" accept=".json" class="hidden" @change="importJson">
        <ChangePasswordModal v-if="showChangePass" :user-id="auth.user.id" :user-name="auth.user.full_name" :for-other="false" @close="showChangePass = false" />
        <DateFilterModal v-if="showDateFilter" title="فیلتر بازه تاریخی گزارش" @close="showDateFilter = false" @apply="applyDateFilter" />
    </main>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { auth } from '../store.js';
import { apiGet, apiPost } from '../api.js';
import { showToast } from '../toast.js';
import { toFa, formatDuration, formatSolarDate } from '../utils/format.js';
import { computeStats, downloadCsv, downloadJson } from '../utils/report.js';
import StatCard from './StatCard.vue';
import DailyBreakdown from './DailyBreakdown.vue';
import ChangePasswordModal from './ChangePasswordModal.vue';
import DateFilterModal from './DateFilterModal.vue';

const allSessions = ref([]);
const search = ref('');
const filterText = ref('');
const dateRange = ref({ from: null, to: null });
const showChangePass = ref(false);
const showDateFilter = ref(false);
const fileInput = ref(null);

let timer = null;
watch(search, (v) => { clearTimeout(timer); timer = setTimeout(() => { filterText.value = v.trim(); }, 200); });

const hasFilter = computed(() => filterText.value || dateRange.value.from);
const filtered = computed(() => {
    let list = [...allSessions.value];
    if (filterText.value && filterText.value.length >= 3) list = list.filter(s => s.task && s.task.includes(filterText.value));
    const { from, to } = dateRange.value;
    if (from && to) list = list.filter(s => s.startTime >= from && s.startTime <= to);
    return list;
});
const stats = computed(() => computeStats(filtered.value));

const load = async () => {
    const { ok, data } = await apiGet('get_user_report', { target_id: auth.user.id });
    if (ok) allSessions.value = data.sessions || [];
};

const applyDateFilter = (r) => { dateRange.value = r; };
const clearFilter = () => { search.value = ''; filterText.value = ''; dateRange.value = { from: null, to: null }; };
const exportCsv = () => downloadCsv(allSessions.value, `chrono-${auth.user.username}-${Date.now()}.csv`);
const print = () => window.print();

const exportJson = async () => {
    const { ok, data } = await apiGet('export_user_json', { user_id: auth.user.id });
    if (ok) downloadJson(data, `backup-${auth.user.username}-${Date.now()}.json`);
    else showToast('خطا در پشتیبان‌گیری', 'error');
};

const importJson = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async (ev) => {
        try {
            const parsed = JSON.parse(ev.target.result);
            const { ok } = await apiPost('import_user_json', { user_id: auth.user.id, sessions: parsed.sessions || [] });
            if (ok) { showToast('بازیابی اطلاعات با موفقیت انجام شد', 'success'); await load(); }
            else showToast('خطا در بازیابی داده‌ها', 'error');
        } catch (err) { showToast('فایل نامعتبر است', 'error'); }
        e.target.value = '';
    };
    reader.readAsText(file);
};

onMounted(load);
</script>
