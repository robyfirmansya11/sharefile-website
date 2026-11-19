<script setup lang="ts">
import { t, formatDate, formatNumber } from '@/i18n';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Folder, FileText, Upload, FolderPlus, Copy, Scissors, ClipboardPaste, Trash2, Download, ChevronRight, HardDrive, RefreshCw, Search, FilePlus2, X, ArrowLeft, CloudUpload, CheckCircle2, Info, LayoutGrid, List, Image, FileSpreadsheet, FileArchive, File, Pencil, Eye, History } from '@lucide/vue';
import { dashboard } from '@/routes';
import FilePreview from '@/components/FilePreview.vue';
import RecycleBinDialog from '@/components/RecycleBinDialog.vue';
import ActivityLogDialog from '@/components/ActivityLogDialog.vue';
import SearchFilesDialog from '@/components/SearchFilesDialog.vue';
import { useShareUpload } from '@/composables/useShareUpload';
import { shareApi as api } from '@/lib/shareApi';

defineOptions({ layout: { breadcrumbs: [{ title: 'Sharefolder', href: dashboard() }] } });
type Entry = { name: string; path: string; directory: boolean; size: number | null; modified: string };
const page = usePage();
const readOnly = computed(() => !page.props.share.writesEnabled);
const entries = ref<Entry[]>([]);
const path = ref('');
const selected = ref<string[]>([]);
const query = ref('');
const view = ref<'list' | 'grid'>('list');
const folderCount = computed(() => entries.value.filter(entry => entry.directory).length);
const fileCount = computed(() => entries.value.length - folderCount.value);
const totalSize = computed(() => entries.value.reduce((sum, entry) => sum + (entry.size ?? 0), 0));
function fileIcon(entry: Entry) {
    if (entry.directory) return Folder;
    const extension = entry.name.split('.').pop()?.toLowerCase() ?? '';
    if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(extension)) return Image;
    if (['xls', 'xlsx', 'csv'].includes(extension)) return FileSpreadsheet;
    if (['zip', 'rar', '7z'].includes(extension)) return FileArchive;
    if (['txt', 'pdf', 'doc', 'docx'].includes(extension)) return FileText;
    return File;
}
function fileTone(entry: Entry) {
    if (entry.directory) return 'file-folder';
    const icon = fileIcon(entry);
    return icon === Image ? 'file-image' : icon === FileSpreadsheet ? 'file-sheet' : 'file-document';
}
const busy = ref(false);
const loading = ref(false);
const error = ref('');
const notice = ref('');
const drag = ref(false);
const uploadInput = ref<HTMLInputElement>();
const clipboard = ref<{ action: 'copy' | 'move'; sources: string[] } | null>(null);
const context = ref<{ x: number; y: number } | null>(null);
const modal = ref<'folder' | 'file' | 'delete' | 'rename' | null>(null);
const previewFile = ref<Entry | null>(null);
const showTrash = ref(false);
const showActivity = ref(false);
const showSearch = ref(false);
const { items: uploadItems, running: uploading, upload: uploadFiles } = useShareUpload();
const uploadLabels = { waiting: 'Menunggu', uploading: 'Mengunggah', saving: 'Menyimpan di server', done: 'Selesai', failed: 'Gagal' };
const name = ref('');
const content = ref('');
const visible = computed(() => entries.value.filter(e => e.name.toLowerCase().includes(query.value.toLowerCase())).sort((a, b) => Number(b.directory) - Number(a.directory) || a.name.localeCompare(b.name)));
const crumbs = computed(() => path.value ? path.value.split('/') : []);
const selectedEntry = computed(() => selected.value.length === 1 ? entries.value.find(e => e.path === selected.value[0]) : undefined);
const selectedFile = computed(() => entries.value.find(e => selected.value.length === 1 && selected.value[0] === e.path && !e.directory));
const disabled = computed(() => busy.value || loading.value);
const size = (bytes: number | null) => bytes === null ? '—' : bytes < 1024 ? `${formatNumber(bytes)} B` : bytes < 1048576 ? `${formatNumber(bytes / 1024, 1)} KB` : `${formatNumber(bytes / 1048576, 1)} MB`;
async function navigate(destination = path.value) {
    if (loading.value) return;
    loading.value = true;
    context.value = null;
    error.value = '';
    try {
        const data = await api(`/share/entries?path=${encodeURIComponent(destination)}`);
        entries.value = data.entries;
        path.value = destination;
        selected.value = [];
        query.value = '';
    } catch (e) { error.value = (e as Error).message; }
    finally { loading.value = false; }
}
function select(entry: Entry, event: MouseEvent) {
    if (disabled.value) return;
    if (event.ctrlKey || event.metaKey || event.shiftKey) toggle(entry.path);
    else selected.value = [entry.path];
}
function toggle(value: string) {
    selected.value = selected.value.includes(value) ? selected.value.filter(p => p !== value) : [...selected.value, value];
}
function remember(action: 'copy' | 'move') {
    clipboard.value = { action, sources: [...selected.value] };
    notice.value = t('clipboard.ready', { count: formatNumber(selected.value.length), action: t(action === 'copy' ? 'copy.action' : 'move.action') });
    context.value = null;
}
function openModal(kind: 'folder' | 'file' | 'delete' | 'rename') {
    if (readOnly.value || disabled.value || (kind === 'rename' && !selectedEntry.value)) return;
    error.value = '';
    modal.value = kind;
    name.value = kind === 'rename' ? selectedEntry.value!.name : '';
    content.value = '';
    context.value = null;
}
function openPreview(entry = selectedFile.value) {
    if (entry && !disabled.value) { context.value = null; previewFile.value = entry; }
}
async function operation(payload: Record<string, unknown>) {
    if (disabled.value || readOnly.value) return;
    busy.value = true;
    error.value = '';
    notice.value = '';
    context.value = null;
    try {
        const data = await api('/share/operations', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ path: path.value, ...payload }) });
        modal.value = null;
        if (['move', 'rename', 'delete'].includes(String(payload.action))) clipboard.value = null;
        await navigate();
        notice.value = data.message;
    } catch (e) { error.value = (e as Error).message; }
    finally { busy.value = false; }
}
function paste() { if (clipboard.value) void operation(clipboard.value); }
function download(entry = selectedFile.value) {
    if (entry) window.location.assign(`/share/download?path=${encodeURIComponent(entry.path)}`);
}
async function upload(files: FileList | File[]) {
    if (disabled.value || readOnly.value || !files.length) return;
    busy.value = true;
    error.value = '';
    notice.value = '';
    try {
        await uploadFiles(Array.from(files), path.value);
        await navigate();
        const succeeded = uploadItems.value.filter(item => item.state === 'done').length;
        const failed = uploadItems.value.filter(item => item.state === 'failed').length;
        notice.value = t('upload.result', { success: formatNumber(succeeded), failed: formatNumber(failed) });
    } catch (e) { error.value = (e as Error).message; }
    finally { busy.value = false; if (uploadInput.value) uploadInput.value.value = ''; }
}
function dropped(event: DragEvent) {
    drag.value = false;
    if (event.dataTransfer?.items && Array.from(event.dataTransfer.items).some(item => item.webkitGetAsEntry?.()?.isDirectory)) {
        error.value = 'Upload folder belum tersedia. Pilih file di dalam folder tersebut.';
        return;
    }
    if (event.dataTransfer?.files) void upload(event.dataTransfer.files);
}
function rightClick(event: MouseEvent, entry: Entry) {
    if (disabled.value) return;
    if (!selected.value.includes(entry.path)) selected.value = [entry.path];
    context.value = { x: Math.max(8, Math.min(event.clientX, window.innerWidth - 200)), y: Math.max(8, Math.min(event.clientY, window.innerHeight - 330)) };
}
function keyboard(event: KeyboardEvent) {
    if ((event.target as HTMLElement)?.closest('input,textarea,select,[contenteditable="true"]') || modal.value || previewFile.value || showTrash.value || showActivity.value || showSearch.value || disabled.value) return;
    if (event.key === 'Escape') { context.value = null; selected.value = []; }
    if (event.ctrlKey || event.metaKey) {
        const key = event.key.toLowerCase();
        if (['a', 'c', 'x', 'v'].includes(key)) event.preventDefault();
        if (key === 'a') selected.value = visible.value.map(e => e.path);
        if (key === 'c' && selected.value.length) remember('copy');
        if (key === 'x' && selected.value.length) remember('move');
        if (key === 'v') paste();
    }
    if (event.key === 'F2' && selectedEntry.value) { event.preventDefault(); openModal('rename'); }
    if (event.key === 'Delete' && selected.value.length) openModal('delete');
}
const closeContext = () => { context.value = null; };
onMounted(() => { void navigate(); window.addEventListener('keydown', keyboard); window.addEventListener('click', closeContext); });
onUnmounted(() => { window.removeEventListener('keydown', keyboard); window.removeEventListener('click', closeContext); });
</script>

