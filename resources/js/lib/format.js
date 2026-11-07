import { bn } from './bn';

/** 380 → "৩৮০ মি", 1450 → "১.৫ কিমি" */
export function distanceText(metres) {
    if (metres == null) return '';
    if (metres < 1000) {
        return `${bn(Math.round(metres / 10) * 10)} মি`;
    }
    return `${bn((metres / 1000).toFixed(1))} কিমি`;
}

/** Walking minutes at ~80 m/min, rounded up, never below 1. */
export function walkingMinutes(metres) {
    return Math.max(1, Math.ceil(metres / 80));
}

/** 15000 paisa → "৳১৫০" */
export function taka(paisa) {
    return `৳${bn(Math.round(paisa / 100))}`;
}

const TZ = 'Asia/Dhaka';

function dhakaParts(date) {
    const parts = new Intl.DateTimeFormat('en-GB', { timeZone: TZ, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
        .formatToParts(new Date(date));
    const get = (type) => Number(parts.find((p) => p.type === type)?.value ?? 0);
    return { hour: get('hour'), minute: get('minute') };
}

function dayPart(hour) {
    if (hour < 4) return 'রাত';
    if (hour < 6) return 'ভোর';
    if (hour < 12) return 'সকাল';
    if (hour < 15) return 'দুপুর';
    if (hour < 18) return 'বিকেল';
    if (hour < 20) return 'সন্ধ্যা';
    return 'রাত';
}

/** ISO time → "দুপুর ২:৩০" in Dhaka time. */
export function clockText(iso) {
    const { hour, minute } = dhakaParts(iso);
    const h12 = hour % 12 === 0 ? 12 : hour % 12;
    return `${dayPart(hour)} ${bn(h12)}:${bn(String(minute).padStart(2, '0'))}`;
}

/** ISO time → "এইমাত্র", "১০ মিনিট আগে", "২ ঘণ্টা আগে". */
export function agoText(iso, now = Date.now()) {
    const minutes = Math.floor((now - new Date(iso).getTime()) / 60000);
    if (minutes < 1) return 'এইমাত্র';
    if (minutes < 60) return `${bn(minutes)} মিনিট আগে`;
    return `${bn(Math.floor(minutes / 60))} ঘণ্টা আগে`;
}

/** Default meal slot for the current Dhaka time. */
export function currentMealSlot(now = new Date()) {
    const { hour } = dhakaParts(now);
    if (hour < 11) return 'breakfast';
    if (hour < 16) return 'lunch';
    if (hour < 19) return 'snack';
    return 'dinner';
}
