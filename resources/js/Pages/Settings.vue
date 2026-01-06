<script lang="ts" setup>
import axios from 'axios';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch, onBeforeUnmount, onMounted } from 'vue';
import { useShepherd } from 'vue-shepherd';
import OnboardingQuestions from '@/Components/OnboardingQuestions.vue';
import onboardingData from '../../../ref/onboarding.json';
import { countOnboardingAnswers, mergeOnboardingAnswers, OnboardingSection } from '@/Utils/onboarding';
import { logoUrl } from '@/Utils/logo';

type ExportItem = {
    id: number;
    status: string;
    size_bytes: number | null;
    created_at: string | null;
    expires_at: string | null;
    error_message: string | null;
    download_url: string | null;
};

type DeletionRequest = {
    id: number;
    status: string;
    scheduled_for: string | null;
    processed_at: string | null;
    error_message: string | null;
    created_at: string | null;
};

type SettingsProps = {
    auth: {
        user: {
            name: string;
            email?: string | null;
            cycle_tracking_enabled?: boolean;
        } | null;
    };
    impersonation?: {
        active: boolean;
        admin_name?: string | null;
    };
    tourStatus?: Record<string, boolean>;
    profile: {
        name: string;
        email: string;
        age_range: string | null;
        gender: string | null;
        time_zone: string | null;
        onboarding_answers: Record<string, unknown> | null;
        cycle_tracking_enabled: boolean;
        cycle_length_days: number | null;
        period_length_days: number | null;
        last_period_start_date: string | null;
        daily_reminder_enabled: boolean;
        daily_reminder_hour: number | null;
        post_attack_follow_up_hours: number | null;
    };
    options: {
        age_ranges: string[];
        genders: Array<{ label: string; value: string }>;
        time_zones: string[];
        reminder_hours: number[];
    };
    exports: ExportItem[];
    deletion_request: DeletionRequest | null;
};

const page = usePage<SettingsProps>();
const userName = computed(() => page.props.auth.user?.name ?? 'Friend');
const userEmail = computed(() => page.props.auth.user?.email ?? 'you@example.com');
const userInitial = computed(() => userName.value.trim().charAt(0).toUpperCase() || 'F');
const impersonation = computed(() => page.props.impersonation ?? { active: false, admin_name: null });
const cycleFeatureEnabled = computed(() => Boolean(page.props.auth.user?.cycle_tracking_enabled));

type SettingsTab = 'profile' | 'onboarding' | 'settings';

const sidebarOpen = ref(false);
const activeSettingsTab = ref<SettingsTab>('profile');
const profileSectionRef = ref<HTMLElement | null>(null);
const onboardingSectionRef = ref<HTMLElement | null>(null);
const settingsSectionRef = ref<HTMLElement | null>(null);

const settingsTour = useShepherd({
    useModalOverlay: true,
    defaultStepOptions: {
        cancelIcon: { enabled: true },
        scrollTo: { behavior: 'smooth', block: 'center' },
    },
});

function buildSettingsTour() {
    settingsTour.steps = [];
    if (profileSectionRef.value) {
        settingsTour.addStep({
            id: 'settings-profile',
            attachTo: { element: profileSectionRef.value, on: 'bottom' },
            title: 'Profile Settings',
            text: 'Update demographics and cycle settings to personalize analytics.',
            buttons: [
                { text: 'Skip', classes: 'shepherd-button-secondary', action: settingsTour.cancel },
                { text: 'Next', classes: 'shepherd-button-primary', action: settingsTour.next },
            ],
        });
    }
    if (onboardingSectionRef.value) {
        settingsTour.addStep({
            id: 'settings-onboarding',
            attachTo: { element: onboardingSectionRef.value, on: 'top' },
            title: 'Onboarding Questions',
            text: 'Answer optional questions for richer, clinician-grade insights.',
            buttons: [
                { text: 'Back', classes: 'shepherd-button-secondary', action: settingsTour.back },
                { text: 'Next', classes: 'shepherd-button-primary', action: settingsTour.next },
            ],
        });
    }
    if (settingsSectionRef.value) {
        settingsTour.addStep({
            id: 'settings-notifications',
            attachTo: { element: settingsSectionRef.value, on: 'top' },
            title: 'Notifications & Exports',
            text: 'Configure reminders, request data exports, or delete your account.',
            buttons: [
                { text: 'Back', classes: 'shepherd-button-secondary', action: settingsTour.back },
                { text: 'Close', classes: 'shepherd-button-primary', action: settingsTour.cancel },
            ],
        });
    }
}

