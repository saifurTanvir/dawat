const BN_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

/** 60 → "৬০". Use for numbers shown in running text. */
export function bn(value) {
    return String(value).replace(/\d/g, (d) => BN_DIGITS[Number(d)]);
}

const VOWEL_SIGNS = ['া', 'ি', 'ী', 'ু', 'ূ', 'ে', 'ৈ', 'ো', 'ৌ'];

/** Bangla "of" for a place: বাড্ডা → বাড্ডার, শাহজাদপুর → শাহজাদপুরের, গুলশান ১ → গুলশান ১-এর (same rule as App\Support\Seo::of). */
export function placeOf(name) {
    const last = name.slice(-1);
    if (VOWEL_SIGNS.includes(last)) return `${name}র`;
    if (/[ক-হৎড়-য়]/.test(last)) return `${name}ের`;
    return `${name}-এর`;
}
