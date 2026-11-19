import { getCurrentInstance, ref } from 'vue';
import type { App, Ref } from 'vue';
import messages from './messages.json';

export type Locale = 'id' | 'en' | 'zh-CN';
export const locale = ref<Locale>('id');
const localeKey = Symbol('sharefolder-locale');
export function useLocale(): Ref<Locale> {
    const scoped = getCurrentInstance()?.appContext.provides[localeKey] as Ref<Locale> | undefined;
    return scoped ?? locale;
}
function activeLocale(): Locale { return useLocale().value; }
export function installLocale(app: App, initial: unknown, ssr: boolean) {
    const value = validLocale(initial) ? initial : 'id';
    // Each SSR app gets its own state; concurrent visitors cannot share a locale.
    if (ssr) app.provide(localeKey, ref(value));
    else {
        locale.value = value;
        app.provide(localeKey, locale);
        app.mixin({ mounted() { if (this === this.$root) initializeLocale(); } });
    }
}
const catalog: Record<string, { id?: string; en: string; 'zh-CN': string }> = messages;
export function validLocale(value: unknown): value is Locale { return value === 'id' || value === 'en' || value === 'zh-CN'; }
export function t(key: string | undefined, params: Record<string, string | number> = {}): string {
    if (!key) return '';
    const entry = catalog[key];
    const text = entry?.[activeLocale()] ?? entry?.id ?? key;
    return text.replace(/\{(\w+)\}/g, (match, name: string) => Object.hasOwn(params, name) ? String(params[name]) : match);
}
export function setLocale(value: unknown) {
    if (!validLocale(value)) return;
    locale.value = value;
    if (typeof document !== 'undefined') {
        document.documentElement.lang = value;
        document.cookie = `app_locale=${value}; Path=/; Max-Age=31536000; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
        try { localStorage.setItem('sharefolder.locale', value); } catch { /* Cookies still preserve the choice. */ }
    }
}
export function initializeLocale() {
    if (typeof document === 'undefined') return;
    const cookie = document.cookie.split('; ').find(item => item.startsWith('app_locale='))?.slice(11);
    let saved: string | null = null;
    try { saved = localStorage.getItem('sharefolder.locale'); } catch { /* Storage can be disabled. */ }
    setLocale(validLocale(cookie) ? cookie : validLocale(saved) ? saved : 'id');
}
export function formatDate(value: string) {
    return new Intl.DateTimeFormat(activeLocale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
export function formatNumber(value: number, maximumFractionDigits = 0) {
    return new Intl.NumberFormat(activeLocale(), { maximumFractionDigits }).format(value);
}
