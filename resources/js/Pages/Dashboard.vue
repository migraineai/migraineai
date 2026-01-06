<script lang="ts" setup>
import axios from 'axios';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import RealtimeRecorder from '../Components/RealtimeRecorder.vue';
import SparklineChart from '../Components/SparklineChart.vue';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useShepherd } from 'vue-shepherd';
import { logoUrl } from '@/Utils/logo';

type SharedProps = {
    auth: {
        user: {
            name: string;
            email?: string | null;
            time_zone?: string | null;
            cycle_tracking_enabled?: boolean;
            onboarding_answers?: Record<string, any> | null;
        } | null;
    };
    impersonation?: {
        active: boolean;
        admin_name?: string | null;
    };
    tourStatus?: Record<string, boolean>;
    episodeInsights?: EpisodeInsightsPayload | null;
};

type EpisodeSuggestion = {
    start_time?: string | null;
    end_time?: string | null;
    intensity?: number | null;
    pain_location?: string | null;
    aura?: boolean | null;
    symptoms?: string[] | null;
    triggers?: string[] | null;
    what_you_tried?: string | null;
    notes?: string | null;
    confidence_breakdown?: Record<string, number>;
};

type TimelineEpisode = {
    id: number;
    start_time: string | null;
    end_time: string | null;
    intensity: number | null;
    pain_location: string | null;
    aura: boolean | null;
    symptoms: string[] | null;
    triggers: string[] | null;
    what_you_tried: string | null;
    notes: string | null;
    created_at: string | null;
    isPeriodDay?: boolean;
};

type SparklinePoint = {
    date: string;
    count?: number | null;
    average_intensity?: number | null;
};

type EpisodeSummaryPayload = {
    total_episodes: number;
    average_intensity: number | null;
    total_duration_hours: number;
    pain_free_days_percent: number | null;
};

type EpisodeSparklinesPayload = {
    attack_count: SparklinePoint[];
    average_intensity: SparklinePoint[];
};

type EpisodeInsightsPayload = {
    range: number;
    episodes: TimelineEpisode[];
    summary: EpisodeSummaryPayload;
    sparklines: EpisodeSparklinesPayload;
    period_days: string[];
};

const page = usePage<SharedProps>();

const userName = computed(() => page.props.auth.user?.name ?? 'Friend');
const userEmail = computed(() => page.props.auth.user?.email ?? 'you@example.com');
const userInitial = computed(() => userName.value.trim().charAt(0).toUpperCase() || 'F');
const userTimeZone = computed(
    () => page.props.auth.user?.time_zone || Intl.DateTimeFormat().resolvedOptions().timeZone
);
const impersonation = computed(() => page.props.impersonation ?? { active: false, admin_name: null });
const cycleFeatureEnabled = computed(() => Boolean(page.props.auth.user?.cycle_tracking_enabled));
const shouldShowAuraLabel = computed(() => {
    const q8AuraValue = page.props.auth.user?.onboarding_answers?.q8_aura;
    if (!q8AuraValue) return false;
    // q8_aura is an array, check if it contains 'none' or is empty
    if (Array.isArray(q8AuraValue)) {
        return q8AuraValue.length > 0 && !q8AuraValue.includes('none');
    }
    // Fallback for string values
    return q8AuraValue !== 'none';
});
const sidebarOpen = ref(false);
const manualEntryOpen = ref(false);
const manualAdvanced = ref(false);
const voiceUploadMessage = ref<string | null>(null);
const latestAudioClipId = ref<number | null>(null);
const showTranscript = ref(false);
const isSubmittingEpisode = ref(false);
const submissionMessage = ref<string | null>(null);
const submissionError = ref<string | null>(null);
const showSaveAnimation = ref(false);
const autoSaveAttempted = ref(false);

const analysisState = reactive<{
    clipId: number | null;
    status: string;
    loading: boolean;
    transcript: string;
    suggestion: EpisodeSuggestion | null;
    error: string | null;
    pollTimer: ReturnType<typeof setTimeout> | null;
}>({
    clipId: null as number | null,
    status: 'idle',
    loading: false,
    transcript: '',
    suggestion: null,
    error: null,
    pollTimer: null,
});

let saveAnimationTimer: ReturnType<typeof setTimeout> | null = null;
let episodePromptHideTimer: ReturnType<typeof setTimeout> | null = null;

