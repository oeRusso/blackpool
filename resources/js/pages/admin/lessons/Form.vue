<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { FileText, ImageUp } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import LessonController from '@/actions/App/Http/Controllers/Admin/LessonController';
import LessonVideoManager from '@/components/admin/LessonVideoManager.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import QuizEditor from '@/components/admin/QuizEditor.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/lessons';
import type { AdminLesson, LessonVideo, Level, Option } from '@/types';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const props = defineProps<{
    lesson?: AdminLesson;
    levels: Level[];
    categories: { id: number; name: string }[];
    statuses: Option[];
    video?: LessonVideo | null;
    uploadLimits?: { max_size: number; extensions: string[] };
    questions?: {
        id: number;
        prompt: string;
        explanation: string | null;
        options: { id: number; text: string; is_correct: boolean }[];
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Lessons', href: index() },
        ],
    },
});

const page = usePage();
const canPublish = computed(() => page.props.auth.can.publishLessons);
const isEditing = computed(() => props.lesson !== undefined);

const selectedCategories = ref<number[]>(props.lesson?.category_ids ?? []);
const coverPreview = ref<string | null>(props.lesson?.cover_url ?? null);

// The same page component is reused after saving, so resync with fresh props.
watch(
    () => props.lesson,
    (lesson) => {
        coverPreview.value = lesson?.cover_url ?? null;
        selectedCategories.value = lesson?.category_ids ?? [];
    },
);

function onCoverChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    coverPreview.value = file
        ? URL.createObjectURL(file)
        : (props.lesson?.cover_url ?? null);
}

const formAttributes = computed(() =>
    props.lesson
        ? LessonController.update.form(props.lesson.id)
        : LessonController.store.form(),
);
</script>

