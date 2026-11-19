<script setup lang="ts">
import { t } from '@/i18n';
import { Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-label="t('Mode gelap')"
        :aria-checked="resolvedAppearance === 'dark'"
        :title="resolvedAppearance === 'dark' ? t('Aktifkan mode terang') : t('Aktifkan mode gelap')"
        class="inline-flex shrink-0 items-center gap-2 rounded-full border border-border bg-muted/50 p-1.5 transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600"
        @click="updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark')"
    >
        <span
            class="flex size-7 items-center justify-center rounded-full transition-colors"
            :class="resolvedAppearance === 'light' ? 'bg-white text-amber-600 shadow-sm' : 'text-muted-foreground'"
        >
            <Sun class="size-4" aria-hidden="true" />
        </span>
        <span
            class="flex size-7 items-center justify-center rounded-full transition-colors"
            :class="resolvedAppearance === 'dark' ? 'bg-teal-800 text-teal-100 shadow-sm' : 'text-muted-foreground'"
        >
            <Moon class="size-4" aria-hidden="true" />
        </span>
    </button>
</template>
