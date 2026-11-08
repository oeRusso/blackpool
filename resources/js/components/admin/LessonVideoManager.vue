<script setup lang="ts">
import { router, usePoll } from '@inertiajs/vue3';
import { Film, Link2, LoaderCircle, Trash2, Upload, X } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import LessonVideoController from '@/actions/App/Http/Controllers/Admin/LessonVideoController';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import EmbedVideoForm from '@/components/admin/EmbedVideoForm.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import VideoPlayer from '@/components/VideoPlayer.vue';
import { useChunkedUpload } from '@/composables/useChunkedUpload';
import { formatDuration } from '@/lib/format';
import type { LessonVideo } from '@/types';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const props = defineProps<{
    lessonId: number;
    video: LessonVideo | null;
    limits: { max_size: number; extensions: string[] };
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const localError = ref<string | null>(null);

const uploader = useChunkedUpload<{ video: LessonVideo }>({
    start: () => LessonVideoController.startUpload.url(props.lessonId),
    chunk: (upload, index) =>
        LessonVideoController.uploadChunk.url({
            lesson: props.lessonId,
            upload,
            index,
        }),
    complete: (upload) =>
        LessonVideoController.completeUpload.url({
            lesson: props.lessonId,
            upload,
        }),
});

const accept = computed(() =>
    props.limits.extensions.map((ext) => `.${ext}`).join(','),
);
const maxMb = computed(() => Math.round(props.limits.max_size / 1024 / 1024));

// While the server transcodes, refresh only the `video` prop every 3 seconds.
const poll = usePoll(3000, { only: ['video'] }, { autoStart: false });

watch(
    () => props.video?.status,
    (status) => (status === 'processing' ? poll.start() : poll.stop()),
);
onMounted(() => props.video?.status === 'processing' && poll.start());

function onFileSelected(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    localError.value = null;

    if (file && file.size > props.limits.max_size) {
        localError.value = t('The video may not be larger than :size MB.', {
            size: maxMb.value,
        });
        selectedFile.value = null;

        return;
    }

    selectedFile.value = file;
}

async function startUpload(): Promise<void> {
    if (!selectedFile.value) {
        return;
    }

    const result = await uploader.upload(selectedFile.value);

    if (result) {
        selectedFile.value = null;

        if (fileInput.value) {
            fileInput.value.value = '';
        }

        router.reload({ only: ['video'] });
    }
}

function removeVideo(): void {
    router.delete(LessonVideoController.destroy.url(props.lessonId), {
        preserveScroll: true,
    });
}

const sizeLabel = (bytes: number | null) =>
    bytes ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : '';
</script>

<template>
    <Card data-test="video-manager">
        <CardHeader>
            <CardTitle class="flex items-center gap-2"
                ><Film class="size-5" /> {{ t('Video') }}</CardTitle
            >
            <CardDescription>{{
                t(
                    'Upload a file (transcoded to adaptive HLS) or link a YouTube / Vimeo video.',
                )
            }}</CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <div v-if="video" class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline" class="capitalize">{{
                        t(video.source)
                    }}</Badge>
                    <Badge
                        :variant="
                            video.status === 'failed'
                                ? 'destructive'
                                : video.status === 'ready'
                                  ? 'default'
                                  : 'secondary'
                        "
                        data-test="video-status"
                        >{{ t(video.status) }}</Badge
                    >
                    <span
                        v-if="video.duration_seconds"
                        class="text-sm text-muted-foreground"
                        >{{ formatDuration(video.duration_seconds) }}</span
                    >
                    <span
                        v-if="video.renditions.length"
                        class="text-sm text-muted-foreground"
                        >{{ video.renditions.join(' · ') }}</span
                    >
                    <span class="text-sm text-muted-foreground">{{
                        video.title ?? video.original_name
                    }}</span>
                    <ConfirmDialog
                        :title="t('Remove this video?')"
                        :description="
                            t('Uploaded files will be deleted from the server.')
                        "
                        :confirm-label="t('Remove')"
                        @confirm="removeVideo"
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            class="ml-auto text-destructive"
                            data-test="remove-video"
                            ><Trash2 /> {{ t('Remove') }}</Button
                        >
                    </ConfirmDialog>
                </div>

                <Alert v-if="video.status === 'processing'">
                    <LoaderCircle class="animate-spin" />
                    <AlertTitle>{{ t('Processing video…') }}</AlertTitle>
                    <AlertDescription>{{
                        t(
                            'ffmpeg is generating the HLS renditions. This page updates automatically.',
                        )
                    }}</AlertDescription>
                </Alert>
                <Alert
                    v-else-if="video.status === 'failed'"
                    variant="destructive"
                >
                    <AlertTitle>{{ t('Processing failed') }}</AlertTitle>
                    <AlertDescription>{{ video.error }}</AlertDescription>
                </Alert>
                <VideoPlayer v-else :video="video" />
            </div>

            <Tabs default-value="upload">
                <TabsList>
                    <TabsTrigger value="upload"
                        ><Upload /> {{ t('Upload file') }}</TabsTrigger
                    >
                    <TabsTrigger value="embed"
                        ><Link2 /> YouTube / Vimeo</TabsTrigger
                    >
                </TabsList>

                <TabsContent value="upload" class="space-y-3 pt-2">
                    <Label for="video-file" class="sr-only">{{
                        t('Video file')
                    }}</Label>
                    <Input
                        id="video-file"
                        ref="fileInput"
                        type="file"
                        :accept="accept"
                        :disabled="uploader.uploading.value"
                        data-test="video-file"
                        @change="onFileSelected"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{
                            t(
                                ':formats · up to :size MB · uploaded in resumable chunks.',
                                {
                                    formats: limits.extensions.join(', '),
                                    size: maxMb,
                                },
                            )
                        }}
                    </p>
                    <div
                        v-if="uploader.uploading.value"
                        class="space-y-2"
                        data-test="upload-progress"
                    >
                        <Progress :model-value="uploader.percentage.value" />
                        <div
                            class="flex items-center justify-between text-xs text-muted-foreground"
                        >
                            <span
                                >{{ uploader.percentage.value }}% ·
                                {{ sizeLabel(uploader.uploadedBytes.value) }} /
                                {{ sizeLabel(uploader.totalBytes.value) }}</span
                            >
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="uploader.cancel"
                                ><X /> {{ t('Cancel') }}</Button
                            >
                        </div>
                    </div>
                    <InputError
                        :message="
                            localError ??
                            (uploader.error.value
                                ? t(uploader.error.value)
                                : undefined)
                        "
                    />
                    <Button
                        type="button"
                        :disabled="!selectedFile || uploader.uploading.value"
                        data-test="start-upload"
                        @click="startUpload"
                    >
                        <Upload /> {{ t('Upload video') }}
                    </Button>
                </TabsContent>

                <TabsContent value="embed" class="pt-2">
                    <EmbedVideoForm :lesson-id="lessonId" />
                </TabsContent>
            </Tabs>
        </CardContent>
    </Card>
</template>
