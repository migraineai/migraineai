<script lang="ts" setup>
import { computed, ref, watch } from 'vue';
import type { OnboardingQuestion, OnboardingSection } from '@/Utils/onboarding';
import { isOnboardingQuestionAnswered } from '@/Utils/onboarding';

const MENSTRUAL_TRACKING_QUESTION_ID = 'q11_track_periods';

const props = defineProps<{
    sections: OnboardingSection[];
    answers: Record<string, unknown>;
    gender: string;
    onAnswer: (questionId: string, answer: unknown) => void;
}>();

const baseQuestions = computed(() => props.sections.flatMap((section) => section.questions));

const menstrualTrackingQuestion: OnboardingQuestion = {
    id: MENSTRUAL_TRACKING_QUESTION_ID,
    question_text: 'Do you want to track your menstrual cycle/periods?',
    ui_type: 'boolean',
    options: [
        { label: 'Yes, I want to track my menstrual cycle', value: true },
        { label: 'No, not right now', value: false },
    ],
};

const questions = computed(() => {
    const list = [...baseQuestions.value];

    if (props.gender === 'female') {
        const triggersIndex = list.findIndex((question) => question.id === 'q11_triggers');

        if (triggersIndex >= 0) {
            list.splice(triggersIndex + 1, 0, menstrualTrackingQuestion);
        } else if (!list.some((question) => question.id === MENSTRUAL_TRACKING_QUESTION_ID)) {
            list.push(menstrualTrackingQuestion);
        }
    }

    return list;
});

const currentQuestionIndex = ref(0);
const totalQuestions = computed(() => questions.value.length);

watch(
    () => questions.value.length,
    (length) => {
        if (currentQuestionIndex.value >= length) {
            currentQuestionIndex.value = Math.max(0, length - 1);
        }
    }
);

const currentQuestion = computed(() => questions.value[currentQuestionIndex.value]);
const progressPercent = computed(() => {
    if (!totalQuestions.value) {
        return 0;
    }

    return ((currentQuestionIndex.value + 1) / totalQuestions.value) * 100;
});

const answeredQuestions = computed(() =>
    questions.value.filter((question) => isOnboardingQuestionAnswered(question, props.answers)).length
);

function getAnswer(questionId: string) {
    return props.answers?.[questionId];
}

function isOptionSelected(questionId: string, optionValue: string | boolean | number) {
    const current = getAnswer(questionId);
    return Array.isArray(current) && current.includes(optionValue);
}

function handleSingleSelect(questionId: string, optionValue: string | boolean | number) {
    props.onAnswer(questionId, optionValue);
}

function handleMultiSelect(questionId: string, optionValue: string | boolean | number) {
    const existing = Array.isArray(getAnswer(questionId)) ? [...(getAnswer(questionId) as Array<string | boolean | number>)] : [];
    const optionIndex = existing.indexOf(optionValue);

    if (optionIndex >= 0) {
        existing.splice(optionIndex, 1);
    } else {
        existing.push(optionValue);
    }

    props.onAnswer(questionId, existing);
}

function handleSlider(questionId: string, value: string) {
    props.onAnswer(questionId, Number(value));
}

function getSliderLabel(question: OnboardingQuestion) {
    const rawValue = getAnswer(question.id);

    if (rawValue === null || rawValue === undefined || rawValue === '') {
        return question.min ?? 0;
    }

    return Number(rawValue);
}

function getConditionalAnswer(questionId: string) {
    const existing = getAnswer(questionId);
    if (existing && typeof existing === 'object') {
        return existing as { takes_preventive: boolean | null; details: string };
    }

    return { takes_preventive: null, details: '' };
}

function handleConditional(questionId: string, takesPreventive: boolean | null, details?: string) {
    const existing = getConditionalAnswer(questionId);
    props.onAnswer(questionId, {
        ...existing,
        takes_preventive: takesPreventive ?? existing.takes_preventive,
        details: details ?? existing.details,
    });
}

function goPrevious() {
    if (currentQuestionIndex.value > 0) {
        currentQuestionIndex.value -= 1;
    }
}

