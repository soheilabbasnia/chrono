<template>
    <div v-if="days.length === 0" class="text-muted-color text-xs sm:text-sm py-4">داده‌ای برای نمایش وجود ندارد.</div>
    <div v-for="day in days" :key="day" class="bg-bg-soft/30 border border-border-main rounded-xl p-3 sm:p-4 mb-3 sm:mb-4">
        <div class="flex justify-between items-center mb-2 pb-2 border-b border-border-main/40">
            <h4 class="text-xs sm:text-base font-bold text-emerald-glow">{{ day }}</h4>
            <span class="text-[11px] sm:text-xs text-soft-color font-medium">مجموع زمان: {{ toFa(formatDuration(dayTotal(dailyGroups[day]))) }}</span>
        </div>
        <div>
            <div v-for="s in dailyGroups[day]" :key="s.id" class="flex items-center justify-between gap-2 sm:gap-4 px-2 sm:px-6 py-2.5 sm:py-3 border-b border-border-main/30 text-xs sm:text-sm hover:bg-bg-soft/30 transition-colors">
                <div class="flex-1 text-main-color whitespace-pre-wrap break-words leading-relaxed">{{ toFa(s.task || 'بدون توضیحات') }}</div>
                <div class="w-16 sm:w-24 text-soft-color text-center font-medium">{{ formatTime(s.startTime) }}</div>
                <div class="w-24 sm:w-36 text-soft-color flex items-center gap-1 sm:gap-1.5 whitespace-nowrap justify-end font-medium">
                    <span>{{ formatTime(s.endTime) }}</span>
                    <span class="text-muted-color text-[10px] sm:text-[11px]">({{ toFa(formatDuration(Number(s.endTime) - Number(s.startTime))) }})</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { toFa, formatTime, formatDuration } from '../utils/format.js';
import { sortedDays, dayTotal } from '../utils/report.js';

const props = defineProps({ dailyGroups: { type: Object, required: true } });
const days = computed(() => sortedDays(props.dailyGroups));
</script>
