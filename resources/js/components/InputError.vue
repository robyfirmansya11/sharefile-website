<script setup lang="ts">
import { t } from '@/i18n';
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-red-600 dark:text-red-500">
            {{ t(message) }}
        </p>
    </div>
</template>
