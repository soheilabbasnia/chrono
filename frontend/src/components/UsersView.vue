<template>
    <main class="flex-1 flex flex-col min-h-0 overflow-y-auto pr-1 space-y-4 sm:space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-panel border border-border-main rounded-2xl p-4 flex-shrink-0">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-main-color">فهرست همکاران</h3>
                <p class="text-xs text-soft-color">مشاهده گزارش همکاران مجاز و وضعیت نوبت جاری</p>
            </div>
            <button v-if="auth.isManager" class="h-10 px-4 rounded-xl bg-emerald-glow text-bg-main font-bold text-xs sm:text-sm shadow-glow hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-1.5 self-stretch sm:self-auto" @click="showAdd = true">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>افزودن کاربر</span>
            </button>
        </div>

        <div class="bg-panel border border-border-main rounded-2xl shadow-xl overflow-hidden flex-shrink-0">
            <div class="px-3 sm:px-6 py-3 border-b border-border-main bg-bg-soft/50 grid grid-cols-12 gap-1 sm:gap-2 text-[11px] sm:text-xs font-semibold text-muted-color items-center">
                <div class="col-span-4 sm:col-span-4">نام و نام خانوادگی</div>
                <div class="hidden sm:block sm:col-span-3">نام کاربری</div>
                <div class="col-span-3 sm:col-span-2 text-center">وضعیت</div>
                <div class="col-span-5 sm:col-span-3 text-left pl-1">عملیات</div>
            </div>
            <div class="divide-y divide-border-main/40">
                <div v-for="u in users" :key="u.id" class="px-3 sm:px-6 py-3 grid grid-cols-12 gap-1 sm:gap-2 text-xs sm:text-sm items-center hover:bg-bg-soft/40 transition-colors">
                    <div class="col-span-4 sm:col-span-4 font-semibold text-main-color flex items-center gap-1 truncate">
                        <span class="truncate">{{ u.full_name }}</span>
                        <span v-if="u.role === 'manager'" class="text-[9px] text-emerald-glow bg-emerald-glow/10 px-1 py-0.5 rounded font-normal">مدیر</span>
                    </div>
                    <div class="hidden sm:block sm:col-span-3 text-soft-color truncate">{{ u.username }}</div>
                    <div class="col-span-3 sm:col-span-2 text-center">
                        <div v-if="u.active_task" class="relative group inline-flex items-center gap-1.5 cursor-pointer">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-glow animate-pulse"></span>
                            <span class="text-emerald-glow text-xs font-bold">آنلاین</span>
                            <div class="user-status-tooltip absolute bottom-full mb-2 hidden group-hover:block z-30 w-56 p-2.5 rounded-xl bg-panel border border-border-main text-xs text-soft-color shadow-2xl pointer-events-none">در حال کار روی: {{ u.active_task }} (شروع: {{ formatTime(u.active_startTime) }})</div>
                        </div>
                        <span v-else class="text-muted-color text-xs">آفلاین</span>
                    </div>
                    <div class="col-span-5 sm:col-span-3 text-left flex justify-end gap-1">
                        <button class="px-2 py-1 rounded-lg bg-emerald-glow/15 border border-emerald-glow/30 text-emerald-glow hover:bg-emerald-glow hover:text-bg-main text-[11px] font-bold" @click="reportUser = u">گزارش</button>
                        <template v-if="auth.isManager">
                            <button class="px-1.5 py-1 rounded-lg bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-[11px]" @click="permUser = u">دسترسی</button>
                            <button v-if="Number(u.id) !== auth.user.id" class="px-1.5 py-1 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500/20 text-[11px]" @click="deleteUser = u">حذف</button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <AddUserModal v-if="showAdd" @close="showAdd = false" @saved="load" />
        <PermissionsModal v-if="permUser" :viewer="permUser" :users="users" :permissions="permissions" @close="permUser = null" @saved="load" />
        <UserReportModal v-if="reportUser" :target="reportUser" @close="reportUser = null" @changed="load" />
        <ConfirmModal v-if="deleteUser" title="حذف کاربر" :message="`آیا از حذف «${deleteUser.full_name}» اطمینان دارید؟`" :loading="delLoading" @confirm="doDeleteUser" @close="deleteUser = null" />
    </main>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { auth } from '../store.js';
import { apiGet, apiPost } from '../api.js';
import { showToast } from '../toast.js';
import { formatTime } from '../utils/format.js';
import AddUserModal from './AddUserModal.vue';
import PermissionsModal from './PermissionsModal.vue';
import UserReportModal from './UserReportModal.vue';
import ConfirmModal from './ConfirmModal.vue';

const users = ref([]);
const permissions = ref([]);
const showAdd = ref(false);
const permUser = ref(null);
const reportUser = ref(null);
const deleteUser = ref(null);
const delLoading = ref(false);

const load = async () => {
    const { ok, data } = await apiGet('get_visible_users');
    if (ok) { users.value = data.users || []; permissions.value = data.permissions || []; }
};

const doDeleteUser = async () => {
    delLoading.value = true;
    try {
        const { ok } = await apiPost('admin_delete_user', { user_id: deleteUser.value.id });
        if (ok) { deleteUser.value = null; await load(); }
        else showToast('خطا در حذف کاربر', 'error');
    } catch (e) { showToast('خطا در برقراری ارتباط با سرور', 'error'); }
    delLoading.value = false;
};

onMounted(load);
</script>
