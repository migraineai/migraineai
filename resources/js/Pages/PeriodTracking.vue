<script lang="ts" setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted } from 'vue';
import axios from 'axios';
import { logoUrl } from '@/Utils/logo';
import { useShepherd } from 'vue-shepherd';

type SharedProps = {
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
};

type DayStatus = 'empty' | 'period' | 'fertile' | 'ovulation' | 'predicted' | 'normal' | 'logged';
type DayIconType = 'period' | 'fertile' | 'ovulation' | 'predicted' | 'logged';

type CalendarDay = {
    label: string;
    status: DayStatus;
    date: string | null;
    is_today: boolean;
    has_log: boolean;
    is_other_month: boolean;
};

type CalendarWeek = CalendarDay[];

type CalendarData = {
    month_label: string;
    current_month: string;
    previous_month: string | null;
    next_month: string | null;
    weeks: CalendarWeek[];
    selected_date: string | null;
};

type PeriodCountdown = {
    daysRemaining: number | null;
    cycleDay: number | null;
    expectedDate: string | null;
};

type CyclePhase = {
    phaseLabel: string;
    cycleDay: number | null;
    totalDays: number | null;
    risk: string;
};

type LogSummary = {
    date: string;
    iso_date: string;
    symptoms: string[];
    severity: number;
    notes?: string | null;
    is_period_day: boolean;
};

type LogsProps = {
    latest_logged_at: string | null;
    recent: LogSummary[];
    by_date: Record<
        string,
        {
            symptoms: string[];
            severity: number | null;
            notes?: string | null;
            is_period_day: boolean;
        }
    >;
};

type Insights = {
    total_logs: number;
    phase_summary: Record<string, number>;
    migraine_episodes_recent: number;
};

type PhaseDuration = {
    phase: string;
    range: string;
    average_minutes: number | null;
    count: number;
};

type PhaseAnalysisItem = {
    label: string;
    count: number;
    percent: number;
    color: string;
};

const props = defineProps<{
    calendar: CalendarData;
    periodCountdown: PeriodCountdown;
    cyclePhase: CyclePhase;
    symptomOptions: string[];
    logs: LogsProps;
    insights: Insights;
    phaseDurations: PhaseDuration[];
    phaseAnalysis: PhaseAnalysisItem[];
}>();

const page = usePage<SharedProps>();

const userName = computed(() => page.props.auth.user?.name ?? 'Friend');
const userEmail = computed(() => page.props.auth.user?.email ?? 'you@example.com');
const userInitial = computed(() => userName.value.trim().charAt(0).toUpperCase() || 'F');
const impersonation = computed(() => page.props.impersonation ?? { active: false, admin_name: null });
const cycleFeatureEnabled = computed(() => Boolean(page.props.auth.user?.cycle_tracking_enabled));

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

// User Tour: refs
const calendarSectionRef = ref<HTMLElement | null>(null);
const logSectionRef = ref<HTMLElement | null>(null);
const insightsSectionRef = ref<HTMLElement | null>(null);

const periodTour = useShepherd({
    useModalOverlay: true,
    defaultStepOptions: {
        cancelIcon: { enabled: true },
        scrollTo: { behavior: 'smooth', block: 'center' },
    },
});

function buildPeriodTour() {
    periodTour.steps = [];
    if (calendarSectionRef.value) {
        periodTour.addStep({
            id: 'period-calendar',
            attachTo: { element: calendarSectionRef.value, on: 'bottom' },
            title: 'Cycle Calendar',
            text: 'Navigate months and see period, fertile, and ovulation indicators.',
            buttons: [
                { text: 'Skip', classes: 'shepherd-button-secondary', action: periodTour.cancel },
                { text: 'Next', classes: 'shepherd-button-primary', action: periodTour.next },
            ],
        });
    }
    if (logSectionRef.value) {
        periodTour.addStep({
            id: 'period-log',
            attachTo: { element: logSectionRef.value, on: 'top' },
            title: 'Log Period Symptoms',
            text: 'Record symptoms and severity for selected days to improve insights.',
            buttons: [
                { text: 'Back', classes: 'shepherd-button-secondary', action: periodTour.back },
                { text: 'Next', classes: 'shepherd-button-primary', action: periodTour.next },
            ],
        });
    }
    if (insightsSectionRef.value) {
        periodTour.addStep({
            id: 'period-insights',
            attachTo: { element: insightsSectionRef.value, on: 'top' },
            title: 'Cycle Insights',
            text: 'Review cycle phase summaries and how they relate to migraines.',
            buttons: [
                { text: 'Back', classes: 'shepherd-button-secondary', action: periodTour.back },
                { text: 'Close', classes: 'shepherd-button-primary', action: periodTour.cancel },
            ],
        });
    }
}

