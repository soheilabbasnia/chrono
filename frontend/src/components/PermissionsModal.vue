<template>
    <BaseModal max-width="max-w-xl" @submit="save" @close="$emit('close')">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-main-color">دسترسی گزارش‌ها</h2>
                <p class="text-xs text-soft-color mt-1">«{{ viewer.full_name }}» می‌تواند گزارش این کاربران را مشاهده کند:</p>
            </div>
            <button class="text-soft-color hover:text-main-color" @click="$emit('close')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="space-y-2 mb-6 max-h-60 overflow-y-auto pr-1">
            <div v-if="others.length === 0" class="text-xs text-muted-color py-4">کاربر دیگری برای تخصیص دسترسی وجود ندارد.</div>
            <label v-for="u in others" :key="u.id" class="flex items-center gap-3 p-2.5 rounded-xl bg-bg-soft/50 hover:bg-bg-soft border border-border-main/50 cursor-pointer">
                <input v-model="selected" type="checkbox" :value="Number(u.id)" class="w-4 h-4 rounded text-emerald-glow focus:ring-0">
                <span class="text-xs sm:text-sm text-main-color font-medium">{{ u.full_name }} ({{ u.username }})</span>
            </label>
        </div>
        <div class="flex gap-3">
            <button :disabled="loading" class="h-11 flex-1 rounded-xl bg-emerald-glow text-bg-main font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center disabled:opacity-60" @click="save">{{ loading ? 'در حال ذخیره...' : 'ذخیره دسترسی‌ها' }}</button>
            <button class="h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center" @click="$emit('close')">انصراف</button>
        </div>
    </BaseModal>
</template>

<script setup>
import { ref, computed } from 'vue';
import BaseModal from './BaseModal.vue';
import { apiPost } from '../api.js';
import { showToast } from '../toast.js';

const props = defineProps({ viewer: { type: Object, required: true }, users: { type: Array, required: true }, permissions: { type: Array, required: true } });
const emit = defineEmits(['close', 'saved']);

const others = computed(() => props.users.filter(u => Number(u.id) !== Number(props.viewer.id)));
const selected = ref(props.permissions.filter(p => Number(p.viewer_id) === Number(props.viewer.id)).map(p => Number(p.target_id)));
const loading = ref(false);

const save = async () => {
    loading.value = true;
    try {
        const { ok, data } = await apiPost('admin_save_permissions', { viewer_id: Number(props.viewer.id), targets: selected.value });
        if (ok && data.status === 'success') { emit('saved'); emit('close'); }
        else { showToast(data.error || 'خطا در ذخیره دسترسی‌ها', 'error'); loading.value = false; }
    } catch (e) { showToast('خطا در برقراری ارتباط با سرور', 'error'); loading.value = false; }
};
</script>
