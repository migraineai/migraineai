<script lang="ts" setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref, watch, onMounted } from 'vue';
import SkullHeatMap3D from '@/Components/analytics/SkullHeatMap3D.vue';
import TriggerAnalysisChart from '@/Components/TriggerAnalysisChart.vue';
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

const page = usePage<SharedProps>();

const userName = computed(() => page.props.auth.user?.name ?? 'Friend');
const userEmail = computed(() => page.props.auth.user?.email ?? 'you@example.com');
const userInitial = computed(() => userName.value.trim().charAt(0).toUpperCase() || 'F');
const impersonation = computed(() => page.props.impersonation ?? { active: false, admin_name: null });
const cycleFeatureEnabled = computed(() => Boolean(page.props.auth.user?.cycle_tracking_enabled));

const sidebarOpen = ref(false);
const activeTab = ref<'overview' | 'analysis'>('overview');

type ValueType = 'count' | 'intensity' | 'minutes';
type DetailSectionKey = 'triggerAnalysis' | 'episodeDetails';

type BaselineComparison = {
    label: string;
    baseline_value: number | null;
    current_value: number | null;
    value_type: ValueType;
    status?: string | null;
    delta?: string | null;
};

type SummaryCard = {
    label: string;
    value: number | null;
    value_type: ValueType;
    helper?: string | null;
};

type LegendItem = {
    label: string;
    color: string;
};

type BreakdownItem = {
    label: string;
    count: number;
    percent: number;
    color: string;
};

type HeatmapItem = {
    label: string;
    count: number;
    percent: number;
    intensity: 'high' | 'medium' | 'low';
};

type HeatmapEpisode = {
    id: number | string;
    intensity: number | null;
    pain_location: string | null;
};

type PhaseDuration = {
    phase: string;
    range: string;
    average_minutes: number | null;
    count: number;
};

type MedicalProfile = {
    attack_duration: string | null;
    pain_location: string | null;
    aura: string | null;
};

type AnalysisMetrics = {
    total_episodes: number;
    average_intensity: number | null;
    total_duration_minutes: number;
    average_duration_minutes: number | null;
    primary_trigger: string | null;
    common_location: string | null;
};

type PeriodEpisode = {
    id: number;
    date: string;
    time_range: string;
    duration: string;
    intensity_display: string;
    location: string;
    triggers: string[];
    start_time_iso: string | null;
    end_time_iso: string | null;
    intensity_value: number | null;
    pain_location_value: string | null;
    notes: string | null;
    what_you_tried: string | null;
    aura: boolean | null;
    symptoms: string[];
};

type PeriodPayload = {
    label: string;
    metrics: AnalysisMetrics;
    trigger_breakdown: Array<{ label: string; value: number }>;
    episodes: PeriodEpisode[];
    episode_count: number;
};

const props = defineProps<{
    overview: {
        baseline_comparisons: BaselineComparison[];
        summary_cards: SummaryCard[];
        trigger_legend: LegendItem[];
        symptom_legend: LegendItem[];
        location_legend: LegendItem[];
        trigger_breakdown: BreakdownItem[];
        symptom_breakdown: BreakdownItem[];
        location_breakdown: BreakdownItem[];
        location_heatmap: HeatmapItem[];
        phase_durations: PhaseDuration[];
        heatmap_episodes: HeatmapEpisode[];
        medical_profile: MedicalProfile | null;
    };
    analysis: {
        periods: Record<string, PeriodPayload>;
    };
}>();

const detailSections = ref<Record<DetailSectionKey, boolean>>({
    triggerAnalysis: true,
    episodeDetails: true,
});
const overviewTriggerOpen = ref(true);

const clinicianForm = reactive({
    clinicianName: '',
    clinicianEmail: '',
});

const clinicianSending = ref(false);
const clinicianDownloadLoading = ref(false);
const clinicianStatus = ref<{ type: 'success' | 'error' | ''; message: string }>({
    type: '',
    message: '',
});

const painLocationOptions = [
    { value: '', label: 'Select location' },
    { value: 'left', label: 'Left side' },
    { value: 'right', label: 'Right side' },
    { value: 'bilateral', label: 'Both sides' },
    { value: 'frontal', label: 'Front / forehead' },
    { value: 'occipital', label: 'Back of head' },
    { value: 'other', label: 'Other' },
] as const;

function toggleSection(section: DetailSectionKey) {
    detailSections.value[section] = !detailSections.value[section];
}

function expandAll() {
    Object.keys(detailSections.value).forEach((key) => {
        detailSections.value[key as DetailSectionKey] = true;
    });
}

function collapseAll() {
    Object.keys(detailSections.value).forEach((key) => {
        detailSections.value[key as DetailSectionKey] = false;
    });
}


function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
}

function stopImpersonation() {
    router.post('/super-admin/stop-impersonating');
}

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
// User Tour: refs for key sections
const analyticsHeroRef = ref<HTMLElement | null>(null);
const triggerPanelRef = ref<HTMLElement | null>(null);
const locationSectionRef = ref<HTMLElement | null>(null);
const clinicianSectionRef = ref<HTMLElement | null>(null);

const analyticsTour = useShepherd({
    useModalOverlay: true,
    defaultStepOptions: {
        cancelIcon: { enabled: true },
        scrollTo: { behavior: 'smooth', block: 'center' },
    },
});

function buildAnalyticsTour() {
    analyticsTour.steps = [];
    if (analyticsHeroRef.value) {
        analyticsTour.addStep({
            id: 'analytics-intro',
            attachTo: { element: analyticsHeroRef.value, on: 'bottom' },
            title: 'Analytics Overview',
            text: 'See high-level comparisons and insights about your migraines.',
            buttons: [
                { text: 'Skip', classes: 'shepherd-button-secondary', action: analyticsTour.cancel },
                { text: 'Next', classes: 'shepherd-button-primary', action: analyticsTour.next },
            ],
        });
    }
    if (triggerPanelRef.value) {
        analyticsTour.addStep({
            id: 'analytics-triggers',
            attachTo: { element: triggerPanelRef.value, on: 'top' },
            title: 'Trigger & Symptom Analysis',
            text: 'Explore common triggers and symptoms. Use legends to interpret breakdowns.',
            buttons: [
                { text: 'Back', classes: 'shepherd-button-secondary', action: analyticsTour.back },
                { text: 'Next', classes: 'shepherd-button-primary', action: analyticsTour.next },
            ],
        });
    }
    if (locationSectionRef.value) {
        analyticsTour.addStep({
            id: 'analytics-locations',
            attachTo: { element: locationSectionRef.value, on: 'top' },
            title: 'Location Patterns',
            text: 'See where pain occurs most often. Log locations to improve accuracy.',
            buttons: [
                { text: 'Back', classes: 'shepherd-button-secondary', action: analyticsTour.back },
                { text: 'Next', classes: 'shepherd-button-primary', action: analyticsTour.next },
            ],
        });
    }
    if (clinicianSectionRef.value) {
        analyticsTour.addStep({
            id: 'analytics-clinician',
            attachTo: { element: clinicianSectionRef.value, on: 'top' },
            title: 'Clinician Report',
            text: 'Email or download a PDF summary to share with your clinician.',
            buttons: [
                { text: 'Back', classes: 'shepherd-button-secondary', action: analyticsTour.back },
                { text: 'Close', classes: 'shepherd-button-primary', action: analyticsTour.cancel },
            ],
        });
    }
}

