<template>
    <div class="print-card bg-panel border border-border-main rounded-2xl p-4 sm:p-5 shadow-xl flex flex-col justify-between">
        <p class="text-soft-color text-xs sm:text-sm font-medium">{{ label }}</p>
        <div>
            <h3 :class="['text-lg sm:text-2xl font-black mb-1', accent ? 'text-emerald-glow' : 'text-main-color']">{{ value }}</h3>
            <p class="text-muted-color text-[11px] sm:text-xs">{{ sub }}</p>
        </div>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, default: '' },
    value: { type: String, default: '' },
    sub: { type: String, default: '' },
    accent: { type: Boolean, default: false }
});
</script>
