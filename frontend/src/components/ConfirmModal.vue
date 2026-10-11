<template>
    <BaseModal max-width="max-w-xl" @submit="$emit('confirm')" @close="$emit('close')">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 sm:h-14 sm:w-14 rounded-xl bg-red-500/10 border border-red-500/30 mb-5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </div>
            <h2 class="text-base sm:text-lg font-bold text-main-color mb-2">{{ title }}</h2>
            <p class="text-soft-color mb-6 text-xs sm:text-sm">{{ message }}</p>
            <div class="flex gap-3">
                <button :disabled="loading" class="h-11 flex-1 rounded-xl bg-red-500 text-white font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center disabled:opacity-60" @click="$emit('confirm')">{{ loading ? 'در حال انجام...' : confirmLabel }}</button>
                <button class="h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center" @click="$emit('close')">انصراف</button>
            </div>
        </div>
    </BaseModal>
</template>

<script setup>
import BaseModal from './BaseModal.vue';

defineProps({
    title: { type: String, default: 'تایید' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'بله، حذف شود' },
    loading: { type: Boolean, default: false }
});
defineEmits(['confirm', 'close']);
</script>
