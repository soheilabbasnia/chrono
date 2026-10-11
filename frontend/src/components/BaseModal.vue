<template>
    <teleport to="body">
        <div class="modal-overlay fixed inset-0 flex items-center justify-center p-3 sm:p-4" :style="{ zIndex }">
            <div :class="['bg-panel border-2 border-emerald-glow/40 rounded-[20px] p-5 sm:p-8 w-full max-h-[90vh] overflow-y-auto shadow-glow-strong relative text-main-color', maxWidth]">
                <slot />
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { registerModal, unregisterModal } from '../modalStack.js';

const props = defineProps({
    maxWidth: { type: String, default: 'max-w-xl' },
    dismissable: { type: Boolean, default: true },
    enterSubmits: { type: Boolean, default: true }
});
const emit = defineEmits(['submit', 'close']);

const zIndex = ref(60);
const api = {
    dismissable: props.dismissable,
    enterSubmits: props.enterSubmits,
    close: () => emit('close'),
    submit: () => emit('submit')
};

onMounted(() => { zIndex.value = 60 + registerModal(api) * 10; });
onUnmounted(() => unregisterModal(api));
</script>
