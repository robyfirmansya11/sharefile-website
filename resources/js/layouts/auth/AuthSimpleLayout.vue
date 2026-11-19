<script setup lang="ts">
import { t } from '@/i18n';
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, CloudUpload, FileText, Folder, Folders, Layers, MousePointer2, Pause, Play } from '@lucide/vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import LanguageSelect from '@/components/LanguageSelect.vue';
import { home } from '@/routes';

const motionPaused = ref(false);

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="auth-shell min-h-svh bg-background text-foreground lg:grid lg:grid-cols-[1.05fr_1fr]">
        <aside :class="{ 'motion-paused': motionPaused }" class="brand-panel relative hidden flex-col overflow-hidden p-10 text-white lg:flex xl:p-14">
            <div class="ambient-glow pointer-events-none absolute inset-0" aria-hidden="true"></div>
            <div class="brand-grid pointer-events-none absolute inset-0" aria-hidden="true"></div>
            <Link :href="home()" class="relative z-10 flex w-fit items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">
                <span class="flex size-11 items-center justify-center rounded-2xl border border-white/20 bg-white/10"><Folders :size="24" /></span>
                <span><span class="block text-lg font-semibold tracking-tight">Sharefolder</span><span class="text-[10px] tracking-[0.16em] text-teal-100/70">ARTABUMI SENTRA INDUSTRI</span></span>
            </Link>
            <div class="brand-intro relative z-10 my-auto max-w-xl py-14">
                <span class="mb-6 inline-flex items-center gap-2 rounded-full border border-teal-200/20 bg-teal-200/10 px-3 py-1.5 text-xs font-medium text-teal-100"><span class="status-dot size-1.5 rounded-full bg-teal-300"></span> {{ t('Ruang kerja digital tim Anda') }}</span>
                <h2 class="text-4xl leading-[1.14] font-semibold tracking-tight xl:text-5xl">{{ t('Berkas tertata.') }}<br /><span class="text-teal-200">{{ t('Kerja lebih mudah.') }}</span></h2>
                <p class="mt-5 max-w-sm text-sm leading-7 text-teal-50/75">{{ t('Satu tempat untuk mengelola dokumen, merapikan folder, dan melanjutkan pekerjaan bersama tim.') }}</p>
                <div class="file-illustration relative mt-10 max-w-md select-none" aria-hidden="true">
                    <div class="illustration-window rounded-2xl border border-white/20 bg-white/[0.08] p-5 shadow-2xl backdrop-blur-sm">
                        <div class="mb-5 flex items-center justify-between"><span class="flex items-center gap-2 text-sm font-medium"><Folder :size="17" class="text-teal-200" /> {{ t('Ruang kerja tim') }}</span><span class="flex gap-1"><i class="size-1 rounded-full bg-white/35"></i><i class="size-1 rounded-full bg-white/35"></i><i class="size-1 rounded-full bg-white/35"></i></span></div>
                        <div class="grid grid-cols-3 gap-3"><div v-for="(folder, index) in [t('Dokumen'), t('Laporan'), t('Arsip')]" :key="folder" :style="{ '--folder-delay': `${index * 1.6}s` }" class="animated-folder rounded-xl border border-white/10 bg-white/[0.07] p-3"><Folder class="mb-3 size-7 fill-teal-200/20 text-teal-200" /><span class="text-xs text-teal-50">{{ folder }}</span></div></div>
                        <div class="document-preview relative mt-3 flex items-center gap-3 overflow-hidden rounded-xl bg-white/10 p-3"><span class="rounded-lg bg-white/10 p-2"><FileText :size="18" class="text-teal-100" /></span><div class="flex-1 space-y-2"><div class="h-1.5 w-28 rounded-full bg-white/40"></div><div class="h-1 w-20 rounded-full bg-white/15"></div></div><ArrowUpRight :size="16" class="text-teal-100/60" /></div>
                    </div>
                    <div class="upload-badge absolute -right-3 -bottom-5 flex items-center gap-3 rounded-xl border border-white/30 bg-teal-50 px-4 py-3 text-teal-950 shadow-xl"><CloudUpload class="upload-icon" :size="22" /><span class="text-xs font-semibold">{{ t('Tarik. Lepaskan. Selesai.') }}</span><MousePointer2 class="demo-cursor absolute -right-3 -bottom-4 size-7 fill-teal-300 text-teal-900" /></div>
                </div>
            </div>
            <div class="relative z-10 flex flex-wrap items-center justify-between gap-4 text-xs text-teal-100/65"><span class="flex items-center gap-2"><Layers :size="15" />{{ t('Lebih teratur, dari folder pertama.') }}</span><button type="button" class="motion-control flex items-center gap-2 rounded-lg border border-white/15 px-3 py-2 text-teal-100 transition-colors hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" :aria-pressed="motionPaused" :aria-label="t('Jeda animasi')" @click="motionPaused = !motionPaused"><Play v-if="motionPaused" :size="13" aria-hidden="true" /><Pause v-else :size="13" aria-hidden="true" />{{ motionPaused ? t('Lanjutkan animasi') : t('Jeda animasi') }}</button></div>
        </aside>
        <main class="relative flex min-h-svh flex-col px-6 py-6 sm:px-10 lg:px-12">
            <header class="flex items-center justify-between gap-4">
                <Link :href="home()" class="flex items-center gap-2 font-semibold lg:hidden"><Folders class="size-6 text-teal-700 dark:text-teal-400" /> Sharefolder</Link>
                <div class="ml-auto flex flex-wrap items-center justify-end gap-2"><LanguageSelect /><ThemeToggle /></div>
            </header>
            <div class="mx-auto flex w-full max-w-[400px] flex-1 flex-col justify-center py-12 sm:py-16">
                <div class="mb-8">
                    <span class="mb-6 inline-flex size-12 items-center justify-center rounded-2xl border border-teal-100 bg-teal-50 text-teal-700 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-300"><Folders :size="24" /></span>
                    <h1 class="text-3xl font-semibold tracking-tight">{{ t(title) }}</h1>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">{{ t(description) }}</p>
                </div>
                <slot />
            </div>
            <footer class="text-center text-xs leading-5 text-muted-foreground">Sharefolder <span class="px-1 text-border">/</span> Artabumi Sentra Industri</footer>
        </main>
    </div>
