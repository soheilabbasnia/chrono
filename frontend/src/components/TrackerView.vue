<template>
    <main class="flex-1 flex flex-col min-h-0">
        <div class="bg-panel border border-border-main rounded-[20px] shadow-2xl flex flex-col flex-1 min-h-0 overflow-hidden">
            <div class="p-3 sm:p-4 flex flex-col sm:flex-row gap-2 sm:gap-4 border-b border-border-main bg-bg-soft/30 flex-shrink-0">
                <input v-model="search" type="search" autocomplete="new-password" class="search-field flex-1 bg-bg-main border border-border-main rounded-xl px-4 text-main-color text-sm focus:outline-none focus:border-emerald-glow" placeholder="جستجوی نام تسک (حداقل ۳ حرف)...">
                <div class="flex gap-2">
                    <button class="flex-1 sm:flex-none h-12 px-4 rounded-xl bg-bg-main border border-border-main text-soft-color hover:border-emerald-glow hover:text-emerald-glow transition-all text-xs sm:text-sm font-medium whitespace-nowrap" @click="showDateFilter = true">فیلتر بازه تاریخی</button>
                    <button v-if="hasFilter" class="h-12 px-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500/20 transition-all text-xs sm:text-sm font-medium whitespace-nowrap" @click="clearFilter">حذف فیلترها</button>
                </div>
            </div>

            <div class="hidden sm:block px-3 sm:px-6 py-2.5 sm:py-3 border-b border-border-main bg-bg-soft/50 flex-shrink-0">
                <div class="grid grid-cols-12 gap-1 sm:gap-2 text-[11px] sm:text-sm text-muted-color font-medium items-center">
                    <div class="col-span-3 sm:col-span-2">تاریخ</div>
                    <div class="col-span-4 sm:col-span-6 px-1">تسک‌ها / توضیحات</div>
                    <div class="col-span-2 sm:col-span-1 text-center">شروع</div>
                    <div class="col-span-2 sm:col-span-2 text-center">پایان (مدت)</div>
                    <div class="col-span-1 text-left pl-1">عملیات</div>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto min-h-0">
                <SessionRow v-if="showActive" :session="sessions.active" :active="true" />
                <SessionRow v-for="s in filtered" :key="s.id" :session="s" :active="false"
                            @edit="editing = s" @delete="deletingId = s.id" />
                <div v-if="filtered.length === 0 && !sessions.active" class="text-center text-muted-color py-16 sm:py-20 text-xs sm:text-sm">موردی یافت نشد.</div>
            </div>
        </div>

        <EditSessionModal v-if="editing" :session="editing" @close="editing = null" @saved="reload" />
        <ConfirmModal v-if="deletingId" title="حذف نوبت کاری" message="آیا از حذف این تسک مطمئن هستید؟" :loading="deleteLoading" @confirm="doDelete" @close="deletingId = null" />
        <DateFilterModal v-if="showDateFilter" @close="showDateFilter = false" @apply="applyDateFilter" />
    </main>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { sessions, loadSessions } from '../sessions.js';
import { apiPost } from '../api.js';
import { showToast } from '../toast.js';
import SessionRow from './SessionRow.vue';
import EditSessionModal from './EditSessionModal.vue';
import ConfirmModal from './ConfirmModal.vue';
import DateFilterModal from './DateFilterModal.vue';

const search = ref('');
const filterText = ref('');
const dateRange = ref({ from: null, to: null });
const editing = ref(null);
const deletingId = ref(null);
const deleteLoading = ref(false);
const showDateFilter = ref(false);

let searchTimer = null;
watch(search, (v) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { filterText.value = v.trim(); }, 200);
});

const hasFilter = computed(() => filterText.value || dateRange.value.from || dateRange.value.to);
const showActive = computed(() => sessions.active && !filterText.value && !dateRange.value.from);

const filtered = computed(() => {
    let list = [...sessions.list].reverse();
    if (filterText.value && filterText.value.length >= 3) list = list.filter(s => s.task && s.task.includes(filterText.value));
    const { from, to } = dateRange.value;
    if (from && to) list = list.filter(s => s.startTime >= from && s.startTime <= to);
    return list;
});

const reload = () => loadSessions();
const applyDateFilter = (range) => { dateRange.value = range; };
const clearFilter = () => { search.value = ''; filterText.value = ''; dateRange.value = { from: null, to: null }; };

const doDelete = async () => {
    deleteLoading.value = true;
    try {
        const { ok } = await apiPost('delete_session', { id: deletingId.value });
        if (ok) { deletingId.value = null; await loadSessions(); }
        else showToast('خطا در حذف نوبت', 'error');
    } catch (e) { showToast('خطا در برقراری ارتباط با سرور', 'error'); }
    deleteLoading.value = false;
};

onMounted(loadSessions);
</script>
