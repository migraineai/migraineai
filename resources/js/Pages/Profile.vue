<script lang="ts" setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { logoUrl } from '@/Utils/logo';

type SharedProps = {
    auth: {
        user: {
            name: string;
            email?: string | null;
            age?: number | null;
            age_range?: string | null;
            gender?: string | null;
            time_zone?: string | null;
            cycle_tracking_enabled?: boolean;
            cycle_length_days?: number | null;
            period_length_days?: number | null;
            last_period_start_date?: string | null;
        } | null;
    };
    impersonation?: {
        active: boolean;
        admin_name?: string | null;
    };
};

type ProfileOverview = {
    total_episodes: number;
    average_intensity: number | null;
    total_duration_minutes: number;
    pain_free_days: number;
    total_days: number;
    pain_free_percentage: number | null;
    top_trigger: string | null;
    common_location: string | null;
};

type ProfilePageProps = SharedProps & {
    overview: ProfileOverview;
};

const page = usePage<ProfilePageProps>();

const userName = computed(() => page.props.auth.user?.name ?? 'Friend');
const userEmail = computed(() => page.props.auth.user?.email ?? 'you@example.com');
const userInitial = computed(() => userName.value.trim().charAt(0).toUpperCase() || 'F');
const impersonation = computed(() => page.props.impersonation ?? { active: false, admin_name: null });
const cycleFeatureEnabled = computed(() => Boolean(page.props.auth.user?.cycle_tracking_enabled));
const overviewData = computed(() => page.props.overview);

const sidebarOpen = ref(false);

function logout() {
    router.visit('/logout', {
        method: 'post',
        preserveState: false,
        onSuccess: () => {
            window.location.href = '/login';
        },
        onError: () => {
            window.location.href = '/login';
        },
    });
}

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
}

function stopImpersonation() {
    router.post('/super-admin/stop-impersonating');
}

const navigationLinks = computed(() => {
    const url = page.url;

    return [
        { label: 'Home', icon: 'home', href: '/dashboard', active: url.startsWith('/dashboard') },
        ...(cycleFeatureEnabled.value ?
            [{ label: 'Period Tracking', icon: 'calendar', href: '/period-tracking', active: url.startsWith('/period-tracking') }] :
            []),
        { label: 'Analytics', icon: 'chart', href: '/analytics', active: url.startsWith('/analytics') },
        { label: 'Settings', icon: 'settings', href: '/settings', active: url.startsWith('/settings') },
        { label: 'Profile', icon: 'user', href: '/profile', active: url.startsWith('/profile') },
    ] as const;
});

const userProfile = computed(() => page.props.auth.user);

const profileHighlights = computed(() => {
    const user = userProfile.value;
    return [
        { label: 'Age', value: user?.age_range ?? user?.age?.toString() ?? '—', icon: 'age' },
        { label: 'Gender', value: formatGender(user?.gender), icon: 'gender' },
        { label: 'Episodes / Month', value: '4', icon: 'episodes' },
        { label: 'Typical Intensity', value: '7/10', icon: 'intensity', accent: true },
    ];
});

function formatDurationMinutes(minutes: number): string {
    if (!minutes || minutes <= 0) {
        return '0h';
    }

    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    if (hours && remainingMinutes) {
        return `${hours}h ${remainingMinutes}m`;
    }

    if (hours) {
        return `${hours}h`;
    }

    return `${remainingMinutes}m`;
}

const overviewStats = computed(() => {
    const overview = overviewData.value;

    if (!overview) {
        return [
            { label: 'Episodes', value: '0' },
            { label: 'Avg Intensity', value: '—' },
            { label: 'Total Duration', value: '0h' },
            { label: 'Pain-Free Days', value: '—' },
        ];
    }

    return [
        { label: 'Episodes', value: overview.total_episodes.toString() },
        {
            label: 'Avg Intensity',
            value: overview.average_intensity !== null ? `${overview.average_intensity}/10` : '—',
        },
        { label: 'Total Duration', value: formatDurationMinutes(overview.total_duration_minutes) },
        {
            label: 'Pain-Free Days',
            value:
                overview.pain_free_percentage !== null ? `${overview.pain_free_percentage}%` : '—',
        },
    ];
});

const topTriggerLabel = computed(() => overviewData.value?.top_trigger ?? 'N/A');
const commonLocationLabel = computed(() => overviewData.value?.common_location ?? 'N/A');

const painQualities = ['throbbing', 'pulsating'] as const;

function formatGender(gender: string | null | undefined): string {
    switch (gender) {
        case 'female':
            return 'Female';
        case 'male':
            return 'Male';
        case 'non_binary':
            return 'Non-binary';
        case 'prefer_not_to_say':
            return 'Prefer not to say';
        default:
            return '—';
    }
}
</script>