function logout() {
    // Force a full page reload after logout to get fresh CSRF token
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

// User Tour: refs and setup
const voiceEntryRef = ref<HTMLElement | null>(null);
const manualEntryRef = ref<HTMLElement | null>(null);
const chartsRef = ref<HTMLElement | null>(null);
const timelineRef = ref<HTMLElement | null>(null);

const dashboardTour = useShepherd({
    useModalOverlay: true,
    defaultStepOptions: {
        cancelIcon: { enabled: true },
        scrollTo: { behavior: 'smooth', block: 'center' },
    },
});

function buildDashboardTour() {
    dashboardTour.steps = [];
    if (voiceEntryRef.value) {
        dashboardTour.addStep({
            id: 'dashboard-voice',
            attachTo: { element: voiceEntryRef.value, on: 'top' },
            title: 'Quick Voice Entry',
            text: 'Tap the mic to log a migraine hands-free. We capture details automatically.',
            buttons: [
                { text: 'Skip', classes: 'shepherd-button-secondary', action: dashboardTour.cancel },
                { text: 'Next', classes: 'shepherd-button-primary', action: dashboardTour.next },
            ],
        });
    }
    if (chartsRef.value) {
        dashboardTour.addStep({
            id: 'dashboard-charts',
            attachTo: { element: chartsRef.value, on: 'top' },
            title: 'Trends',
            text: 'See attack count and average intensity trends over time.',
            buttons: [
                { text: 'Back', classes: 'shepherd-button-secondary', action: dashboardTour.back },
                { text: 'Next', classes: 'shepherd-button-primary', action: dashboardTour.next },
            ],
        });
    }
    if (timelineRef.value) {
        dashboardTour.addStep({
            id: 'dashboard-timeline',
            attachTo: { element: timelineRef.value, on: 'top' },
            title: 'Recent Episodes',
            text: 'Review and edit recent episodes. Use the icons to edit or delete.',
            buttons: [
                { text: 'Back', classes: 'shepherd-button-secondary', action: dashboardTour.back },
                { text: 'Close', classes: 'shepherd-button-primary', action: dashboardTour.cancel },
            ],
        });
    }
}

async function markTourSeen(pageKey: string) {
    try {
        await axios.post('/user/tour-status', { page: pageKey, seen: true });
    } catch {}
}

function startDashboardTour() {
    buildDashboardTour();
    dashboardTour.start();
}

dashboardTour.on('cancel', () => markTourSeen('dashboard'));
dashboardTour.on('complete', () => markTourSeen('dashboard'));

onMounted(() => {
    const seen = Boolean(page.props.tourStatus?.dashboard);
    if (!seen) {
        startDashboardTour();
    }
});
function openManualEntry() {
    if (!latestAudioClipId.value) {
        resetManualForm();
    }
    manualEntryOpen.value = true;
    manualAdvanced.value = false;
}

function closeManualEntry() {
    manualEntryOpen.value = false;
    editingEpisodeId.value = null;
}

function toggleManualAdvanced() {
    manualAdvanced.value = !manualAdvanced.value;
}

function handleVoiceRecorded(payload: { id: number; status: string }) {
    resetManualForm();
    prefillStartTimeWithNow();
    latestAudioClipId.value = payload.id;
    manualForm.audioClipId = payload.id;
    manualEntryOpen.value = true;
    voiceUploadMessage.value = 'Audio received. We’re transcribing and extracting the episode details.';
    startAnalysisPolling(payload.id);
}

function handleVoiceError(message: string) {
    voiceUploadMessage.value = message;
    if (/unauthenticated|session expired/i.test(message)) {
        void router.visit('/login', { replace: true });
    }
}

function handleRealtimeEpisode(payload: {
    episodeId: number | null;
    structured: EpisodeSuggestion | null;
    transcript: string;
    message?: string | null;
}) {
    voiceUploadMessage.value = payload.message ?? 'Your episode is recorded. Thank you.';
    submissionMessage.value = 'Episode saved successfully.';
    manualEntryOpen.value = false;
    autoSaveAttempted.value = true;

    if (payload.structured) {
        applySuggestion(payload.structured, payload.transcript);
    }

    void fetchEpisodes();
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

const triggerOptions = [
    { label: 'Stress', icon: '😤' },
    { label: 'Screen time', icon: '💻' },
    { label: 'Sleep disruption', icon: '💤' },
    { label: 'Weather', icon: '🌦' },
    { label: 'Dehydration', icon: '💧' },
] as const;

const symptomOptions = [
    { label: 'Nausea', icon: '🤢' },
    { label: 'Vomiting', icon: '🤮' },
    { label: 'Light Sensitivity', icon: '💡' },
    { label: 'Sound Sensitivity', icon: '🔊' },
    { label: 'Visual Aura', icon: '🌈' },
    { label: 'Dizziness', icon: '🌀' },
    { label: 'Brain Fog', icon: '🌫' },
    { label: 'Anxiety', icon: '😰' },
] as const;

const medicationOptions = ['Ibuprofen', 'Sumatriptan', 'Acetaminophen', 'Naproxen'];

const painLocationOptions = [
    { value: 'left', label: 'Left side' },
    { value: 'right', label: 'Right side' },
    { value: 'bilateral', label: 'Both sides' },
    { value: 'frontal', label: 'Front / forehead' },
    { value: 'occipital', label: 'Back of head' },
    { value: 'other', label: 'Other' },
] as const;

const episodeDurationOptions = [
    { label: '15 min', minutes: 15 },
    { label: '30 min', minutes: 30 },
    { label: '1 hr', minutes: 60 },
    { label: '2 hrs', minutes: 120 },
    { label: '3 hrs', minutes: 180 },
    { label: '6 hrs', minutes: 360 },
] as const;

const impactLevels = [
    { label: 'None', description: 'Resolved / barely noticeable' },
    { label: 'Reduced', description: 'Can do some activities but limited' },
    { label: 'Minimal', description: 'Slight impact, mostly functional' },
    { label: 'Bedridden', description: 'In bed, unable to function' },
] as const;

const manualForm = reactive({
    audioClipId: null as number | null,
    startDate: '' as string,
    startTime: '' as string,
    endDate: '' as string,
    endTime: '' as string,
    painLevel: 5,
    painLevelConfirmed: false,
    painLocation: '' as string,
    aura: null as boolean | null,
    triggers: [] as string[],
    symptoms: [] as string[],
    medications: [] as string[],
    impact: 'None',
    notes: '',
    whatYouTried: '',
});

const painGuide = {
    mild: 'Mild',
    severe: 'Severe',
};

const painTypical = 7;

function resetManualForm() {
    manualForm.audioClipId = null;
    autoSaveAttempted.value = false;
    manualForm.startDate = '';
    manualForm.startTime = '';
    manualForm.endDate = '';
    manualForm.endTime = '';
    manualForm.painLevel = 5;
    manualForm.painLevelConfirmed = false;
    manualForm.painLocation = '';
    manualForm.aura = null;
    manualForm.triggers = [];
    manualForm.symptoms = [];
    manualForm.medications = [];
    manualForm.impact = 'None';
    manualForm.notes = '';
    manualForm.whatYouTried = '';
    showTranscript.value = false;
}

const episodeRange = ref(30);
const episodesLoading = ref(false);
const episodesError = ref<string | null>(null);
const episodes = ref<TimelineEpisode[]>([]);
const EPISODE_PROMPT_DELAY_MINUTES = 15;
const episodeEndPromptEpisode = ref<TimelineEpisode | null>(null);
const episodeEndPromptState = reactive({
    selectedMinutes: null as number | null,
    saving: false,
    showThanks: false,
    error: null as string | null,
});
const episodeEndHours = ref<number>(1);
function handleEpisodeSliderInput() {
    episodeEndPromptState.selectedMinutes = episodeEndHours.value * 60;
    episodeEndPromptState.error = null;
}
const dismissedEpisodePromptIds = ref<number[]>([]);
const summary = reactive<EpisodeSummaryPayload>({
    total_episodes: 0,
    average_intensity: null as number | null,
    total_duration_hours: 0,
    pain_free_days_percent: null as number | null,
});
const periodDays = ref<string[]>([]);
const sparklines = reactive<EpisodeSparklinesPayload>({
    attack_count: [],
    average_intensity: [],
});

function sanitizeSparkline<T extends Record<string, unknown>>(points: unknown, valueKey: string): T[] {
    const arr = Array.isArray(points) ? points : [];
    return arr.filter((p) => p && typeof p === 'object' && valueKey in (p as Record<string, unknown>)) as T[];
}

function applyEpisodeInsightsPayload(payload?: EpisodeInsightsPayload | null) {
    console.log('applyEpisodeInsightsPayload called with payload range:', payload?.range, 'summary:', payload?.summary);
    
    if (!payload) {
        console.log('No payload received, returning');
        return;
    }

    if (typeof payload.range === 'number') {
        console.log('Setting episodeRange from payload:', payload.range);
        episodeRange.value = payload.range;
    }

    episodes.value = Array.isArray(payload.episodes) ? [...payload.episodes] : [];
    console.log('Set episodes count:', episodes.value.length);

    const oldSummary = { ...summary };
    summary.total_episodes = payload.summary?.total_episodes ?? 0;
    summary.average_intensity = payload.summary?.average_intensity ?? null;
    summary.total_duration_hours = payload.summary?.total_duration_hours ?? 0;
    summary.pain_free_days_percent = payload.summary?.pain_free_days_percent ?? null;
    
    console.log('Updated summary from', oldSummary, 'to', { ...summary });

    const sparklinePayload = payload.sparklines ?? { attack_count: [], average_intensity: [] };
    sparklines.attack_count = sanitizeSparkline<SparklinePoint>(sparklinePayload.attack_count ?? [], 'count');
    sparklines.average_intensity = sanitizeSparkline<SparklinePoint>(
        sparklinePayload.average_intensity ?? [],
        'average_intensity'
    );

    periodDays.value = Array.isArray(payload.period_days) ? [...payload.period_days] : [];
}

applyEpisodeInsightsPayload(page.props.episodeInsights ?? null);

function setEpisodeRange(range: number) {
    console.log('setEpisodeRange called with range:', range, 'current range:', episodeRange.value);
    
    if (episodeRange.value === range) {
        console.log('Range unchanged, skipping fetch');
        return;
    }

    console.log('Updating range from', episodeRange.value, 'to', range);
    episodeRange.value = range;
    console.log('Triggering fetchEpisodes for range:', range);
    void fetchEpisodes();
}

const showAllEpisodes = ref(false);
const editingEpisodeId = ref<number | null>(null);
const deleteModalOpen = ref(false);
const episodeToDeleteId = ref<number | null>(null);

function toggleSelection(list: string[], value: string) {
    const index = list.indexOf(value);
    if (index === -1) {
        list.push(value);
    } else {
        list.splice(index, 1);
    }
}

function updateEpisodeEndPromptTarget() {
    if (episodeEndPromptState.saving || episodeEndPromptState.showThanks) {
        return;
    }

    const candidate = findEpisodeNeedingEnd();
    const candidateChanged = candidate?.id !== episodeEndPromptEpisode.value?.id;

    episodeEndPromptEpisode.value = candidate;

    if (!candidate || candidateChanged) {
        episodeEndPromptState.selectedMinutes = null;
        episodeEndPromptState.error = null;
    }
}

function findEpisodeNeedingEnd(): TimelineEpisode | null {
    const now = Date.now();
    const threshold = EPISODE_PROMPT_DELAY_MINUTES * 60_000;

    for (const episode of episodes.value) {
        if (!episode.start_time || episode.end_time) {
            continue;
        }

        if (dismissedEpisodePromptIds.value.includes(episode.id)) {
            continue;
        }

        const start = new Date(episode.start_time);
        if (Number.isNaN(start.getTime())) {
            continue;
        }

        if (now - start.getTime() >= threshold) {
            return episode;
        }
    }

    return null;
}

function selectEpisodeDuration(minutes: number) {
    episodeEndPromptState.selectedMinutes = minutes;
    episodeEndPromptState.error = null;
}

function skipEpisodeEndPrompt() {
    const episode = episodeEndPromptEpisode.value;
    if (!episode) {
        return;
    }

    if (!dismissedEpisodePromptIds.value.includes(episode.id)) {
        dismissedEpisodePromptIds.value = [...dismissedEpisodePromptIds.value, episode.id];
    }

    episodeEndPromptEpisode.value = null;
    episodeEndPromptState.selectedMinutes = null;
    episodeEndPromptState.error = null;
}

async function saveEpisodeEndDuration() {
    const episode = episodeEndPromptEpisode.value;
    if (!episode) {
        return;
    }

    if (episodeEndPromptState.selectedMinutes === null) {
        episodeEndPromptState.error = 'Select how long the migraine lasted.';
        return;
    }

    if (!episode.start_time) {
        episodeEndPromptState.error = 'We need a start time before we can set the end.';
        return;
    }

    const start = new Date(episode.start_time);
    if (Number.isNaN(start.getTime())) {
        episodeEndPromptState.error = 'Unable to read the start time for this episode.';
        return;
    }

    const end = new Date(start.getTime() + episodeEndPromptState.selectedMinutes * 60_000);

    episodeEndPromptState.saving = true;
    episodeEndPromptState.error = null;

    try {
        await axios.put(`/episodes/${episode.id}`, {
            end_time: end.toISOString(),
        });
        episodeEndPromptState.showThanks = true;
        dismissedEpisodePromptIds.value = dismissedEpisodePromptIds.value.filter((id) => id !== episode.id);
        void fetchEpisodes();

        if (episodePromptHideTimer) {
            window.clearTimeout(episodePromptHideTimer);
        }
        episodePromptHideTimer = window.setTimeout(() => {
            episodeEndPromptState.showThanks = false;
            episodeEndPromptEpisode.value = null;
            episodeEndPromptState.selectedMinutes = null;
            updateEpisodeEndPromptTarget();
            episodePromptHideTimer = null;
        }, 1400);
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            episodeEndPromptState.error = error.response.data.message;
        } else {
            episodeEndPromptState.error = 'Unable to update the episode right now.';
        }
    } finally {
        episodeEndPromptState.saving = false;
    }
}

function painLabel(value: number | null) {
    if (value === null || Number.isNaN(value)) return 'Not set';
    if (value <= 3) return 'Low';
    if (value <= 6) return 'Moderate';
    if (value <= 8) return 'High';
    return 'Severe';
}

function formatDateForInput(date: Date): string {
    return date.toISOString().slice(0, 10);
}

function formatTimeForInput(date: Date): string {
    return date.toISOString().slice(11, 16);
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

function mapLocationValue(value: unknown): string {
    if (!value || typeof value !== 'string') {
        return '';
    }

    const normalized = value.toLowerCase();
    const option = painLocationOptions.find((item) => item.value === normalized);
    return option ? option.value : '';
}

function setDateTimeFields(iso: unknown, type: 'start' | 'end') {
    if (!iso || typeof iso !== 'string') {
        if (type === 'start') {
            manualForm.startDate = '';
            manualForm.startTime = '';
        } else {
            manualForm.endDate = '';
            manualForm.endTime = '';
        }
        return;
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return;
    }

    if (type === 'start') {
        manualForm.startDate = formatDateForInput(date);
        manualForm.startTime = formatTimeForInput(date);
    } else {
        manualForm.endDate = formatDateForInput(date);
        manualForm.endTime = formatTimeForInput(date);
    }
}

function prefillStartTimeWithNow() {
    if (manualForm.startDate || manualForm.startTime) {
        return;
    }

    const now = new Date();
    manualForm.startDate = formatDateForInput(now);
    manualForm.startTime = formatTimeForInput(now);
}

function applySuggestion(payload: EpisodeSuggestion | null, transcript: string) {
    if (!payload) {
        return;
    }

    analysisState.suggestion = payload;
    analysisState.transcript = transcript;

    if (typeof payload.intensity === 'number') {
        manualForm.painLevel = Math.round(payload.intensity);
        manualForm.painLevelConfirmed = true;
    }

    const location = mapLocationValue(payload.pain_location);
    if (location) {
        manualForm.painLocation = location;
    }

    if (typeof payload.start_time === 'string' && payload.start_time.trim()) {
        setDateTimeFields(payload.start_time, 'start');
    } else {
        prefillStartTimeWithNow();
    }
    setDateTimeFields(payload.end_time ?? null, 'end');

    if (typeof payload.aura === 'boolean') {
        manualForm.aura = payload.aura;
    }

    if (Array.isArray(payload.triggers)) {
        manualForm.triggers = payload.triggers
            .map((item) => (typeof item === 'string' ? capitalize(item) : null))
            .filter((item): item is string => item !== null);
    }

    if (Array.isArray(payload.symptoms)) {
        manualForm.symptoms = payload.symptoms
            .map((item) => (typeof item === 'string' ? capitalize(item) : null))
            .filter((item): item is string => item !== null);
    }

    if (typeof payload.what_you_tried === 'string') {
        manualForm.whatYouTried = payload.what_you_tried;
    }

    if (typeof payload.notes === 'string') {
        manualForm.notes = payload.notes;
    }

    void attemptAutoSaveFromSuggestion();
}

async function attemptAutoSaveFromSuggestion() {
    if (
        !analysisState.suggestion ||
        !manualForm.audioClipId ||
        autoSaveAttempted.value ||
        analysisState.status !== 'transcribed'
    ) {
        return;
    }

    const suggestion = analysisState.suggestion;
    const hasStart = Boolean(combineDateTime(manualForm.startDate, manualForm.startTime));
    const hasIntensity = typeof suggestion.intensity === 'number';
    const hasLocation = Boolean(mapLocationValue(suggestion.pain_location));
    const hasTriggers =
        Array.isArray(suggestion.triggers) &&
        suggestion.triggers.some((item) => typeof item === 'string' && item.trim().length > 0);
    const hasSymptoms =
        Array.isArray(suggestion.symptoms) &&
        suggestion.symptoms.some((item) => typeof item === 'string' && item.trim().length > 0);

    if (hasStart && hasIntensity && hasLocation && hasTriggers && hasSymptoms) {
        autoSaveAttempted.value = true;
        await submitEpisode();
    }
}

function triggerSaveAnimation() {
    if (saveAnimationTimer) {
        window.clearTimeout(saveAnimationTimer);
        saveAnimationTimer = null;
    }

    showSaveAnimation.value = true;
    saveAnimationTimer = window.setTimeout(() => {
        showSaveAnimation.value = false;
        saveAnimationTimer = null;
    }, 1500);
}

function capitalize(value: string): string {
    return value
        .split(' ')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return 'Unknown';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return 'Unknown';
    }
    try {
        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            timeZone: userTimeZone.value || undefined,
        }).format(date);
    } catch {
        // Fallback to default locale/timezone formatting if something goes wrong
        return date.toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    }
}

