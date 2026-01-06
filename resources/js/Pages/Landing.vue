<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { logoUrl } from '@/Utils/logo';
import { onMounted, onUnmounted, ref } from 'vue';

const navigation = [
    { label: 'Home', href: '#home' },
    { label: 'Outcomes', href: '#services' },
    { label: 'Mission', href: '#mission' },
    { label: 'Proof', href: '#stats' },
    { label: 'Beta', href: '#cta' },
];

const serviceHighlights = [
    {
        title: 'Zero-Touch Symptom Logging',
        description:
            'During an attack, screens are painful. Our voice-first interface allows you to log pain.',
    },
    {
        title: 'Neurologist-Grade Diagnostics',
        description:
            'We generate two distinct reports: a "Pattern Finder" for you, and a specialized "Doctor\'s Report" designed for rapid clinical assessment.',
    },
];

const stats = [
    { value: '300+', label: 'Active Beta Users' },
    { value: '5', label: 'Clinical Partners (LOIs)' },
    { value: '100%', label: 'Hands-Free Interaction' },
    { value: '2', label: 'Distinct Diagnostic Engines' },
];

const footerCompany = [
    { label: 'About Us', href: '/about' },
    { label: 'Clinical Partners', href: '/clinical-partners' },
    { label: 'Careers', href: '/careers' },
];

const footerLegal = [
    { label: 'Privacy Policy (HIPAA/GDPR)', href: '/privacy-policy' },
    { label: 'Terms of Service', href: '/terms-service' },
    { label: 'User Safety', href: '/user-safety' },
];

const footerContact = [{ label: 'Support', href: '/support' }];

const heroStatus = {
    title: 'Trigger Analysis Complete',
    insight: 'Barometric pressure drop detected. Hydration protocol recommended.',
};

const STORAGE_KEY = 'migraineai_add_to_home_dismissed';
const DEFAULT_DESCRIPTION = 'Install MigraineAI for quick, gentle migraine tracking.';
const IOS_HINT =
    'Open the share menu (the square with an arrow in Safari or ⋮ in Chrome) and tap Add to Home Screen.';

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const bannerVisible = ref(false);
const descriptionText = ref(DEFAULT_DESCRIPTION);
const hintVisible = ref(false);
const hintText = ref('');

let platform: 'ios' | 'android' | null = null;
let deferredPrompt: BeforeInstallPromptEvent | null = null;
let beforeInstallPromptHandler: ((event: Event) => void) | null = null;

const isStandaloneDisplay = () => {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
        window.navigator.standalone === true
    );
};

const setDismissedFlag = () => {
    try {
        localStorage.setItem(STORAGE_KEY, '1');
    } catch (_) {
        // ignore storage errors
    }
};

const hasPreviouslyDismissed = () => {
    try {
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch (_) {
        return false;
    }
};

const markBannerDismissed = () => {
    bannerVisible.value = false;
    setDismissedFlag();
};

const showBanner = () => {
    bannerVisible.value = true;
};

const handleAddToHomeClick = () => {
    if (platform === 'ios') {
        hintVisible.value = true;
        return;
    }

    if (!deferredPrompt) {
        descriptionText.value = 'Install prompt is not available yet; try again shortly.';
        return;
    }

    deferredPrompt.prompt();
    deferredPrompt.userChoice.then((choice) => {
        if (choice.outcome === 'accepted') {
            markBannerDismissed();
        }
        deferredPrompt = null;
    });
};

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    if (import.meta.env.PROD && 'serviceWorker' in navigator) {
        navigator.serviceWorker.register('/service-worker.js').catch(() => {});
    }

    const isiOS =
        /iPad|iPhone|iPod/.test(navigator.userAgent) &&
        !(window.MSStream || (navigator.userAgent.includes('Macintosh') && 'ontouchend' in document));
    const isAndroid = /Android/.test(navigator.userAgent);

    if (!isiOS && !isAndroid) {
        return;
    }

    if (hasPreviouslyDismissed() || isStandaloneDisplay()) {
        return;
    }

    platform = isiOS ? 'ios' : 'android';

    if (isiOS) {
        descriptionText.value = 'Tap the Share icon and choose “Add to Home Screen”.';
        hintText.value = IOS_HINT;
        hintVisible.value = false;
        showBanner();
        return;
    }

    beforeInstallPromptHandler = (event: Event) => {
        event.preventDefault();
        deferredPrompt = event as BeforeInstallPromptEvent;
        descriptionText.value = DEFAULT_DESCRIPTION;
        showBanner();
    };

    window.addEventListener('beforeinstallprompt', beforeInstallPromptHandler);
});

