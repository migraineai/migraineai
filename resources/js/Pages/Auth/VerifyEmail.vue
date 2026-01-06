<script lang="ts" setup>
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({});
const logoutForm = useForm({});

const submit = () => {
    form.post('/email/verification-notification', {
        onSuccess: () => {
            // The status will be automatically updated through the page props
        },
        onError: (errors) => {
            console.error('Failed to send verification email:', errors);
        }
    });
};

const logout = async () => {
    try {
        await window.axios.get('/sanctum/csrf-cookie');
    } catch {}
    logoutForm.post('/logout', {
        onFinish: () => {
            window.location.href = '/login';
        }
    });
};
</script>

<template>
    <Head title="Email Verification" />

    <main class="relative flex min-h-screen items-center justify-center px-4 py-16 text-[--color-text-primary]">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
        >
            <div
                class="absolute left-1/2 top-10 h-64 w-64 -translate-x-1/2 rounded-full bg-[--color-accent]/20 blur-3xl"
            ></div>
            <div
                class="absolute bottom-10 right-10 h-48 w-48 rounded-full bg-[--color-accent]/10 blur-[110px]"
            ></div>
        </div>

        <section
            class="glass-panel w-full max-w-xl border-[--color-border-strong] p-10"
        >
            <header class="mb-10 flex flex-col items-center gap-4 text-center">
                <div class="flex size-16 items-center justify-center rounded-2xl bg-[--color-accent]/15 text-[--color-accent]">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        class="size-7"
                        fill="currentColor"
                    >
                        <path
                            d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4.7-8 5.3-8-5.3V6l8 5.3L20 6z"
                        />
                    </svg>
                </div>

                <div>
                    <h1 class="text-3xl font-semibold tracking-tight">Verify Your Email</h1>
                    <p class="mt-3 text-sm leading-relaxed text-[--color-text-muted]">
                        Thanks for signing up! Before getting started, please verify your email address by clicking on the
                        link we just emailed to you. If you didn't receive the email, we'll gladly send you another.
                    </p>
                </div>
            </header>

            <div v-if="status === 'verification-link-sent'" class="mb-6 rounded-2xl border border-green-500/40 bg-green-500/10 px-4 py-3 text-sm text-green-200">
                A new verification link has been sent to your email address.
            </div>

            <div class="flex flex-col gap-4">
                <form @submit.prevent="submit">
                    <button
                        type="submit"
                        class="button-primary w-full text-base"
                        :disabled="form.processing"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                            <path
                                d="M4 6h16a1 1 0 0 1 .78 1.63l-8 9a1 1 0 0 1-1.56 0l-8-9A1 1 0 0 1 4 6Z"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <path
                                d="m21 19-5.43-6.11"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            />
                            <path
                                d="m3 19 5.4-6.06"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            />
                        </svg>
                        {{ form.processing ? 'Sending...' : 'Resend Verification Email' }}
                    </button>
                </form>

                <div class="text-center">
                    <button
                        type="button"
                        class="text-sm text-[--color-text-muted] hover:text-[--color-text-primary] transition-colors duration-150"
                        :disabled="logoutForm.processing"
                        @click="logout"
                    >
                        Log Out
                    </button>
                </div>
            </div>
        </section>
    </main>
</template>
