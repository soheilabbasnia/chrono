import { toFa, toEn, formatSolarDate, formatTime, formatDuration, jalaaliToGregorian, isToday, getStartOfWeek } from './utils.js';

document.addEventListener('DOMContentLoaded', () => {
    const THEME_KEY = 'chronos_theme';
    let currentUser = null;
    let isManager = false;
    let state = { activeSession: null, sessions: [], filters: { text: '', from: null, to: null } };
    let myReportSessions = [];
    let reportFilters = { text: '', from: null, to: null };
    let allUsersList = [];
    let allPermissionsList = [];

    const loginContainer = document.getElementById('login-container');
    const appContainer = document.getElementById('app-container');
    const loginForm = document.getElementById('login-form');
    const loginError = document.getElementById('login-error');
    const logoutBtn = document.getElementById('logout-btn');
    const userInfoText = document.getElementById('user-info-text');
    const mobUserInfoText = document.getElementById('mob-user-info-text');

    // تبدیل خودکار اعداد در ورودی‌ها
    document.addEventListener('input', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            if (['username-input', 'password-input', 'new-user-username', 'new-user-password', 'change-old-pass', 'change-new-pass', 'search-input', 'report-search-input'].includes(e.target.id)) {
                return;
            }
            const start = e.target.selectionStart;
            const end = e.target.selectionEnd;
            const orig = e.target.value;
            const converted = toFa(orig);
            if (orig !== converted) {
                e.target.value = converted;
                e.target.setSelectionRange(start, end);
            }
        }
    });

    // تم
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    const mobThemeToggle = document.getElementById('mob-theme-toggle');
    const iconDark = document.getElementById('theme-icon-dark');
    const iconLight = document.getElementById('theme-icon-light');
    const htmlEl = document.documentElement;

    const applyTheme = (theme) => {
        if (theme === 'light') {
            htmlEl.classList.add('light');
            iconLight.classList.add('hidden');
            iconDark.classList.remove('hidden');
        } else {
            htmlEl.classList.remove('light');
            iconDark.classList.add('hidden');
            iconLight.classList.remove('hidden');
        }
    };
    applyTheme(localStorage.getItem(THEME_KEY) || 'dark');

    const toggleTheme = () => {
        const newTheme = htmlEl.classList.contains('light') ? 'dark' : 'light';
        applyTheme(newTheme);
        localStorage.setItem(THEME_KEY, newTheme);
    };

    themeToggleBtn.addEventListener('click', toggleTheme);
    mobThemeToggle.addEventListener('click', toggleTheme);

    // پشته مودال‌ها
    const modalRoot = document.getElementById('modal-root');
    const modalStack = [];

    const openModal = (contentHtml, onMount, customMaxWidth = 'max-w-xl') => {
        const zIndex = 60 + (modalStack.length * 10);
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay fixed inset-0 flex items-center justify-center p-3 sm:p-4';
        overlay.style.zIndex = zIndex;
        overlay.innerHTML = `<div class="bg-panel border-2 border-emerald-glow/40 rounded-[20px] p-5 sm:p-8 w-full ${customMaxWidth} max-h-[90vh] overflow-y-auto shadow-glow-strong relative text-main-color">${contentHtml}</div>`;
        modalRoot.appendChild(overlay);
        modalStack.push(overlay);
        if (onMount) onMount(overlay);
    };

    const closeModal = () => {
        if (modalStack.length > 0) {
            const topModal = modalStack.pop();
            topModal.remove();
        }
    };

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalStack.length > 0) closeModal();
    });

    // تب‌ها و منوی کشویی موبایل
    const tabTracker = document.getElementById('tab-tracker');
    const tabReports = document.getElementById('tab-reports');
    const tabUsers = document.getElementById('tab-users');
    const mobTabTracker = document.getElementById('mob-tab-tracker');
    const mobTabReports = document.getElementById('mob-tab-reports');
    const mobTabUsers = document.getElementById('mob-tab-users');
    const mobileSidebar = document.getElementById('mobile-sidebar');
    const openMobileMenuBtn = document.getElementById('open-mobile-menu');
    const closeMobileMenuBtn = document.getElementById('close-mobile-menu');

    const trackerView = document.getElementById('tracker-view');
    const reportsView = document.getElementById('reports-view');
    const usersView = document.getElementById('users-view');
    const sessionList = document.getElementById('session-list');
    const toggleBtn = document.getElementById('toggle-session-btn');
    const toggleBtnText = document.getElementById('toggle-btn-text');
    const toggleBtnIcon = document.getElementById('toggle-btn-icon');
    const searchInput = document.getElementById('search-input');
    const clearFilterBtn = document.getElementById('clear-filter-btn');

    openMobileMenuBtn.addEventListener('click', () => mobileSidebar.classList.remove('translate-x-full'));
    closeMobileMenuBtn.addEventListener('click', () => mobileSidebar.classList.add('translate-x-full'));

    const switchView = (view) => {
        mobileSidebar.classList.add('translate-x-full');
        [trackerView, reportsView, usersView].forEach(v => {
            v.classList.add('hidden');
            v.classList.remove('flex');
        });

        [tabTracker, tabReports, tabUsers].forEach(t => {
            t.classList.remove('bg-emerald-glow/15', 'text-emerald-glow');
            t.classList.add('text-soft-color');
        });

        [mobTabTracker, mobTabReports, mobTabUsers].forEach(t => {
            t.classList.remove('bg-emerald-glow/15', 'text-emerald-glow');
            t.classList.add('text-soft-color', 'bg-bg-soft');
        });

        if (view === 'reports') {
            reportsView.classList.remove('hidden'); reportsView.classList.add('flex');
            tabReports.classList.add('bg-emerald-glow/15', 'text-emerald-glow');
            mobTabReports.classList.add('bg-emerald-glow/15', 'text-emerald-glow');
            mobTabReports.classList.remove('bg-bg-soft');
            loadMyReports();
        } else if (view === 'users') {
            usersView.classList.remove('hidden'); usersView.classList.add('flex');
            tabUsers.classList.add('bg-emerald-glow/15', 'text-emerald-glow');
            mobTabUsers.classList.add('bg-emerald-glow/15', 'text-emerald-glow');
            mobTabUsers.classList.remove('bg-bg-soft');
            loadVisibleUsers();
        } else {
            trackerView.classList.remove('hidden'); trackerView.classList.add('flex');
            tabTracker.classList.add('bg-emerald-glow/15', 'text-emerald-glow');
            mobTabTracker.classList.add('bg-emerald-glow/15', 'text-emerald-glow');
            mobTabTracker.classList.remove('bg-bg-soft');
            renderTracker();
        }
    };

    tabTracker.addEventListener('click', () => switchView('tracker'));
    tabReports.addEventListener('click', () => switchView('reports'));
    tabUsers.addEventListener('click', () => switchView('users'));
    mobTabTracker.addEventListener('click', () => switchView('tracker'));
    mobTabReports.addEventListener('click', () => switchView('reports'));
    mobTabUsers.addEventListener('click', () => switchView('users'));

    const fetchUserData = async () => {
        try {
            const res = await fetch('api.php?action=get_sessions');
            if (res.ok) {
                const data = await res.json();
                state.sessions = data.sessions || [];
                state.activeSession = data.activeSession || null;
                renderTracker();
            }
        } catch (e) {
            console.error(e);
        }
    };

    const updateToggleButton = () => {
        if (state.activeSession) {
            toggleBtnText.textContent = 'پایان نوبت';
            toggleBtnIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.5 7.5a3 3 0 013-3h9a3 3 0 013 3v9a3 3 0 01-3 3h-9a3 3 0 01-3-3v-9z" clip-rule="evenodd" /></svg>`;
            toggleBtn.classList.add('animate-pulse-glow', 'shadow-glow-strong');
            toggleBtn.classList.remove('shadow-glow');
        } else {
            toggleBtnText.textContent = 'شروع نوبت';
            toggleBtnIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" /></svg>`;
            toggleBtn.classList.remove('animate-pulse-glow', 'shadow-glow-strong');
            toggleBtn.classList.add('shadow-glow');
        }
    };

    const getFilteredSessions = () => {
        let sessions = [...state.sessions].reverse();
        const { text, from, to } = state.filters;
        if (text && text.length >= 3) sessions = sessions.filter(s => s.task && s.task.includes(text));
        if (from && to) sessions = sessions.filter(s => s.startTime >= from && s.startTime <= to);
        return sessions;
    };

    searchInput.addEventListener('input', (e) => {
        state.filters.text = e.target.value.trim();
        renderTracker();
    });

    clearFilterBtn.addEventListener('click', () => {
        state.filters = { text: '', from: null, to: null };
        searchInput.value = '';
        renderTracker();
    });

    document.getElementById('date-filter-btn').addEventListener('click', () => {
        const html = `
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-base sm:text-xl font-bold text-main-color">فیلتر بازه تاریخی</h2>
                <button class="modal-close-x text-soft-color hover:text-main-color"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 mb-6">
                <div>
                    <label class="block text-xs text-soft-color mb-2">از تاریخ (YYYY/MM/DD)</label>
                    <input type="text" id="date-from-input" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-xs sm:text-sm focus:outline-none focus:border-emerald-glow" placeholder="۱۴۰۵/۰۱/۰۱">
                </div>
                <div>
                    <label class="block text-xs text-soft-color mb-2">تا تاریخ (YYYY/MM/DD)</label>
                    <input type="text" id="date-to-input" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-xs sm:text-sm focus:outline-none focus:border-emerald-glow" placeholder="۱۴۰۵/۰۱/۳۱">
                </div>
            </div>
            <div class="flex gap-3">
                <button id="modal-apply-date" class="h-11 flex-1 rounded-xl bg-emerald-glow text-bg-main font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center">اعمال فیلتر</button>
                <button class="modal-cancel h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center">انصراف</button>
            </div>
        `;
        openModal(html, (modal) => {
            const fromInput = modal.querySelector('#date-from-input');
            const toInput = modal.querySelector('#date-to-input');
            modal.querySelector('.modal-close-x').onclick = closeModal;
            modal.querySelector('.modal-cancel').onclick = closeModal;

            modal.querySelector('#modal-apply-date').onclick = () => {
                try {
                    const fromVal = fromInput.value.trim();
                    const toVal = toInput.value.trim();
                    if (fromVal) {
                        const [jy, jm, jd] = toEn(fromVal).split('/').map(Number);
                        state.filters.from = jalaaliToGregorian(jy, jm, jd).getTime();
                    } else state.filters.from = null;

                    if (toVal) {
                        const [jy, jm, jd] = toEn(toVal).split('/').map(Number);
                        let gDate = jalaaliToGregorian(jy, jm, jd);
                        gDate.setHours(23, 59, 59, 999);
                        state.filters.to = gDate.getTime();
                    } else state.filters.to = null;

                    closeModal();
                    renderTracker();
                } catch (err) {
                    alert("فرمت تاریخ نامعتبر است");
                }
            };
        });
    });

    const createTrackerRowElement = (session, isActive) => {
        const row = document.createElement('div');
        row.className = `session-row flex items-center px-3 sm:px-6 py-2.5 sm:py-3 border-b border-border-main/50 transition-all text-xs sm:text-sm ${isActive ? 'bg-emerald-glow/5' : 'hover:bg-bg-soft'}`;
        
        const dateCol = `<div class="w-20 sm:w-28 text-soft-color font-medium text-[11px] sm:text-sm flex-shrink-0">${formatSolarDate(session.startTime)}</div>`;
        const taskCol = `<div class="flex-1 px-3 text-main-color whitespace-pre-wrap break-words leading-relaxed">${toFa(session.task || 'بدون توضیحات')}</div>`;
        const startCol = `<div class="w-16 sm:w-24 text-center text-soft-color flex-shrink-0">${formatTime(session.startTime)}</div>`;
        
        let endCol;
        if (isActive) {
            endCol = `<div class="w-28 sm:w-36 text-center flex-shrink-0"><span class="px-2 py-0.5 sm:py-1 rounded-lg bg-emerald-glow/15 text-emerald-glow text-[10px] sm:text-xs font-bold animate-pulse">در حال اجرا</span></div>`;
        } else {
            const dur = formatDuration(session.endTime - session.startTime);
            endCol = `<div class="w-28 sm:w-36 text-center text-soft-color flex items-center justify-center gap-1.5 whitespace-nowrap text-[11px] sm:text-sm flex-shrink-0"><span>${formatTime(session.endTime)}</span><span class="text-muted-color text-[10px] sm:text-[11px]">(${toFa(dur)})</span></div>`;
        }
    
        let actionCol = `<div class="w-10 sm:w-12 text-left flex-shrink-0 pl-1"></div>`;
        if (!isActive) {
            actionCol = `
                <div class="w-10 sm:w-12 text-left flex-shrink-0 pl-1 relative flex justify-end">
                    <button class="action-menu-btn p-1.5 rounded-lg hover:bg-border-main text-soft-color hover:text-main-color" data-id="${session.id}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                    </button>
                    <div class="dropdown-menu absolute left-0 mt-1 w-28 bg-panel border border-border-main rounded-xl shadow-xl py-2 hidden z-30 text-right">
                        <button class="edit-btn block w-full px-4 py-2 text-xs text-soft-color hover:bg-bg-soft hover:text-emerald-glow" data-id="${session.id}">ویرایش</button>
                        <button class="delete-btn block w-full px-4 py-2 text-xs text-soft-color hover:bg-bg-soft hover:text-red-500" data-id="${session.id}">حذف</button>
                    </div>
                </div>`;
        }
    
        row.innerHTML = `${dateCol}${taskCol}${startCol}${endCol}${actionCol}`;
    
        const menuBtn = row.querySelector('.action-menu-btn');
        const dropMenu = row.querySelector('.dropdown-menu');
        const editBtn = row.querySelector('.edit-btn');
        const deleteBtn = row.querySelector('.delete-btn');
    
        if (menuBtn) {
            menuBtn.onclick = (e) => {
                e.stopPropagation();
                document.querySelectorAll('.dropdown-menu').forEach(m => m !== dropMenu && m.classList.add('hidden'));
                dropMenu.classList.toggle('hidden');
            };
        }
        if (editBtn) editBtn.onclick = () => openEditModal(session, fetchUserData);
        if (deleteBtn) deleteBtn.onclick = () => openDeleteModal(session.id, fetchUserData);
    
        return row;
    };

    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
    });

    const renderTracker = () => {
        sessionList.innerHTML = '';
        if (state.filters.text || state.filters.from || state.filters.to) clearFilterBtn.classList.remove('hidden');
        else clearFilterBtn.classList.add('hidden');

        if (state.activeSession && !state.filters.text && !state.filters.from) {
            sessionList.appendChild(createTrackerRowElement(state.activeSession, true));
        }

        const filtered = getFilteredSessions();
        if (filtered.length === 0 && !state.activeSession) {
            sessionList.innerHTML = `<div class="text-center text-muted-color py-16 sm:py-20 text-xs sm:text-sm">موردی یافت نشد.</div>`;
        } else {
            filtered.forEach(s => sessionList.appendChild(createTrackerRowElement(s, false)));
        }
        updateToggleButton();
    };

    toggleBtn.addEventListener('click', async () => {
        if (state.activeSession) {
            const res = await fetch('api.php?action=stop_session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endTime: Date.now() })
            });
            if (res.ok) fetchUserData();
        } else {
            openTaskModal();
        }
    });

    const openTaskModal = () => {
        const html = `
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-base sm:text-xl font-bold text-main-color">افزودن تسک / توضیحات</h2>
                <button class="modal-close-x text-soft-color hover:text-main-color"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
            </div>
            <textarea id="task-input" rows="4" class="w-full bg-bg-soft border border-border-main rounded-xl p-3.5 sm:p-4 text-main-color resize-none focus:outline-none focus:border-emerald-glow transition-colors mb-6 text-xs sm:text-sm" placeholder="تسک‌های این نوبت کاری را وارد کنید..."></textarea>
            <div class="flex gap-3">
                <button id="modal-confirm" class="h-11 flex-1 rounded-xl bg-emerald-glow text-bg-main font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center">تایید و شروع</button>
                <button class="modal-cancel h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center">انصراف</button>
            </div>
        `;
        openModal(html, (modal) => {
            const taskInput = modal.querySelector('#task-input');
            modal.querySelector('.modal-close-x').onclick = closeModal;
            modal.querySelector('.modal-cancel').onclick = closeModal;
            taskInput.focus();

            const confirmTask = async () => {
                const now = Date.now();
                const res = await fetch('api.php?action=start_session', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: now, startTime: now, task: toFa(taskInput.value.trim()) })
                });
                if (res.ok) {
                    closeModal();
                    fetchUserData();
                }
            };

            taskInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    confirmTask();
                }
            });
            modal.querySelector('#modal-confirm').onclick = confirmTask;
        });
    };

    const openEditModal = (session, callback) => {
        const startDate = new Date(Number(session.startTime));
        const endDate = new Date(Number(session.endTime));
        const parts = new Intl.DateTimeFormat('en-u-ca-persian', { year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(startDate);
        let jy = '', jm = '', jd = '';
        parts.forEach(p => {
            if (p.type === 'year') jy = p.value;
            if (p.type === 'month') jm = p.value;
            if (p.type === 'day') jd = p.value;
        });

        const editDateStr = toFa(`${jy}/${jm}/${jd}`);
        const editStartStr = toFa(`${String(startDate.getHours()).padStart(2, '0')}:${String(startDate.getMinutes()).padStart(2, '0')}`);
        const editEndStr = toFa(`${String(endDate.getHours()).padStart(2, '0')}:${String(endDate.getMinutes()).padStart(2, '0')}`);

        const html = `
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-base sm:text-xl font-bold text-main-color">ویرایش نوبت کاری</h2>
                <button class="modal-close-x text-soft-color hover:text-main-color"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
            </div>
            <div class="space-y-4 mb-6 text-xs sm:text-sm">
                <div>
                    <label class="block text-xs text-soft-color mb-2">تاریخ (YYYY/MM/DD)</label>
                    <input type="text" id="edit-date" value="${editDateStr}" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color focus:outline-none focus:border-emerald-glow">
                </div>
                <div class="grid grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <label class="block text-xs text-soft-color mb-2">ساعت شروع (HH:MM)</label>
                        <input type="text" id="edit-start" value="${editStartStr}" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color focus:outline-none focus:border-emerald-glow">
                    </div>
                    <div>
                        <label class="block text-xs text-soft-color mb-2">ساعت پایان (HH:MM)</label>
                        <input type="text" id="edit-end" value="${editEndStr}" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color focus:outline-none focus:border-emerald-glow">
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-soft-color mb-2">تسک‌ها / توضیحات</label>
                    <textarea id="edit-task" rows="3" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color resize-none focus:outline-none focus:border-emerald-glow">${toFa(session.task || '')}</textarea>
                </div>
            </div>
            <div class="flex gap-3">
                <button id="modal-save" class="h-11 flex-1 rounded-xl bg-emerald-glow text-bg-main font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center">ذخیره تغییرات</button>
                <button class="modal-cancel h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center">انصراف</button>
            </div>
        `;

        openModal(html, (modal) => {
            modal.querySelector('.modal-close-x').onclick = closeModal;
            modal.querySelector('.modal-cancel').onclick = closeModal;

            modal.querySelector('#modal-save').onclick = async () => {
                try {
                    const dateParts = toEn(modal.querySelector('#edit-date').value.trim()).split('/').map(Number);
                    const gregorianDate = jalaaliToGregorian(dateParts[0], dateParts[1], dateParts[2]);
                    const year = gregorianDate.getFullYear();
                    const month = gregorianDate.getMonth();
                    const day = gregorianDate.getDate();

                    const startParts = toEn(modal.querySelector('#edit-start').value.trim()).split(':').map(Number);
                    const endParts = toEn(modal.querySelector('#edit-end').value.trim()).split(':').map(Number);

                    const newStart = new Date(year, month, day, startParts[0] || 0, startParts[1] || 0).getTime();
                    const newEnd = new Date(year, month, day, endParts[0] || 0, endParts[1] || 0).getTime();

                    if (newEnd <= newStart) throw new Error("زمان پایان باید بعد از شروع باشد");

                    const res = await fetch('api.php?action=update_session', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id: session.id,
                            startTime: newStart,
                            endTime: newEnd,
                            task: toFa(modal.querySelector('#edit-task').value.trim())
                        })
                    });
                    if (res.ok) {
                        closeModal();
                        if (callback) callback();
                    }
                } catch (err) {
                    alert("خطا: " + err.message);
                }
            };
        });
    };

    const openDeleteModal = (id, callback) => {
        const html = `
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 sm:h-14 sm:w-14 rounded-xl bg-red-500/10 border border-red-500/30 mb-5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </div>
                <h2 class="text-base sm:text-lg font-bold text-main-color mb-2">حذف نوبت کاری</h2>
                <p class="text-soft-color mb-6 text-xs sm:text-sm">آیا از حذف این تسک مطمئن هستید؟</p>
                <div class="flex gap-3">
                    <button id="modal-delete" class="h-11 flex-1 rounded-xl bg-red-500 text-white font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center">بله، حذف شود</button>
                    <button class="modal-cancel h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center">انصراف</button>
                </div>
            </div>
        `;
        openModal(html, (modal) => {
            modal.querySelector('.modal-cancel').onclick = closeModal;
            modal.querySelector('#modal-delete').onclick = async () => {
                const res = await fetch('api.php?action=delete_session', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                if (res.ok) {
                    closeModal();
                    if (callback) callback();
                }
            };
        });
    };

    const openChangePasswordModal = (targetUserId, targetUserName) => {
        const isForOther = targetUserId !== currentUser.id;
        const html = `
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-base sm:text-lg font-bold text-main-color">تغییر رمز عبور ${isForOther ? `«${targetUserName}»` : ''}</h2>
                <button class="modal-close-x text-soft-color hover:text-main-color"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
            </div>
            <div class="space-y-4 mb-6 text-xs sm:text-sm">
                ${!isManager || !isForOther ? `
                <div>
                    <label class="block text-xs text-soft-color mb-2">رمز عبور فعلی</label>
                    <input type="password" id="change-old-pass" dir="ltr" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-left focus:outline-none focus:border-emerald-glow">
                </div>` : ''}
                <div>
                    <label class="block text-xs text-soft-color mb-2">رمز عبور جدید</label>
                    <input type="password" id="change-new-pass" dir="ltr" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-left focus:outline-none focus:border-emerald-glow">
                </div>
            </div>
            <div class="flex gap-3">
                <button id="modal-save-pass" class="h-11 flex-1 rounded-xl bg-emerald-glow text-bg-main font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center">ذخیره رمز جدید</button>
                <button class="modal-cancel h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center">انصراف</button>
            </div>
        `;
        openModal(html, (modal) => {
            modal.querySelector('.modal-close-x').onclick = closeModal;
            modal.querySelector('.modal-cancel').onclick = closeModal;

            modal.querySelector('#modal-save-pass').onclick = async () => {
                const oldPass = modal.querySelector('#change-old-pass') ? modal.querySelector('#change-old-pass').value : '';
                const newPass = modal.querySelector('#change-new-pass').value;

                if (!newPass) {
                    alert("رمز عبور جدید را وارد کنید");
                    return;
                }

                const res = await fetch('api.php?action=change_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: targetUserId, old_password: oldPass, new_password: newPass })
                });
                const data = await res.json();
                if (res.ok) {
                    alert("رمز عبور با موفقیت تغییر یافت");
                    closeModal();
                } else {
                    alert(data.error || "خطا در تغییر رمز عبور");
                }
            };
        });
    };

    document.getElementById('change-pass-btn').onclick = () => openChangePasswordModal(currentUser.id, currentUser.full_name);

    const triggerCsvDownload = (sessionsData, filename) => {
        let csv = '\uFEFFتاریخ,تسک,ساعت شروع,ساعت پایان,مدت زمان\n';
        sessionsData.forEach(s => {
            const start = Number(s.startTime);
            const end = Number(s.endTime);
            const dur = formatDuration(end - start);
            const date = formatSolarDate(start);
            const task = `"${(s.task || '').replace(/"/g, '""').replace(/\n/g, ' ')}"`;
            csv += `${date},${task},${formatTime(start)},${formatTime(end)},${dur}\n`;
        });
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    document.getElementById('export-csv').onclick = () => {
        triggerCsvDownload(myReportSessions, `chrono-${currentUser.username}-${Date.now()}.csv`);
    };
    document.getElementById('export-pdf').onclick = () => window.print();

    const exportUserJson = async (userId, username) => {
        try {
            const res = await fetch(`api.php?action=export_user_json&user_id=${userId}`);
            if (res.ok) {
                const data = await res.json();
                const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `backup-${username}-${Date.now()}.json`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        } catch (e) {
            alert("خطا در پشتیبان‌گیری");
        }
    };

    const importUserJson = (userId, callback) => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = async (event) => {
                try {
                    const parsed = JSON.parse(event.target.result);
                    const sessions = parsed.sessions || [];
                    const res = await fetch('api.php?action=import_user_json', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ user_id: userId, sessions })
                    });
                    if (res.ok) {
                        alert("بازیابی اطلاعات با موفقیت انجام شد");
                        if (callback) callback();
                    } else {
                        alert("خطا در بازیابی داده‌ها");
                    }
                } catch (err) {
                    alert("فایل نامعتبر است");
                }
            };
            reader.readAsText(file);
        };
        input.click();
    };

    document.getElementById('export-json-btn').onclick = () => exportUserJson(currentUser.id, currentUser.username);
    document.getElementById('import-json-btn').onclick = () => importUserJson(currentUser.id, loadMyReports);

    const loadMyReports = async () => {
        document.getElementById('rep-user-fullname').textContent = currentUser.full_name;
        document.getElementById('rep-user-username').textContent = currentUser.username;
        document.getElementById('rep-user-role').textContent = currentUser.role === 'manager' ? 'مدیر سیستم' : 'همکار';
        document.getElementById('rep-user-created').textContent = currentUser.created_at ? formatSolarDate(currentUser.created_at * 1000) : '-';

        if (isManager) {
            document.getElementById('export-json-btn').classList.remove('hidden');
            document.getElementById('import-json-btn').classList.remove('hidden');
        }

        try {
            const res = await fetch(`api.php?action=get_user_report&target_id=${currentUser.id}`);
            if (res.ok) {
                const data = await res.json();
                myReportSessions = data.sessions || [];
                renderReports();
            }
        } catch (e) {
            console.error(e);
        }
    };

    const getFilteredReportSessions = () => {
        let sessions = [...myReportSessions];
        const { text, from, to } = reportFilters;
        if (text && text.length >= 3) sessions = sessions.filter(s => s.task && s.task.includes(text));
        if (from && to) sessions = sessions.filter(s => s.startTime >= from && s.startTime <= to);
        return sessions;
    };

    const renderReports = () => {
        const filtered = getFilteredReportSessions();
        let totalMs = 0, weekMs = 0, todayMs = 0;
        let totalTasks = filtered.length;
        let weekTasks = 0, todayTasks = 0;
        const dailyData = {};
        const dailyGroups = {};
        const weekStart = getStartOfWeek(Date.now());

        filtered.forEach(s => {
            const start = Number(s.startTime);
            const end = Number(s.endTime) || Date.now();
            const durMs = end - start;
            totalMs += durMs;

            if (start >= weekStart) {
                weekMs += durMs;
                weekTasks++;
            }
            if (isToday(start)) {
                todayMs += durMs;
                todayTasks++;
            }

            const day = formatSolarDate(start);
            dailyData[day] = (dailyData[day] || 0) + durMs;
            if (!dailyGroups[day]) dailyGroups[day] = [];
            dailyGroups[day].push(s);
        });

        const uniqueDays = Object.keys(dailyData).length;
        const avgMs = uniqueDays > 0 ? (totalMs / uniqueDays) : 0;
        const avgTasks = uniqueDays > 0 ? Math.round(totalTasks / uniqueDays) : 0;

        document.getElementById('total-hours').textContent = toFa(formatDuration(totalMs));
        document.getElementById('total-tasks').textContent = `${toFa(totalTasks)} تسک ثبت شده`;
        document.getElementById('week-hours').textContent = toFa(formatDuration(weekMs));
        document.getElementById('week-tasks').textContent = `${toFa(weekTasks)} تسک ثبت شده`;
        document.getElementById('today-hours').textContent = toFa(formatDuration(todayMs));
        document.getElementById('today-tasks').textContent = `${toFa(todayTasks)} تسک ثبت شده`;
        document.getElementById('avg-hours').textContent = toFa(formatDuration(avgMs));
        document.getElementById('avg-tasks').textContent = `${toFa(avgTasks)} تسک در روز`;

        renderDailyBreakdown(dailyGroups, 'daily-breakdown');
    };

    const createReportPureRowElement = (session) => {
        const row = document.createElement('div');
        row.className = 'flex items-center justify-between gap-2 sm:gap-4 px-2 sm:px-6 py-2.5 sm:py-3 border-b border-border-main/30 text-xs sm:text-sm hover:bg-bg-soft/30 transition-colors';

        const taskCol = `<div class="flex-1 text-main-color whitespace-pre-wrap break-words leading-relaxed">${toFa(session.task || 'بدون توضیحات')}</div>`;
        const startCol = `<div class="w-16 sm:w-24 text-soft-color text-center font-medium">${formatTime(session.startTime)}</div>`;

        const dur = formatDuration(Number(session.endTime) - Number(session.startTime));
        const endCol = `<div class="w-24 sm:w-36 text-soft-color flex items-center gap-1 sm:gap-1.5 whitespace-nowrap justify-end font-medium"><span>${formatTime(session.endTime)}</span><span class="text-muted-color text-[10px] sm:text-[11px]">(${toFa(dur)})</span></div>`;

        row.innerHTML = `${taskCol}${startCol}${endCol}`;
        return row;
    };

    const renderDailyBreakdown = (dailyGroups, containerId) => {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        const sortedDays = Object.keys(dailyGroups).sort((a, b) => Number(dailyGroups[b][0].startTime) - Number(dailyGroups[a][0].startTime));

        if (sortedDays.length === 0) {
            container.innerHTML = '<div class="text-muted-color text-xs sm:text-sm py-4">داده‌ای برای نمایش وجود ندارد.</div>';
            return;
        }

        sortedDays.forEach(day => {
            const sessions = dailyGroups[day];
            let dayMs = 0;

            const dayCard = document.createElement('div');
            dayCard.className = 'bg-bg-soft/30 border border-border-main rounded-xl p-3 sm:p-4 mb-3 sm:mb-4';

            const rowsContainer = document.createElement('div');

            sessions.forEach(s => {
                const start = Number(s.startTime);
                const end = Number(s.endTime) || Date.now();
                dayMs += (end - start);

                rowsContainer.appendChild(createReportPureRowElement(s));
            });

            dayCard.innerHTML = `
                <div class="flex justify-between items-center mb-2 pb-2 border-b border-border-main/40">
                    <h4 class="text-xs sm:text-base font-bold text-emerald-glow">${day}</h4>
                    <span class="text-[11px] sm:text-xs text-soft-color font-medium">مجموع زمان: ${toFa(formatDuration(dayMs))}</span>
                </div>
            `;
            dayCard.appendChild(rowsContainer);
            container.appendChild(dayCard);
        });
    };

    const reportSearchInput = document.getElementById('report-search-input');
    const reportClearFilterBtn = document.getElementById('report-clear-filter-btn');

    reportSearchInput.addEventListener('input', (e) => {
        reportFilters.text = e.target.value.trim();
        if (reportFilters.text || reportFilters.from) reportClearFilterBtn.classList.remove('hidden');
        else reportClearFilterBtn.classList.add('hidden');
        renderReports();
    });

    reportClearFilterBtn.addEventListener('click', () => {
        reportFilters = { text: '', from: null, to: null };
        reportSearchInput.value = '';
        reportClearFilterBtn.classList.add('hidden');
        renderReports();
    });

    document.getElementById('report-date-filter-btn').addEventListener('click', () => {
        const html = `
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-base sm:text-xl font-bold text-main-color">فیلتر بازه تاریخی گزارش</h2>
                <button class="modal-close-x text-soft-color hover:text-main-color"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 mb-6">
                <div>
                    <label class="block text-xs text-soft-color mb-2">از تاریخ (YYYY/MM/DD)</label>
                    <input type="text" id="rep-date-from-input" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-xs sm:text-sm focus:outline-none focus:border-emerald-glow" placeholder="۱۴۰۵/۰۱/۰۱">
                </div>
                <div>
                    <label class="block text-xs text-soft-color mb-2">تا تاریخ (YYYY/MM/DD)</label>
                    <input type="text" id="rep-date-to-input" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-xs sm:text-sm focus:outline-none focus:border-emerald-glow" placeholder="۱۴۰۵/۰۱/۳۱">
                </div>
            </div>
            <div class="flex gap-3">
                <button id="modal-apply-rep-date" class="h-11 flex-1 rounded-xl bg-emerald-glow text-bg-main font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center">اعمال فیلتر</button>
                <button class="modal-cancel h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center">انصراف</button>
            </div>
        `;
        openModal(html, (modal) => {
            const fromInput = modal.querySelector('#rep-date-from-input');
            const toInput = modal.querySelector('#rep-date-to-input');
            modal.querySelector('.modal-close-x').onclick = closeModal;
            modal.querySelector('.modal-cancel').onclick = closeModal;

            modal.querySelector('#modal-apply-rep-date').onclick = () => {
                try {
                    const fromVal = fromInput.value.trim();
                    const toVal = toInput.value.trim();
                    if (fromVal) {
                        const [jy, jm, jd] = toEn(fromVal).split('/').map(Number);
                        reportFilters.from = jalaaliToGregorian(jy, jm, jd).getTime();
                    } else reportFilters.from = null;

                    if (toVal) {
                        const [jy, jm, jd] = toEn(toVal).split('/').map(Number);
                        let gDate = jalaaliToGregorian(jy, jm, jd);
                        gDate.setHours(23, 59, 59, 999);
                        reportFilters.to = gDate.getTime();
                    } else reportFilters.to = null;

                    reportClearFilterBtn.classList.remove('hidden');
                    closeModal();
                    renderReports();
                } catch (err) {
                    alert("فرمت تاریخ نامعتبر است");
                }
            };
        });
    });

    const loadVisibleUsers = async () => {
        try {
            const res = await fetch('api.php?action=get_visible_users');
            if (res.ok) {
                const data = await res.json();
                allUsersList = data.users || [];
                allPermissionsList = data.permissions || [];
                isManager = data.is_manager;

                if (isManager) document.getElementById('add-user-btn').classList.remove('hidden');
                else document.getElementById('add-user-btn').classList.add('hidden');

                renderUsersTable();
            }
        } catch (e) {
            console.error(e);
        }
    };

    const renderUsersTable = () => {
        const tbody = document.getElementById('user-table-body');
        tbody.innerHTML = '';

        allUsersList.forEach(u => {
            const row = document.createElement('div');
            row.className = 'px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between gap-2 text-xs sm:text-sm hover:bg-bg-soft/40 transition-colors';
            const isSelf = Number(u.id) === currentUser.id;

            let statusHtml;
            if (u.active_task) {
                const activeStart = formatTime(u.active_startTime);
                const hoverText = `در حال کار روی: ${u.active_task} (شروع: ${activeStart})`;
                statusHtml = `
                    <div class="relative group inline-flex items-center gap-1.5 cursor-pointer">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-glow animate-pulse"></span>
                        <span class="text-emerald-glow text-xs font-bold">آنلاین</span>
                        <div class="absolute bottom-full right-0 mb-2 hidden group-hover:block z-30 w-56 p-2.5 rounded-xl bg-panel border border-border-main text-xs text-soft-color shadow-2xl pointer-events-none">
                            ${hoverText}
                        </div>
                    </div>
                `;
            } else {
                statusHtml = `<span class="text-muted-color text-xs">آفلاین</span>`;
            }

            row.innerHTML = `
                <div class="w-36 sm:w-44 font-semibold text-main-color flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
                    <span>${u.full_name}</span>
                    ${u.role === 'manager' ? '<span class="text-[9px] sm:text-[10px] text-emerald-glow bg-emerald-glow/10 px-1.5 sm:px-2 py-0.5 rounded-md font-normal">مدیر</span>' : ''}
                </div>
                <div class="hidden sm:block flex-1 px-4 text-soft-color">${u.username}</div>
                <div class="w-20 text-center flex-shrink-0">${statusHtml}</div>
                <div class="w-36 sm:w-48 text-left flex justify-end gap-1.5 sm:gap-2 flex-shrink-0">
                    <button class="view-user-rep-btn px-2 sm:px-3 py-1.5 rounded-lg bg-emerald-glow/15 border border-emerald-glow/30 text-emerald-glow hover:bg-emerald-glow hover:text-bg-main transition-all text-[11px] sm:text-xs font-bold" data-id="${u.id}">گزارش</button>
                    ${isManager ? `
                        <button class="perm-btn px-2 sm:px-3 py-1.5 rounded-lg bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-[11px] sm:text-xs" data-id="${u.id}">دسترسی‌ها</button>
                        ${!isSelf ? `<button class="del-user-btn px-2 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500/20 text-[11px] sm:text-xs" data-id="${u.id}">حذف</button>` : ''}
                    ` : ''}
                </div>
            `;
            tbody.appendChild(row);
        });

        tbody.querySelectorAll('.view-user-rep-btn').forEach(b => {
            b.onclick = () => openUserReportModal(Number(b.dataset.id));
        });
        tbody.querySelectorAll('.perm-btn').forEach(b => {
            b.onclick = () => openPermissionsModal(Number(b.dataset.id));
        });
        tbody.querySelectorAll('.del-user-btn').forEach(b => {
            b.onclick = () => openDeleteUserModal(Number(b.dataset.id));
        });
    };

    const openUserReportModal = async (targetUserId) => {
        try {
            const res = await fetch(`api.php?action=get_user_report&target_id=${targetUserId}`);
            if (!res.ok) {
                alert("خطا در بارگذاری گزارش");
                return;
            }
            const data = await res.json();
            const targetUser = data.user;
            let sessions = data.sessions || [];

            let totalMs = 0, weekMs = 0, todayMs = 0;
            let totalTasks = sessions.length;
            let weekTasks = 0, todayTasks = 0;
            const dailyData = {};
            const dailyGroups = {};
            const weekStart = getStartOfWeek(Date.now());

            sessions.forEach(s => {
                const start = Number(s.startTime);
                const end = Number(s.endTime) || Date.now();
                const durMs = end - start;
                totalMs += durMs;

                if (start >= weekStart) {
                    weekMs += durMs;
                    weekTasks++;
                }
                if (isToday(start)) {
                    todayMs += durMs;
                    todayTasks++;
                }

                const day = formatSolarDate(start);
                dailyData[day] = (dailyData[day] || 0) + durMs;
                if (!dailyGroups[day]) dailyGroups[day] = [];
                dailyGroups[day].push(s);
            });

            const uniqueDays = Object.keys(dailyData).length;
            const avgMs = uniqueDays > 0 ? (totalMs / uniqueDays) : 0;
            const avgTasks = uniqueDays > 0 ? Math.round(totalTasks / uniqueDays) : 0;

            const html = `
                <div class="flex justify-between items-center mb-6 border-b border-border-main/40 pb-4">
                    <div>
                        <h2 class="text-base sm:text-xl font-bold text-main-color">گزارش کامل فعالیت: ${targetUser.full_name}</h2>
                        <p class="text-xs text-soft-color mt-1">${targetUser.username} (${targetUser.role === 'manager' ? 'مدیر' : 'همکار'})</p>
                    </div>
                    <button class="modal-close-x text-soft-color hover:text-main-color"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                </div>

                <div class="flex flex-wrap items-center gap-2 mb-6">
                    ${isManager ? `
                        <button id="modal-change-user-pass" class="px-3 py-1.5 sm:py-2 rounded-xl bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-xs font-medium">تغییر رمز کاربر</button>
                        <button id="modal-export-user-json" class="px-3 py-1.5 sm:py-2 rounded-xl bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-xs font-medium">پشتیبان JSON</button>
                        <button id="modal-import-user-json" class="px-3 py-1.5 sm:py-2 rounded-xl bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-xs font-medium">بازیابی JSON</button>
                    ` : ''}
                    <button id="modal-export-user-csv" class="px-3 py-1.5 sm:py-2 rounded-xl bg-bg-soft border border-border-main text-soft-color hover:text-emerald-glow text-xs font-medium">خروجی CSV</button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
                    <div class="bg-bg-soft/40 border border-border-main rounded-2xl p-3 sm:p-4">
                        <span class="text-xs text-soft-color">کل ساعت‌ها:</span>
                        <h4 class="text-sm sm:text-lg font-black text-emerald-glow mt-1">${toFa(formatDuration(totalMs))}</h4>
                        <span class="text-[10px] sm:text-[11px] text-muted-color">${toFa(totalTasks)} تسک</span>
                    </div>
                    <div class="bg-bg-soft/40 border border-border-main rounded-2xl p-3 sm:p-4">
                        <span class="text-xs text-soft-color">این هفته:</span>
                        <h4 class="text-sm sm:text-lg font-black text-main-color mt-1">${toFa(formatDuration(weekMs))}</h4>
                        <span class="text-[10px] sm:text-[11px] text-muted-color">${toFa(weekTasks)} تسک</span>
                    </div>
                    <div class="bg-bg-soft/40 border border-border-main rounded-2xl p-3 sm:p-4">
                        <span class="text-xs text-soft-color">میانگین روزانه:</span>
                        <h4 class="text-sm sm:text-lg font-black text-main-color mt-1">${toFa(formatDuration(avgMs))}</h4>
                        <span class="text-[10px] sm:text-[11px] text-muted-color">${toFa(avgTasks)} تسک</span>
                    </div>
                    <div class="bg-bg-soft/40 border border-border-main rounded-2xl p-3 sm:p-4">
                        <span class="text-xs text-soft-color">امروز:</span>
                        <h4 class="text-sm sm:text-lg font-black text-main-color mt-1">${toFa(formatDuration(todayMs))}</h4>
                        <span class="text-[10px] sm:text-[11px] text-muted-color">${toFa(todayTasks)} تسک</span>
                    </div>
                </div>

                <h3 class="text-sm font-bold text-main-color mb-3">نوبت‌های کاری</h3>
                <div id="modal-daily-breakdown" class="max-h-72 sm:max-h-80 overflow-y-auto space-y-3 pr-1 mb-6"></div>

                <div class="flex">
                    <button class="modal-cancel h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center">بستن</button>
                </div>
            `;

            openModal(html, (modal) => {
                modal.querySelector('.modal-close-x').onclick = closeModal;
                modal.querySelector('.modal-cancel').onclick = closeModal;

                renderDailyBreakdown(dailyGroups, 'modal-daily-breakdown');

                modal.querySelector('#modal-export-user-csv').onclick = () => {
                    triggerCsvDownload(sessions, `chrono-${targetUser.username}-${Date.now()}.csv`);
                };

                if (isManager) {
                    modal.querySelector('#modal-change-user-pass').onclick = () => openChangePasswordModal(targetUser.id, targetUser.full_name);
                    modal.querySelector('#modal-export-user-json').onclick = () => exportUserJson(targetUser.id, targetUser.username);
                    modal.querySelector('#modal-import-user-json').onclick = () => importUserJson(targetUser.id, () => {
                        closeModal();
                        openUserReportModal(targetUserId);
                    });
                }
            }, 'max-w-4xl');
        } catch (e) {
            console.error(e);
        }
    };

    document.getElementById('add-user-btn').addEventListener('click', () => {
        const html = `
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-base sm:text-lg font-bold text-main-color">افزودن همکار جدید</h2>
                <button class="modal-close-x text-soft-color hover:text-main-color"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
            </div>
            <div class="space-y-4 mb-6 text-xs sm:text-sm">
                <div>
                    <label class="block text-xs text-soft-color mb-2">نام و نام خانوادگی</label>
                    <input type="text" id="new-user-fullname" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color focus:outline-none focus:border-emerald-glow" placeholder="مثال: علی رضایی">
                </div>
                <div>
                    <label class="block text-xs text-soft-color mb-2">نام کاربری</label>
                    <input type="text" id="new-user-username" dir="ltr" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-left focus:outline-none focus:border-emerald-glow" placeholder="alirezai">
                </div>
                <div>
                    <label class="block text-xs text-soft-color mb-2">رمز عبور</label>
                    <input type="password" id="new-user-password" dir="ltr" class="w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color text-left focus:outline-none focus:border-emerald-glow">
                </div>
                <div>
                    <label class="block text-xs text-soft-color mb-2">نقش کاربری</label>
                    <select id="new-user-role" class="custom-select w-full bg-bg-soft border border-border-main rounded-xl p-3 text-main-color focus:outline-none focus:border-emerald-glow">
                        <option value="partner">همکار</option>
                        <option value="manager">مدیر</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3">
                <button id="modal-save-user" class="h-11 flex-1 rounded-xl bg-emerald-glow text-bg-main font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center">ایجاد کاربر</button>
                <button class="modal-cancel h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center">انصراف</button>
            </div>
        `;
        openModal(html, (modal) => {
            modal.querySelector('.modal-close-x').onclick = closeModal;
            modal.querySelector('.modal-cancel').onclick = closeModal;

            modal.querySelector('#modal-save-user').onclick = async () => {
                const fullName = modal.querySelector('#new-user-fullname').value.trim();
                const username = modal.querySelector('#new-user-username').value.trim();
                const password = modal.querySelector('#new-user-password').value;
                const role = modal.querySelector('#new-user-role').value;

                if (!fullName || !username || !password) {
                    alert("تمام فیلدها الزامی هستند");
                    return;
                }

                const res = await fetch('api.php?action=admin_create_user', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ full_name: fullName, username, password, role })
                });
                const data = await res.json();
                if (res.ok) {
                    closeModal();
                    loadVisibleUsers();
                } else {
                    alert(data.error || "خطا در ثبت کاربر");
                }
            };
        });
    });

    const openDeleteUserModal = (userId) => {
        const targetUser = allUsersList.find(u => Number(u.id) === userId);
        const html = `
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 sm:h-14 sm:w-14 rounded-xl bg-red-500/10 border border-red-500/30 mb-5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </div>
                <h2 class="text-base sm:text-lg font-bold text-main-color mb-2">حذف کاربر</h2>
                <p class="text-soft-color mb-6 text-xs sm:text-sm">آیا از حذف «${targetUser?.full_name || ''}» اطمینان دارید؟</p>
                <div class="flex gap-3">
                    <button id="modal-confirm-del-user" class="h-11 flex-1 rounded-xl bg-red-500 text-white font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center">بله، حذف شود</button>
                    <button class="modal-cancel h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center">انصراف</button>
                </div>
            </div>
        `;
        openModal(html, (modal) => {
            modal.querySelector('.modal-cancel').onclick = closeModal;
            modal.querySelector('#modal-confirm-del-user').onclick = async () => {
                const res = await fetch('api.php?action=admin_delete_user', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId })
                });
                if (res.ok) {
                    closeModal();
                    loadVisibleUsers();
                }
            };
        });
    };

    const openPermissionsModal = (viewerId) => {
        const targetUser = allUsersList.find(u => Number(u.id) === viewerId);
        const currentPermTargets = allPermissionsList
            .filter(p => Number(p.viewer_id) === viewerId)
            .map(p => Number(p.target_id));

        const otherUsers = allUsersList.filter(u => Number(u.id) !== viewerId);

        let checkboxesHtml = '';
        if (otherUsers.length === 0) {
            checkboxesHtml = '<div class="text-xs text-muted-color py-4">کاربر دیگری برای تخصیص دسترسی وجود ندارد.</div>';
        } else {
            otherUsers.forEach(u => {
                const isChecked = currentPermTargets.includes(Number(u.id)) ? 'checked' : '';
                checkboxesHtml += `
                    <label class="flex items-center gap-3 p-2.5 rounded-xl bg-bg-soft/50 hover:bg-bg-soft border border-border-main/50 cursor-pointer">
                        <input type="checkbox" class="perm-checkbox w-4 h-4 rounded text-emerald-glow focus:ring-0" value="${u.id}" ${isChecked}>
                        <span class="text-xs sm:text-sm text-main-color font-medium">${u.full_name} (${u.username})</span>
                    </label>
                `;
            });
        }

        const html = `
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-main-color">دسترسی گزارش‌ها</h2>
                    <p class="text-xs text-soft-color mt-1">«${targetUser?.full_name}» می‌تواند گزارش این کاربران را مشاهده کند:</p>
                </div>
                <button class="modal-close-x text-soft-color hover:text-main-color"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
            </div>
            <div class="space-y-2 mb-6 max-h-60 overflow-y-auto pr-1">
                ${checkboxesHtml}
            </div>
            <div class="flex gap-3">
                <button id="modal-save-perms" class="h-11 flex-1 rounded-xl bg-emerald-glow text-bg-main font-bold transition-all hover:opacity-90 active:scale-95 text-xs sm:text-sm flex items-center justify-center">ذخیره دسترسی‌ها</button>
                <button class="modal-cancel h-11 flex-1 rounded-xl bg-bg-soft border border-border-main text-soft-color font-medium transition-all hover:bg-border-main active:scale-95 text-xs sm:text-sm flex items-center justify-center">انصراف</button>
            </div>
        `;

        openModal(html, (modal) => {
            modal.querySelector('.modal-close-x').onclick = closeModal;
            modal.querySelector('.modal-cancel').onclick = closeModal;

            modal.querySelector('#modal-save-perms').onclick = async () => {
                const checkedIds = Array.from(modal.querySelectorAll('.perm-checkbox:checked')).map(cb => Number(cb.value));
                const res = await fetch('api.php?action=admin_save_permissions', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ viewer_id: viewerId, targets: checkedIds })
                });
                if (res.ok) {
                    closeModal();
                    loadVisibleUsers();
                }
            };
        });
    };

    const checkAuth = async () => {
        try {
            const res = await fetch('api.php?action=me');
            if (res.ok) {
                const data = await res.json();
                currentUser = data.user;
                isManager = (currentUser.role === 'manager');
                showApp(data.can_view_users);
            } else {
                showLogin();
            }
        } catch (e) {
            showLogin();
        }
    };

    const showLogin = () => {
        loginContainer.classList.remove('hidden');
        appContainer.classList.add('hidden');
        appContainer.classList.remove('flex');
    };

    const showApp = (canViewUsers) => {
        loginContainer.classList.add('hidden');
        appContainer.classList.remove('hidden');
        appContainer.classList.add('flex');
        
        const roleLabel = currentUser.role === 'manager' ? 'مدیر' : 'همکار';
        userInfoText.textContent = `${currentUser.full_name} (${roleLabel})`;
        mobUserInfoText.textContent = `${currentUser.full_name} (${roleLabel})`;

        if (canViewUsers) {
            tabUsers.classList.remove('hidden');
            mobTabUsers.classList.remove('hidden');
        } else {
            tabUsers.classList.add('hidden');
            mobTabUsers.classList.add('hidden');
        }

        fetchUserData();
    };

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        loginError.classList.add('hidden');
        const username = document.getElementById('username-input').value.trim();
        const password = document.getElementById('password-input').value;

        try {
            const res = await fetch('api.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const data = await res.json();
            if (res.ok) {
                currentUser = data.user;
                isManager = (currentUser.role === 'manager');
                loginForm.reset();
                checkAuth();
            } else {
                loginError.textContent = data.error || 'خطا در ورود';
                loginError.classList.remove('hidden');
            }
        } catch (err) {
            loginError.textContent = 'خطا در برقراری ارتباط با سرور';
            loginError.classList.remove('hidden');
        }
    });

    const logoutUser = async () => {
        await fetch('api.php?action=logout');
        currentUser = null;
        state.sessions = [];
        state.activeSession = null;
        showLogin();
    };

    logoutBtn.addEventListener('click', logoutUser);
    document.getElementById('mob-logout-btn').addEventListener('click', logoutUser);

    checkAuth();
});