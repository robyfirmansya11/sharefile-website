<script setup lang="ts">
import { t, formatDate } from '@/i18n';
import { ref, watch } from 'vue';
import { Trash2, RotateCcw, Folder, FileText, LoaderCircle } from '@lucide/vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { shareApi } from '@/lib/shareApi';

type Item = { id: string; name: string; original_path: string; directory: boolean; deleted_at: string };
const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: []; changed: [] }>();
const items = ref<Item[]>([]);
const loading = ref(false);
const busy = ref(false);
const error = ref('');
const notice = ref('');
const confirmItem = ref<Item | null>(null);
async function load() {
    loading.value = true;
    try { items.value = (await shareApi('/share/trash')).entries; }
    catch (cause) { error.value = (cause as Error).message; }
    finally { loading.value = false; }
}
watch(() => props.open, value => { if (value) { error.value = ''; notice.value = ''; confirmItem.value = null; void load(); } });
async function act(item: Item, action: 'restore' | 'purge') {
    if (busy.value) return;
    busy.value = true; error.value = ''; notice.value = '';
    try {
        const result = await shareApi('/share/trash', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: item.id, action, ...(action === 'purge' ? { confirm: true } : {}) }) });
        confirmItem.value = null;
        notice.value = result.message;
        await load();
        emit('changed');
    } catch (cause) { error.value = (cause as Error).message; }
    finally { busy.value = false; }
}
</script>

<template>
    <Dialog :open="open" @update:open="value => { if (!value && !busy) emit('close'); }">
        <DialogContent class="max-h-[90svh] overflow-y-auto rounded-2xl sm:max-w-3xl" :show-close-button="!busy" @escape-key-down="event => { if (busy) event.preventDefault(); }" @pointer-down-outside="event => { if (busy) event.preventDefault(); }">
            <DialogHeader><DialogTitle class="flex items-center gap-2"><Trash2 class="size-5 text-teal-600" /> {{ t('Recycle Bin') }}</DialogTitle><DialogDescription>{{ t('Item yang Anda hapus dari folder lokal. Pulihkan ke lokasi asal atau hapus permanen.') }}</DialogDescription></DialogHeader>
            <p class="text-xs leading-5 text-muted-foreground">{{ t('Jika folder asal ikut dihapus, pulihkan folder tersebut terlebih dahulu. Nama yang sudah ada tidak akan ditimpa. Tidak ada penghapusan otomatis.') }}</p>
            <p v-if="error" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-200">{{ t(error) }}</p>
            <p v-if="notice" role="status" class="rounded-lg bg-teal-50 p-3 text-sm text-teal-800 dark:bg-teal-950 dark:text-teal-200">{{ t(notice) }}</p>
            <div v-if="confirmItem" class="space-y-3 rounded-xl border border-red-300 p-4">
                <h3 class="font-semibold">{{ t('purge.confirm', { name: confirmItem.name }) }}</h3>
                <p class="text-sm text-muted-foreground">{{ t('File atau folder beserta seluruh isinya tidak dapat dipulihkan melalui aplikasi setelah tindakan ini.') }}</p>
                <div class="flex flex-wrap justify-end gap-2"><button class="rounded-lg border px-3 py-2 text-sm" :disabled="busy" @click="confirmItem = null">{{ t('Batal') }}</button><button class="rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="busy" @click="act(confirmItem, 'purge')">{{ busy ? t('Memproses…') : t('Ya, hapus permanen') }}</button></div>
            </div>
            <div v-if="loading" role="status" class="flex justify-center gap-2 p-8 text-sm"><LoaderCircle class="size-4 animate-spin" /> {{ t('Memuat Recycle Bin…') }}</div>
            <div v-else-if="!items.length" class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">{{ t('Recycle Bin Anda kosong.') }}</div>
            <ul v-else class="space-y-3">
                <li v-for="item in items" :key="item.id" class="flex flex-wrap items-center gap-3 rounded-xl border p-4">
                    <Folder v-if="item.directory" class="size-6 shrink-0 text-amber-500" /><FileText v-else class="size-6 shrink-0 text-blue-500" />
                    <div class="min-w-0 flex-1"><p class="break-all text-sm font-semibold">{{ item.name }}</p><p class="break-all text-xs text-muted-foreground">{{ t('Asal: /') }}{{ item.original_path }}</p><p class="mt-1 text-xs text-muted-foreground">{{ t('Dihapus') }} {{ formatDate(item.deleted_at) }}</p></div>
                    <div class="flex gap-2"><button class="inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-xs font-medium hover:bg-muted disabled:opacity-40" :disabled="busy || !!confirmItem" @click="act(item, 'restore')"><RotateCcw class="size-4" /> {{ t('Pulihkan') }}</button><button class="rounded-lg px-3 py-2 text-xs text-red-600 hover:bg-red-50 disabled:opacity-40 dark:hover:bg-red-950" :disabled="busy || !!confirmItem" @click="confirmItem = item; error = ''">{{ t('Hapus permanen') }}</button></div>
                </li>
            </ul>
        </DialogContent>
    </Dialog>
</template>
