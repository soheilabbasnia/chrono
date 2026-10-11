<template>
    <BaseModal max-width="max-w-xl" @submit="confirm" @close="$emit('close')">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-base sm:text-xl font-bold text-main-color">افزودن تسک / توضیحات</h2>
            <button class="text-soft-color hover:text-main-color" @click="$emit('close')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <textarea ref="input" v-model="task" rows="4" class="w-full bg-bg-soft border border-border-main rounded-xl p-3.5 sm:p-4 text-main-color resize-none focus:outline-none focus:border-emerald-glow transition-colors mb-6 text-xs sm:text-sm" placeholder="تسک‌های این نوبت کاری را وارد کنید..." @keydown.enter.exact.prevent="confirm"></textarea>
        <div class="flex gap-3">
            <button :disabled="loading" class="h-11 flex-1 rounded-xl bg-emerald-glow text-bg-main font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center disabled:opacity-60" @click="confirm">{{ loading ? 'در حال ثبت...' : 'تایید و شروع' }}</button>
            <button class="h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center" @click="$emit('close')">انصراف</button>
        </div>
    </BaseModal>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import BaseModal from './BaseModal.vue';
import { startSession } from '../sessions.js';
import { showToast } from '../toast.js';
import { toFa } from '../utils/format.js';

const emit = defineEmits(['close']);
const task = ref('');
const loading = ref(false);
const input = ref(null);

onMounted(() => nextTick(() => input.value && input.value.focus()));

const confirm = async () => {
    loading.value = true;
    try {
        const ok = await startSession(toFa(task.value.trim()));
        if (ok) emit('close');
        else { showToast('خطا در شروع نوبت', 'error'); loading.value = false; }
    } catch (e) {
        showToast('خطا در برقراری ارتباط با سرور', 'error');
        loading.value = false;
    }
};
</script>