</template>

<style scoped>
.brand-panel { background: radial-gradient(ellipse at 10% 0%, #1b8278 0%, transparent 55%), radial-gradient(ellipse at 100% 90%, #16685f 0%, transparent 55%), #073d38; }
.brand-grid { background-image: linear-gradient(rgb(255 255 255 / 0.035) 1px, transparent 1px), linear-gradient(90deg, rgb(255 255 255 / 0.035) 1px, transparent 1px); background-size: 48px 48px; mask-image: linear-gradient(to bottom, transparent, black, transparent); }
/* Transform and opacity keep decorative motion independent of layout. */
.ambient-glow { background: radial-gradient(ellipse at 65% 55%, rgb(94 234 212 / 0.12), transparent 60%); animation: glow-drift 12s ease-in-out infinite; }
.brand-intro { animation: intro-reveal 700ms ease-out both; }
.file-illustration { animation: card-float 7s ease-in-out infinite; }
.animated-folder { animation: folder-highlight 6s ease-in-out infinite; animation-delay: var(--folder-delay); }
.document-preview::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, transparent 20%, rgb(204 251 241 / 0.12) 50%, transparent 80%); transform: translateX(-110%); animation: document-sheen 7s ease-in-out infinite; pointer-events: none; }
.upload-badge { animation: badge-float 7s ease-in-out infinite; }
.upload-icon { animation: upload-lift 3.5s ease-in-out infinite; }
.demo-cursor { animation: cursor-nudge 7s ease-in-out infinite; }
.status-dot { animation: status-glow 4s ease-in-out infinite; }
@keyframes intro-reveal { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
@keyframes card-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
@keyframes badge-float { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(4px) rotate(-1deg); } }
@keyframes folder-highlight { 0%, 45%, 100% { transform: translateY(0); background-color: rgb(255 255 255 / 0.07); } 20% { transform: translateY(-4px); background-color: rgb(153 246 228 / 0.18); } }
@keyframes document-sheen { 0%, 45% { transform: translateX(-110%); } 80%, 100% { transform: translateX(110%); } }
@keyframes upload-lift { 0%, 65%, 100% { transform: translateY(0); } 35% { transform: translateY(-4px); } }
@keyframes cursor-nudge { 0%, 20%, 100% { transform: translate(0, 0); } 45%, 55% { transform: translate(-8px, -8px); } 50% { transform: translate(-8px, -8px) scale(0.88); } }
@keyframes status-glow { 0%, 100% { opacity: 0.65; } 50% { opacity: 1; } }
.motion-paused *, .motion-paused *::after { animation-play-state: paused !important; }
@media (prefers-reduced-motion: reduce) {
    .brand-panel *, .brand-panel *::after { animation: none !important; }
    .document-preview::after, .motion-control { display: none; }
}
</style>