function goNext() {
    if (currentQuestionIndex.value < totalQuestions.value - 1) {
        currentQuestionIndex.value += 1;
    }
}
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-2 rounded-3xl border border-[--color-border] bg-[--color-bg] p-6">
            <div class="h-1 w-full overflow-hidden rounded-full bg-[--color-border-strong]">
                <div class="h-full rounded-full bg-[--color-accent]" :style="{ width: `${progressPercent}%` }"></div>
            </div>

            <div class="text-center">
                <p class="text-xs uppercase tracking-[0.5em] text-[--color-text-muted]">
                    Step {{ currentQuestionIndex + 1 }} of {{ totalQuestions }}
                </p>
            </div>

            <div class="flex flex-col items-center gap-2 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[--color-accent]/20 text-[--color-accent]">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5">
                        <path
                            d="M8 7h8M8 11h8M8 15h4M19 5h-3V4a1 1 0 0 0-1-1h-6a1 1 0 0 0-1 1v1H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1z"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>
                <p class="text-xl font-semibold text-[--color-text-primary]" v-if="currentQuestion">
                    {{ currentQuestion.question_text }}
                </p>
            </div>

            <div class="text-center text-xs text-[--color-text-muted]" v-if="currentQuestion">
                {{ answeredQuestions }} / {{ totalQuestions }} answered
            </div>
        </div>

        <div v-if="currentQuestion" class="rounded-3xl border border-[--color-border] bg-[--color-bg-alt]/90 p-6">
            <div class="space-y-4">
                <template v-if="currentQuestion.ui_type === 'single_select'">
                    <label
                        v-for="option in currentQuestion.options ?? []"
                        :key="option.value"
                        class="flex cursor-pointer items-center gap-3 rounded-2xl border border-[--color-border] px-4 py-3 text-sm transition hover:border-[--color-accent]"
                    >
                        <input
                            type="radio"
                            :name="currentQuestion.id"
                            :value="option.value"
                            :checked="getAnswer(currentQuestion.id) === option.value"
                            class="accent-[--color-accent]"
                            @change="handleSingleSelect(currentQuestion.id, option.value)"
                        />
                        <span>{{ option.label }}</span>
                    </label>
                </template>

                <template v-else-if="currentQuestion.ui_type === 'multi_select'">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="option in currentQuestion.options ?? []"
                            :key="option.value"
                            class="flex cursor-pointer items-center gap-3 rounded-2xl border border-[--color-border] px-4 py-3 text-sm transition hover:border-[--color-accent]"
                        >
                            <input
                                type="checkbox"
                                :value="option.value"
                                :checked="isOptionSelected(currentQuestion.id, option.value)"
                                class="accent-[--color-accent]"
                                @change="handleMultiSelect(currentQuestion.id, option.value)"
                            />
                            <span>{{ option.label }}</span>
                        </label>
                    </div>
                </template>

                <template v-else-if="currentQuestion.ui_type === 'boolean'">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="option in currentQuestion.options ?? []"
                            :key="String(option.value)"
                            class="flex cursor-pointer items-center justify-center gap-3 rounded-2xl border border-[--color-border] px-4 py-3 text-sm font-semibold transition hover:border-[--color-accent]"
                        >
                            <input
                                type="radio"
                                :name="currentQuestion.id"
                                :value="option.value"
                                :checked="getAnswer(currentQuestion.id) === option.value"
                                class="accent-[--color-accent]"
                                @change="handleSingleSelect(currentQuestion.id, option.value)"
                            />
                            <span>{{ option.label }}</span>
                        </label>
                    </div>
                </template>

                <template v-else-if="currentQuestion.ui_type === 'slider'">
                    <div class="space-y-3">
                        <div class="flex justify-center mb-1">
                            <span class="text-2xl font-bold text-[--color-accent]">{{ getSliderLabel(currentQuestion) }}</span>
                        </div>
                        <input
                            type="range"
                            :min="currentQuestion.min ?? 0"
                            :max="currentQuestion.max ?? 10"
                            :step="currentQuestion.step ?? 1"
                            :value="getSliderLabel(currentQuestion)"
                            class="h-2 w-full accent-[--color-accent]"
                            @input="handleSlider(currentQuestion.id, $event.target.value)"
                        />
                        <div class="flex justify-between text-xs text-[--color-text-muted]">
                            <span>{{ currentQuestion.min ?? 0 }}</span>
                            <span>{{ currentQuestion.max ?? 10 }}</span>
                        </div>
                    </div>
                </template>

                <template v-else-if="currentQuestion.ui_type === 'voice_input_conditional'">
                    <div class="space-y-3">
                        <div class="flex gap-3">
                            <button
                                type="button"
                                class="flex-1 rounded-2xl border border-[--color-border] px-4 py-3 text-sm font-medium transition hover:border-[--color-accent]"
                                :class="{
                                    'border-[--color-accent] bg-[--color-accent]/10 text-[--color-accent]':
                                        getConditionalAnswer(currentQuestion.id).takes_preventive === true,
                                }"
                                @click="handleConditional(currentQuestion.id, true)"
                            >
                                Yes
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-2xl border border-[--color-border] px-4 py-3 text-sm font-medium transition hover:border-[--color-accent]"
                                :class="{
                                    'border-[--color-accent] bg-[--color-accent]/10 text-[--color-accent]':
                                        getConditionalAnswer(currentQuestion.id).takes_preventive === false,
                                }"
                                @click="handleConditional(currentQuestion.id, false)"
                            >
                                No
                            </button>
                        </div>
                        <textarea
                            class="w-full rounded-2xl border border-[--color-border] bg-transparent p-4 text-sm focus:border-[--color-accent]"
                            rows="3"
                            :value="getConditionalAnswer(currentQuestion.id).details"
                            placeholder="If yes, share names or dose..."
                            @input="handleConditional(currentQuestion.id, null, $event.target.value)"
                        ></textarea>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 text-sm sm:justify-between">
            <button
                type="button"
                class="settings-outline-button flex-1 sm:flex-none"
                :disabled="currentQuestionIndex === 0"
                @click="goPrevious"
            >
                Back
            </button>
            <button
                v-if="currentQuestionIndex < totalQuestions - 1"
                type="button"
                class="button-primary flex-1 sm:flex-none"
                :disabled="currentQuestionIndex >= totalQuestions - 1"
                @click="goNext"
            >
                Next
            </button>
        </div>
    </div>
</template>
