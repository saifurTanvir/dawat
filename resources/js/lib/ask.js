import { reactive } from 'vue';

/**
 * In-app yes/no question instead of window.confirm (QA report): the browser's box
 * shows the site address, small English buttons, and on some phones can be silenced.
 * Usage: if (!(await ask('বক্স বাতিল করবেন?', { yes: 'বাতিল করুন', danger: true }))) return;
 */
export const asking = reactive({ open: false, text: '', yes: 'হ্যাঁ', no: 'না', danger: false, resolve: null });

export function ask(text, { yes = 'হ্যাঁ', no = 'না', danger = false } = {}) {
    asking.resolve?.(false); // a second question replaces the first
    return new Promise((resolve) => {
        Object.assign(asking, { open: true, text, yes, no, danger, resolve });
    });
}

export function answer(value) {
    const resolve = asking.resolve;
    Object.assign(asking, { open: false, resolve: null });
    resolve?.(value);
}