async function markTourSeen(pageKey: string) {
    try {
        await axios.post('/user/tour-status', { page: pageKey, seen: true });
    } catch {}
}

function startPeriodTour() {
    buildPeriodTour();
    periodTour.start();
}

periodTour.on('cancel', () => markTourSeen('periodTracking'));
periodTour.on('complete', () => markTourSeen('periodTracking'));

onMounted(() => {
    const seen = Boolean(page.props.tourStatus?.periodTracking);
    if (!seen) {
        startPeriodTour();
    }
});
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

const weekDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as const;

const calendar = computed(() => props.calendar);
const calendarWeeks = computed<CalendarWeek[]>(() => calendar.value.weeks as CalendarWeek[]);
const calendarMonthLabel = computed(() => calendar.value.month_label);
const periodCountdown = computed(() => props.periodCountdown);
const cyclePhase = computed(() => props.cyclePhase);
const insights = computed(() => props.insights);
const symptomOptions = computed(() => props.symptomOptions);
const phaseDurations = computed<PhaseDuration[]>(() => props.phaseDurations ?? []);
const phaseAnalysis = computed<PhaseAnalysisItem[]>(() => props.phaseAnalysis ?? []);
const phaseAnalysisTotal = computed(() => phaseAnalysis.value.reduce((sum, item) => sum + item.count, 0));
const phaseDurationMap = computed<Record<string, PhaseDuration>>(() => {
    const map: Record<string, PhaseDuration> = {};
    phaseDurations.value.forEach((item) => {
        map[item.phase] = item;
    });
    return map;
});

const cycleLegend: Array<{ status: DayStatus; label: string; icon: DayIconType }> = [
    { status: 'period', label: 'Period days', icon: 'period' },
    { status: 'fertile', label: 'Fertile window', icon: 'fertile' },
    { status: 'ovulation', label: 'Ovulation', icon: 'ovulation' },
    { status: 'predicted', label: 'Projected cycle', icon: 'predicted' },
    { status: 'logged', label: 'Logged symptoms', icon: 'logged' },
];

const selectedDate = ref<string | null>(calendar.value.selected_date);

watch(
    () => calendar.value.selected_date,
    (value) => {
        selectedDate.value = value;
    }
);

const dayLookup = computed<Record<string, CalendarDay>>(() => {
    const map: Record<string, CalendarDay> = {};
    props.calendar.weeks.forEach((week) => {
        week.forEach((day) => {
            if (day.date) {
                map[day.date] = day as CalendarDay;
            }
        });
    });
    return map;
});

const selectedDay = computed<CalendarDay | null>(() =>
    selectedDate.value ? dayLookup.value[selectedDate.value] ?? null : null
);

const selectedDayLog = computed(() => (selectedDate.value ? props.logs.by_date?.[selectedDate.value] ?? null : null));

const customSymptom = ref('');

const form = useForm({
    date: selectedDate.value,
    month: calendar.value.current_month,
    symptoms: selectedDayLog.value?.symptoms ? [...selectedDayLog.value.symptoms] : [],
    severity: selectedDayLog.value?.severity ?? 3,
    notes: selectedDayLog.value?.notes ?? '',
    is_period_day: selectedDayLog.value?.is_period_day ?? (selectedDay.value?.status === 'period'),
});

watch(
    () => calendar.value.current_month,
    (value) => {
        form.month = value;
    },
    { immediate: true }
);

