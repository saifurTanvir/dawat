const BN_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

/** 60 → "৬০". Use for numbers shown in running text. */
export function bn(value) {
    return String(value).replace(/\d/g, (d) => BN_DIGITS[Number(d)]);
}
