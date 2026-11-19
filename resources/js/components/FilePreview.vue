<script setup lang="ts">
import { t } from '@/i18n';
import { defineAsyncComponent, ref, watch } from 'vue';
import { Download, LoaderCircle, FileSearch } from '@lucide/vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { shareApi } from '@/lib/shareApi';

const PdfPreview = defineAsyncComponent(() => import('@/components/PdfPreview.vue'));
const props = defineProps<{ file: { name: string; path: string } | null }>();
const emit = defineEmits<{ close: [] }>();
const loading = ref(false);
const error = ref('');
const preview = ref<{ kind: 'text'; content: string } | { kind: 'image'; source: string } | { kind: 'pdf'; data: string } | null>(null);
watch(() => props.file, async (file, _, onCleanup) => {
    preview.value = null;
    error.value = '';
    if (!file) return;
    const controller = new AbortController();
    onCleanup(() => controller.abort());
    loading.value = true;
    try { preview.value = await shareApi(`/share/preview?path=${encodeURIComponent(file.path)}`, { signal: controller.signal }); }
    catch (cause) { if (!controller.signal.aborted) error.value = (cause as Error).message; }
    finally { if (!controller.signal.aborted) loading.value = false; }
}, { immediate: true });
</script>

<template>
    <Dialog :open="!!file" @update:open="value => { if (!value) emit('close'); }">
        <DialogContent class="max-h-[90svh] overflow-y-auto rounded-2xl sm:max-w-3xl">
            <DialogHeader class="pr-7">
                <DialogTitle class="break-all">{{ file?.name }}</DialogTitle>
                <DialogDescription>{{ t('Pratinjau file · gambar maksimal 5 MB, teks UTF-8 maksimal 1 MB, PDF maksimal 10 MB.') }}</DialogDescription>
            </DialogHeader>
            <div v-if="loading" class="flex min-h-48 items-center justify-center gap-2 text-sm text-muted-foreground" role="status"><LoaderCircle class="size-5 animate-spin" /> {{ t('Membuka file…') }}</div>
            <div v-else-if="error" class="flex min-h-48 flex-col items-center justify-center gap-3 rounded-xl bg-muted/50 p-6 text-center"><FileSearch class="size-9 text-muted-foreground" /><p role="status" class="text-sm">{{ t(error) }}</p></div>
            <img v-else-if="preview?.kind === 'image'" :src="preview.source" :alt="file?.name" class="max-h-[60svh] w-full rounded-xl bg-muted/30 object-contain" @error="error = t('Gambar tidak dapat ditampilkan. Silakan unduh file.')" />
            <PdfPreview v-else-if="preview?.kind === 'pdf'" :key="file?.path" :data="preview.data" />
            <pre v-else-if="preview?.kind === 'text'" class="max-h-[60svh] overflow-auto rounded-xl border bg-muted/30 p-4 text-sm whitespace-pre-wrap break-words">{{ preview.content || t('(File kosong)') }}</pre>
            <a v-if="file" :href="`/share/download?path=${encodeURIComponent(file.path)}`" class="inline-flex w-fit items-center gap-2 rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-teal-800"><Download class="size-4" /> {{ t('Unduh file') }}</a>
        </DialogContent>
    </Dialog>
</template>
