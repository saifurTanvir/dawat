// F54: the Facebook (Meta) pixel. It is only on the page when META_PIXEL_ID is set
// (resources/views/partials/meta-pixel.blade.php); otherwise these do nothing.

function fbq(...args) {
    if (typeof window !== 'undefined' && typeof window.fbq === 'function') {
        window.fbq(...args);
    }
}

export function pageView() {
    fbq('track', 'PageView');
}

/**
 * Fires an event the server has already reported to Meta, with the same id,
 * so Meta counts it once. The server sends it as `meta_event` in the response.
 */
export function trackServerEvent(event) {
    if (!event || !event.name || !event.id) {
        return;
    }
    fbq('track', event.name, event.params || {}, { eventID: event.id });
}
