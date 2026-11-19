<script setup lang="ts">
import { t, formatDate, formatNumber } from '@/i18n';
import { ref, watch } from 'vue';
import { History, ChevronLeft, ChevronRight, LoaderCircle, RefreshCw } from '@lucide/vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { shareApi } from '@/lib/shareApi';

type Activity = { id: number; actor_name: string; action: string; status: string; http_status: number | null; created_at: string; details: { path?: string; name?: string; original_path?: string; id?: string; sources?: string[]; files?: string[] } };
const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: [] }>();
const labels: Record<string, string> = { folder: 'Buat folder', file: 'Buat file', rename: 'Ganti nama', copy: 'Salin', move: 'Pindahkan', delete: 'Ke Recycle Bin', restore: 'Pulihkan', purge: 'Hapus permanen', upload: 'Upload', download: 'Unduh', preview: 'Preview', unknown: 'Permintaan tidak valid' };
const statuses: Record<string, string> = { success: 'Berhasil', failed: 'Gagal', prepared: 'Unduhan disiapkan', processing: 'Hasil belum tercatat' };
const rows = ref<Activity[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const action = ref('');
const loading = ref(false);
const error = ref('');
async function load(target = 1) {
    if (loading.value) return;
    loading.value = true; error.value = '';
    try {
        const data = await shareApi(`/share/activity?page=${target}&action=${encodeURIComponent(action.value)}`);
        rows.value = data.data; page.value = data.current_page; lastPage.value = data.last_page; total.value = data.total;
    } catch (cause) { error.value = (cause as Error).message; }
    finally { loading.value = false; }
}
function targets(row: Activity): string {
    const d = row.details;
    if (d.original_path) return d.original_path;
    if (d.sources?.length) return d.sources.join(', ');
    if (d.files?.length) return d.files.map(name => [d.path, name].filter(Boolean).join('/')).join(', ');
    return [d.path, d.name].filter(Boolean).join('/') || d.id || '/';
}
watch(() => props.open, open => { if (open) { action.value = ''; void load(); } });
</script>

<template>
    <Dialog :open="open" @update:open="value => { if (!value) emit('close'); }">
        <DialogContent class="max-h-[90svh] overflow-y-auto rounded-2xl sm:max-w-4xl">
            <DialogHeader><DialogTitle class="flex items-center gap-2"><History class="size-5 text-teal-600" /> {{ t('Aktivitas saya') }}</DialogTitle><DialogDescription>{{ t('Riwayat akun Anda pada folder penyimpanan yang sedang digunakan. Catatan dimulai sejak fitur ini diaktifkan.') }}</DialogDescription></DialogHeader>
            <div class="flex items-center justify-between gap-3"><label class="flex items-center gap-2 text-sm">{{ t('Tindakan') }}<select v-model="action" class="rounded-lg border bg-background px-3 py-2" :disabled="loading" @change="load()"><option value="">{{ t('Semua tindakan') }}</option><option v-for="(label, key) in labels" v-show="key !== 'unknown'" :key="key" :value="key">{{ t(label) }}</option></select></label><button class="rounded-lg border p-2 hover:bg-muted disabled:opacity-40" :disabled="loading" :aria-label="t('Muat ulang riwayat')" @click="load(page)"><RefreshCw class="size-4" /></button></div>
            <p v-if="error" role="alert" class="text-sm text-red-600">{{ t(error) }}</p>
            <div v-if="loading" role="status" class="flex justify-center gap-2 p-8 text-sm"><LoaderCircle class="size-4 animate-spin" /> {{ t('Memuat aktivitas…') }}</div>
            <p v-else-if="!rows.length" class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">{{ t('Belum ada aktivitas untuk filter ini.') }}</p>
            <ul v-else class="space-y-3">
                <li v-for="row in rows" :key="row.id" class="rounded-xl border p-4">
                    <div class="flex flex-wrap justify-between gap-2"><strong class="text-sm">{{ t(labels[row.action] || row.action) }}</strong><span class="rounded-full px-2 py-1 text-xs" :class="row.status === 'failed' ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-200' : row.status === 'processing' ? 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200' : 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200'">{{ t(statuses[row.status] || row.status) }}{{ row.status === 'failed' ? ` (${row.http_status})` : '' }}</span></div>
                    <p class="mt-2 break-all text-sm">{{ targets(row) }}</p>
                    <p v-if="row.action === 'rename'" class="mt-1 break-all text-xs text-muted-foreground">{{ t('Nama baru:') }} {{ row.details.name }}</p>
                    <p v-if="['copy', 'move'].includes(row.action)" class="mt-1 break-all text-xs text-muted-foreground">{{ t('Folder tujuan: /') }}{{ row.details.path }}</p>
                    <p class="mt-2 text-xs text-muted-foreground">{{ row.actor_name }} · {{ formatDate(row.created_at) }}</p>
                </li>
            </ul>
            <div class="flex items-center justify-between gap-2 text-xs text-muted-foreground"><span>{{ t('activity.pages', { total: formatNumber(total), page: formatNumber(page), pages: formatNumber(lastPage) }) }}</span><div class="flex gap-2"><button class="rounded-lg border p-2 disabled:opacity-30" :aria-label="t('Halaman sebelumnya')" :disabled="loading || page <= 1" @click="load(page - 1)"><ChevronLeft class="size-4" /></button><button class="rounded-lg border p-2 disabled:opacity-30" :aria-label="t('Halaman berikutnya')" :disabled="loading || page >= lastPage" @click="load(page + 1)"><ChevronRight class="size-4" /></button></div></div>
            <p class="text-xs leading-5 text-muted-foreground">{{ t('Status unduhan mencatat file disiapkan server, bukan bukti file selesai diterima. Kegagalan operasi banyak file dapat menyisakan sebagian hasil; periksa folder sebelum mencoba kembali.') }}</p>
        </DialogContent>
    </Dialog>
</template>