async function markTourSeen(pageKey: string) {
    try {
        await axios.post('/user/tour-status', { page: pageKey, seen: true });
    } catch {}
}

function startSettingsTour() {
    buildSettingsTour();
    settingsTour.start();
}

settingsTour.on('cancel', () => markTourSeen('settings'));
settingsTour.on('complete', () => markTourSeen('settings'));

onMounted(() => {
    const seen = Boolean(page.props.tourStatus?.settings);
    if (!seen) {
        startSettingsTour();
    }
});

const profileForm = reactive({
    name: page.props.profile.name,
    age_range: page.props.profile.age_range ?? '',
    gender: page.props.profile.gender ?? '',
    time_zone: page.props.profile.time_zone ?? '',
    cycle_tracking_enabled: page.props.profile.cycle_tracking_enabled,
    cycle_length_days: page.props.profile.cycle_length_days ?? 28,
    period_length_days: page.props.profile.period_length_days ?? 5,
    last_period_start_date: page.props.profile.last_period_start_date ?? '',
    daily_reminder_enabled: page.props.profile.daily_reminder_enabled,
    daily_reminder_hour: page.props.profile.daily_reminder_hour ?? 9,
    post_attack_follow_up_hours: page.props.profile.post_attack_follow_up_hours ?? 12,
});

const onboardingDefinition = onboardingData.onboarding_flow;
const onboardingSections = onboardingDefinition.sections as OnboardingSection[];
const onboardingTotalQuestions =
    typeof onboardingDefinition.meta?.total_questions === 'number'
        ? onboardingDefinition.meta.total_questions
        : onboardingSections.reduce((total, section) => total + section.questions.length, 0);

const onboardingAnswers = reactive(
    mergeOnboardingAnswers(onboardingSections, page.props.profile.onboarding_answers ?? {})
);

const onboardingAnsweredCount = computed(() => countOnboardingAnswers(onboardingSections, onboardingAnswers));
const primaryOnboardingSection = computed(() => onboardingSections[0] ?? null);
const primaryOnboardingSectionTitle = computed(() => primaryOnboardingSection.value?.title ?? 'The Basics');
const primaryOnboardingSectionQuestionCount = computed(
    () => primaryOnboardingSection.value?.questions.length ?? 0
);

function handleOnboardingAnswer(questionId: string, answer: unknown) {
    onboardingAnswers[questionId] = answer;
}

const savingProfile = ref(false);
const profileMessage = ref<string | null>(null);
const profileError = ref<string | null>(null);
const savingReminders = ref(false);
const reminderMessage = ref<string | null>(null);
const reminderError = ref<string | null>(null);

const exportState = ref<'idle' | 'working'>('idle');
const deleteState = ref<'idle' | 'working'>('idle');
const exportMessage = ref<string | null>(null);
const exportError = ref<string | null>(null);
const deleteMessage = ref<string | null>(null);
const deleteError = ref<string | null>(null);

const exportItems = ref<ExportItem[]>([...page.props.exports]);
const activeDeletion = ref<DeletionRequest | null>(page.props.deletion_request);
const exportsRefreshing = ref(false);
let exportRefreshTimer: ReturnType<typeof setTimeout> | null = null;

const hasActiveExport = computed(() =>
    exportItems.value.some((item) => item.status === 'pending' || item.status === 'in_progress')
);

const hasActiveDeletion = computed(() => {
    const status = activeDeletion.value?.status;
    return status === 'pending' || status === 'scheduled' || status === 'processing';
});

const notifyStates = reactive<Record<string, boolean>>({
    predictions: true,
    cycle: true,
    medication: true,
    digest: true,
});

const sectionRefs: Record<SettingsTab, typeof profileSectionRef> = {
    profile: profileSectionRef,
    onboarding: onboardingSectionRef,
    settings: settingsSectionRef,
};

