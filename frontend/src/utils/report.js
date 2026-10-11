import { toFa, formatSolarDate, formatTime, formatDuration, isToday, getStartOfWeek } from './format.js';

export function computeStats(list) {
    let totalMs = 0, weekMs = 0, todayMs = 0, weekTasks = 0, todayTasks = 0;
    const totalTasks = list.length;
    const dailyData = {};
    const dailyGroups = {};
    const weekStart = getStartOfWeek(Date.now());

    list.forEach(s => {
        const start = Number(s.startTime);
        const end = Number(s.endTime) || Date.now();
        const dur = end - start;
        totalMs += dur;
        if (start >= weekStart) { weekMs += dur; weekTasks++; }
        if (isToday(start)) { todayMs += dur; todayTasks++; }
        const day = formatSolarDate(start);
        dailyData[day] = (dailyData[day] || 0) + dur;
        if (!dailyGroups[day]) dailyGroups[day] = [];
        dailyGroups[day].push(s);
    });

    const uniqueDays = Object.keys(dailyData).length;
    const avgMs = uniqueDays > 0 ? (totalMs / uniqueDays) : 0;
    const avgTasks = uniqueDays > 0 ? Math.round(totalTasks / uniqueDays) : 0;
    return { totalMs, weekMs, todayMs, avgMs, totalTasks, weekTasks, todayTasks, avgTasks, dailyGroups };
}

export function sortedDays(dailyGroups) {
    return Object.keys(dailyGroups).sort((a, b) => Number(dailyGroups[b][0].startTime) - Number(dailyGroups[a][0].startTime));
}

export function dayTotal(list) {
    return list.reduce((acc, s) => acc + ((Number(s.endTime) || Date.now()) - Number(s.startTime)), 0);
}

export function downloadCsv(list, filename) {
    let csv = '﻿تاریخ,تسک,ساعت شروع,ساعت پایان,مدت زمان\n';
    list.forEach(s => {
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
}

export function downloadJson(data, filename) {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
