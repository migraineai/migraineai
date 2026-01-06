<script lang="ts" setup>
import { computed } from 'vue';
import type { OnboardingQuestion, OnboardingSection } from '@/Utils/onboarding';
import { countOnboardingAnswers } from '@/Utils/onboarding';

const props = defineProps<{
    sections: OnboardingSection[];
    answers: Record<string, unknown>;
    onAnswer: (questionId: string, answer: unknown) => void;
}>();

const sections = computed(() => props.sections);
const questionList = computed(() => sections.value.flatMap((section) => section.questions));
const totalQuestions = computed(() => questionList.value.length);
const answeredCount = computed(() => countOnboardingAnswers(sections.value, props.answers));

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
    const current = Array.isArray(getAnswer(questionId)) ? [...(getAnswer(questionId) as Array<string | boolean | number>)] : [];
    const valueIndex = current.indexOf(optionValue);

    if (valueIndex >= 0) {
        current.splice(valueIndex, 1);
    } else {
        current.push(optionValue);
    }

    props.onAnswer(questionId, current);
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
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between text-xs uppercase tracking-wide text-[--color-text-muted]">
            <span>Onboarding overview</span>
            <span>{{ answeredCount }} / {{ totalQuestions }} answered</span>
        </div>
        <div v-for="section in sections" :key="section.section_id" class="space-y-4">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold tracking-wider text-[--color-text-muted]">{{ section.title }}</p>
                <span class="text-xs text-[--color-text-muted]">{{ section.questions.length }} questions</span>
            </div>

            <div class="space-y-4">
                <div
                    v-for="question in section.questions"
                    :key="question.id"
                    class="rounded-3xl border border-[--color-border] bg-[--color-bg-alt]/80 p-4"
                >
                    <div class="flex items-start justify-between gap-4">
                        <p class="text-sm font-semibold text-[--color-text-primary]">
                            {{ question.question_text }}
                        </p>
                        <span v-if="question.ui_type === 'slider'" class="text-sm font-semibold text-[--color-text-primary]">
                            {{ getSliderLabel(question) }}
                        </span>
                    </div>

                    <div class="mt-3 space-y-3">
                        <template v-if="question.ui_type === 'single_select'">
                            <label
                                v-for="option in question.options ?? []"
                                :key="option.value"
                                class="flex cursor-pointer items-center gap-3 rounded-2xl border border-[--color-border] px-3 py-2 text-sm transition hover:border-[--color-accent]"
                            >
                                <input
                                    type="radio"
                                    :name="question.id"
                                    :value="option.value"
                                    :checked="getAnswer(question.id) === option.value"
                                    class="accent-[--color-accent]"
                                    @change="handleSingleSelect(question.id, option.value)"
                                />
                                <span>{{ option.label }}</span>
                            </label>
                        </template>

                        <template v-else-if="question.ui_type === 'multi_select'">
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="option in question.options ?? []"
                                    :key="option.value"
                                    class="flex cursor-pointer items-center gap-3 rounded-2xl border border-[--color-border] px-3 py-2 text-sm transition hover:border-[--color-accent]"
                                >
                                    <input
                                        type="checkbox"
                                        :value="option.value"
                                        :checked="isOptionSelected(question.id, option.value)"
                                        class="accent-[--color-accent]"
                                        @change="handleMultiSelect(question.id, option.value)"
                                    />
                                    <span>{{ option.label }}</span>
                                </label>
                            </div>
                        </template>

                        <template v-else-if="question.ui_type === 'boolean'">
                            <div class="flex gap-3">
                                <label
                                    v-for="option in question.options ?? []"
                                    :key="String(option.value)"
                                    class="flex cursor-pointer items-center gap-2 rounded-2xl border border-[--color-border] px-3 py-2 text-sm transition hover:border-[--color-accent]"
                                >
                                    <input
                                        type="radio"
                                        :name="question.id"
                                        :value="option.value"
                                        :checked="getAnswer(question.id) === option.value"
                                        class="accent-[--color-accent]"
                                        @change="handleSingleSelect(question.id, option.value)"
                                    />
                                    <span>{{ option.label }}</span>
                                </label>
                            </div>
                        </template>

                        <template v-else-if="question.ui_type === 'slider'">
                            <div class="flex flex-col gap-2">
                                <input
                                    type="range"
                                    :min="question.min ?? 0"
                                    :max="question.max ?? 10"
                                    :step="question.step ?? 1"
                                    :value="getSliderLabel(question)"
                                    class="h-2 w-full accent-[--color-accent]"
                                    @input="handleSlider(question.id, $event.target.value)"
                                />
                                <div class="flex justify-between text-xs text-[--color-text-muted]">
                                    <span>{{ question.min ?? 0 }}</span>
                                    <span>{{ question.max ?? 10 }}</span>
                                </div>
                            </div>
                        </template>

                        <template v-else-if="question.ui_type === 'voice_input_conditional'">
                            <div class="space-y-2">
                                <div class="flex gap-3">
                                    <button
                                        type="button"
                                        class="flex-1 rounded-2xl border border-[--color-border] px-3 py-2 text-sm font-medium transition hover:border-[--color-accent]"
                                        :class="{
                                            'border-[--color-accent] bg-[--color-accent]/10 text-[--color-accent]':
                                                getConditionalAnswer(question.id).takes_preventive === true,
                                        }"
                                        @click="handleConditional(question.id, true)"
                                    >
                                        Yes
                                    </button>
                                    <button
                                        type="button"
                                        class="flex-1 rounded-2xl border border-[--color-border] px-3 py-2 text-sm font-medium transition hover:border-[--color-accent]"
                                        :class="{
                                            'border-[--color-accent] bg-[--color-accent]/10 text-[--color-accent]':
                                                getConditionalAnswer(question.id).takes_preventive === false,
                                        }"
                                        @click="handleConditional(question.id, false)"
                                    >
                                        No
                                    </button>
                                </div>
                                <textarea
                                    class="w-full rounded-2xl border border-[--color-border] bg-transparent p-3 text-sm focus:border-[--color-accent]"
                                    rows="2"
                                    :value="getConditionalAnswer(question.id).details"
                                    placeholder="If yes, share names or dose..."
                                    @input="handleConditional(question.id, null, $event.target.value)"
                                ></textarea>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