function formatRelativeTimeFromNow(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const diffMs = Date.now() - date.getTime();
    if (diffMs < 60_000) {
        return 'just moments ago';
    }

    const minutes = Math.floor(diffMs / 60_000);
    if (minutes < 60) {
        return minutes === 1 ? 'about a minute ago' : `${minutes} minutes ago`;
    }

    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
        return hours === 1 ? 'about an hour ago' : `${hours} hours ago`;
    }

    const days = Math.floor(hours / 24);
    if (days === 1) {
        return 'yesterday';
    }

    return `${days} days ago`;
}

function formatDuration(start: string | null, end: string | null): string {
    if (!start || !end) return '—';
    const startDate = new Date(start);
    const endDate = new Date(end);
    if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
        return '—';
    }
    const minutes = Math.round((endDate.getTime() - startDate.getTime()) / 60000);
    if (minutes <= 0) return '—';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;
    return remaining ? `${hours}h ${remaining}m` : `${hours}h`;
}

function toMonthlyRate(total: number | null, rangeDays: number): number | null {
    if (total === null || rangeDays <= 0) {
        return null;
    }

    return (total / rangeDays) * 30;
}

function formatNumber(value: number, digits = 1): string {
    return Number.parseFloat(value.toFixed(digits)).toString();
}

function determineComparisonStatus(
    current: number | null,
    baseline: number | null,
    lowerIsBetter: boolean
): string {
    if (current === null || baseline === null) {
        return 'No data';
    }

    if (baseline === 0) {
        if (current === 0) {
            return 'On Track';
        }
        return lowerIsBetter ? 'Improved' : 'Needs Attention';
    }

    const diffPercent = ((current - baseline) / baseline) * 100;
    if (!Number.isFinite(diffPercent) || Math.abs(diffPercent) <= 5) {
        return 'On Track';
    }

    const improved = lowerIsBetter ? diffPercent < -5 : diffPercent > 5;
    return improved ? 'Improved' : 'Needs Attention';
}

function formatBaselineDelta(
    current: number | null,
    baseline: number | null,
    noun: string,
    lowerIsBetter: boolean
): string {
    if (current === null || baseline === null) {
        return 'We need a bit more data to compare.';
    }

    if (baseline === 0) {
        if (current === 0) {
            return 'In line with your baseline.';
        }
        const direction = lowerIsBetter ? 'above' : 'above';
        return `${formatNumber(current, 1)} ${noun} ${direction} baseline.`;
    }

    const difference = current - baseline;
    if (Math.abs(difference) < 0.05) {
        return 'In line with your typical baseline.';
    }

    const percentChange = Number.isFinite(baseline) ? (difference / baseline) * 100 : null;
    const improvement = lowerIsBetter ? difference < 0 : difference > 0;
    const directionWord = improvement ? 'fewer' : 'more';
    const magnitude = formatNumber(Math.abs(difference), 1);
    const percentText =
        percentChange !== null && Number.isFinite(percentChange) ?
            ` (${improvement ? '-' : '+'}${formatNumber(Math.abs(percentChange), 1)}%)` :
            '';

    return `${magnitude} ${directionWord} ${noun} than baseline${percentText}.`;
}

