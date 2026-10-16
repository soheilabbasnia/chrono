<template>
    <div class="flex-1 flex flex-col min-h-0">
        <LoginView v-if="auth.ready && !auth.user" />
        <template v-else-if="auth.ready && auth.user">
            <div v-if="auth.mustChange" class="fixed inset-0 z-40 bg-bg-main"></div>
            <ForcedPasswordModal v-if="auth.mustChange" />
            <AppShell v-else />
        </template>
    </div>
    <ToastHost />
</template>

<script setup>
import { onMounted } from 'vue';
import { auth, refreshAuth } from './store.js';
import LoginView from './components/LoginView.vue';
import AppShell from './components/AppShell.vue';
import ForcedPasswordModal from './components/ForcedPasswordModal.vue';
import ToastHost from './components/ToastHost.vue';

onMounted(refreshAuth);
</script>
