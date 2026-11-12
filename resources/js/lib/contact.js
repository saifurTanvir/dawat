/**
 * F33: how people reach the Dawat team. Same number as config('dawat.contact.phone').
 */
export const CONTACT = {
    phone: '01685284290',
    phoneIntl: '+8801685284290',
    whatsapp: 'https://wa.me/8801685284290?text=' + encodeURIComponent('আসসালামু আলাইকুম, দাওয়াত সম্পর্কে জানতে চাই।'),
    facebook: 'https://www.facebook.com/groups/dawat.cloud/',
};

/**
 * F33 popup. F52 turned it off: the first screen now explains Dawat itself,
 * and the footer keeps the same phone and WhatsApp.
 * Set to true to bring the popup back.
 */
export const JOIN_BANNER_ENABLED = false;
