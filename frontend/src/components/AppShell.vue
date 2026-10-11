<template>
    <div class="w-full max-w-[1320px] mx-auto px-2 sm:px-4 py-3 sm:py-4 flex-1 flex flex-col min-h-0 overflow-hidden">
        <header class="flex justify-between items-center mb-3 sm:mb-4 gap-2 sm:gap-4 flex-shrink-0">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-emerald-glow/10 border border-emerald-glow/30 flex items-center justify-center shadow-glow">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-glow" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div>
                    <h1 class="text-lg sm:text-2xl font-black tracking-tight text-main-color">کرونو <span class="text-emerald-glow text-xs sm:text-sm font-normal">(Chrono)</span></h1>
                    <div class="text-[11px] sm:text-xs text-soft-color font-normal">{{ userLabel }}</div>
                </div>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <button :disabled="toggleLoading" class="h-10 px-4 sm:px-5 rounded-xl bg-emerald-glow text-bg-main font-bold text-xs sm:text-sm shadow-glow transition-all duration-300 hover:scale-105 active:scale-95 flex items-center gap-1.5 sm:gap-2 z-10 disabled:opacity-60" :class="sessions.active ? 'animate-pulse-glow shadow-glow-strong' : 'shadow-glow'" @click="onToggle">
                    <span>{{ sessions.active ? 'پایان نوبت' : 'شروع نوبت' }}</span>
                </button>
                <nav class="hidden md:flex h-11 items-center gap-1 bg-panel border border-border-main rounded-xl p-1">
                    <button :class="tabClass('tracker')" @click="view = 'tracker'">ثبت زمان</button>
                    <button :class="tabClass('reports')" @click="view = 'reports'">گزارش‌ها</button>
                    <button v-if="auth.canViewUsers" :class="tabClass('users')" @click="view = 'users'">کاربران</button>
                </nav>
                <button class="hidden md:flex h-11 w-11 rounded-xl bg-panel border border-border-main text-soft-color hover:text-emerald-glow transition-all items-center justify-center" @click="toggleTheme" aria-label="تغییر تم" v-html="themeIcon"></button>
                <button class="hidden md:flex h-11 w-11 rounded-xl bg-panel border border-border-main text-soft-color hover:text-red-400 hover:border-red-400/40 transition-all items-center justify-center" title="خروج" @click="doLogout">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                </button>
                <button class="md:hidden h-10 w-10 rounded-xl bg-panel border border-border-main text-soft-color flex items-center justify-center hover:text-emerald-glow" @click="mobileMenu = true">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            </div>
        </header>

        <component :is="currentComponent" />

        <teleport to="body">
            <div v-if="mobileMenu" class="fixed inset-0 z-50 bg-bg-main flex flex-col p-6 md:hidden">
                <div class="flex items-center justify-between pb-6 border-b border-border-main">
                    <div class="text-sm text-soft-color">{{ userLabel }}</div>
                    <button class="p-2 text-soft-color hover:text-main-color" @click="mobileMenu = false">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <nav class="flex flex-col gap-3 py-6 flex-1">
                    <button :class="mobTabClass('tracker')" @click="go('tracker')">ثبت زمان</button>
                    <button :class="mobTabClass('reports')" @click="go('reports')">گزارش‌ها</button>
                    <button v-if="auth.canViewUsers" :class="mobTabClass('users')" @click="go('users')">کاربران و همکاران</button>
                </nav>
                <div class="pt-6 border-t border-border-main flex flex-col gap-3">
                    <button class="w-full h-11 rounded-xl bg-bg-soft text-soft-color text-sm font-medium" @click="toggleTheme">تغییر حالت شب و روز</button>
                    <button class="w-full h-11 rounded-xl bg-red-500/10 text-red-400 text-sm font-medium" @click="doLogout">خروج از حساب</button>
                </div>
            </div>
        </teleport>

        <TaskModal v-if="showTask" @close="showTask = false" />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { auth, logout } from '../store.js';
import { sessions, stopSession } from '../sessions.js';
import { theme, toggleTheme } from '../theme.js';
import { showToast } from '../toast.js';
import TrackerView from './TrackerView.vue';
import ReportsView from './ReportsView.vue';
import UsersView from './UsersView.vue';
import TaskModal from './TaskModal.vue';

const view = ref('tracker');
const mobileMenu = ref(false);
const showTask = ref(false);
const toggleLoading = ref(false);

const components = { tracker: TrackerView, reports: ReportsView, users: UsersView };
const currentComponent = computed(() => components[view.value] || TrackerView);
const userLabel = computed(() => auth.user ? `${auth.user.full_name} (${auth.isManager ? 'مدیر' : 'همکار'})` : '');

const activeTab = 'h-9 px-4 sm:px-5 rounded-lg font-bold text-xs sm:text-sm transition-all bg-emerald-glow/15 text-emerald-glow';
const idleTab = 'h-9 px-4 sm:px-5 rounded-lg font-bold text-xs sm:text-sm transition-all text-soft-color hover:text-main-color';
const tabClass = (v) => view.value === v ? activeTab : idleTab;
const mobActive = 'w-full h-12 rounded-xl font-bold text-sm bg-emerald-glow/15 text-emerald-glow flex items-center px-4';
const mobIdle = 'w-full h-12 rounded-xl font-bold text-sm text-soft-color bg-bg-soft flex items-center px-4';
const mobTabClass = (v) => view.value === v ? mobActive : mobIdle;
const go = (v) => { view.value = v; mobileMenu.value = false; };

const onToggle = async () => {
    if (sessions.active) {
        toggleLoading.value = true;
        try { if (!(await stopSession())) showToast('خطا در پایان نوبت', 'error'); }
        catch (e) { showToast('خطا در برقراری ارتباط با سرور', 'error'); }
        toggleLoading.value = false;
    } else {
        showTask.value = true;
    }
};

const doLogout = async () => { await logout(); mobileMenu.value = false; };

const themeIcon = computed(() => theme.value === 'light'
    ? '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" /></svg>'
    : '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" /></svg>');
</script>