<template>
    <Head title="Sharefolder" />
    <main class="workspace flex min-w-0 flex-1 flex-col gap-5 p-4 md:p-7 lg:p-9" @dragover.prevent="drag = true" @dragleave.self="drag = false" @drop.prevent="dropped">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div><p class="mb-2 text-xs font-semibold tracking-[0.18em] text-teal-700 dark:text-teal-400">{{ t('RUANG KERJA BERSAMA') }}</p><h1 class="text-3xl font-semibold tracking-tight">{{ t('File tim, lebih teratur.') }}</h1><p class="mt-2 text-sm text-muted-foreground">{{ t('Temukan, simpan, dan kelola dokumen dalam satu tempat.') }}</p></div>
            <button class="primary" :disabled="disabled || readOnly" @click="uploadInput?.click()"><Upload :size="18" /> {{ t('Unggah file') }}</button>
            <input ref="uploadInput" type="file" multiple class="hidden" :aria-label="t('Pilih file untuk diunggah')" @change="upload(($event.target as HTMLInputElement).files ?? [])" />
        </header>
        <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <span class="h-2 w-2 rounded-full" :class="page.props.share.driver === 'windows' ? 'bg-teal-500' : 'bg-amber-500'"></span>
            <strong class="font-medium text-foreground">{{ page.props.share.driver === 'windows' ? 'Windows / Active Directory' : t('Mode percobaan') }}</strong>
            <span v-if="page.props.share.driver === 'windows'">{{ page.props.share.domain }}{{ readOnly ? t(' · Uji akses baca') : '' }}</span>
            <span v-else>{{ t('· Belum terhubung ke folder perusahaan') }}</span>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="summary-card"><span class="file-symbol file-folder"><Folder :size="23" /></span><div><p class="text-xs text-muted-foreground">{{ t('Folder di lokasi ini') }}</p><p class="mt-1 text-2xl font-semibold">{{ loading ? '—' : formatNumber(folderCount) }} <span class="text-xs font-normal text-muted-foreground">{{ t('folder') }}</span></p></div></div>
            <div class="summary-card"><span class="file-symbol file-document"><FileText :size="23" /></span><div><p class="text-xs text-muted-foreground">{{ t('File di lokasi ini') }}</p><p class="mt-1 text-2xl font-semibold">{{ loading ? '—' : formatNumber(fileCount) }} <span class="text-xs font-normal text-muted-foreground">{{ t('file') }}</span></p></div></div>
            <div class="summary-card"><span class="file-symbol file-sheet"><HardDrive :size="23" /></span><div><p class="text-xs text-muted-foreground">{{ t('Ukuran file di lokasi ini') }}</p><p class="mt-1 text-2xl font-semibold">{{ loading ? '—' : size(totalSize) }}</p></div></div>
        </div>
        <button class="upload-zone group" :disabled="disabled || readOnly" @click="uploadInput?.click()">
            <span class="rounded-2xl bg-teal-100 p-3 text-teal-700 transition group-hover:-translate-y-1 dark:bg-teal-900 dark:text-teal-300"><CloudUpload :size="28" /></span>
            <span class="flex-1 text-left"><span class="block font-semibold">{{ busy ? t('Sedang memproses file…') : t('Punya dokumen untuk dibagikan?') }}</span><span class="mt-1 block text-sm text-muted-foreground">{{ t('Tarik file ke sini, atau') }} <span class="font-medium text-teal-700 dark:text-teal-400">{{ t('pilih dari komputer') }}</span>.</span></span>
            <span class="hidden text-right text-xs leading-5 text-muted-foreground md:block">{{ t('Maks. 25 MB per file') }}<br />{{ t('Hingga 20 file sekaligus') }}</span>
        </button>
        <section v-if="uploadItems.length" class="rounded-2xl border bg-card p-4" :aria-label="t('Antrean upload')">
            <div class="mb-3 flex items-center justify-between"><h2 class="text-sm font-semibold">{{ uploading ? t('Upload sedang berjalan') : t('Hasil upload') }}</h2><button v-if="!uploading" class="tool !p-1" :aria-label="t('Tutup hasil upload')" @click="uploadItems = []"><X :size="16" /></button></div>
            <div class="max-h-64 space-y-4 overflow-y-auto">
                <div v-for="(item, index) in uploadItems" :key="index" class="space-y-1.5">
                    <div class="flex justify-between gap-3 text-xs"><span class="truncate font-medium" :title="item.name">{{ item.name }}</span><span class="shrink-0" :class="item.state === 'failed' ? 'text-red-600' : 'text-muted-foreground'">{{ t(uploadLabels[item.state]) }} {{ item.state === 'uploading' ? `${item.progress}%` : '' }}</span></div>
                    <progress class="h-1.5 w-full accent-teal-600" :value="item.progress" max="100" :aria-label="t('upload.progress', { name: item.name })"></progress>
                    <p v-if="item.error" class="text-xs text-red-600 dark:text-red-400">{{ t(item.error) }}</p>
                </div>
            </div>
            <p class="mt-3 text-xs text-muted-foreground">{{ t('100% transfer belum berarti tersimpan. Tunggu status Selesai; file yang gagal tidak menimpa file lama.') }}</p>
        </section>
        <div v-if="error" role="alert" class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ t(error) }}</div>
        <div v-if="notice" role="status" class="flex items-center gap-3 rounded-xl border border-teal-200 bg-teal-50 p-3 text-sm text-teal-800 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-200"><CheckCircle2 :size="18" class="shrink-0" /><span class="flex-1">{{ t(notice) }}</span><button :aria-label="t('Tutup pemberitahuan')" @click="notice = ''"><X :size="16" /></button></div>
        <section class="relative flex min-h-96 min-w-0 flex-1 flex-col overflow-hidden rounded-2xl border bg-card shadow-sm" :aria-busy="disabled">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b p-4">
                <nav :aria-label="t('Lokasi folder')" class="flex min-w-0 flex-wrap items-center gap-2 text-sm"><button v-if="path" class="tool !p-2" :aria-label="t('Kembali ke folder induk')" :disabled="disabled" @click="navigate(crumbs.slice(0, -1).join('/'))"><ArrowLeft :size="17" /></button><button class="flex items-center gap-2 font-medium text-teal-700" :disabled="disabled" @click="navigate('')"><HardDrive :size="17" /> {{ t('Semua file') }}</button><template v-for="(part, index) in crumbs" :key="index"><ChevronRight :size="14" class="text-muted-foreground" /><button :disabled="disabled" @click="navigate(crumbs.slice(0, index + 1).join('/'))">{{ part }}</button></template></nav>
                <label class="flex w-full items-center gap-2 rounded-xl border bg-muted/30 px-3 py-2.5 sm:w-auto"><Search :size="16" class="text-muted-foreground" /><input v-model="query" :aria-label="t('Cari file di folder ini')" :placeholder="t('Cari di folder ini…')" class="w-full bg-transparent text-sm outline-none sm:w-48" /></label>
            </div>
            <div class="flex flex-wrap items-center gap-2 border-b p-3">
                <button class="tool" :disabled="disabled || readOnly" @click="openModal('folder')"><FolderPlus :size="17" /> {{ t('Folder baru') }}</button>
                <button class="tool" :disabled="disabled || readOnly" @click="openModal('file')"><FilePlus2 :size="17" /> {{ t('File teks baru') }}</button>
                <button v-if="page.props.share.driver === 'local'" class="tool" :disabled="disabled" @click="showTrash = true"><Trash2 :size="17" /> {{ t('Recycle Bin') }}</button>
                <button class="tool" :disabled="disabled" @click="showActivity = true"><History :size="17" /> {{ t('Aktivitas saya') }}</button>
                <button class="tool" :disabled="disabled" @click="showSearch = true"><Search :size="17" /> {{ t('Cari hingga subfolder') }}</button>
                <div class="ml-auto flex items-center gap-1">
                    <button class="tool !p-2" :aria-label="t('Muat ulang')" :title="t('Muat ulang')" :disabled="disabled" @click="navigate()"><RefreshCw :size="17" :class="{ 'animate-spin': disabled }" /></button>
                    <div class="flex rounded-lg border bg-muted/40 p-1" role="group" :aria-label="t('Tampilan file')"><button class="view-button" :class="{ 'view-active': view === 'list' }" :aria-label="t('Tampilan daftar')" :aria-pressed="view === 'list'" @click="view = 'list'"><List :size="17" /></button><button class="view-button" :class="{ 'view-active': view === 'grid' }" :aria-label="t('Tampilan kartu')" :aria-pressed="view === 'grid'" @click="view = 'grid'"><LayoutGrid :size="17" /></button></div>
                </div>
            </div>
            <div v-if="clipboard" class="flex flex-wrap items-center gap-2 border-b bg-teal-50 px-4 py-2 text-sm text-teal-900 dark:bg-teal-950 dark:text-teal-100"><ClipboardPaste :size="17" /><span class="flex-1">{{ t('clipboard.ready', { count: formatNumber(clipboard.sources.length), action: t(clipboard.action === 'copy' ? 'copy.action' : 'move.action') }) }}</span><button class="tool font-semibold" :disabled="disabled || readOnly" @click="paste">{{ t('Tempel di sini') }}</button><button class="tool !p-2" :aria-label="t('Batalkan salin atau pindah')" :disabled="disabled" @click="clipboard = null; notice = ''"><X :size="16" /></button></div>
            <div v-if="selected.length" class="flex flex-wrap items-center gap-1 border-b bg-muted/40 px-3 py-2">
                <span class="mr-2 text-sm font-medium">{{ t('selection.count', { count: formatNumber(selected.length) }) }}</span>
                <button class="tool" :disabled="disabled" @click="remember('copy')"><Copy :size="16" /> {{ t('Salin') }}</button>
                <button class="tool" :disabled="disabled" @click="remember('move')"><Scissors :size="16" /> {{ t('Potong') }}</button>
                <button v-if="selectedEntry" class="tool" :disabled="disabled || readOnly" @click="openModal('rename')"><Pencil :size="16" /> {{ t('Ganti nama') }}</button>
                <button v-if="selectedFile" class="tool" :disabled="disabled" @click="openPreview()"><Eye :size="16" /> {{ t('Preview') }}</button>
                <button v-if="selectedFile" class="tool" :disabled="disabled" @click="download()"><Download :size="16" /> {{ t('Unduh') }}</button>
                <button class="tool text-red-600 dark:text-red-400" :disabled="disabled || readOnly" @click="openModal('delete')"><Trash2 :size="16" /> {{ t('Hapus') }}</button>
                <button class="tool ml-auto" :disabled="disabled" @click="selected = []">{{ t('Batal pilih') }}</button>
            </div>
            <div v-if="view === 'grid' && visible.length" class="grid grid-cols-2 gap-3 p-4 md:grid-cols-3 xl:grid-cols-4">
                <article v-for="entry in visible" :key="entry.path" class="file-card" :class="{ 'selected-card': selected.includes(entry.path), 'opacity-50': clipboard?.action === 'move' && clipboard.sources.includes(entry.path) }" @click="select(entry, $event)" @contextmenu.prevent.stop="rightClick($event, entry)">
                    <div class="mb-4 flex items-start justify-between"><span class="file-symbol" :class="fileTone(entry)"><component :is="fileIcon(entry)" :size="26" /></span><input type="checkbox" :aria-label="t('select.file', { name: entry.name })" :checked="selected.includes(entry.path)" :disabled="disabled" @click.stop @change="toggle(entry.path)" /></div>
                    <button class="w-full truncate text-left text-sm font-semibold hover:text-teal-700" :title="entry.name" :disabled="disabled" @click.stop="entry.directory ? navigate(entry.path) : openPreview(entry)">{{ entry.name }}</button>
                    <p class="mt-1 text-xs text-muted-foreground">{{ entry.directory ? t('Folder') : size(entry.size) }}</p>
                </article>
            </div>
            <div v-if="view === 'list'" class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/25 text-xs text-muted-foreground"><tr><th class="w-12 p-4"><input type="checkbox" :aria-label="t('Pilih semua file')" :disabled="disabled || !visible.length" :checked="visible.length > 0 && visible.every(e => selected.includes(e.path))" @change="selected = ($event.target as HTMLInputElement).checked ? visible.map(e => e.path) : []" /></th><th class="py-4 font-medium">{{ t('Nama') }}</th><th class="px-4 font-medium">{{ t('Diubah') }}</th><th class="px-4 font-medium">{{ t('Ukuran') }}</th><th class="px-4 font-medium">{{ t('Jenis') }}</th></tr></thead>
                    <tbody><tr v-for="entry in visible" :key="entry.path" class="cursor-default border-b last:border-0 hover:bg-muted/50" :class="{ 'bg-teal-50 dark:bg-teal-950': selected.includes(entry.path), 'opacity-50': clipboard?.action === 'move' && clipboard.sources.includes(entry.path) }" @click="select(entry, $event)" @dblclick="!disabled && (entry.directory ? navigate(entry.path) : openPreview(entry))" @contextmenu.prevent.stop="rightClick($event, entry)"><td class="p-4"><input type="checkbox" :aria-label="t('select.file', { name: entry.name })" :checked="selected.includes(entry.path)" :disabled="disabled" @click.stop @change="toggle(entry.path)" /></td><td class="py-4"><button class="flex items-center gap-3 text-left font-medium" :disabled="disabled" @click.stop="entry.directory ? navigate(entry.path) : openPreview(entry)"><span class="file-symbol !h-9 !w-9 !rounded-lg" :class="fileTone(entry)"><component :is="fileIcon(entry)" :size="20" /></span><span class="max-w-52 truncate sm:max-w-80" :title="entry.name">{{ entry.name }}</span></button></td><td class="whitespace-nowrap px-4 text-muted-foreground">{{ formatDate(entry.modified) }}</td><td class="whitespace-nowrap px-4 text-muted-foreground">{{ size(entry.size) }}</td><td class="px-4 text-muted-foreground">{{ entry.directory ? t('Folder') : t('File') }}</td></tr></tbody>
                </table>
            </div>
            <div v-if="!visible.length" class="flex flex-1 flex-col items-center justify-center gap-3 p-12 text-center"><span class="rounded-3xl bg-teal-50 p-5 dark:bg-teal-950"><Search v-if="query" :size="36" class="text-teal-600" /><Folder v-else :size="36" class="text-teal-600" /></span><h2 class="font-medium">{{ loading ? t('Memuat file…') : query ? t('File tidak ditemukan') : t('Mulai dari file pertamamu') }}</h2><p class="max-w-sm text-sm text-muted-foreground">{{ query ? t('Coba kata pencarian lain.') : t('Unggah dokumen atau buat folder untuk merapikan pekerjaan tim.') }}</p><button v-if="!query && !loading" class="primary mt-2" :disabled="disabled || readOnly" @click="uploadInput?.click()"><Upload :size="17" /> {{ t('Pilih file') }}</button><button v-if="query" class="tool" @click="query = ''">{{ t('Hapus pencarian') }}</button></div>
            <footer class="mt-auto flex flex-wrap justify-between gap-2 border-t px-4 py-3 text-xs text-muted-foreground"><span>{{ t('items.count', { count: formatNumber(visible.length) }) }} · {{ t('selection.count', { count: formatNumber(selected.length) }) }}</span><span>{{ busy ? t('Memproses…') : t('Centang untuk memilih · Klik nama untuk membuka / preview') }}</span></footer>
            <div v-if="drag" class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-xl border-2 border-dashed border-teal-600 bg-teal-50/95 text-lg font-medium text-teal-800">{{ t('Lepaskan file untuk mengunggah') }}</div>
        </section>
        <div class="flex items-start gap-2 text-xs leading-relaxed text-muted-foreground"><Info :size="15" class="mt-0.5 shrink-0" /><p>{{ t('File dengan nama sama tidak akan ditimpa. Tip: gunakan Ctrl + C, Ctrl + X, dan Ctrl + V untuk salin, potong, dan tempel.') }}</p></div>
        <div v-if="context" class="fixed z-40 grid w-48 gap-1 rounded-xl border bg-popover p-2 shadow-xl" :style="{ left: `${context.x}px`, top: `${context.y}px` }" role="menu" @click.stop><button class="tool" role="menuitem" :disabled="!selectedFile" @click="openPreview()"><Eye :size="16" /> {{ t('Preview') }}</button><button class="tool" role="menuitem" :disabled="readOnly || !selectedEntry" @click="openModal('rename')"><Pencil :size="16" /> {{ t('Ganti nama') }}</button><button class="tool" role="menuitem" @click="remember('copy')"><Copy :size="16" /> {{ t('Salin') }}</button><button class="tool" role="menuitem" @click="remember('move')"><Scissors :size="16" /> {{ t('Potong') }}</button><button class="tool" role="menuitem" :disabled="disabled || readOnly || !clipboard" @click="paste"><ClipboardPaste :size="16" /> {{ t('Tempel di sini') }}</button><button class="tool" role="menuitem" :disabled="!selectedFile" @click="download(); context = null"><Download :size="16" /> {{ t('Unduh') }}</button><button class="tool text-red-600" role="menuitem" :disabled="readOnly" @click="openModal('delete')"><Trash2 :size="16" /> {{ t('Hapus') }}</button></div>
        <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.esc="!busy && (modal = null)">
            <form class="w-full max-w-md rounded-2xl border bg-card p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="dialog-title" @submit.prevent="operation(modal === 'delete' ? { action: 'delete', sources: selected } : modal === 'rename' ? { action: 'rename', sources: selected, name } : { action: modal, name, content })">
                <h2 id="dialog-title" class="mb-4 text-lg font-semibold">{{ modal === 'delete' ? t('delete.confirm', { count: formatNumber(selected.length) }) : modal === 'rename' ? t('Ganti nama') : modal === 'folder' ? t('Buat folder baru') : t('Buat file teks') }}</h2>
                <p v-if="modal === 'delete'" class="mb-5 text-sm text-muted-foreground">{{ t('Item yang dipilih beserta seluruh isi folder akan dipindahkan ke Recycle Bin. Anda dapat memulihkannya ke lokasi asal.') }}</p>
                <template v-else><label class="text-sm" for="new-name">{{ modal === 'rename' ? t('Nama baru (sertakan ekstensi untuk file)') : modal === 'folder' ? t('Nama folder') : t('Nama file (contoh: catatan.txt)') }}</label><input id="new-name" v-model="name" v-focus required maxlength="200" class="mt-2 mb-4 w-full rounded-lg border bg-transparent px-3 py-2" /><template v-if="modal === 'file'"><label for="new-content" class="text-sm">{{ t('Isi file') }}</label><textarea id="new-content" v-model="content" rows="5" class="mt-2 mb-4 w-full rounded-lg border bg-transparent p-3"></textarea></template></template>
                <p v-if="error" role="alert" class="mb-4 text-sm text-red-600">{{ t(error) }}</p>
                <div class="flex justify-end gap-2"><button type="button" class="tool" :disabled="busy" @click="modal = null">{{ t('Batal') }}</button><button class="primary" :class="{ '!bg-red-600 hover:!bg-red-700': modal === 'delete' }" :disabled="busy">{{ busy ? t('Memproses…') : modal === 'delete' ? t('Pindahkan ke Recycle Bin') : modal === 'rename' ? t('Simpan nama') : t('Buat') }}</button></div>
            </form>
        </div>
        <ActivityLogDialog :open="showActivity" @close="showActivity = false" />
        <SearchFilesDialog :open="showSearch" :path="path" @close="showSearch = false" @navigate="navigate" @preview="openPreview" />
        <RecycleBinDialog :open="showTrash" @close="showTrash = false" @changed="navigate()" />
        <FilePreview :file="previewFile" @close="previewFile = null" />
    </main>
