<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleCheck, CircleX, ListChecks, RotateCcw } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { submit as submitQuiz } from '@/routes/lessons/quiz';
import type { Quiz } from '@/types';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const props = defineProps<{ quiz: Quiz; lessonSlug: string }>();

const reviewing = ref(props.quiz.lastAttempt !== null);
const answers = reactive<Record<number, number>>({});
const submitting = ref(false);

const allAnswered = computed(() =>
    props.quiz.questions.every(
        (question) => answers[question.id] !== undefined,
    ),
);

function submit(): void {
    router.post(
        submitQuiz.url(props.lessonSlug),
        { answers },
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onFinish: () => (submitting.value = false),
            onSuccess: () => (reviewing.value = true),
        },
    );
}

function retry(): void {
    Object.keys(answers).forEach((key) => delete answers[Number(key)]);
    reviewing.value = false;
}

function optionState(
    questionId: number,
    optionId: number,
    correctId: number | null,
) {
    const chosen = props.quiz.lastAttempt?.answers[questionId];

    if (optionId === correctId) {
        return 'correct';
    }

    return chosen === optionId ? 'wrong' : 'neutral';
}
</script>

<template>
    <Card data-test="quiz">
        <CardHeader>
            <CardTitle class="flex items-center gap-2"
                ><ListChecks class="size-5" />
                {{ t('Check your understanding') }}</CardTitle
            >
            <CardDescription>{{
                t('Score :percent% or more to complete the lesson.', {
                    percent: quiz.passPercentage,
                })
            }}</CardDescription>
        </CardHeader>

        <CardContent v-if="reviewing && quiz.lastAttempt" class="space-y-5">
            <div
                class="flex items-center gap-3 rounded-lg p-4"
                :class="
                    quiz.lastAttempt.passed
                        ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100'
                        : 'bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-100'
                "
                data-test="quiz-result"
            >
                <component
                    :is="quiz.lastAttempt.passed ? CircleCheck : CircleX"
                    class="size-6 shrink-0"
                />
                <p class="font-medium">
                    {{
                        t('You scored :score/:total (:percent%).', {
                            score: quiz.lastAttempt.score,
                            total: quiz.lastAttempt.total,
                            percent: quiz.lastAttempt.percentage,
                        })
                    }}
                    {{
                        quiz.lastAttempt.passed
                            ? t('Passed!')
                            : t('Not quite yet.')
                    }}
                </p>
            </div>
            <ol class="space-y-4">
                <li
                    v-for="(question, index) in quiz.questions"
                    :key="question.id"
                >
                    <p class="font-medium">
                        {{ index + 1 }}. {{ question.prompt }}
                    </p>
                    <ul class="mt-2 grid gap-1.5">
                        <li
                            v-for="option in question.options"
                            :key="option.id"
                            class="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
                            :class="{
                                'border-emerald-500 bg-emerald-50 dark:bg-emerald-950':
                                    optionState(
                                        question.id,
                                        option.id,
                                        question.correct_option_id,
                                    ) === 'correct',
                                'border-red-400 bg-red-50 dark:bg-red-950':
                                    optionState(
                                        question.id,
                                        option.id,
                                        question.correct_option_id,
                                    ) === 'wrong',
                            }"
                        >
                            {{ option.text }}
                        </li>
                    </ul>
                    <p
                        v-if="question.explanation"
                        class="mt-1.5 text-sm text-muted-foreground"
                    >
                        {{ question.explanation }}
                    </p>
                </li>
            </ol>
        </CardContent>

        <CardContent v-else>
            <form class="space-y-5" @submit.prevent="submit">
                <fieldset
                    v-for="(question, index) in quiz.questions"
                    :key="question.id"
                    class="space-y-2"
                >
                    <legend class="font-medium">
                        {{ index + 1 }}. {{ question.prompt }}
                    </legend>
                    <label
                        v-for="option in question.options"
                        :key="option.id"
                        class="flex cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm transition hover:bg-muted/60 has-checked:border-primary has-checked:bg-primary/5"
                    >
                        <input
                            v-model="answers[question.id]"
                            type="radio"
                            :name="`question-${question.id}`"
                            :value="option.id"
                            class="accent-primary"
                        />
                        {{ option.text }}
                    </label>
                </fieldset>
                <Button
                    type="submit"
                    :disabled="!allAnswered || submitting"
                    data-test="submit-quiz"
                    >{{ t('Submit answers') }}</Button
                >
            </form>
        </CardContent>

        <CardFooter v-if="reviewing">
            <Button variant="outline" data-test="retry-quiz" @click="retry"
                ><RotateCcw /> {{ t('Try again') }}</Button
            >
        </CardFooter>
    </Card>
</template>