watch(
    [selectedDate, selectedDayLog],
    () => {
        form.date = selectedDate.value;
        const log = selectedDayLog.value;

        if (log) {
            form.symptoms = [...log.symptoms];
            form.severity = log.severity ?? 3;
            form.notes = log.notes ?? '';
            form.is_period_day = Boolean(log.is_period_day);
        } else {
            form.symptoms = [];
            form.severity = 3;
            form.notes = '';
            form.is_period_day = selectedDay.value?.status === 'period';
        }
    },
    { immediate: true }
);

const latestLogDate = computed(() => props.logs.latest_logged_at ?? 'Not logged yet');
const loggedEntries = computed(() => props.logs.recent ?? []);

function toggleSymptom(symptom: string) {
    const current = form.symptoms ?? [];
    const index = current.indexOf(symptom);

    if (index === -1) {
        form.symptoms = [...current, symptom];
    } else {
        form.symptoms = current.filter((item) => item !== symptom);
    }

    if (form.symptoms.length > 0) {
        form.clearErrors('symptoms');
    }
}

function addCustomSymptom() {
    const normalized = customSymptom.value.trim();

    if (!normalized) {
        return;
    }

    if (!form.symptoms.includes(normalized)) {
        form.symptoms = [...form.symptoms, normalized];
    }

    customSymptom.value = '';
    form.clearErrors('symptoms');
}

const canSubmit = computed(() => Boolean(form.date) && form.symptoms.length > 0 && !form.processing);

function logSymptoms() {
    if (!form.date) {
        form.setError('date', 'Select a calendar day to log symptoms.');
        return;
    }

    form.post('/period-tracking/logs', {
        preserveScroll: true,
        onSuccess: () => {
            customSymptom.value = '';
            form.clearErrors();
        },
    });
}

function selectCalendarDay(day: CalendarDay) {
    if (!day.date || day.status === 'empty') {
        return;
    }

    selectedDate.value = day.date;
    form.clearErrors('date');
}

function navigateToMonth(month: string | null) {
    if (!month) {
        return;
    }

    const params = new URLSearchParams({ month });
    router.visit(`/period-tracking?${params.toString()}`, {
        preserveScroll: true,
    });
}

function calendarDayClass(dayInput: CalendarDay | Record<string, any>) {
    const day = dayInput as CalendarDay;
    const base = ['period-calendar-day'];

    if (!day.date || day.status === 'empty') {
        base.push('period-calendar-day--empty');
        return base;
    }

    if (selectedDate.value === day.date) {
        base.push('period-calendar-day--selected');
    }

    if (day.status === 'period') {
        base.push('period-calendar-day--period');
    }

    if (day.status === 'fertile') {
        base.push('period-calendar-day--fertile');
    }

    if (day.status === 'ovulation') {
        base.push('period-calendar-day--ovulation');
    }

    if (day.status === 'predicted') {
        base.push('period-calendar-day--predicted');
    }

    if (day.status === 'normal' || day.status === 'logged') {
        base.push('period-calendar-day--normal');
    }

    if (day.status === 'logged') {
        base.push('period-calendar-day--logged');
    }

    if (day.is_today) {
        base.push('period-calendar-day--today');
    }

    return base;
}

function calendarDayIcons(dayInput: CalendarDay | Record<string, any>): DayIconType[] {
    const day = dayInput as CalendarDay;

    if (!day.date || day.status === 'empty') {
        return [];
    }

    const icons: DayIconType[] = [];

    if (day.status === 'period') {
        icons.push('period');
    }

    if (day.status === 'fertile') {
        icons.push('fertile');
    }

    if (day.status === 'ovulation') {
        icons.push('ovulation');
    }

    if (day.status === 'predicted') {
        icons.push('predicted');
    }

    if (day.status === 'logged' || day.has_log) {
        icons.push('logged');
    }

    return icons;
}

const selectedDateLabel = computed(() => {
    if (!selectedDate.value) {
        return 'Select a date';
    }

    return formatDate(selectedDate.value);
});

const phaseSummaryChips = computed(() =>
    Object.entries(insights.value.phase_summary ?? {}).map(([phase, count]) => {
        const duration = phaseDurationMap.value[phase] ?? null;

        return {
            label: phase,
            count: Number(count),
            average: duration?.average_minutes ?? null,
            range: duration?.range ?? null,
        };
    })
);