</template>

<style scoped>
@reference "../../css/app.css";
.workspace { background: radial-gradient(ellipse at top right, rgb(20 184 166 / 0.055), transparent 55%); }
.summary-card { @apply flex items-center gap-4 rounded-2xl border bg-card p-5; }
.file-symbol { @apply inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl; }
.file-folder { @apply bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-400; }
.file-document { @apply bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400; }
.file-image { @apply bg-violet-50 text-violet-600 dark:bg-violet-950 dark:text-violet-400; }
.file-sheet { @apply bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-400; }
.upload-zone { @apply flex items-center gap-4 rounded-2xl border border-dashed border-teal-300 bg-teal-50/40 p-5 transition hover:border-teal-500 hover:bg-teal-50 disabled:cursor-wait disabled:opacity-60 dark:border-teal-800 dark:bg-teal-950/20 dark:hover:bg-teal-950/40; }
.file-card { @apply min-w-0 cursor-default rounded-xl border p-4 transition hover:border-teal-300 hover:shadow-sm dark:hover:border-teal-700; }
.selected-card { @apply border-teal-500 bg-teal-50 ring-1 ring-teal-500 dark:bg-teal-950; }
.view-button { @apply rounded-md p-1.5 text-muted-foreground transition hover:text-foreground; }
.view-active { @apply bg-background text-teal-700 shadow-sm dark:text-teal-400; }
button, input, textarea { @apply focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600; }
input[type='checkbox'] { @apply h-4 w-4 cursor-pointer rounded accent-teal-700; }
.tool { @apply inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-35; }
.primary { @apply inline-flex items-center gap-2 rounded-xl bg-teal-700 px-5 py-3 shadow-sm text-sm font-medium text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50; }
</style>
