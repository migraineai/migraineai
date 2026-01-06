<script lang="ts" setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import RegisterOnboardingFlow from '@/Components/RegisterOnboardingFlow.vue';
import onboardingData from '../../../../ref/onboarding.json';
import {
    buildDefaultOnboardingAnswers,
    countOnboardingAnswers,
    OnboardingSection,
} from '@/Utils/onboarding';
import { logoUrl } from '@/Utils/logo';

const props = defineProps<{
    initialMode: 'login' | 'register';
}>();

const onboardingDefinition = onboardingData.onboarding_flow;
const onboardingSections = onboardingDefinition.sections as OnboardingSection[];
const baseOnboardingQuestionCount =
    typeof onboardingDefinition.meta?.total_questions === 'number'
        ? onboardingDefinition.meta.total_questions
        : onboardingSections.reduce((total, section) => total + section.questions.length, 0);
const registerStep = ref<'account' | 'onboarding'>('account');

const forgotPasswordForm = useForm({
  email: ''
});

//type Mode = 'login' | 'register';

type Mode = 'login' | 'register' | 'forgot-password' | 'reset-password';

const mode = ref<Mode>(props.initialMode);

watch(
    () => props.initialMode,
    (nextMode) => {
        mode.value = nextMode;
        if (nextMode === 'register') {
            registerStep.value = 'account';
        }
    }
);

const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
});

const registerForm = useForm({
    name: '',
    email: '',
    gender: 'female',
    age: '',
    password: '',
    password_confirmation: '',
    onboarding_answers: buildDefaultOnboardingAnswers(onboardingSections),
});
const OPTIONAL_MENSTRUAL_QUESTION_ID = 'q11_track_periods';
if (!(OPTIONAL_MENSTRUAL_QUESTION_ID in registerForm.onboarding_answers)) {
    registerForm.onboarding_answers[OPTIONAL_MENSTRUAL_QUESTION_ID] = null;
}

// Resend verification link logic
const resendProcessing = ref(false);
const resendStatus = ref('');
const resendError = ref('');

async function resendVerificationLink() {
    resendProcessing.value = true;
    resendStatus.value = '';
    resendError.value = '';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const response = await fetch('/email/resend-verification', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                email: loginForm.email,
            }),
        });

        if (!response.ok && response.status === 419) {
            resendError.value = 'CSRF token mismatch. Please refresh and try again.';
            resendProcessing.value = false;
            return;
        }

        const data = await response.json();

        if (response.ok) {
            resendStatus.value = 'Verification email sent! Check your inbox.';
        } else {
            resendError.value = data.message || 'Failed to send verification email.';
        }
    } catch (error) {
        resendError.value = 'An error occurred. Please try again.';
        console.error('Resend verification error:', error);
    } finally {
        resendProcessing.value = false;
    }
}

const pageTitle = computed(() => (mode.value === 'login' ? 'Sign In' : 'Create Account'));
const page = usePage();
const flashSuccess = computed(() => {
    const success = page.props.flash?.success;

    return typeof success === 'function' ? success() : success;
});
const flashError = computed(() => {
    const error = page.props.flash?.error;

    return typeof error === 'function' ? error() : error;
});
const hasLoginErrors = computed(() => Object.keys(loginForm.errors).length > 0);
const loginErrorText = computed(() => {
    const e: unknown = (loginForm.errors.email ?? loginForm.errors.password ?? '');
    return typeof e === 'string' ? e : '';
});
const requiresVerification = computed(() => /verify your email/i.test(loginErrorText.value));
const hasRegisterErrors = computed(() => Object.keys(registerForm.errors).length > 0);
const computedTotalOnboardingQuestions = computed(() => {
    return baseOnboardingQuestionCount + (registerForm.gender === 'female' ? 1 : 0);
});