function formatPhaseMinutes(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return 'N/A';
    }

    const minutes = Math.round(value);

    if (minutes < 60) {
        return `${minutes}m`;
    }

    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    return remainder ? `${hours}h ${remainder}m` : `${hours}h`;
}

function formatDate(value: string): string {
    const date = new Date(`${value}T00:00:00`);
    return new Intl.DateTimeFormat(undefined, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(date);
}
</script>

<template>
    <Head title="Period Tracking" />

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
                            <path
                                d="M4 20V4m0 16h16"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            />
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
                    <span class="breadcrumb-current">Period Tracking</span>
                </div>

                <div class="toolbar-actions">
                    <button
                        type="button"
                        class="stats-action"
                        title="Need a quick tour?"
                        @click="startPeriodTour"
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
                <section class="glass-panel px-8 py-6">
                    <div class="flex flex-col gap-6">
                        <div class="flex flex-col gap-2">
                            <p class="text-xs uppercase tracking-[0.35em] text-[--color-text-muted]">Period Tracking</p>
                            <div class="flex flex-wrap items-end justify-between gap-4">
                                <h1 class="text-3xl font-semibold tracking-tight text-white">Track your cycle and migraine signals</h1>
                                <span class="rounded-full border border-[--color-border] bg-[--color-bg-alt]/70 px-4 py-2 text-xs font-semibold text-[--color-text-muted]">
                                    Synced with MigraineAI insights
                                </span>
                            </div>
                            <p class="max-w-2xl text-sm text-[--color-text-muted]">
                                Review where you are in your current cycle, log symptoms, and watch how patterns line up with your migraine episodes.
                            </p>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-2">
                            <article class="cycle-card">
                                <header class="cycle-card-header">
                                    <span class="cycle-card-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-6" fill="none">
                                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                                            <path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <div>
                                        <h2 class="cycle-card-title">Period Countdown</h2>
                                        <p class="cycle-card-subtitle">Track your cycle</p>
                                    </div>
                                </header>
                                <div class="cycle-card-body">
                                    <p class="cycle-countdown-value">
                                        <span>{{ periodCountdown.daysRemaining ?? '—' }}</span>
                                        <small>days remaining</small>
                                    </p>
                                    <div class="cycle-countdown-meta">
                                        <span class="cycle-chip">Day {{ periodCountdown.cycleDay ?? '—' }}</span>
                                        <Link
                                            v-if="periodCountdown.expectedDate === 'Update in settings'"
                                            href="/settings"
                                            class="cycle-chip cycle-chip--outline"
                                        >
                                            Update in settings
                                        </Link>
                                        <span v-else class="cycle-chip cycle-chip--outline">
                                            {{ periodCountdown.expectedDate ?? 'Set your start date' }}
                                        </span>
                                    </div>
                                </div>
                            </article>

                            <article class="cycle-card cycle-card--phase">
                                <header class="cycle-card-header">
                                    <span class="cycle-card-icon cycle-card-icon--green">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-6" fill="none">
                                            <rect x="4" y="4" width="16" height="16" rx="4" stroke="currentColor" stroke-width="1.5" />
                                            <path d="M12 8v4l2 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <div>
                                        <h2 class="cycle-card-title">Current Phase</h2>
                                        <p class="cycle-card-subtitle">Cycle overview</p>
                                    </div>
                                </header>
                                <div class="cycle-card-body">
                                    <p class="cycle-phase-value">
                                        <template v-if="cyclePhase.cycleDay !== null">
                                            Day {{ cyclePhase.cycleDay }}
                                            <template v-if="cyclePhase.totalDays"> of {{ cyclePhase.totalDays }}</template>
                                        </template>
                                        <template v-else>
                                            Cycle day unavailable
                                        </template>
                                    </p>
                                    <p class="cycle-phase-label">{{ cyclePhase.phaseLabel }}</p>
                                    <span
                                        class="cycle-chip"
                                        :class="cyclePhase.risk.toLowerCase().includes('high') ? 'cycle-chip--alert' : ''"
                                    >
                                        {{ cyclePhase.risk }}
                                    </span>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="flex flex-col gap-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-white">Track Your Cycle</h2>
                            <p class="text-sm text-[--color-text-muted]">Log period activity and symptoms to get ahead of migraine triggers.</p>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-[--color-text-muted]">
                            <span class="inline-flex items-center gap-1">
                                <span class="size-1.5 rounded-full bg-[--color-accent-strong]/80"></span>
                                Synced period days
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span class="size-1.5 rounded-full bg-[--color-accent]/60"></span>
                                Fertility insights
                            </span>
                        </div>
                    </div>

                    <div class="w-full">
                            <article class="cycle-calendar-card glass-panel p-6" ref="calendarSectionRef">
                            <header class="cycle-calendar-header">
                                <div class="flex items-start gap-4">
                                    <span class="cycle-card-icon cycle-card-icon--green">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-6" fill="none">
                                            <path
                                                d="M5 5a2 2 0 0 1 2-2h1V1h2v2h4V1h2v2h1a2 2 0 0 1 2 2v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5Zm2 5h10"
                                                stroke="currentColor"
                                                stroke-width="1.5"
                                                stroke-linecap="round"
                                            />
                                            <path d="m9 14 2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <div>
                                        <h3 class="cycle-card-title">Cycle Calendar</h3>
                                        <p class="cycle-card-subtitle">Track your phases visually</p>
                                    </div>
                                </div>
                                <div class="cycle-calendar-nav">
                                    <button
                                        type="button"
                                        class="cycle-nav-button"
                                        aria-label="Previous month"
                                        :disabled="!calendar.previous_month"
                                        @click="navigateToMonth(calendar.previous_month)"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                            <path d="M15 5 8 12l7 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                    <p class="cycle-month-label">{{ calendarMonthLabel }}</p>
                                    <button
                                        type="button"
                                        class="cycle-nav-button"
                                        aria-label="Next month"
                                        :disabled="!calendar.next_month"
                                        @click="navigateToMonth(calendar.next_month)"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                            <path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                </div>
                            </header>

                            <div class="period-calendar">
                                <div class="period-calendar-week period-calendar-week--labels">
                                    <span v-for="day in weekDays" :key="day">{{ day }}</span>
                                </div>

                                <div v-for="(week, index) in calendarWeeks" :key="index" class="period-calendar-week">
                                    <button
                                        v-for="day in week"
                                        :key="day.date ?? `${index}-${day.label || 'empty'}`"
                                        type="button"
                                        class="period-calendar-day-button"
                                        :class="calendarDayClass(day)"
                                        @click="selectCalendarDay(day)"
                                    >
                                        <span class="period-day-label">{{ day.label }}</span>
                                        <div v-if="calendarDayIcons(day).length" class="period-day-icons">
                                            <template v-for="icon in calendarDayIcons(day)" :key="icon">
                                                <svg
                                                    v-if="icon === 'period'"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    class="period-day-icon period-day-icon--period"
                                                    fill="none"
                                                >
                                                    <path
                                                        d="M12 3.5c-1.8 2.8-4 5.6-4 7.8a4 4 0 1 0 8 0c0-2.2-2.2-5-4-7.8Z"
                                                        stroke="currentColor"
                                                        stroke-width="1.4"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                                <svg
                                                    v-else-if="icon === 'fertile'"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    class="period-day-icon period-day-icon--fertile"
                                                    fill="none"
                                                >
                                                    <path
                                                        d="M6 13c2.5 0 4.5 2 4.5 4.5C10.5 20 8.5 22 6 22c-2 0-4-2-4-4.5C2 15 4 13 6 13ZM18 2c2.2 0 4 1.8 4 4 0 5-4 9-10 9 0-6 4-13 6-13Z"
                                                        stroke="currentColor"
                                                        stroke-width="1.4"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                                <svg
                                                    v-else-if="icon === 'ovulation'"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    class="period-day-icon period-day-icon--ovulation"
                                                    fill="none"
                                                >
                                                    <path
                                                        d="m12 4 1.7 3.5L17.5 8l-2.8 2.7.7 3.8L12 12.7 8.6 14.5l.7-3.8L6.5 8l3.8-.5L12 4Z"
                                                        stroke="currentColor"
                                                        stroke-width="1.4"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                                <svg
                                                    v-else-if="icon === 'predicted'"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    class="period-day-icon period-day-icon--predicted"
                                                    fill="none"
                                                >
                                                    <path
                                                        d="M12 5.5a6.5 6.5 0 1 1 0 13 6.5 6.5 0 0 1 0-13Z"
                                                        stroke="currentColor"
                                                        stroke-width="1.3"
                                                        stroke-dasharray="2 2"
                                                    />
                                                </svg>
                                                <svg
                                                    v-else-if="icon === 'logged'"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    class="period-day-icon period-day-icon--logged"
                                                    fill="none"
                                                >
                                                    <path
                                                        d="M12 21s-6.5-4.2-6.5-9.5A4.5 4.5 0 0 1 12 8a4.5 4.5 0 0 1 6.5 3.5C18.5 16.8 12 21 12 21Z"
                                                        stroke="currentColor"
                                                        stroke-width="1.4"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                            </template>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <div class="cycle-legend">
                                <span v-for="item in cycleLegend" :key="item.status" class="cycle-legend-item">
                                    <span :class="['cycle-legend-icon', `cycle-legend-icon--${item.status}`]">
                                        <svg
                                            v-if="item.icon === 'period'"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            class="cycle-legend-svg period-day-icon period-day-icon--period"
                                            fill="none"
                                        >
                                            <path
                                                d="M12 3.5c-1.8 2.8-4 5.6-4 7.8a4 4 0 1 0 8 0c0-2.2-2.2-5-4-7.8Z"
                                                stroke="currentColor"
                                                stroke-width="1.4"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                        <svg
                                            v-else-if="item.icon === 'fertile'"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            class="cycle-legend-svg period-day-icon period-day-icon--fertile"
                                            fill="none"
                                        >
                                            <path
                                                d="M6 13c2.5 0 4.5 2 4.5 4.5C10.5 20 8.5 22 6 22c-2 0-4-2-4-4.5C2 15 4 13 6 13ZM18 2c2.2 0 4 1.8 4 4 0 5-4 9-10 9 0-6 4-13 6-13Z"
                                                stroke="currentColor"
                                                stroke-width="1.4"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                        <svg
                                            v-else-if="item.icon === 'ovulation'"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            class="cycle-legend-svg period-day-icon period-day-icon--ovulation"
                                            fill="none"
                                        >
                                            <path
                                                d="m12 4 1.7 3.5L17.5 8l-2.8 2.7.7 3.8L12 12.7 8.6 14.5l.7-3.8L6.5 8l3.8-.5L12 4Z"
                                                stroke="currentColor"
                                                stroke-width="1.4"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                        <svg
                                            v-else-if="item.icon === 'predicted'"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            class="cycle-legend-svg period-day-icon period-day-icon--predicted"
                                            fill="none"
                                        >
                                            <path
                                                d="M12 5.5a6.5 6.5 0 1 1 0 13 6.5 6.5 0 0 1 0-13Z"
                                                stroke="currentColor"
                                                stroke-width="1.3"
                                                stroke-dasharray="2 2"
                                            />
                                        </svg>
                                        <svg
                                            v-else-if="item.icon === 'logged'"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            class="cycle-legend-svg period-day-icon period-day-icon--logged"
                                            fill="none"
                                        >
                                            <path
                                                d="M12 21s-6.5-4.2-6.5-9.5A4.5 4.5 0 0 1 12 8a4.5 4.5 0 0 1 6.5 3.5C18.5 16.8 12 21 12 21Z"
                                                stroke="currentColor"
                                                stroke-width="1.4"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </span>
                                    {{ item.label }}
                                </span>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-2">
                    <!-- Left Column: Log Period Symptoms -->
                    <article class="glass-panel p-6" ref="logSectionRef">
                        <div class="flex items-start justify-between gap-6">
                            <div>
                                <h3 class="cycle-card-title">Log Period Symptoms</h3>
                                <p class="cycle-card-subtitle">Track changes that may affect migraines</p>
                            </div>
                            <span class="cycle-chip cycle-chip--outline">Last logged {{ latestLogDate }}</span>
                        </div>

                        <div class="mt-6 space-y-6">
                            <div class="flex flex-col gap-2">
                                <span class="log-label">Select Date</span>
                                <button type="button" class="log-date-picker">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                        <rect
                                            x="3"
                                            y="5"
                                            width="18"
                                            height="16"
                                            rx="3"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                        />
                                        <path d="M16 3v4M8 3v4M3 11h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                    </svg>
                                    <span>{{ selectedDateLabel }}</span>
                                </button>
                                <p v-if="form.errors.date" class="log-error">{{ form.errors.date }}</p>
                                <p v-else-if="selectedDayLog" class="text-xs text-[--color-text-muted]">
                                    Existing entry selected – saving will update it.
                                </p>
                            </div>

                            <div class="flex flex-col gap-3">
                                <span class="log-label">Select Symptoms</span>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="symptom in symptomOptions"
                                        :key="symptom"
                                        type="button"
                                        class="symptom-chip"
                                        :class="form.symptoms.includes(symptom) ? 'symptom-chip--active' : ''"
                                        @click="toggleSymptom(symptom)"
                                    >
                                        {{ symptom }}
                                    </button>
                                </div>
                                <div class="flex items-center gap-3">
                                    <input
                                        v-model="customSymptom"
                                        type="text"
                                        placeholder="Add custom symptom"
                                        class="flex-1 bg-transparent"
                                    />
                                    <button type="button" class="cycle-add-button" @click="addCustomSymptom">+</button>
                                </div>
                                <p v-if="form.errors.symptoms" class="log-error">{{ form.errors.symptoms }}</p>
                            </div>

                            <div class="flex flex-col gap-3">
                                <div class="flex items-center justify-between text-sm text-[--color-text-muted]">
                                    <span class="log-label">Severity</span>
                                    <span class="font-semibold text-[--color-text-primary]">{{ form.severity }}/5</span>
                                </div>
                                <input
                                    v-model.number="form.severity"
                                    type="range"
                                    min="1"
                                    max="5"
                                    step="1"
                                    class="cycle-range"
                                />
                                <div class="flex justify-between text-xs uppercase tracking-[0.22em] text-[--color-text-muted]/70">
                                    <span>Mild</span>
                                    <span>Moderate</span>
                                    <span>Intense</span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2">
                                <span class="log-label">Notes (optional)</span>
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    placeholder="Add any additional details to tie back to migraine episodes."
                                ></textarea>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <span class="log-label">Is this a period day?</span>
                                <button
                                    type="button"
                                    class="cycle-chip"
                                    :class="form.is_period_day ? 'cycle-chip--success' : 'cycle-chip--outline'"
                                    @click="form.is_period_day = !form.is_period_day"
                                >
                                    {{ form.is_period_day ? 'Marked as period day' : 'Mark as period day' }}
                                </button>
                            </div>

                            <button
                                type="button"
                                class="button-primary w-full justify-center"
                                :disabled="!canSubmit"
                                :class="!canSubmit ? 'opacity-60 cursor-not-allowed' : ''"
                                @click="logSymptoms"
                            >
                                {{ form.processing ? 'Saving...' : 'Log Symptoms' }}
                            </button>
                        </div>

                        <div class="soft-divider mt-8"></div>

                        <div class="mt-6 space-y-4">
                            <h4 class="text-sm font-semibold uppercase tracking-[0.28em] text-[--color-text-muted]">
                                Logged entries
                            </h4>
                            <div v-if="!loggedEntries.length" class="text-xs text-[--color-text-muted]">
                                No entries logged yet. Select a date and add your first update.
                            </div>
                            <div
                                v-for="entry in loggedEntries"
                                :key="entry.iso_date ?? entry.date"
                                class="logged-entry"
                            >
                                <div class="logged-entry-header">
                                    <span>{{ entry.date }}</span>
                                    <span class="cycle-chip cycle-chip--soft">Severity {{ entry.severity }}/5</span>
                                </div>
                                <div class="logged-entry-body">
                                    <div class="logged-entry-badges">
                                        <span v-for="symptom in entry.symptoms" :key="symptom" class="logged-entry-chip">
                                            {{ symptom }}
                                        </span>
                                    </div>
                                    <p v-if="entry.notes" class="logged-entry-notes">
                                        {{ entry.notes }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <!-- Right Column: Patterns & Phase Analysis -->
                    <div class="flex flex-col gap-6">
                        <!-- Cycle-Migraine Patterns -->
                        <article class="glass-panel p-6" ref="insightsSectionRef">
                            <div class="flex items-start gap-4">
                                <span class="cycle-card-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-6" fill="none">
                                        <path
                                            d="M5 5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v14l-7-3-7 3V5Z"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </span>
                                <div class="space-y-3">
                                    <h3 class="cycle-card-title">Cycle–Migraine Patterns</h3>
                                    <p class="text-sm text-[--color-text-muted]">
                                        {{ insights.total_logs }} period entries captured in your recent cycles. Keep logging symptoms during different
                                        phases so we can surface recurring triggers and relief patterns.
                                    </p>
                                    <ul class="list-disc space-y-2 pl-5 text-sm text-[--color-text-muted]">
                                        <li>Log migraines during ovulation to trace hormonal triggers.</li>
                                        <li>Compare severity during period vs. luteal weeks.</li>
                                        <li>Enable smart reminders to fill missing days.</li>
                                    </ul>
                                </div>
                            </div>
                        </article>

                        <!-- Episodes by Cycle Phase -->
                        <article class="glass-panel p-6" v-if="phaseAnalysis.length">
                            <header class="flex items-start gap-4 mb-6">
                                <span class="cycle-card-icon cycle-card-icon--green">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-6" fill="none">
                                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                                        <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                    </svg>
                                </span>
                                <div>
                                    <h3 class="cycle-card-title">Migraine Episodes by Cycle Phase</h3>
                                    <p class="cycle-card-subtitle">Track how many episodes logged in each phase</p>
                                </div>
                            </header>

                            <div v-if="phaseAnalysisTotal === 0" class="text-center py-8">
                                <p class="text-[--color-text-muted]">No episode data available for analysis.</p>
                            </div>
                            <div v-else class="flex flex-col gap-8">
                                <!-- Donut Chart -->
                                <div class="flex justify-center">
                                    <div class="relative w-48 h-48">
                                        <svg viewBox="0 0 120 120" class="w-full h-full">
                                            <circle cx="60" cy="60" r="50" fill="none" stroke="#e5e7eb" stroke-width="12" />
                                            <template v-for="(item, index) in phaseAnalysis" :key="item.label">
                                                <circle
                                                    cx="60"
                                                    cy="60"
                                                    r="50"
                                                    fill="none"
                                                    :stroke="item.color"
                                                    stroke-width="12"
                                                    :stroke-dasharray="`${(item.percent / 100) * 314.159} 314.159`"
                                                    :stroke-dashoffset="`${-phaseAnalysis.slice(0, index).reduce((sum, p) => sum + (p.percent / 100) * 314.159, 0)}`"
                                                    stroke-linecap="round"
                                                    class="transition-all duration-300"
                                                    style="transform: rotate(-90deg); transform-origin: 60px 60px"
                                                />
                                            </template>
                                        </svg>
                                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                                            <div class="text-3xl font-bold text-[--color-accent]">{{ phaseAnalysisTotal }}</div>
                                            <div class="text-xs text-[--color-text-muted]">EPISODES</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Legend -->
                                <div class="space-y-3">
                                    <div v-for="item in phaseAnalysis" :key="item.label" class="flex items-center justify-between p-3 rounded-xl border border-[--color-border] bg-[--color-bg-alt]/30">
                                        <div class="flex items-center gap-3">
                                            <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: item.color }"></div>
                                            <span class="text-sm font-medium text-[--color-text-primary]">{{ item.label }}</span>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-semibold text-[--color-text-primary]">{{ item.count }}</div>
                                            <div class="text-xs text-[--color-text-muted]">{{ item.percent }}%</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>
