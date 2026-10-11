<template>
    <div :class="['session-row border-b border-border-main/50 transition-all', active ? 'bg-emerald-glow/5' : 'hover:bg-bg-soft']">
        <div class="hidden sm:grid sm:grid-cols-12 sm:gap-2 sm:items-center px-3 sm:px-6 py-2.5 sm:py-3 text-xs sm:text-sm">
            <div class="col-span-3 sm:col-span-2 text-soft-color font-medium text-[11px] sm:text-sm truncate">{{ dateStr }}</div>
            <div class="col-span-4 sm:col-span-6 px-1 text-main-color whitespace-pre-wrap break-words leading-relaxed">{{ taskStr }}</div>
            <div class="col-span-2 sm:col-span-1 text-center text-soft-color text-[11px] sm:text-sm">{{ startStr }}</div>
            <div class="col-span-2 sm:col-span-2 text-center text-[11px] sm:text-sm whitespace-nowrap">
                <span v-if="active" class="px-1.5 py-0.5 rounded-md bg-emerald-glow/15 text-emerald-glow text-[10px] font-bold animate-pulse">اجرا</span>
                <span v-else class="text-soft-color"><span>{{ endStr }}</span> <span class="text-muted-color text-[10px]">({{ durStr }})</span></span>
            </div>
            <div class="col-span-1 flex justify-end gap-1">
                <template v-if="!active">
                    <button class="p-1.5 rounded-lg hover:bg-border-main text-soft-color hover:text-emerald-glow" aria-label="ویرایش" @click="$emit('edit')" v-html="editIcon"></button>
                    <button class="p-1.5 rounded-lg hover:bg-border-main text-soft-color hover:text-red-500" aria-label="حذف" @click="$emit('delete')" v-html="trashIcon"></button>
                </template>
            </div>
        </div>

        <div class="sm:hidden flex flex-col gap-2 px-3 py-3">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 text-main-color whitespace-pre-wrap break-words leading-relaxed text-sm">{{ taskStr }}</div>
                <div v-if="!active" class="flex items-center gap-1 shrink-0">
                    <button class="p-1.5 rounded-lg bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow" aria-label="ویرایش" @click="$emit('edit')" v-html="editIcon"></button>
                    <button class="p-1.5 rounded-lg bg-bg-soft border border-border-main text-soft-color hover:text-red-500" aria-label="حذف" @click="$emit('delete')" v-html="trashIcon"></button>
                </div>
            </div>
            <div class="flex items-center flex-wrap gap-x-2 gap-y-1 text-[11px] text-muted-color">
                <span class="text-soft-color font-medium">{{ dateStr }}</span>
                <span class="w-1 h-1 rounded-full bg-border-main"></span>
                <template v-if="active"><span>شروع {{ startStr }}</span><span class="w-1 h-1 rounded-full bg-border-main"></span><span class="px-1.5 py-0.5 rounded-md bg-emerald-glow/15 text-emerald-glow text-[10px] font-bold animate-pulse">اجرا</span></template>
                <template v-else><span>{{ startStr }} – {{ endStr }}</span><span class="text-emerald-glow/80 font-medium">({{ durStr }})</span></template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { toFa, formatSolarDate, formatTime, formatDuration } from '../utils/format.js';

const props = defineProps({ session: { type: Object, required: true }, active: { type: Boolean, default: false } });
defineEmits(['edit', 'delete']);

const dateStr = computed(() => formatSolarDate(props.session.startTime));
const startStr = computed(() => formatTime(props.session.startTime));
const taskStr = computed(() => toFa(props.session.task || 'بدون توضیحات'));
const endStr = computed(() => props.active ? '' : formatTime(props.session.endTime));
const durStr = computed(() => props.active ? '' : toFa(formatDuration(props.session.endTime - props.session.startTime)));

const editIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>';
const trashIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>';
</script>
