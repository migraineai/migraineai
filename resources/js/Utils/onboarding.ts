export type OnboardingOption = {
    label: string;
    value: string | boolean | number;
};

export type OnboardingQuestion = {
    id: string;
    question_text: string;
    ui_type: string;
    options?: OnboardingOption[];
    min?: number;
    max?: number;
    step?: number;
    logic?: string;
    voice_prompt?: string;
};

export type OnboardingSection = {
    section_id: string;
    title: string;
    questions: OnboardingQuestion[];
};

export function isOnboardingQuestionAnswered(
    question: OnboardingQuestion,
    answers: Record<string, unknown> = {}
): boolean {
    const value = answers[question.id];

    if (question.ui_type === 'multi_select') {
        return Array.isArray(value) && value.length > 0;
    }

    if (question.ui_type === 'voice_input_conditional') {
        const conditional = value as { takes_preventive?: boolean | null; details?: string } | undefined;
        return (
            conditional?.takes_preventive !== null && conditional?.takes_preventive !== undefined ||
            Boolean(conditional?.details && conditional.details.length > 0)
        );
    }

    if (question.ui_type === 'slider') {
        return value !== null && value !== undefined && value !== '';
    }

    return value !== null && value !== undefined && value !== '';
}

export function countOnboardingAnswers(
    sections: OnboardingSection[],
    answers: Record<string, unknown> = {}
): number {
    return sections.reduce((count, section) => {
        return count + section.questions.filter((question) => isOnboardingQuestionAnswered(question, answers)).length;
    }, 0);
}

function buildDefaultAnswer(question: OnboardingQuestion): unknown {
    switch (question.ui_type) {
        case 'multi_select':
            return [];
        case 'slider':
            return question.min ?? 1;
        case 'voice_input_conditional':
            return { takes_preventive: null, details: '' };
        default:
            return null;
    }
}

export function buildDefaultOnboardingAnswers(sections: OnboardingSection[]): Record<string, unknown> {
    const answers: Record<string, unknown> = {};

    sections.forEach((section) => {
        section.questions.forEach((question) => {
            answers[question.id] = buildDefaultAnswer(question);
        });
    });

    return answers;
}

export function mergeOnboardingAnswers(
    sections: OnboardingSection[],
    existing: Record<string, unknown> | null | undefined
): Record<string, unknown> {
    const defaults = buildDefaultOnboardingAnswers(sections);

    if (!existing) {
        return defaults;
    }

    const merged: Record<string, unknown> = { ...defaults };

    Object.keys(existing).forEach((key) => {
        if (key in merged) {
            merged[key] = existing[key];
        }
    });

    return merged;
}