async function markTourSeen(pageKey: string) {
    try {
        await axios.post('/user/tour-status', { page: pageKey, seen: true });
    } catch {}
}

function startAnalyticsTour() {
    buildAnalyticsTour();
    analyticsTour.start();
}

analyticsTour.on('cancel', () => markTourSeen('analytics'));
analyticsTour.on('complete', () => markTourSeen('analytics'));

onMounted(() => {
    const seen = Boolean(page.props.tourStatus?.analytics);
    if (!seen) {
        startAnalyticsTour();
    }
});

function clearClinicianStatus() {
    clinicianStatus.value = { type: '', message: '' };
}

async function sendClinicianReport() {
    clearClinicianStatus();
    clinicianSending.value = true;
    try {
        await axios.post('/analytics/clinician-report/send', {
            clinician_name: clinicianForm.clinicianName,
            clinician_email: clinicianForm.clinicianEmail,
            requested_by: userName.value,
        });
        clinicianStatus.value = { type: 'success', message: 'Report emailed to your clinician.' };
    } catch (error: any) {
        clinicianStatus.value = {
            type: 'error',
            message:
                error?.response?.data?.message ??
                'Unable to send report right now. Please try again shortly.',
        };
    } finally {
        clinicianSending.value = false;
    }
}

async function downloadClinicianReport() {
    clearClinicianStatus();
    clinicianDownloadLoading.value = true;
    try {
        const params = new URLSearchParams({
            clinician_name: clinicianForm.clinicianName,
            clinician_email: clinicianForm.clinicianEmail,
        });

        if (selectedPeriod.value) {
            params.set('period', selectedPeriod.value);
        }

        const response = await axios.get(`/analytics/clinician-report/download?${params.toString()}`, {
            responseType: 'blob',
        });

        const blob = new Blob([response.data], { type: 'application/pdf' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'migraineai-clinician-report.pdf';
        link.click();
        URL.revokeObjectURL(link.href);
    } catch (error: any) {
        clinicianStatus.value = {
            type: 'error',
            message: error?.response?.data?.message ?? 'Unable to download the report.',
        };
    } finally {
        clinicianDownloadLoading.value = false;
    }
}

function toggleOverviewTriggerPanel() {
    overviewTriggerOpen.value = !overviewTriggerOpen.value;
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

const reportPeriods = computed(() =>
    Object.entries(props.analysis.periods ?? {}).map(([value, data]) => ({
        value,
        label: data.label,
    }))
);

const selectedPeriod = ref<string>('');

watch(
    reportPeriods,
    (periods) => {
        if (!periods.length) {
            selectedPeriod.value = '';
            return;
        }

        if (selectedPeriod.value && periods.some((period) => period.value === selectedPeriod.value)) {
            return;
        }

        const preferred = periods.find((period) => period.value === '60') ?? periods[0];
        selectedPeriod.value = preferred.value;
    },
    { immediate: true }
);

const selectedPeriodData = computed<PeriodPayload | null>(() => {
    if (!selectedPeriod.value) {
        return null;
    }

    return props.analysis.periods[selectedPeriod.value] ?? null;
});

const selectedPeriodLabel = computed(() => selectedPeriodData.value?.label ?? 'Selected Period');

const editModalState = reactive({
    open: false,
    loading: false,
    error: '',
});

const editForm = reactive({
    id: null as number | null,
    startDate: '',
    startTime: '',
    endDate: '',
    endTime: '',
    intensity: '',
    painLocation: '',
    triggersInput: '',
    notes: '',
    whatYouTried: '',
});

const deleteModalState = reactive({
    open: false,
    loading: false,
    episodeId: null as number | null,
    episodeLabel: '',
    error: '',
});

const triggerBreakdownPalette = ['#7d7f8d', '#2f3144', '#4bd3ad', '#6feec3', '#25d38f'];

const legendColorMap: Record<string, string> = {
    'legend-primary': '#7d7f8d',
    'legend-secondary': '#2f3144',
    'legend-tertiary': '#4bd3ad',
    'legend-quaternary': '#6feec3',
    'legend-muted': '#25d38f',
};

function resolveLegendColor(color: string): string {
    return legendColorMap[color] ?? 'rgba(97,216,118,0.42)';
}

const overviewHeatmapEpisodes = computed<HeatmapEpisode[]>(() => props.overview.heatmap_episodes ?? []);
const medicalProfile = computed<MedicalProfile | null>(() => props.overview.medical_profile ?? null);
const medicalProfileLines = computed(() => {
    if (!medicalProfile.value) {
        return [];
    }

    const lines: string[] = [];
    const primaryParts: string[] = [];

    if (medicalProfile.value.attack_duration) {
        primaryParts.push(`Attack duration: ${medicalProfile.value.attack_duration}`);
    }

    if (medicalProfile.value.pain_location) {
        primaryParts.push(`Pain location: ${medicalProfile.value.pain_location}`);
    }

    if (primaryParts.length) {
        lines.push(primaryParts.join(' • '));
    }

    if (medicalProfile.value.aura) {
        lines.push(medicalProfile.value.aura);
    }

    return lines;
});

const detailedTriggerBreakdown = computed(() => {
    const data = selectedPeriodData.value;
    if (!data) {
        return [];
    }

    const breakdown = data.trigger_breakdown ?? [];
    const total = breakdown.reduce((sum, item) => sum + (item.value ?? 0), 0);

    return breakdown.map((item, index) => ({
        label: item.label,
        count: item.value ?? 0,
        percent: total ? ((item.value ?? 0) / total) * 100 : 0,
        color: triggerBreakdownPalette[index % triggerBreakdownPalette.length],
    }));
});

const triggerBreakdownTotal = computed(() =>
    detailedTriggerBreakdown.value.reduce((sum, item) => sum + (item.count ?? 0), 0)
);

const triggerHighlights = computed(() => {
    const data = selectedPeriodData.value;
    if (!data) {
        return [];
    }

    const metrics = data.metrics;
    const normalizedLabel = data.label.toLowerCase();

    return [
        {
            label: 'Total Episodes',
            value: formatValue(metrics.total_episodes, 'count'),
            helper: `in ${normalizedLabel}`,
            icon: 'episodes',
            accent: 'primary',
        },
        {
            label: 'Average Intensity',
            value: formatValue(metrics.average_intensity, 'intensity'),
            helper: 'pain severity scale',
            icon: 'intensity',
            accent: 'amber',
        },
        {
            label: 'Total Duration',
            value: formatMinutes(metrics.total_duration_minutes),
            helper: 'cumulative time',
            icon: 'duration',
            accent: 'indigo',
        },
        {
            label: 'Average Duration',
            value: formatValue(metrics.average_duration_minutes, 'minutes'),
            helper: 'per episode',
            icon: 'clock',
            accent: 'teal',
        },
        {
            label: 'Primary Trigger',
            value: metrics.primary_trigger ?? 'Not enough data',
            helper: 'most common cause',
            icon: 'trigger',
            accent: 'rose',
        },
        {
            label: 'Common Location',
            value: metrics.common_location ?? 'Not enough data',
            helper: 'pain area',
            icon: 'location',
            accent: 'violet',
        },
    ];
});

const episodeLogs = computed<PeriodEpisode[]>(() => selectedPeriodData.value?.episodes ?? []);

const episodeCountLabel = computed(() => {
    const count = selectedPeriodData.value?.episode_count ?? 0;
    return `${count} episode${count === 1 ? '' : 's'}`;
});

const overviewTriggerBreakdown = computed(() => props.overview.trigger_breakdown ?? []);
const overviewTriggerTotal = computed(() =>
    overviewTriggerBreakdown.value.reduce((sum, item) => sum + item.count, 0)
);
const overviewTriggerChartItems = computed(() =>
    overviewTriggerBreakdown.value.map((item) => ({
        ...item,
        color: resolveLegendColor(item.color),
    }))
);

const overviewSymptomBreakdown = computed(() => props.overview.symptom_breakdown ?? []);
const overviewSymptomTotal = computed(() =>
    overviewSymptomBreakdown.value.reduce((sum, item) => sum + item.count, 0)
);
const overviewSymptomChartItems = computed(() =>
    overviewSymptomBreakdown.value.map((item) => ({
        ...item,
        color: resolveLegendColor(item.color),
    }))
);

const overviewLocationBreakdown = computed(() => props.overview.location_breakdown ?? []);
const overviewLocationTotal = computed(() =>
    overviewLocationBreakdown.value.reduce((sum, item) => sum + item.count, 0)
);

const phaseDurations = computed(() => props.overview.phase_durations ?? []);
const phaseMaxDuration = computed(() => {
    return phaseDurations.value.reduce((max, item) => {
        if (item.average_minutes === null) {
            return max;
        }
        return Math.max(max, item.average_minutes);
    }, 0);
});

function formatValue(value: number | null | undefined, type: ValueType): string {
    if (value === null || value === undefined) {
        return 'N/A';
    }

    if (type === 'count') {
        if (Number.isInteger(value)) {
            return Number(value).toLocaleString();
        }

        return Number(value).toFixed(1).replace(/\.0$/, '');
    }

    if (type === 'intensity') {
        return `${Number(value).toFixed(1).replace(/\.0$/, '')}/10`;
    }

    return formatMinutes(value);
}

function formatMinutes(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return 'N/A';
    }

    const minutes = Math.round(value);

    if (minutes <= 0) {
        return '0m';
    }

    if (minutes < 60) {
        return `${minutes}m`;
    }

    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;

    if (remaining === 0) {
        return `${hours}h 0m`;
    }

    return `${hours}h ${remaining}m`;
}

function formatPercentage(value: number, total: number): string {
    if (!total) {
        return '0%';
    }

    const percent = Math.round((value / total) * 100);
    return `${percent}%`;
}

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

function baselineStatusModifier(status?: string | null): string {
    if (!status) {
        return 'analytics-status--neutral';
    }

    const normalized = status.toLowerCase();

    if (normalized === 'improved') {
        return 'analytics-status--positive';
    }

    if (normalized === 'higher') {
        return 'analytics-status--warning';
    }

    return 'analytics-status--neutral';
}

function formatDateForInput(date: Date): string {
    return date.toISOString().slice(0, 10);
}

function formatTimeForInput(date: Date): string {
    return date.toISOString().slice(11, 16);
}

function isoToDateInput(iso: string | null): string {
    if (!iso) {
        return '';
    }
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    return formatDateForInput(date);
}

function isoToTimeInput(iso: string | null): string {
    if (!iso) {
        return '';
    }
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    return formatTimeForInput(date);
}

function combineDateTime(date: string, time: string): string | null {
    if (!date || !time) {
        return null;
    }

    const local = new Date(`${date}T${time}`);
    if (Number.isNaN(local.getTime())) {
        return null;
    }

    return new Date(local.getTime() - local.getTimezoneOffset() * 60_000).toISOString();
}

function normalizePainLocation(value: string | null): string {
    if (!value) {
        return '';
    }
    const normalized = value.toLowerCase();
    const match = painLocationOptions.find((option) => option.value === normalized);
    return match ? match.value : '';
}

function parseListInput(value: string): string[] | null {
    if (!value.trim()) {
        return null;
    }

    const items = value
        .split(',')
        .map((entry) => entry.trim())
        .filter((entry) => entry.length > 0);

    return items.length ? items : null;
}

function resetEditForm() {
    editForm.id = null;
    editForm.startDate = '';
    editForm.startTime = '';
    editForm.endDate = '';
    editForm.endTime = '';
    editForm.intensity = '';
    editForm.painLocation = '';
    editForm.triggersInput = '';
    editForm.notes = '';
    editForm.whatYouTried = '';
}

function openEditModal(log: PeriodEpisode) {
    editModalState.error = '';
    editModalState.open = true;
    editForm.id = log.id ?? null;
    editForm.startDate = isoToDateInput(log.start_time_iso);
    editForm.startTime = isoToTimeInput(log.start_time_iso);
    editForm.endDate = isoToDateInput(log.end_time_iso);
    editForm.endTime = isoToTimeInput(log.end_time_iso);
    editForm.intensity = log.intensity_value !== null ? String(log.intensity_value) : '';
    editForm.painLocation = normalizePainLocation(log.pain_location_value ?? null);
    editForm.triggersInput = (log.triggers ?? []).join(', ');
    editForm.notes = log.notes ?? '';
    editForm.whatYouTried = log.what_you_tried ?? '';
}

function closeEditModal() {
    if (editModalState.loading) {
        return;
    }
    editModalState.open = false;
    editModalState.error = '';
    resetEditForm();
}

async function saveEpisodeEdits() {
    if (!editForm.id) {
        return;
    }

    editModalState.loading = true;
    editModalState.error = '';

    const notesValue = editForm.notes.trim();
    const triedValue = editForm.whatYouTried.trim();

    let intensityValue: number | null = editForm.intensity === '' ? null : Number(editForm.intensity);
    if (intensityValue !== null && Number.isNaN(intensityValue)) {
        intensityValue = null;
    }

    const payload = {
        start_time: combineDateTime(editForm.startDate, editForm.startTime),
        end_time: combineDateTime(editForm.endDate, editForm.endTime),
        intensity: intensityValue,
        pain_location: editForm.painLocation || null,
        triggers: parseListInput(editForm.triggersInput),
        notes: notesValue ? notesValue : null,
        what_you_tried: triedValue ? triedValue : null,
    };

    try {
        await axios.put(`/episodes/${editForm.id}`, payload);
    } catch (error: any) {
        editModalState.loading = false;
        editModalState.error =
            error?.response?.data?.message ??
            'Unable to update this episode right now. Please try again in a moment.';
        return;
    }

    editModalState.loading = false;
    editModalState.open = false;
    resetEditForm();
    router.reload({ only: ['overview', 'analysis'] });
}

function openDeleteModal(log: PeriodEpisode) {
    deleteModalState.open = true;
    deleteModalState.loading = false;
    deleteModalState.episodeId = log.id ?? null;
    deleteModalState.episodeLabel = `${log.date} • ${log.time_range}`;
    deleteModalState.error = '';
}

function closeDeleteModal() {
    if (deleteModalState.loading) {
        return;
    }
    deleteModalState.open = false;
    deleteModalState.episodeId = null;
    deleteModalState.episodeLabel = '';
    deleteModalState.error = '';
}

async function deleteEpisode() {
    if (!deleteModalState.episodeId) {
        return;
    }

    deleteModalState.loading = true;
    try {
        await axios.delete(`/episodes/${deleteModalState.episodeId}`);
    } catch (error: any) {
        deleteModalState.loading = false;
        deleteModalState.error =
            error?.response?.data?.message ??
            'Could not delete this episode right now. Please try again later.';
        return;
    }

    deleteModalState.loading = false;
    deleteModalState.open = false;
    deleteModalState.episodeId = null;
    deleteModalState.episodeLabel = '';
    deleteModalState.error = '';
    router.reload({ only: ['overview', 'analysis'] });
}
</script>

<template>
    <Head title="Analytics" />

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
                    <span class="breadcrumb-current">Analytics</span>
                </div>

                <div class="toolbar-actions">
                    <button
                        type="button"
                        class="analysis-table-button"
                        title="Need a quick tour?"
                        @click="startAnalyticsTour"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                            <path
                                d="M12 17v.01M11 7a3 3 0 0 1 3 3c0 1.5-1 2-2 3v1"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
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
                <section class="analytics-hero glass-panel" ref="analyticsHeroRef">
                    <div class="analytics-hero-header">
                        <div>
                            <p class="analytics-hero-kicker">Migraine Analytics &amp; Reports</p>
                            <h1 class="analytics-hero-title">Comprehensive insights tailored to your migraine patterns</h1>
                        </div>
                    </div>

                    <!-- <div class="analytics-tabbar">
                        <button
                            type="button"
                            class="analytics-tab"
                            :class="activeTab === 'overview' ? 'analytics-tab--active' : ''"
                            @click="activeTab = 'overview'"
                        >
                            <span>Overview</span>
                        </button>
                        <button
                            type="button"
                            class="analytics-tab"
                            :class="activeTab === 'analysis' ? 'analytics-tab--active' : ''"
                            @click="activeTab = 'analysis'"
                        >
                            <span>Detailed Analysis</span>
                        </button>
                    </div> -->
                </section>

                <template v-if="activeTab === 'overview'">
                <section class="analytics-baseline glass-panel">
                    <header class="analytics-baseline-header">
                        <div class="analytics-baseline-title">
                            <span class="analytics-baseline-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path
                                        d="M5 11a7 7 0 1 1 7 7l-3 3"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </span>
                            <div>
                                <h2>Baseline Comparison</h2>
                                <p>Compare this month to your typical baseline</p>
                            </div>
                        </div>
                        <!-- <button type="button" class="analytics-outline-button">View Trend</button> -->
                    </header>

                    <div class="analytics-baseline-cards">
                        <article
                            v-for="item in props.overview.baseline_comparisons"
                            :key="item.label"
                            class="analytics-baseline-card"
                        >
                            <header>
                                <p class="analytics-baseline-label">{{ item.label }}</p>
                                <span
                                    class="analytics-status"
                                    :class="baselineStatusModifier(item.status)"
                                >
                                    {{ item.status ?? 'No data' }}
                                </span>
                            </header>
                            <div class="analytics-baseline-values">
                                <div>
                                    <span class="analytics-baseline-caption">Your baseline</span>
                                    <p class="analytics-baseline-value">
                                        {{ formatValue(item.baseline_value, item.value_type) }}
                                    </p>
                                </div>
                                <div>
                                    <span class="analytics-baseline-caption">Current month</span>
                                    <p class="analytics-baseline-value">
                                        {{ formatValue(item.current_value, item.value_type) }}
                                    </p>
                                </div>
                            </div>
                            <p v-if="item.delta" class="analytics-baseline-delta">{{ item.delta }}</p>
                        </article>
                    </div>

                    <!-- <div class="analytics-baseline-insight">
                        <div class="analytics-insight-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                <path
                                    d="M12 7v5l3 3"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <path
                                    d="M12 22a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </div>
                        <div>
                            <p class="analytics-insight-title">Personalized Insight</p>
                            <p class="analytics-insight-body">
                                Great news! You're having fewer episodes than typical. Keep tracking what's working for you.
                            </p>
                        </div>
                    </div> -->

                    <div class="analytics-baseline-footer">
                        <div>
                            <p class="analytics-baseline-meta-title">Medical Profile</p>
                            <template v-if="medicalProfileLines.length">
                                <p
                                    v-for="(line, index) in medicalProfileLines"
                                    :key="`medical-profile-line-${index}`"
                                    class="analytics-baseline-meta"
                                >
                                    {{ line }}
                                </p>
                            </template>
                            <p v-else class="analytics-baseline-meta">
                                Add your medical profile details to personalize this view.
                            </p>
                        </div>
                        <Link href="/settings" class="analytics-outline-button">Edit Profile</Link>
                    </div>
                </section>

                <section class="trigger-analysis-panel" :class="{ 'trigger-analysis-panel--collapsed': !overviewTriggerOpen }" ref="triggerPanelRef">
                    <div class="trigger-analysis-panel-header">
                        <div class="trigger-analysis-panel-title">
                            <span class="trigger-analysis-panel-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path
                                        d="M12 4a8 8 0 1 0 8 8"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <path
                                        d="M12 4h6M12 4v6"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </span>
                            <div>
                                <p class="trigger-analysis-panel-heading">Trigger Analysis</p>
                                <p class="trigger-analysis-panel-subtext">Breakdown of episode triggers and patterns</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="trigger-analysis-panel-action"
                            :class="{ 'trigger-analysis-panel-action--open': overviewTriggerOpen }"
                            @click="toggleOverviewTriggerPanel"
                            :aria-expanded="overviewTriggerOpen"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                <path d="m7 10 5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>

                    <div class="trigger-analysis-panel-body">
                        <div class="trigger-analysis-metrics">
                            <article
                                v-for="card in triggerHighlights"
                                :key="card.label"
                                class="trigger-summary-card"
                                :class="`trigger-summary-card--${card.accent}`"
                            >
                                <span class="trigger-summary-icon">
                                    <svg
                                        v-if="card.icon === 'episodes'"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        class="size-5"
                                        fill="none"
                                    >
                                        <path
                                            d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12l-6-3-6 3Z"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                    <svg
                                        v-else-if="card.icon === 'intensity'"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        class="size-5"
                                        fill="none"
                                    >
                                        <path
                                            d="M12 21c4.97 0 9-4.03 9-9s-4.03-9-9-9-9 4.03-9 9 4.03 9 9 9Z"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                        />
                                        <path d="M9 13.5 12 9v4l3-1.5-3 5V13l-3 .5Z" fill="currentColor" />
                                    </svg>
                                    <svg
                                        v-else-if="card.icon === 'duration'"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        class="size-5"
                                        fill="none"
                                    >
                                        <path
                                            d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                        />
                                        <path
                                            d="M12 7v5l3 2"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                    <svg
                                        v-else-if="card.icon === 'clock'"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        class="size-5"
                                        fill="none"
                                    >
                                        <path
                                            d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                        />
                                        <path d="M12 6v6h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                    </svg>
                                    <svg
                                        v-else-if="card.icon === 'trigger'"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        class="size-5"
                                        fill="none"
                                    >
                                        <path
                                            d="M9 3v2M15 3v2M4 9h16"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            stroke-linecap="round"
                                        />
                                        <path
                                            d="M5 7h14v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7Zm7 4v4"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                    <svg
                                        v-else
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        class="size-5"
                                        fill="none"
                                    >
                                        <path
                                            d="M12 21s6-4.35 6-10a6 6 0 0 0-12 0c0 5.65 6 10 6 10Z"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            stroke-linejoin="round"
                                        />
                                        <circle cx="12" cy="11" r="2.5" stroke="currentColor" stroke-width="1.5" />
                                    </svg>
                                </span>
                                <div>
                                    <p class="trigger-summary-label">{{ card.label }}</p>
                                    <p class="trigger-summary-value">{{ card.value }}</p>
                                    <p v-if="card.helper" class="trigger-summary-helper">{{ card.helper }}</p>
                                </div>
                            </article>
                        </div>

                        <TriggerAnalysisChart
                            :breakdown="overviewTriggerChartItems"
                            :total="overviewTriggerTotal"
                            label="Trigger Analysis"
                            subtitle="trigger mentions"
                        />
                        <TriggerAnalysisChart
                            :breakdown="overviewSymptomChartItems"
                            :total="overviewSymptomTotal"
                            label="Symptom Analysis"
                            subtitle="symptom mentions"
                        />
                    </div>
                </section>

                <section class="analytics-grid" ref="locationSectionRef">
                    <SkullHeatMap3D :episodes="overviewHeatmapEpisodes" />
                </section>
             
                <section class="analytics-grid">
                    <!-- <article class="analytics-panel analytics-panel--wide">
                        <header class="analytics-panel-header">
                            <div>
                                <h3>Location Patterns</h3>
                                <p>Where your episodes occur most frequently</p>
                            </div>
                        </header>
                        <div v-if="!overviewLocationBreakdown.length" class="analytics-donut-placeholder">
                            <div class="analytics-donut-ring analytics-donut-ring--secondary">
                                <span>Log locations</span>
                            </div>
                        </div>
                        <ul v-else class="analytics-bar-list">
                            <li
                                v-for="item in overviewLocationBreakdown"
                                :key="item.label"
                                class="analytics-bar-list-item"
                            >
                                <div class="analytics-bar-list-header">
                                    <span class="analytics-bar-list-label">{{ item.label }}</span>
                                    <span class="analytics-bar-list-meta">
                                        {{ item.count }} • {{ item.percent }}%
                                    </span>
                                </div>
                                <div class="analytics-bar-track">
                                    <div
                                        class="analytics-bar-fill"
                                        :style="{
                                            width: `${overviewLocationTotal ? Math.max(
                                                8,
                                                Math.round((item.count / overviewLocationTotal) * 100)
                                            ) : 0}%`,
                                        }"
                                    ></div>
                                </div>
                            </li>
                        </ul>
                    </article> -->

                    <!-- <article class="analytics-panel">
                        <header class="analytics-panel-header">
                            <div>
                                <h3>Average Duration by Phase</h3>
                                <p>Compare episode length across menstrual phases</p>
                            </div>
                        </header>
                        <div v-if="!phaseDurations.length || !phaseMaxDuration" class="analytics-bar-placeholder">
                            <p class="analytics-bar-note">
                                Collect more logs during each phase to unlock detailed comparisons.
                            </p>
                        </div>
                        <ul v-else class="analytics-phase-list">
                            <li
                                v-for="phase in phaseDurations"
                                :key="phase.phase"
                                class="analytics-phase-item"
                            >
                                <div class="analytics-phase-header">
                                    <div>
                                        <p class="analytics-phase-label">{{ phase.phase }}</p>
                                        <p class="analytics-phase-range">{{ phase.range }}</p>
                                    </div>
                                    <div class="analytics-phase-meta">
                                        <span>{{ phase.count }} logs</span>
                                        <span>{{ formatPhaseMinutes(phase.average_minutes) }}</span>
                                    </div>
                                </div>
                                <div class="analytics-phase-track">
                                    <div
                                        class="analytics-phase-fill"
                                        :style="{
                                            width: `${Math.max(
                                                6,
                                                Math.round(((phase.average_minutes ?? 0) / phaseMaxDuration) * 100)
                                            )}%`,
                                        }"
                                    ></div>
                                </div>
                            </li>
                        </ul>
                    </article> -->
                    <article class="analytics-panel analytics-panel--wide" ref="clinicianSectionRef">
                        <header class="analytics-panel-header">
                            <div>
                                <h3>Clinician Report</h3>
                                <p>Email a PDF summary to your clinician or download it directly.</p>
                            </div>
                        </header>
                        <div class="analysis-clinician-grid">
                            <div class="analysis-clinician-form">
                                <label class="analysis-field">
                                    <span>Your Name</span>
                                    <input type="text" :value="userName" readonly />
                                </label>
                                <label class="analysis-field">
                                    <span>Clinician Name</span>
                                    <input
                                        type="text"
                                        v-model="clinicianForm.clinicianName"
                                        placeholder="Dr. Smith"
                                    />
                                </label>
                                <label class="analysis-field">
                                    <span>Clinician Email</span>
                                    <input
                                        type="email"
                                        v-model="clinicianForm.clinicianEmail"
                                        placeholder="doctor@clinic.com"
                                    />
                                </label>
                            </div>
                            <div class="analysis-clinician-actions">
                                <div class="clinician-hint-block">
                                    <p class="clinician-hint-title">How it works</p>
                                    <ol>
                                        <li>Download the PDF to review or attach.</li>
                                        <li>Send the secure PDF to your clinician.</li>
                                        <li>The PDF includes triggers, locations, and timing snapshots.</li>
                                    </ol>
                                </div>
                                <div class="analysis-clinician-buttons">
                                    <button
                                        type="button"
                                        class="analysis-control-button"
                                        @click="downloadClinicianReport"
                                        :disabled="clinicianDownloadLoading"
                                    >
                                        {{ clinicianDownloadLoading ? 'Downloading…' : 'Download Report PDF' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="analysis-table-button analysis-table-button--primary"
                                        @click="sendClinicianReport"
                                        :disabled="clinicianSending"
                                    >
                                        {{ clinicianSending ? 'Sending…' : 'Send report' }}
                                    </button>
                                </div>
                                <p
                                    v-if="clinicianStatus.type"
                                    :class="[
                                        'clinician-status',
                                        clinicianStatus.type === 'error'
                                            ? 'clinician-status--error'
                                            : 'clinician-status--success',
                                    ]"
                                >
                                    {{ clinicianStatus.message }}
                                </p>
                            </div>
                        </div>
                    </article>
                </section>
                </template>
                <template v-else>
                    <section class="analysis-period glass-panel">
                        <header class="analysis-period-header">
                            <div>
                                <p class="analysis-period-label">Report Period</p>
                                <h2 class="analysis-period-title">Detailed analytics for your selected range</h2>
                            </div>
                            <div v-if="reportPeriods.length" class="analysis-period-controls">
                                <button
                                    v-for="period in reportPeriods"
                                    :key="period.value"
                                    type="button"
                                    class="analysis-period-control"
                                    :class="selectedPeriod === period.value ? 'analysis-period-control--active' : ''"
                                    @click="selectedPeriod = period.value"
                                >
                                    {{ period.label }}
                                </button>
                            </div>
                            <p v-else class="analysis-period-empty">Log new episodes to unlock trend analysis.</p>
                        </header>
                    </section>

                    <section class="analysis-detail glass-panel">
                        <header class="analysis-detail-header">
                            <div class="analysis-detail-title">
                                <span class="analysis-detail-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                        <path
                                            d="M5 12.5 10 17l9-10"
                                            stroke="currentColor"
                                            stroke-width="1.6"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </span>
                                <div>
                                    <h2>Detailed Report Analysis</h2>
                                    <p>Actionable trends across triggers, intensity, and episode history.</p>
                                </div>
                            </div>
                            <div class="analysis-detail-actions">
                                <button type="button" class="analysis-control-button" @click="expandAll">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                        <path
                                            d="M6 9a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75H6.75A.75.75 0 0 1 6 19.5Z"
                                            stroke="currentColor"
                                            stroke-width="1.4"
                                            stroke-linejoin="round"
                                        />
                                        <path d="M12 7.5v9m4.5-4.5h-9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                    </svg>
                                    <span>Expand All</span>
                                </button>
                                <button type="button" class="analysis-control-button" @click="collapseAll">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                        <path
                                            d="M6 4.5a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75H6.75A.75.75 0 0 1 6 15Z"
                                            stroke="currentColor"
                                            stroke-width="1.4"
                                            stroke-linejoin="round"
                                        />
                                        <path d="M7.5 12h9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                    </svg>
                                    <span>Collapse All</span>
                                </button>
                            </div>
                        </header>

                        <div class="analysis-detail-body">
                            <article
                                class="analysis-section"
                                :class="detailSections.triggerAnalysis ? 'analysis-section--expanded' : ''"
                            >
                                <button type="button" class="analysis-section-toggle" @click="toggleSection('triggerAnalysis')">
                                    <span class="analysis-section-leading">
                                        <span class="analysis-section-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                                <path
                                                    d="M12 19a7 7 0 1 1 7-7"
                                                    stroke="currentColor"
                                                    stroke-width="1.6"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                                <path d="M12 12 20 20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                            </svg>
                                        </span>
                                        <span class="analysis-section-text">
                                            <span class="analysis-section-title">Trigger Analysis</span>
                                            <span class="analysis-section-subtitle">
                                                Breakdown of episode triggers and patterns
                                            </span>
                                        </span>
                                    </span>
                                    <span
                                        class="analysis-section-chevron"
                                        :class="detailSections.triggerAnalysis ? 'analysis-section-chevron--open' : ''"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                            <path d="m7 10 5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                </button>
                                <div v-show="detailSections.triggerAnalysis" class="analysis-section-content">
                                    <div class="trigger-analysis-panel trigger-analysis-panel--nested">
                                        <div class="trigger-analysis-panel-body">
                                            <div class="trigger-analysis-metrics">
                                                <article
                                                    v-for="card in triggerHighlights"
                                                    :key="card.label"
                                                    class="trigger-summary-card"
                                                    :class="`trigger-summary-card--${card.accent}`"
                                                >
                                                    <span class="trigger-summary-icon">
                                                        <svg
                                                            v-if="card.icon === 'episodes'"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 24 24"
                                                            class="size-5"
                                                            fill="none"
                                                        >
                                                            <path
                                                                d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12l-6-3-6 3Z"
                                                                stroke="currentColor"
                                                                stroke-width="1.5"
                                                                stroke-linejoin="round"
                                                            />
                                                        </svg>
                                                        <svg
                                                            v-else-if="card.icon === 'intensity'"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 24 24"
                                                            class="size-5"
                                                            fill="none"
                                                        >
                                                            <path
                                                                d="M12 21c4.97 0 9-4.03 9-9s-4.03-9-9-9-9 4.03-9 9 4.03 9 9 9Z"
                                                                stroke="currentColor"
                                                                stroke-width="1.5"
                                                            />
                                                            <path d="M9 13.5 12 9v4l3-1.5-3 5V13l-3 .5Z" fill="currentColor" />
                                                        </svg>
                                                        <svg
                                                            v-else-if="card.icon === 'duration'"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 24 24"
                                                            class="size-5"
                                                            fill="none"
                                                        >
                                                            <path
                                                                d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"
                                                                stroke="currentColor"
                                                                stroke-width="1.5"
                                                            />
                                                            <path
                                                                d="M12 7v5l3 2"
                                                                stroke="currentColor"
                                                                stroke-width="1.5"
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                            />
                                                        </svg>
                                                        <svg
                                                            v-else-if="card.icon === 'clock'"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 24 24"
                                                            class="size-5"
                                                            fill="none"
                                                        >
                                                            <path
                                                                d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"
                                                                stroke="currentColor"
                                                                stroke-width="1.5"
                                                            />
                                                            <path d="M12 6v6h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                                        </svg>
                                                        <svg
                                                            v-else-if="card.icon === 'trigger'"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 24 24"
                                                            class="size-5"
                                                            fill="none"
                                                        >
                                                            <path
                                                                d="M9 3v2M15 3v2M4 9h16"
                                                                stroke="currentColor"
                                                                stroke-width="1.5"
                                                                stroke-linecap="round"
                                                            />
                                                            <path
                                                                d="M5 7h14v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7Zm7 4v4"
                                                                stroke="currentColor"
                                                                stroke-width="1.5"
                                                                stroke-linejoin="round"
                                                            />
                                                        </svg>
                                                        <svg
                                                            v-else
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 24 24"
                                                            class="size-5"
                                                            fill="none"
                                                        >
                                                            <path
                                                                d="M12 21s6-4.35 6-10a6 6 0 0 0-12 0c0 5.65 6 10 6 10Z"
                                                                stroke="currentColor"
                                                                stroke-width="1.5"
                                                                stroke-linejoin="round"
                                                            />
                                                            <circle cx="12" cy="11" r="2.5" stroke="currentColor" stroke-width="1.5" />
                                                        </svg>
                                                    </span>
                                                    <div>
                                                        <p class="trigger-summary-label">{{ card.label }}</p>
                                                        <p class="trigger-summary-value">{{ card.value }}</p>
                                                        <p v-if="card.helper" class="trigger-summary-helper">{{ card.helper }}</p>
                                                    </div>
                                                </article>
                                            </div>

                                            <TriggerAnalysisChart
                                                :breakdown="detailedTriggerBreakdown"
                                                :total="triggerBreakdownTotal"
                                                label="Trigger Analysis"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <article
                                class="analysis-section"
                                :class="detailSections.episodeDetails ? 'analysis-section--expanded' : ''"
                            >
                                <button type="button" class="analysis-section-toggle" @click="toggleSection('episodeDetails')">
                                    <span class="analysis-section-leading">
                                        <span class="analysis-section-icon analysis-section-icon--calendar">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                                <rect
                                                    x="3.5"
                                                    y="4"
                                                    width="17"
                                                    height="16"
                                                    rx="2"
                                                    stroke="currentColor"
                                                    stroke-width="1.5"
                                                />
                                                <path
                                                    d="M16 2v4M8 2v4M3.5 10.5h17"
                                                    stroke="currentColor"
                                                    stroke-width="1.5"
                                                    stroke-linecap="round"
                                                />
                                            </svg>
                                        </span>
                                        <span class="analysis-section-text">
                                            <span class="analysis-section-title">Episode Details</span>
                                            <span class="analysis-section-subtitle">
                                                Complete episode log with timestamps and context
                                            </span>
                                        </span>
                                    </span>
                                    <span
                                        class="analysis-section-chevron"
                                        :class="detailSections.episodeDetails ? 'analysis-section-chevron--open' : ''"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                            <path d="m7 10 5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                </button>
                                <div v-show="detailSections.episodeDetails" class="analysis-section-content">
                                    <div class="analysis-table-header">
                                        <div>
                                            <p class="analysis-table-heading">Episode Details</p>
                                            <p class="analysis-table-subheading">Complete episode log with timestamps and details</p>
                                        </div>
                                        <div class="analysis-table-meta">
                                            <span class="analysis-table-period">{{ selectedPeriodLabel }}</span>
                                            <span class="analysis-table-count">{{ episodeCountLabel }}</span>
                                        </div>
                                    </div>
                                    <div class="analysis-table-wrapper">
                                        <table class="analysis-table">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Time</th>
                                                    <th>Duration</th>
                                                    <th>Intensity</th>
                                                    <th>Location</th>
                                                    <th>Triggers</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody v-if="episodeLogs.length">
                                                <tr v-for="log in episodeLogs" :key="log.id ?? `${log.date}-${log.time_range}`">
                                                    <td>{{ log.date }}</td>
                                                    <td>{{ log.time_range }}</td>
                                                    <td>{{ log.duration }}</td>
                                                    <td>
                                                        <span class="analysis-intensity-badge">{{ log.intensity_display }}</span>
                                                    </td>
                                                    <td>{{ log.location }}</td>
                                                    <td class="analysis-table-triggers">
                                                        <span
                                                            v-for="trigger in log.triggers"
                                                            :key="trigger"
                                                            class="analysis-tag"
                                                        >
                                                            {{ trigger }}
                                                        </span>
                                                    </td>
                                                    <td class="analysis-table-actions-cell">
                                                        <div class="analysis-table-actions">
                                                            <button
                                                                type="button"
                                                                class="analysis-table-button"
                                                                @click="openEditModal(log)"
                                                            >
                                                                Edit
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="analysis-table-button analysis-table-button--danger"
                                                                @click="openDeleteModal(log)"
                                                            >
                                                                Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tbody v-else>
                                                <tr>
                                                    <td class="analysis-table-empty" colspan="7">
                                                        No episodes recorded in this period.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </article>

                            <article class="analysis-section analysis-clinician-section">
                                <div class="analysis-section-header">
                                    <span class="analysis-section-title">Clinician Report</span>
                                    <p class="analysis-section-subtitle">Email a PDF summary to your clinician or download it directly.</p>
                                </div>
                                <div class="analysis-clinician-grid">
                                    <div class="analysis-clinician-form">
                                        <label class="analysis-field">
                                            <span>Your Name</span>
                                            <input type="text" :value="userName" readonly />
                                        </label>
                                        <label class="analysis-field">
                                            <span>Clinician Name</span>
                                            <input
                                                type="text"
                                                v-model="clinicianForm.clinicianName"
                                                placeholder="Dr. Smith"
                                            />
                                        </label>
                                        <label class="analysis-field">
                                            <span>Clinician Email</span>
                                            <input
                                                type="email"
                                                v-model="clinicianForm.clinicianEmail"
                                                placeholder="doctor@clinic.com"
                                            />
                                        </label>
                                    </div>
                                    <div class="analysis-clinician-actions">
                                        <div class="clinician-hint-block">
                                            <p class="clinician-hint-title">How it works</p>
                                            <ol>
                                                <li>Download the PDF to review or attach.</li>
                                                <li>Send the secure PDF to your clinician.</li>
                                                <li>The PDF includes triggers, locations, and timing snapshots.</li>
                                            </ol>
                                        </div>
                                        <div class="analysis-clinician-buttons">
                                            <button
                                                type="button"
                                                class="analysis-control-button"
                                                @click="downloadClinicianReport"
                                                :disabled="clinicianDownloadLoading"
                                            >
                                                {{ clinicianDownloadLoading ? 'Downloading…' : 'Download Report PDF' }}
                                            </button>
                                            <button
                                                type="button"
                                                class="analysis-table-button analysis-table-button--primary"
                                                @click="sendClinicianReport"
                                                :disabled="clinicianSending"
                                            >
                                                {{ clinicianSending ? 'Sending…' : 'Send report' }}
                                            </button>
                                        </div>
                                        <p
                                            v-if="clinicianStatus.type"
                                            :class="[
                                                'clinician-status',
                                                clinicianStatus.type === 'error'
                                                    ? 'clinician-status--error'
                                                    : 'clinician-status--success',
                                            ]"
                                        >
                                            {{ clinicianStatus.message }}
                                        </p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>
                </template>
            </div>
        </main>
    </div>

    <div v-if="editModalState.open" class="analysis-modal-overlay">
        <div class="analysis-modal">
            <div class="analysis-modal-header">
                <div>
                    <p class="analysis-modal-kicker">Update Episode</p>
                    <h4>Edit episode details</h4>
                </div>
                <button type="button" class="analysis-modal-close" @click="closeEditModal">
                    &times;
                </button>
            </div>
            <form class="analysis-modal-body" @submit.prevent="saveEpisodeEdits">
                <div class="analysis-form-grid">
                    <label class="analysis-field">
                        <span>Start date</span>
                        <input type="date" v-model="editForm.startDate" />
                    </label>
                    <label class="analysis-field">
                        <span>Start time</span>
                        <input type="time" v-model="editForm.startTime" />
                    </label>
                    <label class="analysis-field">
                        <span>End date</span>
                        <input type="date" v-model="editForm.endDate" />
                    </label>
                    <label class="analysis-field">
                        <span>End time</span>
                        <input type="time" v-model="editForm.endTime" />
                    </label>
                    <label class="analysis-field">
                        <span>Intensity</span>
                        <input type="number" min="0" max="10" v-model="editForm.intensity" placeholder="0-10" />
                    </label>
                    <label class="analysis-field">
                        <span>Location</span>
                        <select v-model="editForm.painLocation">
                            <option v-for="option in painLocationOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label class="analysis-field analysis-field--full">
                        <span>Triggers (comma separated)</span>
                        <input type="text" v-model="editForm.triggersInput" placeholder="Stress, Bright lights" />
                    </label>
                    <label class="analysis-field analysis-field--full">
                        <span>Notes</span>
                        <textarea v-model="editForm.notes" rows="3" placeholder="Add context or symptoms"></textarea>
                    </label>
                    <label class="analysis-field analysis-field--full">
                        <span>What you tried</span>
                        <textarea
                            v-model="editForm.whatYouTried"
                            rows="2"
                            placeholder="Medications or actions taken"
                        ></textarea>
                    </label>
                </div>
                <p v-if="editModalState.error" class="analysis-modal-error">{{ editModalState.error }}</p>
                <div class="analysis-modal-actions">
                    <button type="button" class="analysis-table-button" @click="closeEditModal">Cancel</button>
                    <button
                        type="submit"
                        class="analysis-table-button analysis-table-button--primary"
                        :disabled="editModalState.loading"
                    >
                        {{ editModalState.loading ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div v-if="deleteModalState.open" class="analysis-modal-overlay">
        <div class="analysis-modal analysis-modal--confirm">
            <div class="analysis-modal-header">
                <div>
                    <p class="analysis-modal-kicker">Delete Episode</p>
                    <h4>Remove this entry?</h4>
                </div>
                <button type="button" class="analysis-modal-close" @click="closeDeleteModal">
                    &times;
                </button>
            </div>
            <div class="analysis-modal-body">
                <p class="analysis-modal-message">
                    This will permanently delete the episode logged on
                    <strong>{{ deleteModalState.episodeLabel }}</strong>. This action cannot be undone.
                </p>
                <p v-if="deleteModalState.error" class="analysis-modal-error">{{ deleteModalState.error }}</p>
                <div class="analysis-modal-actions">
                    <button type="button" class="analysis-table-button" @click="closeDeleteModal">Cancel</button>
                    <button
                        type="button"
                        class="analysis-table-button analysis-table-button--danger"
                        :disabled="deleteModalState.loading"
                        @click="deleteEpisode"
                    >
                        {{ deleteModalState.loading ? 'Deleting…' : 'Delete episode' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