const optionalQuestionAnswered = computed(() => {
    if (registerForm.gender !== 'female') {
        return 0;
    }

    const value = registerForm.onboarding_answers[OPTIONAL_MENSTRUAL_QUESTION_ID];
    return value === null || value === undefined ? 0 : 1;
});

const onboardingAnsweredCount = computed(() =>
    countOnboardingAnswers(onboardingSections, registerForm.onboarding_answers) + optionalQuestionAnswered.value
);

function navigate(nextMode: Mode) {
    if (mode.value === nextMode) {
        return;
    }

    mode.value = nextMode;
    if (nextMode === 'register') {
        registerStep.value = 'account';
    }

    const routes = {
        login: '/login',
        register: '/register',
        'forgot-password': '/forgot-password'
    };
    router.visit(routes[nextMode], {
        replace: true,
        preserveScroll: true,
        preserveState: true,
    });
}

function submitLogin() {
    loginForm.post('/login', {
        preserveScroll: true,
        onSuccess: () => {
            // Force full page reload to get fresh CSRF token
            window.location.href = '/dashboard';
        },
    });
}

function submitRegister() {
    registerForm.transform((data) => ({
        ...data,
        age: data.age ? Number(data.age) : data.age,
    }));

    registerForm.post('/register', {
        preserveScroll: true,
        onSuccess: () => {
            registerForm.reset('password', 'password_confirmation');
        },
    });
}

function handleOnboardingAnswer(questionId: string, answer: unknown) {
    registerForm.onboarding_answers[questionId] = answer;
}

// Add submit handler
function submitForgotPassword() {
  forgotPasswordForm.post('/forgot-password', {
    preserveScroll: true
  });
}

// Add reset password form if needed
const resetPasswordForm = useForm({
  email: '',
  password: '',
  password_confirmation: '',
  token: props.token || ''
});
</script>

