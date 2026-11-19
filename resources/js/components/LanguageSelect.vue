<script setup lang="ts">
import { Languages } from '@lucide/vue';
import { useLocale, setLocale, t } from '@/i18n';
const locale = useLocale();
</script>

<template>
    <label class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-border bg-background px-2.5 py-2 text-sm">
        <Languages class="size-4 text-muted-foreground" aria-hidden="true" />
        <select :value="locale" :aria-label="t('Bahasa')" class="max-w-28 cursor-pointer bg-background text-foreground outline-none focus-visible:ring-2 focus-visible:ring-teal-600" @change="setLocale(($event.target as HTMLSelectElement).value)">
            <option value="id" lang="id">Indonesia</option>
            <option value="en" lang="en">English</option>
            <option value="zh-CN" lang="zh-CN">简体中文</option>
        </select>
    </label>
</template>
