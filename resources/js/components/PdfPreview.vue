<script setup lang="ts">
import { t, formatNumber } from '@/i18n';
import { nextTick, onMounted, onBeforeUnmount, ref } from 'vue';
import { ChevronLeft, ChevronRight, Minus, Plus, LoaderCircle } from '@lucide/vue';
import type { PDFDocumentLoadingTask, PDFDocumentProxy, RenderTask } from 'pdfjs-dist';

const props = defineProps<{ data: string }>();
const canvas = ref<HTMLCanvasElement>();
const container = ref<HTMLElement>();
const page = ref(1);
const pages = ref(0);
const zoom = ref(1);
const loading = ref(true);
const rendering = ref(false);
const error = ref('');
let pdf: PDFDocumentProxy | undefined;
let task: PDFDocumentLoadingTask | undefined;
let renderTask: RenderTask | undefined;
let disposed = false;
async function render() {
    if (!pdf || !canvas.value || rendering.value || disposed) return;
    rendering.value = true;
    try {
        const pdfPage = await pdf.getPage(page.value);
        if (disposed || !canvas.value) return;
        const natural = pdfPage.getViewport({ scale: 1 });
        const available = Math.max(200, (container.value?.clientWidth ?? 640) - 24);
        const scale = Math.min(available / natural.width, 1.5) * zoom.value;
        const viewport = pdfPage.getViewport({ scale });
        // Bound bitmap memory for unusually large page dimensions.
        const ratio = Math.min(window.devicePixelRatio || 1, 2, Math.sqrt(12000000 / (viewport.width * viewport.height)));
        canvas.value.width = Math.max(1, Math.floor(viewport.width * ratio));
        canvas.value.height = Math.max(1, Math.floor(viewport.height * ratio));
        canvas.value.style.width = `${viewport.width}px`;
        canvas.value.style.height = `${viewport.height}px`;
        renderTask = pdfPage.render({ canvas: canvas.value, viewport, transform: [ratio, 0, 0, ratio, 0, 0] });
        await renderTask.promise;
        pdfPage.cleanup();
    } catch (cause) {
        if (!disposed) error.value = 'Halaman PDF tidak dapat ditampilkan. Silakan unduh dokumen untuk membukanya.';
    } finally { rendering.value = false; }
}
function go(value: number) { if (rendering.value) return; page.value = value; void render(); }
function changeZoom(value: number) { if (rendering.value) return; zoom.value = Math.round(value * 100) / 100; void render(); }
onMounted(async () => {
    try {
        const library = await import('pdfjs-dist');
        const worker = await import('pdfjs-dist/build/pdf.worker.min.mjs?url');
        if (disposed) return;
        library.GlobalWorkerOptions.workerSrc = worker.default;
        const bytes = Uint8Array.from(atob(props.data), character => character.charCodeAt(0));
        task = library.getDocument({
            data: bytes, enableXfa: false,
            cMapUrl: '/pdfjs/cmaps/', cMapPacked: true,
            standardFontDataUrl: '/pdfjs/standard_fonts/',
            wasmUrl: '/pdfjs/wasm/', iccUrl: '/pdfjs/iccs/',
        });
        pdf = await task.promise;
        if (disposed) return;
        pages.value = pdf.numPages;
        loading.value = false;
        await nextTick();
        await render();
    } catch (cause) {
        if (disposed) return;
        error.value = (cause as Error).name === 'PasswordException'
            ? 'PDF ini dilindungi password. Unduh dan buka dengan pembaca PDF Anda.'
            : 'PDF rusak atau tidak dapat ditampilkan. Silakan unduh dokumen.';
    } finally { if (!disposed) loading.value = false; }
});
onBeforeUnmount(() => { disposed = true; renderTask?.cancel(); void task?.destroy(); });
</script>

<template>
    <div class="space-y-3">
        <p v-if="loading" role="status" class="flex items-center justify-center gap-2 p-8 text-sm"><LoaderCircle class="size-5 animate-spin" /> {{ t('Menyiapkan PDF…') }}</p>
        <p v-if="error" role="status" class="rounded-lg bg-muted p-4 text-sm">{{ t(error) }}</p>
        <div v-if="pages && !error" class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <div class="flex items-center gap-2"><button class="pdf-button" :disabled="rendering || page <= 1" :aria-label="t('Halaman PDF sebelumnya')" @click="go(page - 1)"><ChevronLeft :size="17" /></button><span aria-live="polite">{{ formatNumber(page) }} / {{ formatNumber(pages) }}</span><button class="pdf-button" :disabled="rendering || page >= pages" :aria-label="t('Halaman PDF berikutnya')" @click="go(page + 1)"><ChevronRight :size="17" /></button></div>
            <div class="flex items-center gap-2"><button class="pdf-button" :disabled="rendering || zoom <= 0.5" :aria-label="t('Perkecil PDF')" @click="changeZoom(zoom - 0.25)"><Minus :size="17" /></button><span>{{ Math.round(zoom * 100) }}%</span><button class="pdf-button" :disabled="rendering || zoom >= 2" :aria-label="t('Perbesar PDF')" @click="changeZoom(zoom + 0.25)"><Plus :size="17" /></button></div>
        </div>
        <div ref="container" v-show="!loading && !error" class="max-h-[58svh] overflow-auto rounded-xl border bg-slate-100 p-3 dark:bg-slate-900" :aria-busy="rendering"><canvas ref="canvas" class="mx-auto bg-white shadow-sm" role="img" :aria-label="t('pdf.page', { page: formatNumber(page), pages: formatNumber(pages) })"></canvas></div>
        <p class="text-xs text-muted-foreground">{{ t('Pratinjau visual. Gunakan Unduh untuk pencarian teks, salin teks, formulir, atau pembaca layar.') }}</p>
    </div>
</template>

<style scoped>
@reference "../../css/app.css";
.pdf-button { @apply rounded-lg border p-2 hover:bg-muted disabled:opacity-30 focus-visible:outline-2 focus-visible:outline-teal-600; }
</style>
