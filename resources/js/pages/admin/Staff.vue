<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { UserPlus, ShieldCheck, Users, LoaderCircle, CheckCircle2 } from '@lucide/vue';
import { ref } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import { t, formatDate, formatNumber } from '@/i18n';

defineOptions({ layout: { breadcrumbs: [{ title: 'staff.title', href: '/admin/staff' }] } });
type Staff = { id: number; name: string; email: string; is_it_admin: boolean; is_active: boolean; email_verified_at: string | null; created_at: string };
defineProps<{ staff: { data: Staff[]; total: number; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null }; status?: string }>();
const form = useForm({ name: '', email: '' });
const invitation = useForm({});
const page = usePage();
const selectedAccount = ref<Staff | null>(null);
const accountStatus = useForm({ is_active: true });
function changeStatus() {
    if (!selectedAccount.value) return;
    accountStatus.is_active = !selectedAccount.value.is_active;
    accountStatus.patch(`/admin/staff/${selectedAccount.value.id}/status`, { preserveScroll: true, onSuccess: () => { selectedAccount.value = null; } });
}
function resend(id: number) { invitation.post(`/admin/staff/${id}/invitation`, { preserveScroll: true }); }
function submit() {
    form.post('/admin/staff', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="t('staff.title')" />
    <main class="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-8">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div><h1 class="text-2xl font-semibold tracking-tight">{{ t('staff.title') }}</h1><p class="mt-2 max-w-2xl text-sm text-muted-foreground">{{ t('staff.subtitle') }}</p></div>
            <span class="inline-flex items-center gap-2 rounded-full bg-teal-50 px-3 py-2 text-xs font-medium text-teal-800 dark:bg-teal-950 dark:text-teal-200"><ShieldCheck class="size-4" />{{ t('staff.admin_only') }}</span>
        </header>
        <p v-if="status" role="status" class="flex items-start gap-2 rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900 dark:border-teal-800 dark:bg-teal-950 dark:text-teal-100"><CheckCircle2 class="size-5 shrink-0" />{{ t(status) }}</p>
        <div class="grid items-start gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <h2 class="mb-2 flex items-center gap-2 font-semibold"><UserPlus class="size-5 text-teal-600" />{{ t('staff.new') }}</h2>
                <p class="mb-5 text-xs leading-5 text-muted-foreground">{{ t('staff.verification_note') }}</p>
                <form class="space-y-4" @submit.prevent="submit">
                    <div><label for="staff-name" class="text-sm font-medium">{{ t('Full name') }}</label><input id="staff-name" v-model="form.name" required maxlength="255" autocomplete="off" class="mt-1 w-full rounded-lg border bg-background px-3 py-2" :disabled="form.processing" :aria-invalid="!!form.errors.name" /><InputError :message="form.errors.name" /></div>
                    <div><label for="staff-email" class="text-sm font-medium">{{ t('Alamat email') }}</label><input id="staff-email" v-model="form.email" type="email" required maxlength="255" autocomplete="off" class="mt-1 w-full rounded-lg border bg-background px-3 py-2" :disabled="form.processing" :aria-invalid="!!form.errors.email" /><InputError :message="form.errors.email" /></div>
                    <div class="rounded-xl bg-muted/50 p-3 text-xs leading-5 text-muted-foreground">{{ t('staff.role_note') }}</div>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-teal-700 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-800 disabled:opacity-50" :disabled="form.processing"><LoaderCircle v-if="form.processing" class="size-4 animate-spin" /><UserPlus v-else class="size-4" />{{ t(form.processing ? 'Memproses…' : 'staff.submit') }}</button>
                </form>
            </section>
            <section class="min-w-0 overflow-hidden rounded-2xl border bg-card shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b p-5"><h2 class="flex items-center gap-2 font-semibold"><Users class="size-5 text-teal-600" />{{ t('staff.list') }}</h2><span class="text-sm text-muted-foreground">{{ t('items.count', { count: formatNumber(staff.total) }) }}</span></div>
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-muted/40 text-xs text-muted-foreground"><tr><th class="p-4">{{ t('Nama') }}</th><th class="p-4">{{ t('staff.role') }}</th><th class="p-4">{{ t('staff.status') }}</th><th class="p-4">{{ t('staff.date') }}</th><th class="p-4">{{ t('staff.account_status') }}</th></tr></thead><tbody><tr v-for="person in staff.data" :key="person.id" class="border-t"><td class="p-4"><p class="font-medium">{{ person.name }}</p><p class="mt-1 break-all text-xs text-muted-foreground">{{ person.email }}</p></td><td class="whitespace-nowrap p-4">{{ t(person.is_it_admin ? 'staff.admin' : 'staff.member') }}</td><td class="p-4"><span class="inline-block rounded-full px-2 py-1 text-xs" :class="person.email_verified_at ? 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200' : 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200'">{{ t(person.email_verified_at ? 'staff.verified' : 'staff.pending') }}</span><button v-if="!person.email_verified_at && person.is_active" class="mt-2 block text-xs text-teal-700 hover:underline disabled:opacity-50 dark:text-teal-300" :disabled="invitation.processing" @click="resend(person.id)">{{ t('staff.resend_invite') }}</button></td><td class="whitespace-nowrap p-4 text-xs text-muted-foreground">{{ formatDate(person.created_at) }}</td><td class="p-4"><p class="mb-2 text-xs font-medium" :class="person.is_active ? 'text-teal-700 dark:text-teal-300' : 'text-red-600 dark:text-red-300'">{{ t(person.is_active ? 'staff.active' : 'staff.inactive') }}</p><button class="rounded-lg border px-3 py-2 text-xs hover:bg-muted disabled:opacity-40" :disabled="person.id === page.props.auth.user.id || accountStatus.processing" @click="accountStatus.clearErrors(); selectedAccount = person">{{ t(person.is_active ? 'staff.deactivate' : 'staff.activate') }}</button></td></tr></tbody></table></div>
                <footer class="flex items-center justify-between gap-3 border-t p-4 text-xs"><span>{{ formatNumber(staff.current_page) }} / {{ formatNumber(staff.last_page) }}</span><div class="flex gap-3"><Link v-if="staff.prev_page_url" :href="staff.prev_page_url" preserve-scroll class="rounded-lg border px-3 py-2">{{ t('Halaman sebelumnya') }}</Link><Link v-if="staff.next_page_url" :href="staff.next_page_url" preserve-scroll class="rounded-lg border px-3 py-2">{{ t('Halaman berikutnya') }}</Link></div></footer>
            </section>
        </div>
        <Dialog :open="!!selectedAccount" @update:open="value => { if (!value && !accountStatus.processing) selectedAccount = null; }">
            <DialogContent :show-close-button="!accountStatus.processing" @escape-key-down="event => { if (accountStatus.processing) event.preventDefault(); }" @pointer-down-outside="event => { if (accountStatus.processing) event.preventDefault(); }">
                <DialogHeader><DialogTitle>{{ t(selectedAccount?.is_active ? 'staff.deactivate' : 'staff.activate') }}</DialogTitle><DialogDescription>{{ t(selectedAccount?.is_active ? 'staff.disable_note' : 'staff.enable_note') }}</DialogDescription></DialogHeader>
                <p class="break-all text-sm font-medium">{{ selectedAccount?.name }} · {{ selectedAccount?.email }}</p>
                <InputError :message="accountStatus.errors.is_active" />
                <div class="flex justify-end gap-2"><button class="rounded-lg border px-4 py-2 text-sm" :disabled="accountStatus.processing" @click="selectedAccount = null">{{ t('Batal') }}</button><button class="rounded-lg bg-teal-700 px-4 py-2 text-sm text-white disabled:opacity-50" :disabled="accountStatus.processing" @click="changeStatus">{{ t(accountStatus.processing ? 'Memproses…' : selectedAccount?.is_active ? 'staff.deactivate' : 'staff.activate') }}</button></div>
            </DialogContent>
        </Dialog>
    </main>
</template>
