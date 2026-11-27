/**
 * F33: how people reach the Dawat team. Same number as config('dawat.contact.phone').
 */
export const CONTACT = {
    phone: '01685284290',
    phoneIntl: '+8801685284290',
    whatsapp: 'https://wa.me/8801685284290?text=' + encodeURIComponent('আসসালামু আলাইকুম, দাওয়াত সম্পর্কে জানতে চাই।'),
    facebook: 'https://www.facebook.com/groups/dawat.cloud/',
};

const LAUNCH_TAIL = 'অন্য এলাকায় ধাপে ধাপে আসছি; গৃহিণী বা গ্রাহক হিসেবে এখনই নাম দিয়ে রাখতে পারেন, আপনার এলাকায় চালু হলে আমরাই যোগাযোগ করব।';

/** Where Dawat runs, from the areas ticked in admin (F111); same text as Area::launchNotice(). */
export function launchNotice(areas = []) {
    const names = areas.filter((a) => a.serving).map((a) => a.name);
    const head = names.length ? `দাওয়াত এখন চালু: ${names.length > 1 ? `${names.slice(0, -1).join(', ')} আর ${names.at(-1)}` : names[0]}।` : 'দাওয়াত এখন ঢাকার কয়েকটা এলাকায় চালু।';
    return `${head} ${LAUNCH_TAIL}`;
}

/** Before the area list has loaded (the cook page). */
export const LAUNCH_NOTICE = launchNotice();

/**
 * F33 popup. F52 turned it off: the first screen now explains Dawat itself,
 * and the footer keeps the same phone and WhatsApp.
 * Set to true to bring the popup back.
 */
export const JOIN_BANNER_ENABLED = false;
