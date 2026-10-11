<template>
    <teleport to="body">
        <div class="fixed top-4 left-1/2 -translate-x-1/2 z-[100] flex flex-col items-center gap-2 w-full max-w-sm px-3 pointer-events-none">
            <div v-for="t in toastState.items" :key="t.id"
                 :class="['pointer-events-auto w-full bg-panel border rounded-xl shadow-glow-strong px-4 py-3 flex items-center gap-3 text-xs sm:text-sm cursor-pointer', accent(t.type)]"
                 :role="t.type === 'error' ? 'alert' : 'status'"
                 @click="removeToast(t.id)">
                <span class="shrink-0" v-html="icon(t.type)"></span>
                <span class="flex-1 text-main-color leading-relaxed break-words">{{ t.message }}</span>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { toastState, removeToast } from '../toast.js';

const accent = (type) => type === 'success' ? 'text-emerald-glow border-emerald-glow/40'
    : type === 'error' ? 'text-red-400 border-red-500/40'
    : 'text-soft-color border-border-main';

const ICONS = {
    success: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>',
    error: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>',
    info: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>'
};
const icon = (type) => ICONS[type] || ICONS.info;
</script>
