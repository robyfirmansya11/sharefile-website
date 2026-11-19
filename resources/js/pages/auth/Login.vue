<script setup lang="ts">
import { t } from '@/i18n';
import { ArrowRight, CheckCircle2, Info } from '@lucide/vue';
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Selamat datang kembali',
        description: 'Masuk ke akun Anda untuk melanjutkan ke ruang kerja dan mengelola file tim.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head :title="t('Masuk • Sharefolder')" />

    <div
        v-if="status"
        role="status"
        class="mb-5 flex items-start gap-2 rounded-xl border border-teal-200 bg-teal-50 p-3 text-sm text-teal-800 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-200"
    >
        <CheckCircle2 class="mt-0.5 size-4 shrink-0" /><span>{{ status }}</span>
    </div>


    <PasskeyVerify :label="t('Masuk dengan passkey')" :loading-label="t('Memverifikasi…')" :separator="t('atau masuk dengan email')" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{ t('Alamat email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    autocomplete="email"
                    :placeholder="t('nama@perusahaan.com')"
                    class="h-12 rounded-xl bg-muted/20"
                    :aria-invalid="!!errors.email"
                    :aria-describedby="errors.email ? 'email-error' : undefined"
                />
                <InputError id="email-error" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">{{ t('Password') }}</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                    >
                        {{ t('Lupa password?') }}
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    :placeholder="t('Masukkan password Anda')"
                    class="h-12 rounded-xl bg-muted/20"
                    :autofocus="false"
                    :aria-invalid="!!errors.password"
                    :aria-describedby="errors.password ? 'password-error' : undefined"
                />
                <InputError id="password-error" :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" />
                    <span>{{ t('Ingat saya') }}</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-1 h-12 w-full rounded-xl bg-teal-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800 focus-visible:ring-teal-500"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? t('Sedang masuk…') : t('Masuk ke ruang kerja') }}
                <ArrowRight v-if="!processing" class="ml-auto size-4" />
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            {{ t('staff.contact_it') }}
        </div>
    </Form>

    <div class="mt-5 flex items-start gap-2 rounded-xl bg-muted/50 p-3 text-xs leading-5 text-muted-foreground"><Info class="mt-0.5 size-4 shrink-0" /><p>{{ t('Gunakan akun aplikasi yang sudah terdaftar. Login dengan akun Active Directory belum tersedia.') }}</p></div>
</template>