onUnmounted(() => {
    if (beforeInstallPromptHandler && typeof window !== 'undefined') {
        window.removeEventListener('beforeinstallprompt', beforeInstallPromptHandler);
    }
});
</script>

<template>
    <Head title="MigraineAI">
        <link rel="icon" href="/logo-icon.png" />
        <link rel="apple-touch-icon" href="/logo-icon.png" />
        <link rel="manifest" href="/manifest.json" />
        <meta name="theme-color" content="#FDFDFC" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-title" content="MigraineAI" />
    </Head>

    <div
        id="home"
        class="relative min-h-screen overflow-hidden bg-[#070d0a] text-[--color-text-primary]"
    >
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                class="absolute -top-48 left-1/2 h-[32rem] w-[36rem] -translate-x-1/2 rounded-full bg-[radial-gradient(circle_at_center,rgba(62,212,120,0.32),transparent_65%)] blur-3xl"
            />
            <div
                class="absolute bottom-0 right-[-12rem] h-[28rem] w-[28rem] rounded-full bg-[radial-gradient(circle_at_center,rgba(40,95,71,0.45),transparent_60%)] blur-3xl"
            />
            <div
                class="absolute left-[-18rem] top-1/3 h-[26rem] w-[26rem] rounded-full bg-[radial-gradient(circle_at_center,rgba(24,54,40,0.65),transparent_60%)] blur-3xl"
            />
            <span class="absolute left-12 top-56 text-[22rem] font-black tracking-tight text-white/5">
                M
            </span>
            <span class="absolute right-8 bottom-48 text-[18rem] font-black tracking-tight text-white/5">
                A
            </span>
        </div>

        <div class="relative z-10">
            <header class="sticky top-0 z-20 bg-[#070d0a]/65 backdrop-blur-xl">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6 lg:px-0">
                    <div class="flex items-center gap-3">
                        <img
                            :src="logoUrl"
                            alt="MigraineAI logo"
                            class="h-11 w-11 rounded-2xl border border-[rgba(95,158,134,0.35)] bg-[rgba(8,18,14,0.65)] object-contain"
                        />
                        <div class="leading-tight">
                            <p class="text-sm uppercase tracking-[0.36em] text-[rgba(143,185,167,0.68)]">
                                Migraine AI
                            </p>
                            <p class="text-lg font-semibold">Unique Relief</p>
                        </div>
                    </div>
                    <nav class="hidden items-center gap-10 text-sm font-semibold md:flex">
                        <a
                            v-for="item in navigation"
                            :key="item.label"
                            :href="item.href"
                            class="tracking-[0.32em] text-[rgba(221,230,225,0.7)] transition hover:text-[--color-accent]"
                        >
                            {{ item.label }}
                        </a>
                    </nav>
                    <div class="flex items-center gap-3">
                        <Link
                            href="/login"
                            class="rounded-full border border-[rgba(95,158,134,0.35)] px-5 py-2.5 text-sm font-semibold text-[rgba(221,230,225,0.75)] transition hover:border-[rgba(95,158,134,0.6)] hover:text-[--color-accent]"
                        >
                            Login
                        </Link>
                        <Link
                            href="/register"
                            class="button-primary hidden text-sm md:inline-flex"
                        >
                            Join Now
                        </Link>
                    </div>
                </div>
            </header>

            <main>
                <section class="relative px-6 pb-20 pt-24 lg:px-0">
                    <div class="mx-auto grid max-w-6xl gap-16 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                        <div class="space-y-8">
                            <div class="space-y-4">
                                <p class="text-sm font-semibold tracking-[0.42em] text-[rgba(110,254,172,0.75)]">
                                    VOICE-DRIVEN CARE
                                </p>
                                <h1 class="text-4xl font-bold leading-snug md:text-5xl">
                                    The First Voice-Activated Intelligence for Migraine Care.
                                </h1>
                                <p class="max-w-xl text-base text-[rgba(221,230,225,0.76)] md:text-lg">
                                    Stop typing through the pain. MigraineAI turns your spoken symptoms
                                    into clinical-grade data, bridging the gap between your daily
                                    experience and your neurologist's diagnosis.
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-4">
                                <Link
                                    href="/register"
                                    class="button-primary"
                                >
                                    Start Voice Journal
                                </Link>
                                <a
                                    href="#stats"
                                    class="stats-action"
                                >
                                    See Clinical Reports
                                </a>
                            </div>
                        </div>
                        <div class="relative">
                            <div
                                class="relative mx-auto flex h-[460px] max-w-sm items-end justify-center overflow-hidden rounded-[40px] border border-[rgba(95,158,134,0.35)] bg-[rgba(9,20,16,0.88)] p-6 shadow-[0_32px_80px_rgba(0,0,0,0.6)]"
                            >
                                <div
                                    aria-hidden="true"
                                    class="absolute -inset-1 rounded-[48px] bg-[conic-gradient(from_120deg_at_30%_20%,rgba(62,212,120,0.4),rgba(6,17,13,0.2),rgba(62,212,120,0.6))] opacity-60 blur-2xl"
                                />
                                <div class="relative flex h-full w-full flex-col justify-between rounded-[32px] border border-[rgba(95,158,134,0.38)] bg-[rgba(11,24,18,0.82)] p-6 text-left">
                                    <div class="space-y-6 text-sm text-[rgba(221,230,225,0.85)]">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.42em] text-[rgba(143,185,167,0.65)]">
                                                Current Status
                                            </p>
                                            <p class="text-lg font-semibold text-[--color-accent]">
                                                {{ heroStatus.title }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.42em] text-[rgba(143,185,167,0.65)]">
                                                Insight
                                            </p>
                                            <p class="text-sm">
                                                "{{ heroStatus.insight }}"
                                            </p>
                                        </div>
                                        <div
                                            class="rounded-2xl border border-[rgba(95,158,134,0.35)] bg-[rgba(8,18,14,0.88)] px-4 py-3 text-xs font-semibold tracking-[0.32em] text-[rgba(221,230,225,0.8)]"
                                        >
                                            VOICE JOURNALING IN PROGRESS
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    id="services"
                    class="relative border-y border-[rgba(95,158,134,0.22)] bg-[rgba(7,15,11,0.72)] px-6 py-24 lg:px-0"
                >
                    <div class="mx-auto max-w-6xl space-y-12">
                        <div class="space-y-4 text-center">
                            <p class="text-sm font-semibold tracking-[0.42em] text-[rgba(110,254,172,0.75)]">
                                CLINICAL OUTCOMES
                            </p>
                            <h2 class="text-3xl font-bold md:text-4xl">
                                Precision Medicine, Powered by Your Voice.
                            </h2>
                            <p class="mx-auto max-w-3xl text-[rgba(221,230,225,0.78)]">
                                We replace subjective memory with objective data. Every module is built
                                to minimize screen time while maximizing clinical utility.
                            </p>
                        </div>
                        <div class="grid gap-6 lg:grid-cols-2">
                            <article
                                v-for="service in serviceHighlights"
                                :key="service.title"
                                class="group relative overflow-hidden rounded-3xl border border-[rgba(95,158,134,0.28)] bg-[rgba(10,22,17,0.88)] p-6 transition hover:border-[rgba(110,254,172,0.45)] hover:shadow-[0_30px_70px_rgba(62,212,120,0.25)]"
                            >
                                <div class="space-y-3">
                                    <h3 class="text-2xl font-semibold text-[--color-accent]">
                                        {{ service.title }}
                                    </h3>
                                    <p class="text-sm text-[rgba(221,230,225,0.75)]">
                                        {{ service.description }}
                                    </p>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

                <section
                    id="mission"
                    class="relative px-6 py-24 lg:px-0"
                >
                    <div class="mx-auto grid max-w-6xl gap-16 lg:grid-cols-[0.95fr_1.05fr] lg:items-center">
                        <div class="space-y-8 rounded-[36px] border border-[rgba(95,158,134,0.32)] bg-[rgba(9,20,16,0.9)] p-10 shadow-[0_28px_72px_rgba(0,0,0,0.6)]">
                            <span class="text-xs uppercase tracking-[0.42em] text-[rgba(143,185,167,0.75)]">
                                Clinically Validated
                            </span>
                            <h2 class="text-3xl font-bold text-[--color-accent] md:text-4xl">
                                Built with Neurologists. Designed for Patients.
                            </h2>
                            <p class="text-base text-[rgba(221,230,225,0.78)]">
                                MigraineAI began with a critical question: How do we make invisible pain
                                visible to doctors?
                            </p>
                            <p class="text-sm text-[rgba(221,230,225,0.78)]">
                                We are not just a tracker; we are a diagnostic companion. By combining
                                advanced AI with input from practicing neurologists, we translate the
                                chaos of chronic migraine into a clear, data-driven narrative. We empower
                                you to walk into your next appointment with facts, not just feelings.
                            </p>
                        </div>
                        <div class="space-y-6">
                            <p class="text-sm font-semibold tracking-[0.42em] text-[rgba(110,254,172,0.75)]">
                                MISSION
                            </p>
                            <h3 class="text-3xl font-bold md:text-4xl">
                                We illuminate each episode with the clarity your neurologist needs.
                            </h3>
                            <p class="text-[rgba(221,230,225,0.78)]">
                                From trigger detection to treatment adherence, every interaction with
                                MigraineAI is tuned to reduce screen fatigue and elevate clinical relevance.
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    id="stats"
                    class="border-y border-[rgba(95,158,134,0.25)] bg-[rgba(8,18,14,0.85)] px-6 py-20 lg:px-0"
                >
                    <div class="mx-auto flex max-w-6xl flex-col gap-6 md:flex-row">
                        <div class="text-sm uppercase tracking-[0.42em] text-[rgba(143,185,167,0.75)]">
                            Social Proof
                        </div>
                        <div class="grid flex-1 grid-cols-2 gap-6 md:grid-cols-4">
                            <article
                                v-for="stat in stats"
                                :key="stat.label"
                                class="rounded-3xl border border-[rgba(95,158,134,0.28)] bg-[rgba(10,22,17,0.85)] p-6 text-center shadow-[0_24px_60px_rgba(0,0,0,0.55)]"
                            >
                                <p class="text-3xl font-bold text-[--color-accent]">
                                    {{ stat.value }}
                                </p>
                                <p class="text-sm text-[rgba(221,230,225,0.7)]">
                                    {{ stat.label }}
                                </p>
                            </article>
                        </div>
                    </div>
                </section>

                <section
                    id="cta"
                    class="relative px-6 py-24 lg:px-0"
                >
                    <div class="mx-auto max-w-6xl overflow-hidden rounded-[36px] border border-[rgba(95,158,134,0.28)] bg-[rgba(8,18,14,0.88)] p-10 text-center shadow-[0_28px_72px_rgba(0,0,0,0.55)]">
                        <div class="space-y-6">
                            <p class="text-xs uppercase tracking-[0.42em] text-[rgba(110,254,172,0.72)]">
                                READY FOR CLARITY?
                            </p>
                            <h2 class="text-3xl font-bold md:text-4xl">
                                Your data is your best medicine. Let\'s capture it.
                            </h2>
                            <p class="mx-auto max-w-3xl text-[rgba(221,230,225,0.78)]">
                                Join the beta users who are turning their migraine history into a
                                roadmap for relief.
                            </p>
                            <div class="flex flex-wrap justify-center gap-4">
                                <Link
                                    href="/register"
                                    class="button-primary"
                                >
                                    Download Beta (iOS &amp; Android)
                                </Link>
                                <Link
                                    href="/login"
                                    class="stats-action"
                                >
                                    Already a Member? Log In
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="border-t border-[rgba(95,158,134,0.22)] bg-[rgba(7,15,11,0.82)]">
                <div class="mx-auto max-w-6xl px-6 py-12 md:grid md:grid-cols-[1.2fr_1fr_1fr_1fr] md:gap-8 lg:px-0">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <img
                                :src="logoUrl"
                                alt="MigraineAI logo"
                                class="h-10 w-10 rounded-2xl border border-[rgba(95,158,134,0.35)] bg-[rgba(8,18,14,0.65)] object-contain"
                            />
                            <div>
                                <p class="font-semibold text-white">MigraineAI</p>
                                <p class="text-xs text-[rgba(221,230,225,0.65)]">
                                    Diagnostic clarity for every migraine journey.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <a
                                aria-label="Facebook"
                                href="#"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[rgba(95,158,134,0.3)] text-[rgba(221,230,225,0.8)] transition hover:border-[--color-accent] hover:text-[--color-accent]"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor">
                                    <path d="M22 12.07C22 6.48 17.52 2 11.93 2 6.48 2 2 6.48 2 12.07c0 5.03 3.68 9.2 8.5 9.93v-7.03H8.08v-2.9h2.42V9.87c0-2.4 1.43-3.73 3.62-3.73 1.05 0 2.15.19 2.15.19v2.37h-1.21c-1.19 0-1.56.74-1.56 1.5v1.8h2.65l-.42 2.9h-2.23V22c4.82-.73 8.5-4.9 8.5-9.93Z"/>
                                </svg>
                            </a>
                            <a
                                aria-label="X"
                                href="#"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[rgba(95,158,134,0.3)] text-[rgba(221,230,225,0.8)] transition hover:border-[--color-accent] hover:text-[--color-accent]"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor">
                                    <path d="M22 5.924c-.75.332-1.556.556-2.402.657a4.177 4.177 0 0 0 1.837-2.305 8.312 8.312 0 0 1-2.637 1.016 4.156 4.156 0 0 0-7.315 2.839c0 .326.037.643.107.947A11.8 11.8 0 0 1 3.064 4.53a4.152 4.152 0 0 0 1.285 5.54 4.123 4.123 0 0 1-1.884-.52v.052a4.16 4.16 0 0 0 3.332 4.078 4.196 4.196 0 0 1-1.877.072 4.167 4.167 0 0 0 3.887 2.89A8.336 8.336 0 0 1 2 19.533a11.76 11.76 0 0 0 6.373 1.87c7.646 0 11.827-6.338 11.827-11.827 0-.18-.004-.36-.012-.538A8.457 8.457 0 0 0 22 5.924Z"/>
                                </svg>
                            </a>
                            <a
                                aria-label="YouTube"
                                href="#"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[rgba(95,158,134,0.3)] text-[rgba(221,230,225,0.8)] transition hover:border-[--color-accent] hover:text-[--color-accent]"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor">
                                    <path d="M10 15l5.19-3L10 9v6Zm10.6-3.45c0-.95-.07-1.42-.14-1.88a4.66 4.66 0 0 0-.93-1.8 4.7 4.7 0 0 0-1.8-.94c-.52-.15-1.01-.25-2.05-.33-1.62-.2-6.21-.2-7.83 0-1.05.08-1.54.18-2.06.33a4.7 4.7 0 0 0-1.8.94 4.66 4.66 0 0 0-.93 1.8c-.07.46-.14.93-.14 1.88s.07 1.42.14 1.88c.15.88.45 1.4.93 1.8.52.25 1 .45 2.06.55 1.63.2 6.21.2 7.83 0 1.05-.1 1.54-.3 2.05-.55a4.7 4.7 0 0 0 1.8-.94 4.66 4.66 0 0 0 .93-1.8c.07-.46.14-.93.14-1.88Z"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.42em] text-[rgba(143,185,167,0.75)]">
                            Company
                        </p>
                        <ul class="mt-4 space-y-2 text-sm text-[rgba(221,230,225,0.75)]">
                            <li v-for="link in footerCompany" :key="link.label">
                                <Link :href="link.href" class="hover:text-[--color-accent]">
                                    {{ link.label }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.42em] text-[rgba(143,185,167,0.75)]">
                            Legal
                        </p>
                        <ul class="mt-4 space-y-2 text-sm text-[rgba(221,230,225,0.75)]">
                            <li v-for="link in footerLegal" :key="link.label">
                                <Link :href="link.href" class="hover:text-[--color-accent]">
                                    {{ link.label }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.42em] text-[rgba(143,185,167,0.75)]">
                            Contact
                        </p>
                        <ul class="mt-4 space-y-2 text-sm text-[rgba(221,230,225,0.75)]">
                            <li v-for="link in footerContact" :key="link.label">
                                <Link :href="link.href" class="hover:text-[--color-accent]">
                                    {{ link.label }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </footer>
            <div
                id="add-to-home-banner"
                v-show="bannerVisible"
                class="pointer-events-auto fixed inset-x-4 bottom-6 z-50 mx-auto max-w-lg rounded-xl border border-[#e3e3e0] bg-white p-0 shadow-xl transition-all duration-150 dark:border-[#3E3E3A] dark:bg-[#161615]"
            >
                <div class="flex items-start gap-3 px-4 py-4">
                    <img
                        :src="logoUrl"
                        alt="MigraineAI icon"
                        class="h-10 w-10 flex-shrink-0 rounded-2xl object-cover"
                    />
                    <div class="flex-1 text-left">
                        <p class="text-sm font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">
                            Add MigraineAI to your home screen
                        </p>
                        <p class="mt-1 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                            {{ descriptionText }}
                        </p>
                        <p
                            v-if="hintVisible"
                            class="mt-1 text-[11px] text-[#706f6c] dark:text-[#A1A09A]"
                        >
                            {{ hintText }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 border-t border-[#e3e3e0] px-4 py-3 text-xs dark:border-[#62605b]">
                    <button
                        type="button"
                        @click="handleAddToHomeClick"
                        class="flex-1 rounded-lg border border-[#1b1b18] bg-[#1b1b18] px-3 py-2 text-center text-sm font-semibold text-white transition hover:bg-black dark:border-[#EDEDEC] dark:bg-[#EDEDEC] dark:text-[#1b1b18]"
                    >
                        Add to Home
                    </button>
                    <button
                        type="button"
                        @click="markBannerDismissed"
                        class="rounded-lg px-3 py-2 text-center font-semibold text-[#706f6c] transition hover:text-black dark:hover:text-white"
                    >
                        Dismiss
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
