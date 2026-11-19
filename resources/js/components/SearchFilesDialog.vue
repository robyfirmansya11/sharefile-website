<script setup lang="ts">
import { t, formatNumber } from '@/i18n';
import { ref, watch, onUnmounted } from 'vue';
import { Search, Folder, FileText, LoaderCircle } from '@lucide/vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { shareApi } from '@/lib/shareApi';

type Entry = { name: string; path: string; directory: boolean; size: number | null; modified: string };
const props = defineProps<{ open: boolean; path: string }>();
const emit = defineEmits<{ close: []; navigate: [path: string]; preview: [entry: Entry] }>();
const query = ref('');
const scope = ref('');
const entries = ref<Entry[]>([]);
const loading = ref(false);
const searched = ref(false);
const partial = ref(false);
const error = ref('');
let controller: AbortController | undefined;
function reset() { controller?.abort(); controller = undefined; loading.value = false; searched.value = false; entries.value = []; error.value = ''; partial.value = false; }
watch(() => props.open, open => { reset(); if (open) { query.value = ''; scope.value = props.path; } });
onUnmounted(() => controller?.abort());
async function search() {
    controller?.abort();
    const current = new AbortController(); controller = current;
    loading.value = true; error.value = ''; entries.value = []; searched.value = false; partial.value = false;
    try {
        const data = await shareApi(`/share/search?${new URLSearchParams({ q: query.value.trim(), path: scope.value })}`, { signal: current.signal });
        if (current.signal.aborted) return;
        entries.value = data.entries; partial.value = data.partial; searched.value = true;
    } catch (cause) { if (!current.signal.aborted) error.value = (cause as Error).message; }
    finally { if (controller === current) loading.value = false; }
}
function parent(entry: Entry) { return entry.path.split('/').slice(0, -1).join('/'); }
function navigate(path: string) { emit('close'); emit('navigate', path); }
function preview(entry: Entry) { emit('close'); emit('preview', entry); }
</script>

<template>
    <Dialog :open="open" @update:open="value => { if (!value) emit('close'); }">
        <DialogContent class="max-h-[90svh] overflow-y-auto rounded-2xl sm:max-w-3xl">
            <DialogHeader><DialogTitle class="flex items-center gap-2"><Search class="size-5 text-teal-600" /> {{ t('Cari file dan folder') }}</DialogTitle><DialogDescription>{{ t('Cari berdasarkan nama, termasuk seluruh subfolder dalam lokasi yang dipilih.') }}</DialogDescription></DialogHeader>
            <form class="space-y-3" @submit.prevent="search">
                <label class="block text-sm">{{ t('Nama file atau folder') }}<input v-model="query" required minlength="2" maxlength="100" :placeholder="t('Contoh: laporan')" class="mt-1 w-full rounded-lg border bg-background px-3 py-2" /></label>
                <div class="flex flex-wrap items-end gap-3"><label class="min-w-0 flex-1 text-sm">{{ t('Lokasi') }}<select v-model="scope" class="mt-1 w-full rounded-lg border bg-background px-3 py-2"><option value="">{{ t('Semua folder') }}</option><option v-if="path" :value="path">/{{ path }}</option></select></label><button class="rounded-lg bg-teal-700 px-4 py-2 text-sm text-white disabled:opacity-50" :disabled="loading || query.trim().length < 2">{{ t('Cari') }}</button></div>
            </form>
            <p v-if="error" role="alert" class="text-sm text-red-600">{{ t(error) }}</p>
            <p v-if="loading" role="status" class="flex items-center gap-2 py-8 text-sm"><LoaderCircle class="size-4 animate-spin" /> {{ t('Mencari di subfolder…') }}</p>
            <div v-if="searched" aria-live="polite" class="text-sm text-muted-foreground">{{ formatNumber(entries.length) }} {{ t('hasil ditemukan.') }}<p v-if="partial" class="mt-2 rounded-lg bg-amber-50 p-3 text-amber-900 dark:bg-amber-950 dark:text-amber-200">{{ t('Hasil belum lengkap: batas pencarian tercapai atau ada folder yang tidak dapat dibaca. Pilih lokasi yang lebih spesifik atau perjelas nama pencarian.') }}</p></div>
            <ul class="space-y-2">
                <li v-for="entry in entries" :key="entry.path" class="rounded-xl border p-3">
                    <button class="flex max-w-full items-center gap-2 text-left font-medium hover:text-teal-600" @click="entry.directory ? navigate(entry.path) : preview(entry)"><component :is="entry.directory ? Folder : FileText" class="size-5 shrink-0 text-teal-600" /><span class="break-all">{{ entry.name }}</span></button>
                    <p class="mt-1 break-all text-xs text-muted-foreground">{{ t('Lokasi: /') }}{{ parent(entry) }}</p>
                    <button class="mt-2 text-xs font-medium text-teal-700 hover:underline dark:text-teal-300" @click="navigate(parent(entry))">{{ t('Buka folder asal') }}</button>
                </li>
            </ul>
            <p class="text-xs text-muted-foreground">{{ t('Maksimal 100 hasil per pencarian. Pencarian berdasarkan nama, bukan isi dokumen.') }}</p>
        </DialogContent>
    </Dialog>
</template>
