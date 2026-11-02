<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleCheck, ListChecks, Plus, Trash2, X } from '@lucide/vue';
import { reactive, ref } from 'vue';
import QuestionController from '@/actions/App/Http/Controllers/Admin/QuestionController';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Question = {
    id: number;
    prompt: string;
    explanation: string | null;
    options: { id: number; text: string; is_correct: boolean }[];
};

const props = defineProps<{ lessonId: number; questions: Question[] }>();

const blank = () => ({
    prompt: '',
    explanation: '',
    options: ['', '', ''],
    correct: 0,
});
const draft = reactive(blank());
const errors = ref<Record<string, string>>({});
const saving = ref(false);

function addOption(): void {
    if (draft.options.length < 5) {
        draft.options.push('');
    }
}

function removeOption(index: number): void {
    if (draft.options.length > 2) {
        draft.options.splice(index, 1);
        draft.correct = Math.min(draft.correct, draft.options.length - 1);
    }
}

function save(): void {
    router.post(
        QuestionController.store.url(props.lessonId),
        { ...draft },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
            onError: (bag) => (errors.value = bag),
            onSuccess: () => {
                Object.assign(draft, blank());
                errors.value = {};
            },
        },
    );
}

function destroy(question: Question): void {
    router.delete(
        QuestionController.destroy.url({
            lesson: props.lessonId,
            question: question.id,
        }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <Card data-test="quiz-editor">
        <CardHeader>
            <CardTitle class="flex items-center gap-2"
                ><ListChecks class="size-5" /> Quiz</CardTitle
            >
            <CardDescription
                >Multiple-choice questions shown at the end of the lesson.
                Students pass with 70%.</CardDescription
            >
        </CardHeader>
        <CardContent class="space-y-6">
            <ol v-if="questions.length" class="space-y-3">
                <li
                    v-for="(question, index) in questions"
                    :key="question.id"
                    class="rounded-lg border p-3"
                    :data-test="`question-${question.id}`"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-medium">
                            {{ index + 1 }}. {{ question.prompt }}
                        </p>
                        <ConfirmDialog
                            title="Delete this question?"
                            description="Previous attempts keep their score."
                            confirm-label="Delete"
                            @confirm="destroy(question)"
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                class="text-destructive"
                                :aria-label="`Delete question ${index + 1}`"
                                ><Trash2
                            /></Button>
                        </ConfirmDialog>
                    </div>
                    <ul class="mt-1 flex flex-wrap gap-2 text-sm">
                        <li
                            v-for="option in question.options"
                            :key="option.id"
                            class="flex items-center gap-1 rounded-md border px-2 py-0.5"
                            :class="
                                option.is_correct
                                    ? 'border-emerald-500 text-emerald-700 dark:text-emerald-300'
                                    : 'text-muted-foreground'
                            "
                        >
                            <CircleCheck
                                v-if="option.is_correct"
                                class="size-3.5"
                            />{{ option.text }}
                        </li>
                    </ul>
                </li>
            </ol>
            <p v-else class="text-sm text-muted-foreground">
                No questions yet.
            </p>

            <form
                class="space-y-3 rounded-lg border border-dashed p-4"
                @submit.prevent="save"
            >
                <p class="text-sm font-medium">New question</p>
                <div class="grid gap-2">
                    <Label for="question-prompt">Question</Label>
                    <Input
                        id="question-prompt"
                        v-model="draft.prompt"
                        placeholder="She ___ a teacher."
                        required
                    />
                    <InputError :message="errors.prompt" />
                </div>
                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm">
                        Options (select the correct one)
                    </legend>
                    <div
                        v-for="(_, index) in draft.options"
                        :key="index"
                        class="flex items-center gap-2"
                    >
                        <input
                            v-model="draft.correct"
                            type="radio"
                            name="correct-option"
                            :value="index"
                            class="accent-primary"
                            :aria-label="`Option ${index + 1} is correct`"
                        />
                        <Input
                            v-model="draft.options[index]"
                            :placeholder="`Option ${index + 1}`"
                            :aria-label="`Option ${index + 1}`"
                            required
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :disabled="draft.options.length <= 2"
                            :aria-label="`Remove option ${index + 1}`"
                            @click="removeOption(index)"
                            ><X
                        /></Button>
                    </div>
                    <InputError
                        :message="
                            errors.options ??
                            errors['options.0'] ??
                            errors['options.1'] ??
                            errors.correct
                        "
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="justify-self-start"
                        :disabled="draft.options.length >= 5"
                        @click="addOption"
                        ><Plus /> Add option</Button
                    >
                </fieldset>
                <div class="grid gap-2">
                    <Label for="question-explanation"
                        >Explanation (shown after answering)</Label
                    >
                    <Input
                        id="question-explanation"
                        v-model="draft.explanation"
                    />
                </div>
                <Button
                    type="submit"
                    :disabled="saving"
                    data-test="add-question"
                    >Add question</Button
                >
            </form>
        </CardContent>
    </Card>
</template>
