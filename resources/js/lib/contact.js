/**
 * F33: how people reach the Dawat team. Same number as config('dawat.contact.phone').
 */
export const CONTACT = {
    phone: '01685284290',
    phoneIntl: '+8801685284290',
    whatsapp: 'https://wa.me/8801685284290?text=' + encodeURIComponent('আসসালামু আলাইকুম, দাওয়াত সম্পর্কে জানতে চাই।'),
    facebook: 'https://www.facebook.com/groups/dawat.cloud/',
};

/** F104 (owner, 23 Nov): same text as config('dawat.launch.notice'). */
export const LAUNCH_NOTICE = 'দাওয়াত এখন চালু শুধু বাড্ডায় — উত্তর, মধ্য, দক্ষিণ ও মেরুল বাড্ডা, হাতিরঝিল-পাড়ের বাড্ডা, লিংক রোড আর শাহজাদপুর-বাঁশতলা। অন্য এলাকায় ধাপে ধাপে আসছি; গৃহিণী বা গ্রাহক হিসেবে এখনই নাম দিয়ে রাখতে পারেন, আপনার এলাকায় চালু হলে আমরাই যোগাযোগ করব।';

/**
 * F33 popup. F52 turned it off: the first screen now explains Dawat itself,
 * and the footer keeps the same phone and WhatsApp.
 * Set to true to bring the popup back.
 */
export const JOIN_BANNER_ENABLED = false;
