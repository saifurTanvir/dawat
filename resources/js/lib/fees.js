/**
 * F56: what comes off a cook's price — Dawat's fee and her delivery share — as
 * set by admins (for everyone, per area or per cook). Same sums as
 * App\Services\Money\CookFees::breakdown. fees = { platform: {type, value}, delivery: {type, value} },
 * value in paisa ("fixed", per plate) or hundredths of a percent ("percent").
 */
export function cookShare(priceTaka, fees) {
    const price = Math.max(0, Math.round(Number(priceTaka) || 0) * 100);
    const part = (rule) => {
        if (!rule) return 0;
        return rule.type === 'percent' ? Math.round((price * rule.value) / 10000) : rule.value;
    };
    const platform = Math.min(price, part(fees?.platform));
    const delivery = Math.min(price - platform, part(fees?.delivery));
    return { price, platform, delivery, cook: price - platform - delivery };
}
