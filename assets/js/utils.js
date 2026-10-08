export const toFa = (str) => {
    if (str === null || str === undefined) return '';
    return str.toString().replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
};

export const toEn = (str) => {
    if (!str) return '';
    return str.toString().replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
};

export const formatSolarDate = (ts) => {
    try {
        return toFa(new Intl.DateTimeFormat('fa-IR-u-ca-persian', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(ts)).replace(/-/g, '/'));
    } catch (e) {
        return toFa(new Date(ts).toLocaleDateString('fa-IR'));
    }
};

export const formatTime = (ts) => toFa(new Intl.DateTimeFormat('fa-IR', { hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date(ts)));

export const formatDuration = (ms) => {
    if (ms < 0) ms = 0;
    let totalMinutes = Math.floor(ms / 60000);
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
};

export function jalaaliToGregorian(jy, jm, jd) {
    jy = Number(toEn(jy)); jm = Number(toEn(jm)); jd = Number(toEn(jd));
    let gy = (jy <= 979) ? 621 : 1600;
    jy -= (jy <= 979) ? 0 : 979;
    let days = (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor((jy % 33 + 3) / 4) + 78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
    gy += 400 * Math.floor(days / 146097);
    days %= 146097;
    if (days > 36524) { gy += 100 * Math.floor(--days / 36524); days %= 36524; if (days >= 365) days++; }
    gy += 4 * Math.floor(days / 1461); days %= 1461;
    if (days > 365) { gy += Math.floor((days - 1) / 365); days = (days - 1) % 365; }
    let gd = days + 1;
    let sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    let gm = 0;
    for (gm = 0; gm < 13 && gd > sal_a[gm]; gm++) gd -= sal_a[gm];
    return new Date(gy, gm - 1, gd);
}

export const isToday = (ts) => new Date(Number(ts)).toDateString() === new Date().toDateString();

export const getStartOfWeek = (ts) => {
    const d = new Date(Number(ts));
    const day = d.getDay();
    const diff = (day === 6) ? 0 : day + 1;
    d.setDate(d.getDate() - diff);
    d.setHours(0, 0, 0, 0);
    return d.getTime();
};