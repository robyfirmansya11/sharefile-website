<script setup lang="ts">
import { t } from '@/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Settings } from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
const page = usePage();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator v-if="page.props.share.driver !== 'windows'" />
    <DropdownMenuGroup v-if="page.props.share.driver !== 'windows'">
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                {{ t('Settings') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator v-if="page.props.share.driver !== 'windows'" />
    <DropdownMenuItem v-if="page.props.share.driver !== 'windows'" :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            {{ t('Log out') }}
        </Link>
    </DropdownMenuItem>
    <p v-if="page.props.share.driver === 'windows'" class="max-w-64 px-2 py-3 text-xs leading-5 text-muted-foreground">{{ t('Terhubung melalui Windows. Akun dan password dikelola oleh administrator AD. Identitas mengikuti sesi Windows/browser, bukan tombol logout aplikasi.') }}</p>
</template>