function formatRelativeDays(days: number | null): string {
    if (days === null) {
        return '';
    }

    if (days === 0) {
        return 'today';
    }

    if (days === 1) {
        return 'yesterday';
    }

    return `${days} days ago`;
}

const timelineEpisodes = computed(() =>
    episodes.value.map((episode) => {
        const dateKey = episode.start_time ? episode.start_time.slice(0, 10) : null;
        const isPeriodDay = dateKey ? periodDays.value.includes(dateKey) : false;

        return {
            ...episode,
            startFormatted: formatDateTime(episode.start_time),
            durationFormatted: formatDuration(episode.start_time, episode.end_time),
            intensityLabel: episode.intensity !== null ? `${episode.intensity}/10` : '—',
            isPeriodDay,
        };
    })
);

const visibleTimelineEpisodes = computed(() => {
    const list = timelineEpisodes.value;
    if (showAllEpisodes.value || list.length <= 5) return list;
    return list.slice(0, 5);
});

function startEditEpisode(episode: {
    id: number;
    start_time: string | null;
    end_time: string | null;
    intensity: number | null;
    pain_location: string | null;
    aura: boolean | null;
    symptoms: string[] | null;
    triggers: string[] | null;
    what_you_tried: string | null;
    notes: string | null;
}) {
    editingEpisodeId.value = episode.id;
    manualEntryOpen.value = true;
    manualAdvanced.value = true;
    submissionMessage.value = null;
    submissionError.value = null;
    manualForm.painLevel = typeof episode.intensity === 'number' ? episode.intensity : manualForm.painLevel;
    manualForm.painLevelConfirmed = typeof episode.intensity === 'number';
    manualForm.painLocation = episode.pain_location ?? '';
    manualForm.aura = typeof episode.aura === 'boolean' ? episode.aura : null;
    manualForm.triggers = Array.isArray(episode.triggers)
        ? (episode.triggers as string[]).map((t: string) => capitalize(t))
        : [];
    manualForm.symptoms = Array.isArray(episode.symptoms)
        ? (episode.symptoms as string[]).map((s: string) => capitalize(s))
        : [];
    manualForm.whatYouTried = episode.what_you_tried ?? '';
    manualForm.notes = episode.notes ?? '';
    setDateTimeFields(episode.start_time, 'start');
    setDateTimeFields(episode.end_time, 'end');

    nextTick(() => {
        manualEntryRef.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

function openDeleteModal(id: number) {
    episodeToDeleteId.value = id;
    deleteModalOpen.value = true;
}

function closeDeleteModal() {
    deleteModalOpen.value = false;
    episodeToDeleteId.value = null;
}

async function confirmDeleteEpisode() {
    if (!episodeToDeleteId.value) return;
    try {
        await axios.delete(`/episodes/${episodeToDeleteId.value}`);
        closeDeleteModal();
        void fetchEpisodes();
    } catch (error) {
        // silent error; could surface toast later
        closeDeleteModal();
    }
}

async function fetchClipAnalysis(id: number) {
    analysisState.loading = analysisState.status === 'idle';

    try {
        const { data } = await axios.get(`/audio-clips/${id}`);

        analysisState.status = data.status ?? 'unknown';
        analysisState.error = data.analysis_error ?? null;
        analysisState.transcript = data.transcript_text ?? '';

        if (analysisState.status === 'transcribed') {
            applySuggestion((data.structured_payload ?? null) as EpisodeSuggestion | null, analysisState.transcript);
            stopAnalysisPolling();
            voiceUploadMessage.value = 'Transcription ready. Review and confirm the details below.';
        } else if (analysisState.status === 'failed') {
            stopAnalysisPolling();
            voiceUploadMessage.value =
                'We could not analyze that recording. Please review the transcript or try logging again.';
        } else {
            scheduleNextAnalysisPoll(id);
        }
    } catch (error) {
        analysisState.error = 'Unable to fetch analysis right now.';
        scheduleNextAnalysisPoll(id);
    } finally {
        analysisState.loading = false;
    }
}

function startAnalysisPolling(id: number) {
    stopAnalysisPolling();
    analysisState.clipId = id;
    analysisState.status = 'processing';
    analysisState.transcript = '';
    analysisState.suggestion = null;
    analysisState.error = null;
    fetchClipAnalysis(id);
}

function scheduleNextAnalysisPoll(id: number) {
    stopAnalysisPolling();
    analysisState.pollTimer = setTimeout(() => fetchClipAnalysis(id), 3_000);
}

function stopAnalysisPolling() {
    if (analysisState.pollTimer) {
        clearTimeout(analysisState.pollTimer);
        analysisState.pollTimer = null;
    }
}

function ensurePainLevelTouched() {
    manualForm.painLevelConfirmed = true;
}

const missingFields = computed(() => {
    const fields: Array<{ key: string; label: string; suggestionAvailable: boolean }> = [];

    if (!combineDateTime(manualForm.startDate, manualForm.startTime)) {
        fields.push({
            key: 'start_time',
            label: 'When the episode started',
            suggestionAvailable: Boolean(analysisState.suggestion?.start_time),
        });
    }

    if (!manualForm.painLevelConfirmed) {
        fields.push({
            key: 'intensity',
            label: 'Pain intensity',
            suggestionAvailable: typeof analysisState.suggestion?.intensity === 'number',
        });
    }

    if (!manualForm.painLocation) {
        fields.push({
            key: 'pain_location',
            label: 'Pain location',
            suggestionAvailable: Boolean(analysisState.suggestion?.pain_location),
        });
    }

    const requiresTranscriptionExtras = Boolean(manualForm.audioClipId);

    if (requiresTranscriptionExtras && manualForm.triggers.length === 0) {
        const suggestionHasTriggers =
            Array.isArray(analysisState.suggestion?.triggers) &&
            analysisState.suggestion.triggers.some((item) => typeof item === 'string' && item.trim().length > 0);

        fields.push({
            key: 'triggers',
            label: 'Triggers',
            suggestionAvailable: suggestionHasTriggers,
        });
    }

    if (requiresTranscriptionExtras && manualForm.symptoms.length === 0) {
        const suggestionHasSymptoms =
            Array.isArray(analysisState.suggestion?.symptoms) &&
            analysisState.suggestion.symptoms.some((item) => typeof item === 'string' && item.trim().length > 0);

        fields.push({
            key: 'symptoms',
            label: 'Symptoms',
            suggestionAvailable: suggestionHasSymptoms,
        });
    }

    return fields;
});

function applySuggestionForField(key: string) {
    if (!analysisState.suggestion) {
        return;
    }

    switch (key) {
        case 'start_time':
            setDateTimeFields(analysisState.suggestion.start_time ?? null, 'start');
            break;
        case 'intensity':
            if (typeof analysisState.suggestion.intensity === 'number') {
                manualForm.painLevel = Math.round(analysisState.suggestion.intensity);
                manualForm.painLevelConfirmed = true;
            }
            break;
        case 'pain_location':
            manualForm.painLocation = mapLocationValue(analysisState.suggestion.pain_location ?? '');
            break;
        case 'aura':
            if (typeof analysisState.suggestion.aura === 'boolean') {
                manualForm.aura = analysisState.suggestion.aura;
            }
            break;
        case 'triggers':
            if (Array.isArray(analysisState.suggestion.triggers)) {
                manualForm.triggers = analysisState.suggestion.triggers
                    .map((item) => (typeof item === 'string' ? capitalize(item) : null))
                    .filter((item): item is string => item !== null);
            }
            break;
        case 'symptoms':
            if (Array.isArray(analysisState.suggestion.symptoms)) {
                manualForm.symptoms = analysisState.suggestion.symptoms
                    .map((item) => (typeof item === 'string' ? capitalize(item) : null))
                    .filter((item): item is string => item !== null);
            }
            break;
        default:
            break;
    }
}

function normalizeArrayForSubmit(values: string[]): string[] | null {
    const normalized = values
        .map((value) => value.trim())
        .filter((value) => value.length > 0)
        .map((value) => value.toLowerCase());

    return normalized.length ? normalized : null;
}

const canSubmitEpisode = computed(() => missingFields.value.length === 0 && !isSubmittingEpisode.value);

function reapplySuggestion() {
    if (analysisState.suggestion) {
        autoSaveAttempted.value = false;
        applySuggestion(analysisState.suggestion, analysisState.transcript);
    }
}

async function submitEpisode() {
    submissionError.value = null;
    submissionMessage.value = null;

    const requiredKeys = ['start_time', 'intensity', 'pain_location'];
    if (manualForm.audioClipId) {
        requiredKeys.push('triggers', 'symptoms');
    }

    const requiredMissing = missingFields.value.filter((field) => requiredKeys.includes(field.key));

    if (requiredMissing.length > 0) {
        submissionError.value = `Please confirm ${requiredMissing.map((field) => field.label.toLowerCase()).join(', ')} before saving.`;
        return;
    }

    const startIso = combineDateTime(manualForm.startDate, manualForm.startTime);
    const endIso = combineDateTime(manualForm.endDate, manualForm.endTime);
    const intensity = manualForm.painLevelConfirmed ? Math.round(manualForm.painLevel) : null;

    const payload = {
        audio_clip_id: manualForm.audioClipId,
        start_time: startIso,
        end_time: endIso,
        intensity,
        pain_location: manualForm.painLocation || null,
        aura: manualForm.aura,
        triggers: normalizeArrayForSubmit(manualForm.triggers),
        symptoms: normalizeArrayForSubmit(manualForm.symptoms),
        what_you_tried:
            manualForm.whatYouTried ||
            (manualForm.medications.length ? `Medications: ${manualForm.medications.join(', ')}` : null),
        notes: manualForm.notes || null,
        transcript_text: analysisState.transcript || null,
        extraction_confidences: analysisState.suggestion?.confidence_breakdown ?? null,
    };

    isSubmittingEpisode.value = true;
    try {
        if (editingEpisodeId.value) {
            await axios.put(`/episodes/${editingEpisodeId.value}`, payload);
            submissionMessage.value = 'Episode updated successfully.';
        } else {
            await axios.post('/episodes', payload);
            submissionMessage.value = 'Episode saved successfully.';
        }
        voiceUploadMessage.value = 'Episode logged. Thanks for keeping us updated.';
        manualForm.audioClipId = payload.audio_clip_id ?? null;
        if (manualForm.audioClipId) {
            triggerSaveAnimation();
        }
        editingEpisodeId.value = null;
        manualEntryOpen.value = false;
        await fetchEpisodes();
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.data?.message) {
            submissionError.value = error.response.data.message;
        } else {
            submissionError.value = 'Something went wrong while saving. Please try again.';
        }
    } finally {
        isSubmittingEpisode.value = false;
    }
}

const episodesSummaryLoading = computed(() => episodesLoading.value && episodes.value.length === 0);

async function fetchEpisodes() {
    console.log('fetchEpisodes called with range:', episodeRange.value);
    episodesLoading.value = true;
    episodesError.value = null;

    try {
        // Use the CSRF token from the page meta tag
        const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        console.log('CSRF token found:', !!csrfToken);
        
        if (!csrfToken) {
            episodesError.value = 'Authentication token missing. Please refresh the page.';
            return;
        }

        // Ensure proper headers for mobile compatibility
        const headers = {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };
        
        // Also set the global axios defaults to ensure consistency
        if (window.axios?.defaults?.headers?.common) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
            window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        }

        console.log('Making API request for range:', episodeRange.value);
        
        // Use mobile-specific endpoint for mobile browsers
        const isMobile = /Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        const episodesUrl = isMobile ? '/episodes-mobile' : '/episodes';
        
        const requestCurrent = () => axios.get(episodesUrl, { 
            params: { range: episodeRange.value },
            headers
        });
        const requestBaseline = () => axios.get(episodesUrl, { 
            params: { range: baselineRangeDays },
            headers
        });

        let currentResult;
        try {
            currentResult = await requestCurrent();
            console.log('API response received for range:', episodeRange.value, 'data:', currentResult.data);
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 401) {
                console.error('Authentication failed when fetching episodes');
                
                // For mobile devices, suggest a page refresh to restore session
                if (/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                    episodesError.value = 'Session expired on mobile. Tap here to refresh the page.';
                    
                    // Add click handler to refresh page
                    setTimeout(() => {
                        const errorElement = document.querySelector('.episode-error-message');
                        if (errorElement) {
                            errorElement.style.cursor = 'pointer';
                            errorElement.onclick = () => window.location.reload();
                        }
                    }, 100);
                } else {
                    episodesError.value = 'Session expired. Please refresh the page to continue.';
                }
                return;
            }

            console.error('Failed to fetch current episodes:', error);
            episodesError.value =
                axios.isAxiosError(error) && error.response?.data?.message ?
                    error.response.data.message :
                    'Unable to load your episode history right now.';
            return;
        }

        let baselineResult: Awaited<ReturnType<typeof requestBaseline>> | null = null;
        try {
            baselineResult = await requestBaseline();
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 401) {
                console.error('Authentication failed when fetching baseline episodes');
            } else {
                console.warn('Failed to fetch baseline episodes:', error);
            }
        }

        if (currentResult) {
            console.log('Applying episode insights payload for range:', episodeRange.value);
            applyEpisodeInsightsPayload(currentResult.data);
            showAllEpisodes.value = false;
        }

        if (baselineResult) {
            const baselineData = baselineResult.data;
            baselineSummary.total_episodes = baselineData.summary?.total_episodes ?? 0;
            baselineSummary.average_intensity = baselineData.summary?.average_intensity ?? null;
            baselineSummary.total_duration_hours = baselineData.summary?.total_duration_hours ?? 0;
            baselineSummary.pain_free_days_percent = baselineData.summary?.pain_free_days_percent ?? null;
        }

        updateEpisodeEndPromptTarget();
    } catch (error) {
        console.error('Error in fetchEpisodes:', error);
        if (axios.isAxiosError(error) && error.response?.status === 401) {
            return;
        } else {
            episodesError.value =
                axios.isAxiosError(error) && error.response?.data?.message ?
                    error.response.data.message :
                    'Unable to load your episode history right now.';
        }
    } finally {
        episodesLoading.value = false;
    }
}