function scrollToSettingsSection(tab: SettingsTab) {
    const target = sectionRefs[tab].value;

    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function setActiveSettingsTab(tab: SettingsTab) {
    activeSettingsTab.value = tab;
    scrollToSettingsSection(tab);
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

const isFemale = computed(() => profileForm.gender === 'female');
const cycleEnabled = computed(() => isFemale.value && profileForm.cycle_tracking_enabled);
const reminderHours = computed(() => page.props.options.reminder_hours ?? []);

watch(
    () => profileForm.gender,
    (gender) => {
        if (gender !== 'female') {
            profileForm.cycle_tracking_enabled = false;
        }
    }
);

watch(
    () => profileForm.cycle_tracking_enabled,
    (enabled) => {
        if (!enabled) {
            profileForm.cycle_length_days = 28;
            profileForm.period_length_days = 5;
            profileForm.last_period_start_date = '';
        }
    }
);

function upsertExport(item: ExportItem) {
    const index = exportItems.value.findIndex((existing) => existing.id === item.id);
    if (index !== -1) {
        exportItems.value[index] = item;
    } else {
        exportItems.value.unshift(item);
    }
}

function formatExportStatus(status: string): string {
    switch (status) {
        case 'pending':
            return 'Queued';
        case 'in_progress':
            return 'Preparing';
        case 'ready':
            return 'Ready';
        case 'failed':
            return 'Failed';
        default:
            return status;
    }
}

function formatBytes(size: number | null): string {
    if (!size || size <= 0) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let value = size;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return `${value.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function deletionStatusLabel(request: DeletionRequest | null): string {
    if (!request) {
        return 'No active request';
    }

    switch (request.status) {
        case 'scheduled':
            return `Scheduled for ${formatDateTime(request.scheduled_for)}`;
        case 'pending':
            return 'Awaiting confirmation';
        case 'processing':
            return 'Processing deletion';
        case 'completed':
            return `Deleted on ${formatDateTime(request.processed_at)}`;
        case 'failed':
            return 'Deletion failed';
        case 'cancelled':
            return 'Cancelled';
        default:
            return request.status;
    }
}

function formatHourLabel(hour: number): string {
    const base = new Date();
    base.setHours(hour, 0, 0, 0);

    return base.toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    });
}

function clearExportRefresh() {
    if (exportRefreshTimer) {
        clearTimeout(exportRefreshTimer);
        exportRefreshTimer = null;
    }
}

function scheduleExportRefresh() {
    clearExportRefresh();

    exportRefreshTimer = setTimeout(async () => {
        await refreshExports(true);
        if (hasActiveExport.value) {
            scheduleExportRefresh();
        }
    }, 6000);
}

async function refreshExports(silent = false) {
    if (exportsRefreshing.value) {
        return;
    }

    exportsRefreshing.value = true;
    if (!silent) {
        exportError.value = null;
    }

    try {
        const { data } = await axios.get('/settings/exports');
        if (Array.isArray(data.exports)) {
            exportItems.value = data.exports as ExportItem[];
        }
    } catch (error) {
        if (!silent) {
            exportError.value =
                axios.isAxiosError(error) && error.response?.data?.message ?
                    error.response.data.message :
                    'Unable to refresh exports right now.';
        }
    } finally {
        exportsRefreshing.value = false;
    }
}

watch(
    hasActiveExport,
    (active) => {
        if (active) {
            scheduleExportRefresh();
        } else {
            clearExportRefresh();
        }
    },
    { immediate: true }
);

async function logout() {
    try {
        await axios.get('/sanctum/csrf-cookie');
    } catch {}
    try {
        await axios.post('/logout');
    } finally {
        router.visit('/login', { replace: true });
    }
}

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
}

function stopImpersonation() {
    router.post('/super-admin/stop-impersonating');
}

function dismissProfileMessage() {
    profileMessage.value = null;
    profileError.value = null;
}

function dismissReminderMessage() {
    reminderMessage.value = null;
    reminderError.value = null;
}

function normalizeFollowUpHours(): number {
    const followUpHours = Math.min(Math.max(profileForm.post_attack_follow_up_hours ?? 12, 1), 48);
    profileForm.post_attack_follow_up_hours = followUpHours;
    return followUpHours;
}

function normalizeReminderHour(): number {
    const reminderHour = Math.min(Math.max(profileForm.daily_reminder_hour ?? 9, 0), 23);
    profileForm.daily_reminder_hour = reminderHour;
    return reminderHour;
}

async function saveProfile() {
    dismissProfileMessage();
    savingProfile.value = true;

    const followUpHours = normalizeFollowUpHours();
    const reminderHour = normalizeReminderHour();

    try {
        const { data } = await axios.put('/settings/profile', {
            name: profileForm.name,
            age_range: profileForm.age_range || null,
            gender: profileForm.gender || null,
            time_zone: profileForm.time_zone || null,
            cycle_tracking_enabled: cycleEnabled.value,
            cycle_length_days: cycleEnabled.value ? profileForm.cycle_length_days : null,
            period_length_days: cycleEnabled.value ? profileForm.period_length_days : null,
            last_period_start_date:
                cycleEnabled.value && profileForm.last_period_start_date ? profileForm.last_period_start_date : null,
            daily_reminder_enabled: profileForm.daily_reminder_enabled,
            daily_reminder_hour: reminderHour,
            post_attack_follow_up_hours: followUpHours,
            onboarding_answers: onboardingAnswers,
        });

        profileMessage.value = data.message ?? 'Profile updated successfully.';
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            profileError.value = error.response.data.message;
        } else {
            profileError.value = 'We could not update your profile right now. Please try again.';
        }
    } finally {
        savingProfile.value = false;
    }
}

async function saveReminderSettings() {
    dismissReminderMessage();
    savingReminders.value = true;

    const followUpHours = normalizeFollowUpHours();
    const reminderHour = normalizeReminderHour();

    try {
        const { data } = await axios.put('/settings/profile', {
            name: profileForm.name,
            age_range: profileForm.age_range || null,
            gender: profileForm.gender || null,
            time_zone: profileForm.time_zone || null,
            cycle_tracking_enabled: cycleEnabled.value,
            cycle_length_days: cycleEnabled.value ? profileForm.cycle_length_days : null,
            period_length_days: cycleEnabled.value ? profileForm.period_length_days : null,
            last_period_start_date:
                cycleEnabled.value && profileForm.last_period_start_date ? profileForm.last_period_start_date : null,
            daily_reminder_enabled: profileForm.daily_reminder_enabled,
            daily_reminder_hour: reminderHour,
            post_attack_follow_up_hours: followUpHours,
            onboarding_answers: onboardingAnswers,
        });

        reminderMessage.value = data.message ?? 'Reminder preferences saved.';
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            reminderError.value = error.response.data.message;
        } else {
            reminderError.value = 'We could not update your reminders right now. Please try again.';
        }
    } finally {
        savingReminders.value = false;
    }
}

async function requestExport() {
    if (hasActiveExport.value || exportState.value === 'working') {
        return;
    }

    exportState.value = 'working';
    exportMessage.value = null;
    exportError.value = null;

    try {
        const { data } = await axios.post('/settings/request-export');
        exportMessage.value = data.message ?? 'Export request received.';

        if (data.export) {
            upsertExport(data.export as ExportItem);
        }
    } catch (error) {
        exportError.value =
            axios.isAxiosError(error) && error.response?.data?.message ?
                error.response.data.message :
                'Unable to submit export request right now.';
    } finally {
        exportState.value = 'idle';
    }
}

async function requestDeletion() {
    if (hasActiveDeletion.value || deleteState.value === 'working') {
        return;
    }

    if (!confirm('Your account and all migraine history will be deleted in 72 hours. Continue?')) {
        return;
    }

    deleteState.value = 'working';
    deleteMessage.value = null;
    deleteError.value = null;

    try {
        const { data } = await axios.post('/settings/request-deletion');
        deleteMessage.value = data.message ?? 'Deletion request received.';

        if (data.deletion_request) {
            activeDeletion.value = data.deletion_request as DeletionRequest;
        }
    } catch (error) {
        deleteError.value =
            axios.isAxiosError(error) && error.response?.data?.message ?
                error.response.data.message :
                'Unable to submit deletion request right now.';
    } finally {
        deleteState.value = 'idle';
    }
}

onBeforeUnmount(() => {
    clearExportRefresh();
});

const notificationPreferences = [
    {
        title: 'Migraine Predictions',
        description: 'Get early warnings when your tracked data signals higher migraine risk.',
        key: 'predictions',
    },
    {
        title: 'Cycle Phase Reminders',
        description: 'Reminders to log period details as you move through each phase.',
        key: 'cycle',
    },
    {
        title: 'Medication Alerts',
        description: 'Timely nudges so you never miss preventive or acute medication.',
        key: 'medication',
    },
    {
        title: 'Weekly Digest',
        description: 'Top insights, trends, and patterns summarized every Sunday.',
        key: 'digest',
    },
] as const;

const integrations = [
    {
        name: 'Apple Health',
        description: 'Sync migraines, sleep, and cycle data for richer correlations.',
        status: 'Connected',
        icon: '',
    },
    {
        name: 'Fitbit',
        description: 'Bring in heart rate variability and sleep trends automatically.',
        status: 'Not connected',
        icon: '⌚',
    },
    {
        name: 'Oura Ring',
        description: 'Combine readiness scores with migraine episodes for deeper insight.',
        status: 'Not connected',
        icon: '◯',
    },
] as const;

const securityEvents = [
    { label: 'Logged in', value: 'Today, 9:24 AM • MacBook Pro' },
    { label: 'Password changed', value: 'Aug 12, 2024 • Web' },
    { label: 'Two-factor enabled', value: 'Jul 03, 2024 • iPhone 15' },
] as const;
</script>

<template>
    <Head title="Settings" />

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
                            <rect x="3" y="4" width="18" height="17" rx="2" stroke="currentColor" stroke-width="1.5" />
                            <path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
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

                <button type="button" class="sidebar-logout" @click="logout">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                        <path d="M15 12H4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        <path d="m8 8-4 4 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M15 5h2a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3h-2" stroke=
"currentColor" stroke-width="1.5" stroke-linecap="round" />
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
                    <span class="breadcrumb-current">Settings</span>
                </div>

                <div class="toolbar-actions">
                    <button
                        type="button"
                        class="stats-action"
                        title="Need a quick tour?"
                        @click="startSettingsTour"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                            <path d="M12 17v.01M11 7a3 3 0 0 1 3 3c0 1.5-1 2-2 3v1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                        </svg>
                        <span>Need a quick tour?</span>
                    </button>
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
                <section class="settings-hero glass-panel">
                    <div class="settings-hero-header">
                        <div>
                            <p class="settings-hero-kicker">Account &amp; Preferences</p>
                            <h1 class="settings-hero-title">Tune MigraineAI to match your health journey</h1>
                            <p class="settings-hero-subtitle">
                                Manage account details, control alert cadence, and decide how we learn from your data.
                            </p>
                        </div>
                        <!-- <div class="settings-hero-cta">
                            <button type="button" class="button-primary settings-save-button" :disabled="savingProfile" @click="saveProfile">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path d="M5 12.5 9 16l10-10" stroke="#04180d" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                {{ savingProfile ? 'Saving…' : 'Save All Changes' }}
                            </button>
                            <button type="button" class="settings-outline-button" @click="dismissProfileMessage">Dismiss</button>
                        </div> -->
                    </div>

                    <div class="settings-tabbar">
                        <button
                            type="button"
                            class="settings-tab"
                            :class="activeSettingsTab === 'profile' ? 'settings-tab--active' : ''"
                            @click="setActiveSettingsTab('profile')"
                        >
                            Profile
                        </button>
                        <button
                            type="button"
                            class="settings-tab"
                            :class="activeSettingsTab === 'onboarding' ? 'settings-tab--active' : ''"
                            @click="setActiveSettingsTab('onboarding')"
                        >
                            Onboarding
                        </button>
                        <button
                            type="button"
                            class="settings-tab"
                            :class="activeSettingsTab === 'settings' ? 'settings-tab--active' : ''"
                            @click="setActiveSettingsTab('settings')"
                        >
                            Settings
                        </button>
                    </div>
                </section>

                <section ref="profileSectionRef" class="settings-grid">
                    <article class="settings-card settings-card--accent">
                        <header class="settings-card-header">
                            <div class="settings-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="1.5" />
                                    <path
                                        d="M16 14H8c-2.21 0-4 1.79-4 4v1a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-1c0-2.21-1.79-4-4-4Z"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h2>Profile &amp; Identity</h2>
                                <p>Update how MigraineAI greets you and how period tracking behaves.</p>
                            </div>
                        </header>

                        <div class="settings-form-grid">
                            <label class="settings-field">
                                <span>Full name</span>
                                <input v-model="profileForm.name" type="text" />
                            </label>
                            <label class="settings-field">
                                <span>Age range</span>
                                <select v-model="profileForm.age_range">
                                    <option value="">Select…</option>
                                    <option v-for="ageRange in page.props.options.age_ranges" :key="ageRange" :value="ageRange">
                                        {{ ageRange }}
                                    </option>
                                </select>
                            </label>
                            <label class="settings-field">
                                <span>Gender</span>
                                <select v-model="profileForm.gender">
                                    <option value="">Select…</option>
                                    <option v-for="gender in page.props.options.genders" :key="gender.value" :value="gender.value">
                                        {{ gender.label }}
                                    </option>
                                </select>
                            </label>
                            <label class="settings-field">
                                <span>Time zone</span>
                                <select v-model="profileForm.time_zone">
                                    <option value="">Select…</option>
                                    <option v-for="zone in page.props.options.time_zones" :key="zone" :value="zone">
                                        {{ zone }}
                                    </option>
                                </select>
                            </label>
                        </div>

                        <div v-if="isFemale" class="settings-toggle-row">
                            <label class="settings-toggle settings-toggle--inline">
                                <input type="checkbox" v-model="profileForm.cycle_tracking_enabled" />
                                <span class="settings-toggle-slider"></span>
                                <div>
                                    <p>Enable cycle tracking overlay</p>
                                    <span>Highlight period days across charts and episode lists.</span>
                                </div>
                            </label>
                        </div>

                        <div v-if="cycleEnabled" class="settings-form-grid">
                            <label class="settings-field">
                                <span>Cycle length (days)</span>
                                <input v-model.number="profileForm.cycle_length_days" type="number" min="20" max="60" />
                            </label>
                            <label class="settings-field">
                                <span>Period length (days)</span>
                                <input v-model.number="profileForm.period_length_days" type="number" min="1" max="15" />
                            </label>
                            <label class="settings-field">
                                <span>Last period start</span>
                                <input v-model="profileForm.last_period_start_date" type="date" />
                            </label>
                        </div>

                        <div v-if="profileMessage" class="settings-feedback settings-feedback--success">{{ profileMessage }}</div>
                        <div v-if="profileError" class="settings-feedback settings-feedback--error">{{ profileError }}</div>

                        <div class="settings-actions">
                            <button type="button" class="button-primary" :disabled="savingProfile" @click="saveProfile">
                                {{ savingProfile ? 'Saving…' : 'Save profile' }}
                            </button>
                            <button type="button" class="settings-outline-button" @click="dismissProfileMessage">Clear</button>
                        </div>
                    </article>

                    <!-- <article class="settings-card">
                        <header class="settings-card-header">
                            <div class="settings-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path d="M6 9h12M6 13h12M6 17h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                            </div>
                            <div>
                                <h2>Notification Preferences</h2>
                                <p>Decide what MigraineAI should nudge you about.</p>
                            </div>
                        </header>

                        <div class="settings-notification-list">
                            <label
                                v-for="item in notificationPreferences"
                                :key="item.key"
                                class="settings-toggle settings-toggle--inline"
                            >
                                <input type="checkbox" v-model="notifyStates[item.key]" />
                                <span class="settings-toggle-slider"></span>
                                <div>
                                    <p>{{ item.title }}</p>
                                    <span>{{ item.description }}</span>
                                </div>
                            </label>
                        </div>
                    </article> -->

                    <article class="settings-card">
                        <header class="settings-card-header">
                            <div class="settings-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path
                                        d="M12 6v6l3 1.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h2>Reminders &amp; Follow-ups</h2>
                                <p>Stay on track with gentle daily nudges and post-attack check-ins.</p>
                            </div>
                        </header>

                        <div class="settings-toggle-row">
                            <label class="settings-toggle settings-toggle--inline">
                                <input type="checkbox" v-model="profileForm.daily_reminder_enabled" />
                                <span class="settings-toggle-slider"></span>
                                <div>
                                    <p>Daily logging reminder</p>
                                    <span>Receive a short prompt when it’s time to log an episode.</span>
                                </div>
                            </label>
                        </div>

                        <div class="settings-form-grid">
                            <label class="settings-field">
                                <span>Send reminder at</span>
                                <select
                                    v-model.number="profileForm.daily_reminder_hour"
                                    :disabled="!profileForm.daily_reminder_enabled"
                                >
                                    <option v-for="hour in reminderHours" :key="hour" :value="hour">
                                        {{ formatHourLabel(hour) }}
                                    </option>
                                </select>
                            </label>
                        </div>

                        <p class="settings-card-copy settings-card-copy--muted">
                            Follow-up reminders trigger after every saved episode to capture how you felt after the
                            attack.
                        </p>

                        <div v-if="reminderMessage" class="settings-feedback settings-feedback--success">
                            {{ reminderMessage }}
                        </div>
                        <div v-if="reminderError" class="settings-feedback settings-feedback--error">
                            {{ reminderError }}
                        </div>

                        <div class="settings-actions">
                            <button type="button" class="button-primary" :disabled="savingReminders" @click="saveReminderSettings">
                                {{ savingReminders ? 'Saving…' : 'Save' }}
                            </button>
                            <button type="button" class="settings-outline-button" @click="dismissReminderMessage">Clear</button>
                        </div>
                    </article>
                </section>

                <section ref="onboardingSectionRef" class="settings-grid settings-grid--wide">
                    <article class="settings-card settings-card--accent">
                        <header class="settings-card-header">
                            <div class="settings-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path d="M6 7h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                    <path d="M6 13h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                    <path d="M6 19h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                    <circle cx="4" cy="7" r="1" fill="currentColor" />
                                    <circle cx="4" cy="13" r="1" fill="currentColor" />
                                    <circle cx="4" cy="19" r="1" fill="currentColor" />
                                </svg>
                            </div>
                            <div>
                                <h2>Onboarding Baseline</h2>
                                <p>Refresh your migraine profile questions whenever your journey changes.</p>
                            </div>
                        </header>

                        <div class="onboarding-summary">
                            <div class="onboarding-summary-block">
                                <span class="onboarding-summary-label">Onboarding overview</span>
                                <strong class="onboarding-summary-value">
                                    {{ onboardingAnsweredCount }} / {{ onboardingTotalQuestions }}
                                </strong>
                                <span class="onboarding-summary-meta">questions answered</span>
                            </div>
                            <div class="onboarding-summary-block">
                                <span class="onboarding-summary-label">{{ primaryOnboardingSectionTitle }}</span>
                                <strong class="onboarding-summary-value">
                                    {{ primaryOnboardingSectionQuestionCount }} questions
                                </strong>
                            </div>
                        </div>

                        <OnboardingQuestions
                            :sections="onboardingSections"
                            :answers="onboardingAnswers"
                            :onAnswer="handleOnboardingAnswer"
                        />

                        <div class="settings-actions">
                            <button
                                type="button"
                                class="button-primary"
                                :disabled="savingProfile"
                                @click="saveProfile"
                            >
                                {{ savingProfile ? 'Saving…' : 'Save onboarding responses' }}
                            </button>
                            <button type="button" class="settings-outline-button" @click="dismissProfileMessage">Clear</button>
                        </div>
                    </article>
                </section>

                <!-- <section class="settings-grid settings-grid--two">
                    <article class="settings-card">
                        <header class="settings-card-header">
                            <div class="settings-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                    <path d="M5 5h14a2 2 0 0 1 2 2v11H3V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.5" />
                                </svg>
                            </div>
                            <div>
                                <h2>Integrations</h2>
                                <p>Connect wearables and health data sources.</p>
                            </div>
                        </header>

                        <div class="settings-integrations">
                            <div v-for="integration in integrations" :key="integration.name" class="settings-integration">
                                <div class="settings-integration-icon">{{ integration.icon }}</div>
                                <div class="settings-integration-body">
                                    <p class="settings-integration-name">{{ integration.name }}</p>
                                    <span>{{ integration.description }}</span>
                                </div>
                                <button
                                    type="button"
                                    class="settings-integration-button"
                                    :class="integration.status === 'Connected' ? 'settings-integration-button--connected' : ''"
                                >
                                    {{ integration.status }}
                                </button>
                            </div>
                        </div>
                    </article>

                    <article class="settings-card">
                        <header class="settings-card-header">
                            <div class="settings-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path d="M12 6v6l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18Z" stroke="currentColor" stroke-width="1.5" />
                                </svg>
                            </div>
                            <div>
                                <h2>Security Activity</h2>
                                <p>Track recent activity and stay in control.</p>
                            </div>
                        </header>

                        <div class="settings-activity">
                            <div v-for="event in securityEvents" :key="event.label" class="settings-activity-row">
                                <div>
                                    <p>{{ event.label }}</p>
                                    <span>{{ event.value }}</span>
                                </div>
                                <button type="button" class="settings-outline-button settings-outline-button--compact">
                                    Review
                                </button>
                            </div>
                        </div>
                    </article>
                </section> -->

                <section class="settings-grid settings-grid--two" ref="settingsSectionRef">
                    <!-- <article ref="settingsSectionRef" class="settings-card">
                        <header class="settings-card-header">
                            <div class="settings-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path d="M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18Z" stroke="currentColor" stroke-width="1.5" />
                                    <path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <div>
                                <h2>Data Export</h2>
                                <p>Request a copy of your raw logs and analytics.</p>
                            </div>
                        </header>

                        <p class="settings-card-copy">
                            Generate a ZIP archive with your profile, episodes, and voice transcripts for medical sharing.
                        </p>
                        <div v-if="exportMessage" class="settings-feedback settings-feedback--success">{{ exportMessage }}</div>
                        <div v-if="exportError" class="settings-feedback settings-feedback--error">{{ exportError }}</div>
                        <div v-if="exportItems.length" class="settings-export-list">
                            <article v-for="item in exportItems" :key="item.id" class="settings-export-item">
                                <div class="settings-export-meta">
                                    <span
                                        class="settings-export-status"
                                        :class="{
                                            'settings-export-status--ready': item.status === 'ready',
                                            'settings-export-status--failed': item.status === 'failed',
                                        }"
                                    >
                                        {{ formatExportStatus(item.status) }}
                                    </span>
                                    <span class="settings-export-date">{{ formatDateTime(item.created_at) }}</span>
                                </div>
                                <div class="settings-export-actions">
                                    <span class="settings-export-size">{{ formatBytes(item.size_bytes) }}</span>
                                    <a
                                        v-if="item.download_url && item.status === 'ready'"
                                        :href="item.download_url"
                                        class="settings-export-link"
                                    >
                                        Download
                                    </a>
                                    <span v-else-if="item.status === 'failed'" class="settings-export-error">
                                        {{ item.error_message ?? 'Processing failed.' }}
                                    </span>
                                    <span v-else-if="item.status === 'pending' || item.status === 'in_progress'">
                                        Preparing…
                                    </span>
                                </div>
                            </article>
                        </div>
                        <button
                            v-if="exportItems.length"
                            type="button"
                            class="settings-inline-button"
                            :disabled="exportsRefreshing || exportState === 'working'"
                            @click="refreshExports()"
                        >
                            <span v-if="exportsRefreshing">Refreshing…</span>
                            <span v-else>Refresh status</span>
                        </button>
                        <p v-else class="settings-card-copy settings-card-copy--muted">
                            You haven’t requested a data export yet.
                        </p>
                        <button
                            type="button"
                            class="button-primary"
                            :disabled="exportState === 'working' || hasActiveExport"
                            @click="requestExport"
                        >
                            <span v-if="exportState === 'working'">Submitting…</span>
                            <span v-else-if="hasActiveExport">Preparing export…</span>
                            <span v-else>Request export</span>
                        </button>
                    </article> -->

                    <article class="settings-card">
                        <header class="settings-card-header">
                            <div class="settings-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path
                                        d="M12 6a6 6 0 1 1 0 12 6 6 0 0 1 0-12Z"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                
                                        stroke-linecap="round"
                                    />
                                    <path d="M12 9v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                    <path d="M12 15h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                            </div>
                            <div>
                                <h2>Account Deletion</h2>
                                <p>Request removal of your account and associated data.</p>
                            </div>
                        </header>

                        <p class="settings-card-copy">
                            Your request is queued for a 30 days cooling-off window. Reach out to support to cancel.
                        </p>
                        <div class="settings-deletion-status">
                            <span class="settings-chip" :class="hasActiveDeletion ? 'settings-chip--warning' : ''">
                                {{ deletionStatusLabel(activeDeletion) }}
                            </span>
                        </div>
                        <div v-if="deleteMessage" class="settings-feedback settings-feedback--success">{{ deleteMessage }}</div>
                        <div v-if="deleteError" class="settings-feedback settings-feedback--error">{{ deleteError }}</div>
                        <button
                            type="button"
                            class="settings-outline-button"
                            :disabled="deleteState === 'working' || hasActiveDeletion"
                            @click="requestDeletion"
                        >
                            <span v-if="deleteState === 'working'">Submitting…</span>
                            <span v-else-if="hasActiveDeletion">Request queued</span>
                            <span v-else>Request deletion</span>
                        </button>
                    </article>
                </section>
            </div>
        </main>
    </div>
</template>
