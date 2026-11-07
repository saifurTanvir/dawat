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