watch(
    episodes,
    () => {
        updateEpisodeEndPromptTarget();
    },
    { deep: true }
);

watch(
    episodeEndPromptEpisode,
    () => {
        episodeEndHours.value = 1;
        episodeEndPromptState.selectedMinutes = 60;
        episodeEndPromptState.error = null;
    }
);

watch(
    () => manualEntryOpen.value,
    (open) => {
        if (!open) {
            stopAnalysisPolling();
            showTranscript.value = false;
        } else if (analysisState.clipId && analysisState.status !== 'transcribed') {
            startAnalysisPolling(analysisState.clipId);
        }
    }
);

onBeforeUnmount(() => {
    stopAnalysisPolling();
    if (saveAnimationTimer) {
        window.clearTimeout(saveAnimationTimer);
        saveAnimationTimer = null;
    }
    if (episodePromptHideTimer) {
        window.clearTimeout(episodePromptHideTimer);
        episodePromptHideTimer = null;
    }
});

onMounted(() => {
    setTimeout(() => {
        console.log('Fetching episodes on mount');
        void fetchEpisodes();
    }, 250);
});

const trackingStats = [
    { label: 'Episodes', value: '0', helper: 'This week', icon: 'episodes' },
    { label: 'Avg Intensity', value: '4.1/10', helper: '7-day trend', icon: 'intensity' },
    { label: 'Days Since Last', value: '34', helper: 'Migraine-free streak', icon: 'streak' },
] as const;

const baselineRangeDays = 90;
const baselineSummary = reactive({
    total_episodes: 0,
    average_intensity: null as number | null,
    total_duration_hours: 0,
    pain_free_days_percent: null as number | null,
});

const showPreventiveTip = ref(true);

const baselineComparisons = computed(() => {
    const monthlyBaselineEpisodes = toMonthlyRate(baselineSummary.total_episodes, baselineRangeDays);
    const monthlyCurrentEpisodes = toMonthlyRate(summary.total_episodes, episodeRange.value);
    const baselineIntensity = baselineSummary.average_intensity;
    const currentIntensity = summary.average_intensity;

    return [
        {
            key: 'episodes',
            label: 'Episodes / Month',
            baseline: monthlyBaselineEpisodes !== null ? `${formatNumber(monthlyBaselineEpisodes, 1)}/mo` : 'N/A',
            current: monthlyCurrentEpisodes !== null ? `${formatNumber(monthlyCurrentEpisodes, 1)}/mo` : 'N/A',
            status: determineComparisonStatus(monthlyCurrentEpisodes, monthlyBaselineEpisodes, true),
            caption: formatBaselineDelta(monthlyCurrentEpisodes, monthlyBaselineEpisodes, 'episodes per month', true),
        },
        {
            key: 'intensity',
            label: 'Average Intensity',
            baseline: baselineIntensity !== null ? `${formatNumber(baselineIntensity, 1)}/10` : 'N/A',
            current: currentIntensity !== null ? `${formatNumber(currentIntensity, 1)}/10` : 'N/A',
            status: determineComparisonStatus(currentIntensity, baselineIntensity, true),
            caption: formatBaselineDelta(currentIntensity, baselineIntensity, 'intensity', true),
        },
    ];
});