<template>
    <Head title="Profile" />

    <div class="dashboard-layout">
        <aside
            :class="['dashboard-sidebar', sidebarOpen ? 'dashboard-sidebar--open' : 'dashboard-sidebar--collapsed']"
        >
            <div class="sidebar-brand">
                <button class="sidebar-logo" type="button">
                    <img :src="logoUrl" alt="MigraineAI logo" class="sidebar-logo-image" />
                </button>
                <div v-if="sidebarOpen" class="sidebar-brand-text">
                    <p class="sidebar-brand-name">Migraine<span>AI</span></p>
                    <p class="sidebar-brand-sub">Navigation</p>
                </div>
            </div>

            <nav class="sidebar-nav">
                <component
                    v-for="item in navigationLinks"
                    :key="item.label"
                    :is="item.href ? Link : 'button'"
                    v-bind="item.href ? { href: item.href, preserveScroll: true } : { type: 'button' }"
                    class="sidebar-link"
                    :class="item.active ? 'sidebar-link--active' : ''"
                >
                    <span class="sidebar-link-icon">
                        <svg
                            v-if="item.icon === 'home'"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            class="size-5"
                            fill="none"
                        >
                            <path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-5h-4v5H5a1 1 0 0 1-1-1v-9.5Z" fill="currentColor" />
                        </svg>
                        <svg
                            v-else-if="item.icon === 'calendar'"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            class="size-5"
                            fill="none"
                        >
                            <rect
                                x="3"
                                y="4"
                                width="18"
                                height="17"
                                rx="2"
                                stroke="currentColor"
                                stroke-width="1.5"
                            />
                            <path
                                d="M16 2v4M8 2v4M3 10h18"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            />
                        </svg>
                        <svg
                            v-else-if="item.icon === 'chart'"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            class="size-5"
                            fill="none"
                        >
                            <path d="M4 20V4m0 16h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            <path
                                d="m7 12 3 3 4-5 3 3"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                        <svg
                            v-else-if="item.icon === 'settings'"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            class="size-5"
                            fill="none"
                        >
                            <path
                                d="M10.33 3.75a2 2 0 0 1 3.34 0l.35.58a2 2 0 0 0 1.54.94l.66.06a2 2 0 0 1 1.76 1.76l.06.66a2 2 0 0 0 .94 1.54l.59.35a2 2 0 0 1 0 3.34l-.59.35a2 2 0 0 0-.94 1.54l-.06.66a2 2 0 0 1-1.76 1.76l-.66.06a2 2 0 0 0-1.54.94l-.35.59a2 2 0 0 1-3.34 0l-.35-.59a2 2 0 0 0-1.54-.94l-.66-.06a2 2 0 0 1-1.76-1.76l-.06-.66a2 2 0 0 0-.94-1.54l-.59-.35a2 2 0 0 1 0-3.34l.59-.35a2 2 0 0 0 .94-1.54l.06-.66a2 2 0 0 1 1.76-1.76l.66-.06a2 2 0 0 0 1.54-.94l.35-.58Z"
                                stroke="currentColor"
                                stroke-width="1.5"
                            />
                            <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5" />
                        </svg>
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            class="size-5"
                            fill="none"
                        >
                            <path
                                d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-3.33 0-6 2-6 4.5a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5C18 16 15.33 14 12 14Z"
                                fill="currentColor"
                            />
                        </svg>
                    </span>
                    <span v-if="sidebarOpen" class="sidebar-link-label">{{ item.label }}</span>
                </component>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <span class="sidebar-user-avatar">
                        <span>
                            {{ userInitial }}
                        </span>
                    </span>
                    <div v-if="sidebarOpen" class="sidebar-user-meta">
                        <p class="sidebar-user-name">{{ userName }}</p>
                        <p class="sidebar-user-email">{{ userEmail }}</p>
                    </div>
                </div>

                <button
                    type="button"
                    class="sidebar-logout"
                    @click="logout"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                        <path d="M15 12H4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        <path d="m8 8-4 4 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M15 5h2a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3h-2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                    <span v-if="sidebarOpen">Logout</span>
                </button>
            </div>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-toolbar">
                <button class="toolbar-toggle" type="button" @click="toggleSidebar">
                    <svg v-if="sidebarOpen" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                        <path d="M8 6h8M8 12h8M8 18h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                        <path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <img :src="logoUrl" alt="MigraineAI logo" class="toolbar-logo" />

                <div class="toolbar-breadcrumb">
                    <Link href="/dashboard" class="breadcrumb-link">Dashboard</Link>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-current">Profile</span>
                </div>

                <div class="toolbar-actions">
                    <button
                        v-if="impersonation.active"
                        type="button"
                        class="impersonation-return"
                        @click="stopImpersonation"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
                            <path d="M7 7h10v6h4l-9 8-9-8h4V7Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                        </svg>
                        <span>Back to Super Admin</span>
                    </button>
                </div>
            </header>

            <div class="pointer-events-none absolute inset-0 -z-10">
                <div class="absolute left-1/3 top-12 h-80 w-80 rounded-full bg-[--color-accent]/15 blur-[130px]"></div>
                <div class="absolute bottom-10 right-0 h-72 w-72 rounded-full bg-[--color-accent]/12 blur-[120px]"></div>
            </div>

            <div class="mx-auto flex w-full max-w-6xl flex-col gap-8">
                <section class="profile-grid">
                    <article class="profile-card profile-card--primary">
                        <header class="profile-card-header">
                            <div class="profile-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path
                                        d="M5 20v-1a7 7 0 0 1 7-7 7 7 0 0 1 7 7v1"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                    />
                                    <circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.5" />
                                </svg>
                            </div>
                            <h1>Medical Profile</h1>
                            <Link href="/settings" class="profile-edit-button">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                    <path
                                        d="M15.25 5.25 18.75 8.75M13.5 7l-7 7v2.5h2.5l7-7-2.5-2.5Z"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                                Edit
                            </Link>
                        </header>

                        <div class="profile-highlight-grid">
                            <div v-for="item in profileHighlights" :key="item.label" class="profile-highlight">
                                <span class="profile-highlight-label">{{ item.label }}</span>
                                <span :class="['profile-highlight-value', item.accent ? 'profile-highlight-value--accent' : '']">
                                    {{ item.value }}
                                </span>
                            </div>
                        </div>

                        <div class="profile-section">
                            <div class="profile-section-header">
                                <div class="profile-section-icon profile-section-icon--spark"></div>
                                <span>Pain Characteristics</span>
                            </div>
                            <div class="profile-section-body">
                                <div class="profile-section-row">
                                    <span class="profile-section-label">Location</span>
                                    <span class="profile-chip">unilateral</span>
                                </div>
                                <div class="profile-section-row">
                                    <span class="profile-section-label">Quality</span>
                                    <div class="profile-chip-row">
                                        <span v-for="quality in painQualities" :key="quality" class="profile-chip profile-chip--soft">
                                            {{ quality }}
                                        </span>
                                    </div>
                                </div>
                                <div class="profile-section-row">
                                    <span class="profile-section-label">Duration</span>
                                    <span class="profile-section-value">6 hours typical</span>
                                </div>
                            </div>
                        </div>

                        <div class="profile-section">
                            <div class="profile-section-header">
                                <div class="profile-section-icon profile-section-icon--heart"></div>
                                <span>Medical History</span>
                            </div>
                            <div class="profile-section-body">
                                <div class="profile-section-row">
                                    <span class="profile-section-label">Aura symptoms</span>
                                    <span class="profile-chip profile-chip--outline">Managed</span>
                                </div>
                                <div class="profile-section-row">
                                    <span class="profile-section-label">Onset age</span>
                                    <span class="profile-section-value">20 years</span>
                                </div>
                            </div>
                        </div>

                        <div class="profile-section">
                            <div class="profile-section-header">
                                <div class="profile-section-icon profile-section-icon--spark"></div>
                                <span>Cycle Tracking</span>
                            </div>
                            <div class="profile-section-body">
                                <div class="profile-section-row">
                                    <span class="profile-section-label">Status</span>
                                    <span class="profile-chip">{{ userProfile?.cycle_tracking_enabled ? 'Enabled' : 'Disabled' }}</span>
                                </div>
                                <div v-if="userProfile?.cycle_tracking_enabled" class="profile-section-row">
                                    <span class="profile-section-label">Cycle length</span>
                                    <span class="profile-section-value">{{ userProfile?.cycle_length_days ?? 28 }} days</span>
                                </div>
                                <div v-if="userProfile?.cycle_tracking_enabled" class="profile-section-row">
                                    <span class="profile-section-label">Period length</span>
                                    <span class="profile-section-value">{{ userProfile?.period_length_days ?? 5 }} days</span>
                                </div>
                                <div v-if="userProfile?.cycle_tracking_enabled && userProfile?.last_period_start_date" class="profile-section-row">
                                    <span class="profile-section-label">Last period start</span>
                                    <span class="profile-section-value">{{ userProfile?.last_period_start_date }}</span>
                                </div>
                                <div class="profile-section-row">
                                    <span class="profile-section-label">Time zone</span>
                                    <span class="profile-section-value">{{ userProfile?.time_zone ?? '—' }}</span>
                                </div>
                            </div>
                        </div>

                        <footer class="profile-card-footer">
                            Profile completed: <span>26/10/2025</span>
                        </footer>
                    </article>

                    <article class="profile-card">
                        <header class="profile-card-header">
                            <div class="profile-card-icon profile-card-icon--chart">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path
                                        d="M4 19.5V5m0 14.5h16M8 16l3-4 2 2 4-5"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </div>
                            <h2>30-Day Overview</h2>
                        </header>

                        <div class="profile-overview-grid">
                            <div v-for="item in overviewStats" :key="item.label" class="profile-overview-card">
                                <span class="profile-overview-label">{{ item.label }}</span>
                                <span class="profile-overview-value">{{ item.value }}</span>
                            </div>
                        </div>

                        <div class="profile-overview-meta">
                            <div>
                                <span>Top Trigger</span>
                                <span class="profile-pill">{{ topTriggerLabel }}</span>
                            </div>
                            <div>
                                <span>Common Location</span>
                                <span class="profile-pill">{{ commonLocationLabel }}</span>
                            </div>
                        </div>
                    </article>
                </section>
            </div>
        </main>
    </div>
</template>