<template>
    <Head :title="pageTitle" />

    <main v-cloak class="relative flex min-h-screen items-center justify-center px-4 py-16 text-[--color-text-primary]">
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
                    <img :src="logoUrl" alt="MigraineAI logo" class="auth-logo" />
                </div>
                <div>
                    <h1 class="text-3xl font-semibold tracking-tight">MigraineAI Portal</h1>
                    <p class="mt-3 text-sm leading-relaxed text-[--color-text-muted]">
                        {{
                            mode === 'login'
                                ? 'Sign in to pick up where you left off and view today’s MigraineAI recommendations.'
                                : 'Create a new account to analyse your migraine patterns with calming, low-stimulation visualisations.'
                        }}
                    </p>
                </div>
            </header>

            <div
                class="mb-8 grid grid-cols-2 gap-2 rounded-full border border-[--color-border-strong] bg-[--color-bg-alt]/80 p-1 text-sm font-medium"
            >
                <button
                    type="button"
                    class="auth-tab"
                    :class="{ 'auth-tab-active': mode === 'login' }"
                    @click="navigate('login')"
                >
                    Sign In
                </button>
                <button
                    type="button"
                    class="auth-tab"
                    :class="{ 'auth-tab-active': mode === 'register' }"
                    @click="navigate('register')"
                >
                    Sign Up
                </button>
            </div>

            <transition name="fade" mode="out-in">
                <form
                    v-if="mode === 'login'"
                    key="login"
                    class="space-y-6"
                    @submit.prevent="submitLogin"
                >
                    <div v-if="flashSuccess" class="rounded-2xl border border-green-500/40 bg-green-500/10 px-4 py-3 text-sm text-green-200">
                        {{ flashSuccess }}
                    </div>
                    <div v-if="flashError" class="rounded-2xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                        {{ flashError }}
                    </div>
                    <div
                        v-if="hasLoginErrors"
                        class="rounded-2xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200"
                    >
                        <template v-if="requiresVerification">
                            {{ loginErrorText }}
                            <button
                                type="button"
                                class="ml-2 underline text-[--color-accent] hover:text-[--color-accent-muted] focus:outline-none"
                                @click="resendVerificationLink"
                                :disabled="resendProcessing"
                            >
                                {{ resendProcessing ? 'Sending...' : 'Resend Verification Link' }}
                            </button>
                            <span v-if="resendStatus" class="ml-2 text-green-400">{{ resendStatus }}</span>
                            <span v-if="resendError" class="ml-2 text-red-400">{{ resendError }}</span>
                        </template>
                        <template v-else>
                            {{ loginErrorText }}
                        </template>
                    </div>


                    <div>
                        <label for="login-email" class="mb-2 block text-sm font-medium text-[--color-text-primary]">
                            Email
                        </label>
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute left-3 top-3.5 flex size-6 items-center justify-center text-[--color-accent]"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path
                                        d="M3 9l9-6 9 6v6l-9 6-9-6z"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </span>
                            <input
                                id="login-email"
                                v-model="loginForm.email"
                                type="email"
                                autocomplete="email"
                                required
                                class="w-full border border-[--color-border] bg-[--color-bg-alt]/80 pl-12 pr-4 text-sm focus:border-[--color-accent]"
                                placeholder="you@example.com"
                            />
                        </div>
                    </div>

                    <div>
                        <label for="login-password" class="mb-2 block text-sm font-medium">
                            Password
                        </label>
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute left-3 top-3.5 flex size-6 items-center justify-center text-[--color-accent]"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path
                                        d="M7 10V8a5 5 0 0 1 10 0v2"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <rect
                                        x="4"
                                        y="10"
                                        width="16"
                                        height="10"
                                        rx="2"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                    />
                                    <circle cx="12" cy="15" r="1.5" fill="currentColor" />
                                </svg>
                            </span>
                            <input
                                id="login-password"
                                v-model="loginForm.password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="w-full border border-[--color-border] bg-[--color-bg-alt]/80 pl-12 pr-4 text-sm focus:border-[--color-accent]"
                                placeholder="Secure password"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-sm text-[--color-text-muted]">
                        <label class="flex items-center gap-3">
                            <input
                                v-model="loginForm.remember"
                                type="checkbox"
                                class="size-5 rounded border-[--color-border] bg-[--color-bg] text-[--color-accent] accent-[--color-accent]"
                            />
                            Remember me
                        </label>
                       <button
                        type="button"
                        class="text-xs font-medium text-[--color-accent] transition-opacity duration-150 hover:opacity-80"
                        @click="navigate('forgot-password')"
                        >
                        Forgot password?
                        </button>
                    </div>

                    <button
                        type="submit"
                        class="button-primary w-full text-base"
                        :disabled="loginForm.processing"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                            <path
                                d="M4 12h12"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <path
                                d="M14 6l6 6-6 6"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                        {{ loginForm.processing ? 'Signing in…' : 'Sign In' }}
                    </button>

                </form>

                <form
                    v-else-if="mode === 'register'"
                    key="register"
                    class="space-y-6"
                    @submit.prevent="submitRegister"
                >
                    <div
                        v-if="hasRegisterErrors"
                        class="rounded-2xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200"
                    >
                        {{ Object.values(registerForm.errors)[0] }}
                    </div>

                    <div class="mb-4 flex items-center justify-between text-xs uppercase tracking-wide text-[--color-text-muted]">
                        <span v-if="registerStep === 'account'">Step 1 of 2 · Account details</span>
                        <span v-else>Step 2 of 2 · Onboarding questions</span>
                        <span v-if="registerStep === 'onboarding'">{{ onboardingAnsweredCount }} / {{ computedTotalOnboardingQuestions }} answered</span>
                    </div>

                    <div v-if="registerStep === 'account'" class="space-y-6">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="register-name" class="mb-2 block text-sm font-medium">Name</label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute left-3 top-3.5 flex size-6 items-center justify-center text-[--color-accent]"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            class="size-5"
                                            fill="none"
                                        >
                                            <path
                                                d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-3.33 0-6 2-6 4.5A.5.5 0 0 0 6.5 19h11a.5.5 0 0 0 .5-.5C18 16 15.33 14 12 14Z"
                                                fill="currentColor"
                                            />
                                        </svg>
                                    </span>
                                    <input
                                        id="register-name"
                                        v-model="registerForm.name"
                                        type="text"
                                        autocomplete="name"
                                        required
                                        class="w-full border border-[--color-border] bg-[--color-bg-alt]/80 pl-12 pr-4 text-sm focus:border-[--color-accent]"
                                        placeholder="Jane Doe"
                                    />
                                </div>
                            </div>

                            <div class="sm:col-span-2">
                                <label for="register-email" class="mb-2 block text-sm font-medium">Email</label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute left-3 top-3.5 flex size-6 items-center justify-center text-[--color-accent]"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            class="size-5"
                                            fill="none"
                                        >
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
                                    </span>
                                    <input
                                        id="register-email"
                                        v-model="registerForm.email"
                                        type="email"
                                        autocomplete="email"
                                        required
                                        class="w-full border border-[--color-border] bg-[--color-bg-alt]/80 pl-12 pr-4 text-sm focus:border-[--color-accent]"
                                        placeholder="you@example.com"
                                    />
                                </div>
                            </div>

                            <div>
                                <label for="register-gender" class="mb-2 block text-sm font-medium">Gender</label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute left-3 top-3.5 flex size-6 items-center justify-center text-[--color-accent]"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            class="size-5"
                                            fill="none"
                                        >
                                            <path
                                                d="M15 3h6m0 0v6m0-6-7 7"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                            />
                                            <path d="M8 6h6m3 6v6m0-6-4 4" stroke="currentColor" stroke-width="1.5" />
                                        </svg>
                                    </span>
                                    <select
                                        id="register-gender"
                                        v-model="registerForm.gender"
                                        required
                                        class="w-full appearance-none border border-[--color-border] bg-[--color-bg-alt]/80 pl-12 pr-10 text-sm focus:border-[--color-accent]"
                                    >
                                        <option value="female">Female</option>
                                        <option value="male">Male</option>
                                    </select>
                                    <span
                                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[--color-text-muted]"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4">
                                            <path
                                                d="m6 9 6 6 6-6"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </span>
                                </div>
                            </div>

                            <div>
                                <label for="register-age" class="mb-2 block text-sm font-medium">Age</label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute left-3 top-3.5 flex size-6 items-center justify-center text-[--color-accent]"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            class="size-5"
                                            fill="none"
                                        >
                                            <path
                                                d="M12 6v6l4 2"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                            <circle
                                                cx="12"
                                                cy="12"
                                                r="9"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                            />
                                        </svg>
                                    </span>
                                    <input
                                        id="register-age"
                                        v-model="registerForm.age"
                                        type="number"
                                        inputmode="numeric"
                                        min="13"
                                        max="120"
                                        required
                                        class="w-full border border-[--color-border] bg-[--color-bg-alt]/80 pl-12 pr-4 text-sm focus:border-[--color-accent]"
                                        placeholder="28"
                                    />
                                </div>
                            </div>

                            <div>
                                <label for="register-password" class="mb-2 block text-sm font-medium">Password</label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute left-3 top-3.5 flex size-6 items-center justify-center text-[--color-accent]"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            class="size-5"
                                            fill="none"
                                        >
                                            <path
                                                d="M7 11V9a5 5 0 0 1 10 0v2"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                            <rect
                                                x="4"
                                                y="11"
                                                width="16"
                                                height="10"
                                                rx="2"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                            />
                                        </svg>
                                    </span>
                                    <input
                                        id="register-password"
                                        v-model="registerForm.password"
                                        type="password"
                                        autocomplete="new-password"
                                        required
                                        class="w-full border border-[--color-border] bg-[--color-bg-alt]/80 pl-12 pr-4 text-sm focus:border-[--color-accent]"
                                        placeholder="Create a password"
                                    />
                                </div>
                            </div>

                            <div>
                                <label for="register-password-confirmation" class="mb-2 block text-sm font-medium">Confirm Password</label>
                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute left-3 top-3.5 flex size-6 items-center justify-center text-[--color-accent]"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            class="size-5"
                                            fill="none"
                                        >
                                            <path
                                                d="m7 13 3 3 7-7"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                            <rect
                                                x="4"
                                                y="5"
                                                width="16"
                                                height="14"
                                                rx="2"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                            />
                                        </svg>
                                    </span>
                                    <input
                                        id="register-password-confirmation"
                                        v-model="registerForm.password_confirmation"
                                        type="password"
                                        autocomplete="new-password"
                                        required
                                        class="w-full border border-[--color-border] bg-[--color-bg-alt]/80 pl-12 pr-4 text-sm focus:border-[--color-accent]"
                                        placeholder="Repeat password"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <button
                                type="button"
                                class="button-primary w-full text-base flex items-center justify-center gap-2"
                                @click="registerStep = 'onboarding'"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path d="M12 5a7 7 0 1 0 7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M12 2v6m0 0h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                Continue to onboarding
                            </button>
                        </div>
                    </div>

                    <div v-else class="space-y-6">
                        <RegisterOnboardingFlow
                            :sections="onboardingSections"
                            :answers="registerForm.onboarding_answers"
                            :gender="registerForm.gender"
                            :onAnswer="handleOnboardingAnswer"
                        />
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <button type="button" class="settings-outline-button w-full sm:w-auto" @click="registerStep = 'account'">
                                Back to account info
                            </button>
                            <button
                                type="submit"
                                class="button-primary flex-1 text-base flex items-center justify-center gap-2"
                                :disabled="registerForm.processing"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path d="M12 5a7 7 0 1 0 7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M12 2v6m0 0h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                {{ registerForm.processing ? 'Creating account…' : 'Create Account' }}
                            </button>
                        </div>
                    </div>

                    <p class="text-center text-xs text-[--color-text-muted]">
                        By creating an account you agree to receive calm, clinically-informed notifications to help
                        manage your migraines.
                    </p>
                </form>


                <form
                    v-else-if="mode === 'forgot-password'"
                    key="forgot-password"
                    class="space-y-6"
                    @submit.prevent="submitForgotPassword"
                >
                    <div
                        v-if="forgotPasswordForm.errors.email"
                        class="rounded-2xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200"
                    >
                        {{ forgotPasswordForm.errors.email }}
                    </div>

                    <div>
                        <label for="forgot-password-email" class="mb-2 block text-sm font-medium">
                            Email
                        </label>
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute left-3 top-3.5 flex size-6 items-center justify-center text-[--color-accent]"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path
                                        d="M3 9l9-6 9 6v6l-9 6-9-6z"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                    />
                                </svg>
                            </span>
                            <input
                                id="forgot-password-email"
                                v-model="forgotPasswordForm.email"
                                type="email"
                                required
                                class="w-full border border-[--color-border] bg-[--color-bg-alt]/80 pl-12 pr-4 text-sm focus:border-[--color-accent]"
                                placeholder="Enter your email"
                            />
                        </div>
                    </div>

                    <div class="flex justify-between">
                        <button
                            type="button"
                            class="text-sm text-[--color-text-muted] hover:text-[--color-text-primary]"
                            @click="navigate('login')"
                        >
                            Back to login
                        </button>
                        <button
                            type="submit"
                            class="button-primary"
                            :disabled="forgotPasswordForm.processing"
                        >
                            {{ forgotPasswordForm.processing ? 'Sending...' : 'Send Reset Link' }}
                        </button>
                    </div>
                </form>
            </transition>
        </section>
    </main>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateY(4px);
}
</style>