const migraineWeekSummary = computed(() => {
    const now = new Date();
    const windowStart = new Date(now);
    windowStart.setDate(now.getDate() - 6);

    let weekEpisodes = 0;
    let mostRecentEpisodeDate: Date | null = null;

    episodes.value.forEach((episode) => {
        if (!episode.start_time) {
            return;
        }
        const start = new Date(episode.start_time);
        if (!Number.isNaN(start.getTime())) {
            if (!mostRecentEpisodeDate || start > mostRecentEpisodeDate) {
                mostRecentEpisodeDate = start;
            }
            if (start >= windowStart && start <= now) {
                weekEpisodes += 1;
            }
        }
    });

    const isFree = weekEpisodes === 0;
    const daysSinceLast = mostRecentEpisodeDate
        ? Math.floor((now.getTime() - (mostRecentEpisodeDate as Date).getTime()) / 86_400_000)
        : null;

    return {
        isFree,
        title: isFree ? 'Migraine-Free Week!' : 'This Week at a Glance',
        helper: isFree ?
            'Great progress! You haven\'t logged any episodes in the past 7 days.' :
            `You logged ${weekEpisodes} episode${weekEpisodes === 1 ? '' : 's'} in the past 7 days.`,
        subtext:
            daysSinceLast === null ?
                'Keep logging your symptoms to build your personal history.' :
                `Last episode was ${formatRelativeDays(daysSinceLast)}.`,
    };
});

const medicalProfile = [
    'Attack duration: 6 hours',
    'Pain location: unilateral',
    'Experiences aura symptoms',
];

</script>