<template>
    <Head
        :title="
            isEditing
                ? t('Edit: :title', { title: lesson?.title ?? '' })
                : t('New lesson')
        "
    />

    <Form
        v-bind="formAttributes"
        class="flex flex-col gap-6 p-4 md:p-6"
        v-slot="{ errors, processing, progress }"
    >
        <PageHeader
            :title="isEditing ? t('Edit lesson') : t('New lesson')"
            :description="
                isEditing
                    ? `/lessons/${lesson?.slug}`
                    : t(
                          'Fill in the details and attach the learning materials.',
                      )
            "
        >
            <template #actions>
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="save-lesson"
                >
                    {{ processing ? t('Saving…') : t('Save lesson') }}
                </Button>
            </template>
        </PageHeader>

        <progress
            v-if="progress"
            :value="progress.percentage"
            max="100"
            class="h-1 w-full"
        >
            {{ progress.percentage }}%
        </progress>

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="space-y-6">
                <Card>
                    <CardHeader
                        ><CardTitle>{{ t('Content') }}</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <Label for="title">{{ t('Title') }}</Label>
                            <Input
                                id="title"
                                name="title"
                                :default-value="lesson?.title"
                                maxlength="150"
                                required
                            />
                            <InputError :message="errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="summary">{{ t('Summary') }}</Label>
                            <Input
                                id="summary"
                                name="summary"
                                :default-value="lesson?.summary"
                                maxlength="255"
                                required
                            />
                            <InputError :message="errors.summary" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="body">{{ t('Lesson notes') }}</Label>
                            <Textarea
                                id="body"
                                name="body"
                                :default-value="lesson?.body ?? ''"
                                rows="10"
                            />
                            <InputError :message="errors.body" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        ><CardTitle>{{ t('Materials') }}</CardTitle></CardHeader
                    >
                    <CardContent class="grid gap-6 md:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="cover" class="flex items-center gap-2"
                                ><ImageUp class="size-4" />
                                {{ t('Cover image') }}</Label
                            >
                            <img
                                v-if="coverPreview"
                                :src="coverPreview"
                                :alt="t('Cover preview')"
                                class="aspect-video w-full rounded-lg border object-cover"
                            />
                            <Input
                                id="cover"
                                name="cover"
                                type="file"
                                accept="image/*"
                                @change="onCoverChange"
                            />
                            <label
                                v-if="lesson?.cover_url"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    name="remove_cover"
                                    value="1"
                                    class="accent-primary"
                                />
                                {{ t('Remove current cover') }}
                            </label>
                            <p class="text-xs text-muted-foreground">
                                {{ t('Converted to WebP, max 4 MB.') }}
                            </p>
                            <InputError :message="errors.cover" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="pdf" class="flex items-center gap-2"
                                ><FileText class="size-4" />
                                {{ t('PDF worksheet') }}</Label
                            >
                            <p
                                v-if="lesson?.has_pdf"
                                class="text-sm text-muted-foreground"
                                data-test="has-pdf"
                            >
                                {{ t('A worksheet is attached.') }}
                            </p>
                            <Input
                                id="pdf"
                                name="pdf"
                                type="file"
                                accept="application/pdf"
                            />
                            <label
                                v-if="lesson?.has_pdf"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    name="remove_pdf"
                                    value="1"
                                    class="accent-primary"
                                />
                                {{ t('Remove current worksheet') }}
                            </label>
                            <p class="text-xs text-muted-foreground">
                                {{ t('PDF only, max 20 MB.') }}
                            </p>
                            <InputError :message="errors.pdf" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader
                        ><CardTitle>{{
                            t('Publishing')
                        }}</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <Label for="status">{{ t('Status') }}</Label>
                            <NativeSelect
                                id="status"
                                name="status"
                                :model-value="lesson?.status ?? 'draft'"
                                :disabled="!canPublish"
                            >
                                <option
                                    v-for="status in statuses"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </option>
                            </NativeSelect>
                            <input
                                v-if="!canPublish"
                                type="hidden"
                                name="status"
                                :value="lesson?.status ?? 'draft'"
                            />
                            <InputError :message="errors.status" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="published_at">{{
                                t('Publish date')
                            }}</Label>
                            <Input
                                id="published_at"
                                name="published_at"
                                type="datetime-local"
                                :default-value="lesson?.published_at ?? ''"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ t('Leave empty to publish immediately.') }}
                            </p>
                            <InputError :message="errors.published_at" />
                        </div>
                        <label
                            class="flex items-start gap-3 rounded-lg border p-3"
                        >
                            <input
                                type="checkbox"
                                name="is_free"
                                value="1"
                                :checked="lesson?.is_free ?? false"
                                class="mt-1 accent-primary"
                                data-test="is-free"
                            />
                            <span>
                                <span class="block text-sm font-medium">{{
                                    t('Free lesson')
                                }}</span>
                                <span class="text-xs text-muted-foreground">{{
                                    t(
                                        'Available to every registered student. Otherwise premium only.',
                                    )
                                }}</span>
                            </span>
                        </label>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        ><CardTitle>{{
                            t('Classification')
                        }}</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <Label for="level_id">{{ t('CEFR level') }}</Label>
                            <NativeSelect
                                id="level_id"
                                name="level_id"
                                :model-value="lesson?.level_id ?? ''"
                                required
                            >
                                <option value="" disabled>
                                    {{ t('Select a level') }}
                                </option>
                                <option
                                    v-for="level in levels"
                                    :key="level.id"
                                    :value="level.id"
                                >
                                    {{ level.code }} · {{ t(level.name) }}
                                </option>
                            </NativeSelect>
                            <InputError :message="errors.level_id" />
                        </div>
                        <fieldset class="grid gap-2">
                            <legend class="mb-2 text-sm font-medium">
                                {{ t('Categories') }}
                            </legend>
                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    v-for="category in categories"
                                    :key="category.id"
                                    class="flex items-center gap-2 rounded-md border px-2.5 py-1.5 text-sm has-checked:border-primary has-checked:bg-primary/5"
                                >
                                    <input
                                        v-model="selectedCategories"
                                        type="checkbox"
                                        name="category_ids[]"
                                        :value="category.id"
                                        class="accent-primary"
                                    />
                                    {{ category.name }}
                                </label>
                            </div>
                            <InputError
                                :message="
                                    errors.category_ids ??
                                    errors['category_ids.0']
                                "
                            />
                        </fieldset>
                        <div class="grid gap-2">
                            <Label for="position">{{
                                t('Order within level')
                            }}</Label>
                            <Input
                                id="position"
                                name="position"
                                type="number"
                                min="0"
                                :default-value="lesson?.position ?? 0"
                            />
                            <InputError :message="errors.position" />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </Form>

    <section
        v-if="lesson && uploadLimits"
        class="grid gap-6 px-4 pb-6 md:px-6 xl:grid-cols-2"
    >
        <LessonVideoManager
            :lesson-id="lesson.id"
            :video="video ?? null"
            :limits="uploadLimits"
        />
        <QuizEditor :lesson-id="lesson.id" :questions="questions ?? []" />
    </section>
    <p v-else class="px-4 pb-6 text-sm text-muted-foreground md:px-6">
        {{ t('Save the lesson first to attach a video.') }}
    </p>
</template>
