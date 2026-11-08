<script setup lang="ts">
import { Play } from '@lucide/vue';
import { onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import type { LessonVideo } from '@/types';
import { useI18n } from '@/lib/i18n';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        video: LessonVideo;
        startAt?: number;
        title?: string;
    }>(),
    { startAt: 0, title: 'Lesson video' },
);

const emit = defineEmits<{
    progress: [seconds: number, duration: number];
    ended: [];
}>();

const element = useTemplateRef<HTMLVideoElement>('player');
const embedActivated = ref(false);
let hls: { destroy: () => void } | null = null;
let lastReported = 0;

async function attachHls(el: HTMLVideoElement, url: string): Promise<void> {
    hls?.destroy();

    if (el.canPlayType('application/vnd.apple.mpegurl')) {
        el.src = url; // Safari plays HLS natively.

        return;
    }

    const { default: Hls } = await import('hls.js');

    if (Hls.isSupported()) {
        const instance = new Hls({ startPosition: props.startAt || -1 });
        instance.loadSource(url);
        instance.attachMedia(el);
        hls = instance;
    }
}

watch(
    [element, () => props.video.playlist_url],
    ([el, url]) => {
        if (el && url) {
            void attachHls(el, url);
        }
    },
    { immediate: true },
);

function onTimeUpdate(): void {
    const el = element.value;

    if (!el || Math.abs(el.currentTime - lastReported) < 10) {
        return;
    }

    lastReported = el.currentTime;
    emit('progress', Math.floor(el.currentTime), Math.floor(el.duration || 0));
}

function onLoaded(): void {
    if (element.value && props.startAt > 0 && element.value.currentTime === 0) {
        element.value.currentTime = props.startAt;
    }
}

onBeforeUnmount(() => hls?.destroy());
</script>

<template>
    <div
        class="relative aspect-video w-full overflow-hidden rounded-xl bg-black shadow-lg"
        data-test="video-player"
    >
        <template v-if="video.source === 'upload'">
            <video
                v-if="video.status === 'ready'"
                ref="player"
                class="size-full"
                controls
                playsinline
                preload="metadata"
                :poster="video.thumbnail_url ?? undefined"
                :aria-label="title"
                @timeupdate="onTimeUpdate"
                @loadedmetadata="onLoaded"
                @ended="emit('ended')"
            />
            <div
                v-else
                class="flex size-full items-center justify-center p-6 text-center text-sm text-white/80"
            >
                {{
                    video.status === 'processing'
                        ? t(
                              'This video is being prepared. Please check back in a few minutes.',
                          )
                        : t('This video is temporarily unavailable.')
                }}
            </div>
        </template>

        <template v-else>
            <iframe
                v-if="embedActivated"
                :src="`${video.embed_url}&autoplay=1`"
                :title="video.title ?? title"
                class="size-full"
                allow="
                    accelerometer;
                    autoplay;
                    clipboard-write;
                    encrypted-media;
                    gyroscope;
                    picture-in-picture;
                    fullscreen;
                "
                allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"
            />
            <button
                v-else
                type="button"
                class="group relative size-full"
                :aria-label="t('Play :title', { title: video.title ?? title })"
                data-test="play-embed"
                @click="embedActivated = true"
            >
                <img
                    v-if="video.thumbnail_url"
                    :src="video.thumbnail_url"
                    alt=""
                    class="size-full object-cover opacity-80 transition group-hover:opacity-100"
                    loading="lazy"
                />
                <span
                    class="absolute inset-0 m-auto flex size-16 items-center justify-center rounded-full bg-white/90 text-black shadow-xl transition group-hover:scale-110"
                >
                    <Play class="ml-1 size-7 fill-current" />
                </span>
            </button>
        </template>
    </div>
</template>