<template>
    <Head title="Dashboard" />

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
                    <span class="breadcrumb-current">Dashboard</span>
                </div>

                <div class="toolbar-actions">
                    <button
                        type="button"
                        class="stats-action"
                        title="Need a quick tour?"
                        @click="startDashboardTour"
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
                <transition name="slide-fade">
                    <section v-if="episodeEndPromptEpisode" class="episode-end-card">
                        <div class="episode-end-card-glow"></div>
                        <div v-if="episodeEndPromptState.showThanks" class="episode-end-message">
                            <p class="episode-end-title">Thank you!</p>
                            <p class="episode-end-meta">We’ve closed the episode and updated your timeline.</p>
                        </div>
                        <div v-else class="episode-end-content">
                            <header class="episode-end-header">
                                <div>
                                    <p class="episode-end-eyebrow">Finish this entry</p>
                                    <h2>How long did your migraine last?</h2>
                                    <p class="episode-end-meta">
                                        Started {{ formatRelativeTimeFromNow(episodeEndPromptEpisode.start_time) }}
                                        • {{ formatDateTime(episodeEndPromptEpisode.start_time) }}
                                    </p>
                                </div>
                                <button type="button" class="episode-end-dismiss" @click="skipEpisodeEndPrompt">
                                    Skip for now
                                </button>
                            </header>

                            <div class="episode-end-slider">
                                <div class="episode-end-slider-value">
                                    {{ episodeEndHours }} {{ episodeEndHours === 1 ? 'hr' : 'hrs' }}
                                </div>
                                <input
                                    class="episode-end-slider-input"
                                    type="range"
                                    min="1"
                                    max="24"
                                    step="1"
                                    v-model.number="episodeEndHours"
                                    @input="handleEpisodeSliderInput"
                                />
                                <div class="episode-end-slider-scale">
                                    <span>1 hr</span>
                                    <span>24 hrs</span>
                                </div>
                            </div>

                            <div class="episode-end-actions">
                                <div class="episode-end-hint">Help us capture how long the pain lasted.</div>
                                <button
                                    type="button"
                                    class="episode-end-save button-primary"
                                    :class="{ 'opacity-60': episodeEndPromptState.selectedMinutes === null }"
                                    :disabled="episodeEndPromptState.saving || episodeEndPromptState.selectedMinutes === null"
                                    @click="saveEpisodeEndDuration"
                                >
                                    {{ episodeEndPromptState.saving ? 'Saving…' : 'Save duration' }}
                                </button>
                            </div>
                            <p v-if="episodeEndPromptState.error" class="episode-end-error">
                                {{ episodeEndPromptState.error }}
                            </p>
                        </div>
                    </section>
                </transition>

                <section class="voice-card relative overflow-hidden rounded-[28px] border border-[--color-accent]/25 bg-[#111820] px-8 py-10" ref="voiceEntryRef">
                    <div class="absolute inset-0 rounded-[28px] border border-[--color-accent]/35 opacity-0 transition duration-200 voice-card-glow"></div>

                    <div class="flex flex-col gap-8">
                        <div class="space-y-2">
                            <p class="text-lg font-semibold text-white">Quick Voice Entry</p>
                            <p class="voice-subtitle">
                                Speak naturally about your migraine - our AI captures details and follows up in real time.
                            </p>
                            <p class="text-xs uppercase tracking-[0.22em] text-[--color-text-muted]">
                                Tap the mic and keep talking — we auto-stop after silence.
                            </p>
                        </div>

                        <RealtimeRecorder
                            @conversation-complete="handleRealtimeEpisode"
                            @error="handleVoiceError"
                        />

                        <p
                            v-if="voiceUploadMessage"
                            class="text-xs uppercase tracking-[0.22em] text-[--color-text-muted]"
                        >
                            {{ voiceUploadMessage }}
                        </p>

                        <button type="button" class="voice-manual-link" @click="openManualEntry">
                            Or use manual form instead →
                        </button>
                    </div>
                    <div v-if="showSaveAnimation" class="voice-card-saved">
                        <span>Episode saved</span>
                        <span class="voice-card-saved-subtext">Thanks for logging with your voice.</span>
                    </div>
                </section>

                <section v-if="manualEntryOpen" class="manual-card" ref="manualEntryRef">
                    <div class="manual-card-glow"></div>
                    <header class="manual-header">
                        <button type="button" class="manual-back" @click="closeManualEntry">
                            ← Back to voice entry
                        </button>
                        <button type="button" class="manual-close" @click="closeManualEntry">✕</button>
                    </header>

                    <div class="manual-body">
                        <div class="manual-title">
                            <span class="manual-flash">⚡</span>
                            <div>
                                <h3>Manual Episode Entry</h3>
                                <p class="manual-subtitle">Review the AI summary, confirm details, and save your episode.</p>
                            </div>
                        </div>

                        <div v-if="analysisState.status === 'processing'" class="manual-ai-card">
                            <p class="manual-ai-title">Analysing your clip…</p>
                            <p class="manual-ai-subtitle">We’re transcribing your voice note and extracting key fields.</p>
                        </div>

                        <div v-else-if="analysisState.status === 'failed'" class="manual-ai-card manual-ai-card--error">
                            <p class="manual-ai-title">We couldn’t auto-fill this entry</p>
                            <p class="manual-ai-subtitle">
                                {{ analysisState.error ?? 'Please review the form manually and fill in the details.' }}
                            </p>
                        </div>

                        <div v-else-if="analysisState.status === 'transcribed'" class="manual-ai-card manual-ai-card--ready">
                            <div class="flex flex-col gap-2">
                                <p class="manual-ai-title">AI suggestions ready</p>
                                <p class="manual-ai-subtitle">
                                    Confirm or adjust what we heard before saving.
                                </p>
                            </div>
                            <div class="manual-ai-actions">
                                <button type="button" class="manual-chip manual-chip--ghost" @click="reapplySuggestion">
                                    Reapply suggestions
                                </button>
                                <button type="button" class="manual-chip manual-chip--ghost" @click="showTranscript = !showTranscript">
                                    {{ showTranscript ? 'Hide transcript' : 'Show transcript' }}
                                </button>
                            </div>
                            <div v-if="showTranscript" class="manual-transcript">
                                <p>{{ analysisState.transcript || 'Transcript will appear once available.' }}</p>
                            </div>
                        </div>

                        <div v-if="missingFields.length" class="manual-followups">
                            <p class="manual-followups-title">We still need:</p>
                            <ul class="manual-followups-list">
                                <li v-for="field in missingFields" :key="field.key">
                                    <span>{{ field.label }}</span>
                                    <button
                                        v-if="field.suggestionAvailable"
                                        type="button"
                                        class="manual-chip manual-chip--ghost"
                                        @click="applySuggestionForField(field.key)"
                                    >
                                        Use AI suggestion
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div class="manual-section">
                            <div class="manual-section-header">
                                <span>When did it start?</span>
                            </div>
                            <div class="manual-section-grid">
                                <input v-model="manualForm.startDate" type="date" class="manual-input" />
                                <input v-model="manualForm.startTime" type="time" class="manual-input" />
                            </div>
                        </div>

                        <div class="manual-section">
                            <div class="manual-section-header">
                                <span>Pain Level</span>
                                <span class="manual-pain-value">{{ manualForm.painLevel }}/10</span>
                            </div>
                            <div class="manual-range">
                                <span class="manual-range-label">😊 Mild</span>
                                <input
                                    v-model.number="manualForm.painLevel"
                                    type="range"
                                    min="0"
                                    max="10"
                                    step="1"
                                    @input="ensurePainLevelTouched"
                                />
                                <span class="manual-range-label">😣 Severe</span>
                            </div>
                            <div class="manual-range-foot">
                                <span class="manual-pain-chip">{{ painLabel(manualForm.painLevel) }}</span>
                                <span class="manual-typical">Typical: {{ painTypical }}/10</span>
                            </div>
                        </div>

                        <div class="manual-section">
                            <div class="manual-section-header">
                                <span>Pain Location</span>
                            </div>
                            <div class="manual-chip-grid">
                                <button
                                    v-for="location in painLocationOptions"
                                    :key="location.value"
                                    type="button"
                                    class="manual-chip manual-chip--wide"
                                    :class="{ 'manual-chip--active': manualForm.painLocation === location.value }"
                                    @click="manualForm.painLocation = location.value"
                                >
                                    {{ location.label }}
                                </button>
                            </div>
                        </div>

                        <div class="manual-section">
                            <div class="manual-section-header">
                                <span>Aura</span>
                            </div>
                            <div class="manual-chip-row">
                                <button
                                    type="button"
                                    class="manual-chip"
                                    :class="{ 'manual-chip--active': manualForm.aura === true }"
                                    @click="manualForm.aura = true"
                                >
                                    Yes
                                </button>
                                <button
                                    type="button"
                                    class="manual-chip"
                                    :class="{ 'manual-chip--active': manualForm.aura === false }"
                                    @click="manualForm.aura = false"
                                >
                                    No
                                </button>
                                <button
                                    type="button"
                                    class="manual-chip manual-chip--ghost"
                                    :class="{ 'manual-chip--active': manualForm.aura === null }"
                                    @click="manualForm.aura = null"
                                >
                                    Not sure
                                </button>
                            </div>
                        </div>

                        <button type="button" class="manual-more-toggle" @click="toggleManualAdvanced">
                            Additional details
                            <span class="manual-toggle-icon" :class="{ 'manual-toggle-icon--open': manualAdvanced }">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                    <path d="m8 10 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                            </span>
                        </button>

                        <div v-if="manualAdvanced" class="manual-advanced">
                            <div class="manual-section">
                                <span class="manual-section-label">When did it end? (optional)</span>
                                <div class="manual-section-grid">
                                    <input v-model="manualForm.endDate" type="date" class="manual-input" />
                                    <input v-model="manualForm.endTime" type="time" class="manual-input" />
                                </div>
                            </div>

                            <div class="manual-section">
                                <span class="manual-section-label">Potential triggers</span>
                                <div class="manual-chip-row">
                                    <button
                                        v-for="option in triggerOptions"
                                        :key="option.label"
                                        type="button"
                                        class="manual-chip"
                                        :class="{ 'manual-chip--active': manualForm.triggers.includes(option.label) }"
                                        @click="toggleSelection(manualForm.triggers, option.label)"
                                    >
                                        <span>{{ option.icon }}</span>
                                        {{ option.label }}
                                    </button>
                                </div>
                            </div>

                            <div class="manual-section">
                                <span class="manual-section-label">Symptoms</span>
                                <div class="manual-chip-row">
                                    <button
                                        v-for="symptom in symptomOptions"
                                        :key="symptom.label"
                                        type="button"
                                        class="manual-chip"
                                        :class="{ 'manual-chip--active': manualForm.symptoms.includes(symptom.label) }"
                                        @click="toggleSelection(manualForm.symptoms, symptom.label)"
                                    >
                                        <span>{{ symptom.icon }}</span>
                                        {{ symptom.label }}
                                    </button>
                                </div>
                                <p class="manual-helper-text">Tracking symptoms improves diagnosis accuracy by 40%.</p>
                            </div>

                            <div class="manual-section">
                                <span class="manual-section-label">Medications taken</span>
                                <div class="manual-chip-row">
                                    <button
                                        v-for="med in medicationOptions"
                                        :key="med"
                                        type="button"
                                        class="manual-chip"
                                        :class="{ 'manual-chip--active': manualForm.medications.includes(med) }"
                                        @click="toggleSelection(manualForm.medications, med)"
                                    >
                                        {{ med }}
                                    </button>
                                    <button type="button" class="manual-chip manual-chip--ghost">
                                        + Custom
                                    </button>
                                </div>
                            </div>

                            <div class="manual-section">
                                <span class="manual-section-label">Impact on daily activities</span>
                                <div class="manual-impact-grid">
                                    <button
                                        v-for="impact in impactLevels"
                                        :key="impact.label"
                                        type="button"
                                        class="manual-impact"
                                        :class="{ 'manual-impact--active': manualForm.impact === impact.label }"
                                        @click="manualForm.impact = impact.label"
                                    >
                                        <span class="manual-impact-title">{{ impact.label }}</span>
                                        <span class="manual-impact-caption">{{ impact.description }}</span>
                                    </button>
                                </div>
                            </div>

                            <div class="manual-section">
                                <span class="manual-section-label">What you tried for relief</span>
                                <textarea
                                    v-model="manualForm.whatYouTried"
                                    rows="2"
                                    class="manual-textarea"
                                    placeholder="Medications, hydration, rest, etc."
                                ></textarea>
                            </div>

                            <div class="manual-section">
                                <span class="manual-section-label">Additional notes</span>
                                <textarea
                                    v-model="manualForm.notes"
                                    rows="3"
                                    class="manual-textarea"
                                    placeholder="What were you doing when it started? Any warning signs?"
                                ></textarea>
                            </div>
                        </div>

                        <div class="manual-actions">
                            <p v-if="submissionMessage" class="manual-success">{{ submissionMessage }}</p>
                            <p v-if="submissionError" class="manual-error">{{ submissionError }}</p>
                            <button
                                type="button"
                                class="manual-submit button-primary"
                                :class="{ 'opacity-60': !canSubmitEpisode }"
                                :disabled="isSubmittingEpisode || missingFields.length > 0"
                                @click="submitEpisode"
                            >
                                {{ isSubmittingEpisode ? 'Saving…' : 'Save Episode' }}
                            </button>
                            <button type="button" class="manual-cancel" @click="closeManualEntry">
                                Cancel
                            </button>
                        </div>
                    </div>
                </section>

                <section class="episode-insights glass-panel px-8 py-6">
                    <header class="episode-header">
                        <div>
                            <h2>Episode Insights</h2>
                            <p>Track trends and browse your recent migraine logs.</p>
                        </div>
                        <div class="episode-filters">
                            <button
                                v-for="range in [7, 30, 90]"
                                :key="range"
                                type="button"
                                class="episode-filter"
                                :class="episodeRange === range ? 'episode-filter--active' : ''"
                                @click="setEpisodeRange(range)"
                                @touchend.prevent="setEpisodeRange(range)"
                            >
                                Last {{ range }} days
                            </button>
                        </div>
                    </header>

                    <div class="episode-summary-grid">
                        <div class="episode-summary-card">
                            <span class="episode-summary-label">Total episodes</span>
                            <span class="episode-summary-value">{{ summary.total_episodes }}</span>
                        </div>
                        <div class="episode-summary-card">
                            <span class="episode-summary-label">Avg intensity</span>
                            <span class="episode-summary-value">
                                {{ summary.average_intensity !== null ? `${summary.average_intensity}/10` : '—' }}
                            </span>
                        </div>
                        <div class="episode-summary-card">
                            <span class="episode-summary-label">Logged duration</span>
                            <span class="episode-summary-value">{{ summary.total_duration_hours }}h</span>
                        </div>
                        <div class="episode-summary-card">
                            <span class="episode-summary-label">Pain-free days</span>
                            <span class="episode-summary-value">
                                {{ summary.pain_free_days_percent !== null ? `${summary.pain_free_days_percent}%` : '—' }}
                            </span>
                        </div>
                    </div>

                    <div class="episode-chart-grid" ref="chartsRef">
                        <div class="episode-chart">
                            <div class="episode-chart-header">
                                <span>Attack count</span>
                            </div>
                            <SparklineChart :points="sparklines.attack_count" value-key="count" />
                        </div>
                        <div class="episode-chart">
                            <div class="episode-chart-header">
                                <span>Avg intensity trend</span>
                            </div>
                            <SparklineChart
                                :points="sparklines.average_intensity"
                                value-key="average_intensity"
                                stroke="#4CCBA9"
                            />
                        </div>
                    </div>

                    <div class="episode-timeline" ref="timelineRef">
                        <div v-if="periodDays.length" class="episode-legend">
                            <span class="episode-pill period-pill">Period day</span>
                            <p>Episodes overlapping period days help you spot cycle-related patterns.</p>
                        </div>
                        <div v-if="episodesSummaryLoading" class="episode-state">Loading your recent episodes…</div>
                        <div v-else-if="episodesError" class="episode-state episode-state--error episode-error-message">{{ episodesError }}</div>
                        <div v-else-if="!timelineEpisodes.length" class="episode-state">
                            No episodes logged in this range yet. Voice log or add one manually to start seeing trends.
                        </div>
                        <ul v-else class="episode-list">
                            <li
                                v-for="episode in visibleTimelineEpisodes"
                                :key="episode.id"
                                class="episode-list-item"
                                :class="{ 'episode-list-item--period': episode.isPeriodDay }"
                            >
                                <div class="episode-list-meta">
                                    <span class="episode-list-date">{{ episode.startFormatted }}</span>
                                    <span class="episode-list-duration">Duration: {{ episode.durationFormatted }}</span>
                                    <span v-if="episode.isPeriodDay" class="episode-list-pill period-pill">Period day</span>
                                    <span class="episode-actions">
                                        <button
                                            type="button"
                                            class="episode-action"
                                            title="Edit"
                                            @click="startEditEpisode(episode)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                                <path d="M4 20h4l10-10-4-4L4 16v4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="episode-action episode-action--danger"
                                            title="Delete"
                                            @click="openDeleteModal(episode.id)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                                <path d="M6 7h12M10 7v11m4-11v11M9 7l1-2h4l1 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                    </span>
                                </div>
                                <div class="episode-list-body">
                                    <div class="episode-list-stat">
                                        <span>Intensity</span>
                                        <strong>{{ episode.intensityLabel }}</strong>
                                    </div>
                                    <div class="episode-list-stat">
                                        <span>Location</span>
                                        <strong>{{ episode.pain_location ?? '—' }}</strong>
                                    </div>
                                    <div class="episode-list-pillgroup">
                                        <span v-if="episode.symptoms?.length" class="episode-pill">
                                            Symptoms: {{ episode.symptoms.join(', ') }}
                                        </span>
                                        <span v-if="episode.triggers?.length" class="episode-pill">
                                            Triggers: {{ episode.triggers.join(', ') }}
                                        </span>
                                        <span v-if="episode.aura !== null && shouldShowAuraLabel" class="episode-pill">
                                            Aura: {{ episode.aura ? 'Yes' : 'No' }}
                                        </span>
                                    </div>
                                </div>
                                <p v-if="episode.notes" class="episode-list-notes">{{ episode.notes }}</p>
                            </li>
                        </ul>
                        <div v-if="timelineEpisodes.length > 5" class="episode-view-toggle">
                            <button type="button" class="stats-action" @click="showAllEpisodes = !showAllEpisodes">
                                {{ showAllEpisodes ? 'View Less' : 'View More' }}
                            </button>
                        </div>
                    </div>
                </section>
                <transition name="slide-fade">
                    <div v-if="deleteModalOpen" class="modal-backdrop" @click.self="closeDeleteModal">
                        <div class="modal">
                            <div class="modal-header">
                                <p class="modal-title">Delete Episode</p>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete this episode? This action cannot be undone.</p>
                            </div>
                            <div class="modal-actions">
                                <button type="button" class="stats-action" @click="closeDeleteModal">Cancel</button>
                                <button type="button" class="stats-action modal-danger" @click="confirmDeleteEpisode">Delete</button>
                            </div>
                        </div>
                    </div>
                </transition>

                <!-- <section class="space-y-6">
                    <div class="stats-panel">
                        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                            <div class="stats-row">
                                <div
                                    v-for="stat in trackingStats"
                                    :key="stat.label"
                                    class="card-surface"
                                >
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs uppercase tracking-wide text-[--color-text-muted]">{{ stat.label }}</p>
                                        <span class="flex size-8 items-center justify-center rounded-xl bg-[--color-accent]/12 text-[--color-accent]">
                                            <svg v-if="stat.icon === 'episodes'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                                <path d="M4 18.5V6a2 2 0 0 1 2-2h7.5l6 6v8.5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z" stroke="currentColor" stroke-width="1.5"/>
                                                <path d="M13 4v4a2 2 0 0 0 2 2h4" stroke="currentColor" stroke-width="1.5"/>
                                            </svg>
                                        <svg v-else-if="stat.icon === 'intensity'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                            <path d="m4 15 4-4 3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M20 19H4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                            <path d="M7 3v3m10-3v3M5 8h14M6 12h4m4 0h4M6 16h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            <rect x="4" y="5" width="16" height="15" rx="2" stroke="currentColor" stroke-width="1.5"/>
                                        </svg>
                                    </span>
                                </div>
                                <p class="mt-3 text-2xl font-semibold text-[--color-text-primary]">{{ stat.value }}</p>
                                <p class="mt-1 text-xs text-[--color-text-muted]">{{ stat.helper }}</p>
                            </div>
                        </div>

                            <button type="button" class="stats-action">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                    <path d="m10 7 5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                View Analytics
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="showPreventiveTip"
                        class="card-surface card-surface--highlight flex items-start justify-between gap-6"
                    >
                        <div class="flex items-start gap-4">
                            <span class="mt-1 flex size-9 items-center justify-center rounded-xl bg-[--color-accent]/15 text-[--color-accent]">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none">
                                    <path d="M12 5v6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <path d="M8 8v4a4 4 0 0 0 8 0V8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <path d="M5 12v.5a7 7 0 0 0 14 0V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-[--color-text-primary]">Daily Preventive Tip</p>
                                <p class="mt-1 text-xs font-semibold text-[--color-text-primary]">Stay hydrated</p>
                                <p class="mt-1 text-xs text-[--color-text-muted]">
                                    Aim for 8 glasses of water today. Dehydration is a common migraine trigger.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="text-[--color-text-muted] transition hover:text-[--color-text-primary]"
                            @click="showPreventiveTip = false"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                                <path d="m15 9-6 6m0-6 6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </section> -->

                <!-- <section class="baseline-grid">
                    <div class="baseline-card">
                        <div class="baseline-card-glow"></div>
                        <header class="baseline-card-header">
                            <div class="baseline-card-title">
                                <span class="baseline-card-icon">〰️</span>
                                <h3>Baseline Comparison</h3>
                            </div>
                            <span class="baseline-badge baseline-badge--subtle">Updated</span>
                        </header>

                        <div class="baseline-metrics">
                            <article
                                v-for="(item, index) in baselineComparisons"
                                :key="item.label"
                                class="baseline-metric"
                            >
                                <div class="baseline-metric-header">
                                    <div class="baseline-metric-title">
                                        <span class="baseline-metric-icon">〽️</span>
                                        <span>{{ item.label }}</span>
                                    </div>
                                    <span class="baseline-badge baseline-badge--positive">{{ item.status }}</span>
                                </div>

                                <div class="baseline-metric-grid">
                                    <div>
                                        <span class="baseline-label">Your Baseline</span>
                                        <span class="baseline-value">{{ item.baseline }}</span>
                                    </div>
                                    <div>
                                        <span class="baseline-label">Current Month</span>
                                        <span class="baseline-value">{{ item.current }}</span>
                                    </div>
                                </div>

                                <div v-if="index === 0" class="baseline-progress">
                                    <span></span>
                                </div>

                                <p v-if="item.caption" class="baseline-caption">{{ item.caption }}</p>
                            </article>
                        </div>

                        <div class="baseline-insight">
                            <span class="baseline-insight-icon">💡</span>
                            <div>
                                <p class="baseline-insight-title">Personalized Insight</p>
                                <p class="baseline-insight-text">
                                    Great news! You’re having fewer episodes than typical. Keep tracking what’s working for you.
                                </p>
                            </div>
                        </div>

                        <div class="baseline-profile">
                            <p class="baseline-profile-title">Medical Profile</p>
                            <ul>
                                <li v-for="profile in medicalProfile" :key="profile">{{ profile }}</li>
                            </ul>
                        </div>
                    </div>

                    <div class="baseline-card baseline-card--minimal">
                        <header class="baseline-card-header">
                            <div class="baseline-card-title">
                                <span class="baseline-card-icon">✅</span>
                                <h3>{{ migraineWeekSummary.title }}</h3>
                            </div>
                        </header>
                        <p class="baseline-migraine-text">
                            {{ migraineWeekSummary.helper }}
                        </p>
                        <p
                            v-if="migraineWeekSummary.subtext"
                            class="mt-2 text-xs text-[--color-text-muted]"
                        >
                            {{ migraineWeekSummary.subtext }}
                        </p>
                        <Link href="/analytics" class="baseline-migraine-action">View Full Analytics</Link>
                    </div>
                </section> -->
                <Link href="/analytics" class="baseline-migraine-action text-align-center">View Full Analytics</Link>
            </div>
        </main>
    </div>
</template>
