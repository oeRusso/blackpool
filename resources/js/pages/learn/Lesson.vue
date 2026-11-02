<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CircleCheck,
    Clock,
    Download,
    Heart,
    Lock,
    Sparkles,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import LessonCard from '@/components/learn/LessonCard.vue';
import LevelBadge from '@/components/learn/LevelBadge.vue';
import QuizCard from '@/components/learn/QuizCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import VideoPlayer from '@/components/VideoPlayer.vue';
import { formatDate, formatDuration } from '@/lib/format';
import { sendJson } from '@/lib/http';
import { login, register } from '@/routes';
import {
    complete,
    favorite,
    index as catalog,
    position as savePosition,
    show,
    worksheet,
} from '@/routes/lessons';
import type { LessonCard as LessonCardType, LessonVideo, Quiz } from '@/types';

const props = defineProps<{
    lesson: {
        id: number;
        slug: string;
        title: string;
        summary: string;
        body: string | null;
        level: { code: string; name: string };
        categories: { name: string; slug: string }[];
        is_free: boolean;
        duration_seconds: number | null;
        cover_url: string | null;
        published_at: string | null;
        author: string | null;
        has_pdf: boolean;
        is_published: boolean;
    };
    access: {
        canWatch: boolean;
        reason: string | null;
        requiresAccount: boolean;
    };
    video: LessonVideo | Partial<LessonVideo> | null;
    quiz: Quiz | null;
    progress: { completed: boolean; favorite: boolean; position: number };
    navigation: {
        previous: { slug: string; title: string } | null;
        next: { slug: string; title: string } | null;
        position: number | null;
        total: number;
    };
    related: LessonCardType[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const busy = ref(false);

const paragraphs = computed(() =>
    (props.lesson.body ?? '').split(/\n{2,}/).filter((p) => p.trim() !== ''),
);

function post(url: string): void {
    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
}

function onProgress(seconds: number): void {
    if (user.value) {
        void sendJson(savePosition.url(props.lesson.slug), 'POST', {
            seconds,
        }).catch(() => {});
    }
}
</script>

<template>
    <Head :title="lesson.title">
        <meta name="description" :content="lesson.summary" />
        <meta property="og:title" :content="lesson.title" />
        <meta property="og:description" :content="lesson.summary" />
        <meta
            v-if="lesson.cover_url"
            property="og:image"
            :content="lesson.cover_url"
        />
    </Head>

    <article class="mx-auto max-w-6xl px-4 py-8">
        <nav class="mb-4 text-sm text-muted-foreground" aria-label="Breadcrumb">
            <Link :href="catalog()" class="hover:text-foreground">Lessons</Link>
            <span class="mx-2">/</span>
            <Link
                :href="catalog({ query: { level: lesson.level.code } })"
                class="hover:text-foreground"
                >{{ lesson.level.code }} · {{ lesson.level.name }}</Link
            >
        </nav>

        <div class="grid gap-8 lg:grid-cols-[1fr_20rem]">
            <div class="min-w-0 space-y-6">
                <header class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <LevelBadge :code="lesson.level.code" />
                        <Badge v-if="lesson.is_free" variant="outline"
                            >Free</Badge
                        >
                        <Badge v-else variant="secondary"
                            ><Sparkles /> Premium</Badge
                        >
                        <Badge v-if="!lesson.is_published" variant="destructive"
                            >Draft preview</Badge
                        >
                        <span
                            v-if="progress.completed"
                            class="flex items-center gap-1 text-sm font-medium text-emerald-600"
                            data-test="completed-badge"
                            ><CircleCheck class="size-4" /> Completed</span
                        >
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                        {{ lesson.title }}
                    </h1>
                    <p class="text-lg text-muted-foreground">
                        {{ lesson.summary }}
                    </p>
                </header>

                <VideoPlayer
                    v-if="access.canWatch && video && video.source"
                    :video="video as LessonVideo"
                    :start-at="progress.position"
                    :title="lesson.title"
                    @progress="onProgress"
                />

                <div
                    v-else-if="!access.canWatch"
                    class="relative flex aspect-video flex-col items-center justify-center gap-4 overflow-hidden rounded-xl border bg-muted p-6 text-center"
                    data-test="locked-lesson"
                >
                    <img
                        v-if="video?.thumbnail_url ?? lesson.cover_url"
                        :src="(video?.thumbnail_url ?? lesson.cover_url)!"
                        alt=""
                        class="absolute inset-0 size-full object-cover opacity-20 blur-sm"
                    />
                    <Lock class="relative size-10 text-muted-foreground" />
                    <div class="relative space-y-1">
                        <p class="text-lg font-semibold">{{ access.reason }}</p>
                        <p
                            v-if="access.requiresAccount"
                            class="text-sm text-muted-foreground"
                        >
                            Registration is free and gives you access to every
                            free lesson.
                        </p>
                        <p v-else class="text-sm text-muted-foreground">
                            Premium members get every lesson, worksheet and
                            quiz.
                        </p>
                    </div>
                    <div
                        v-if="access.requiresAccount"
                        class="relative flex gap-2"
                    >
                        <Button as-child
                            ><Link :href="register()"
                                >Create free account</Link
                            ></Button
                        >
                        <Button variant="outline" as-child
                            ><Link :href="login()">Log in</Link></Button
                        >
                    </div>
                </div>

                <section
                    v-if="paragraphs.length"
                    class="space-y-4 leading-relaxed"
                >
                    <h2 class="text-xl font-semibold">Lesson notes</h2>
                    <p
                        v-for="(paragraph, index) in paragraphs"
                        :key="index"
                        class="whitespace-pre-line"
                    >
                        {{ paragraph }}
                    </p>
                </section>

                <QuizCard v-if="quiz" :quiz="quiz" :lesson-slug="lesson.slug" />
            </div>

            <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start">
                <div class="space-y-3 rounded-xl border p-4">
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground">Level</dt>
                            <dd class="font-medium">
                                {{ lesson.level.code }} ·
                                {{ lesson.level.name }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Duration</dt>
                            <dd class="flex items-center gap-1 font-medium">
                                <Clock class="size-3.5" />{{
                                    formatDuration(lesson.duration_seconds)
                                }}
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-muted-foreground">Topics</dt>
                            <dd class="mt-1 flex flex-wrap gap-1">
                                <Link
                                    v-for="category in lesson.categories"
                                    :key="category.slug"
                                    :href="
                                        catalog({
                                            query: { category: category.slug },
                                        })
                                    "
                                >
                                    <Badge variant="outline">{{
                                        category.name
                                    }}</Badge>
                                </Link>
                            </dd>
                        </div>
                        <div
                            v-if="lesson.published_at"
                            class="col-span-2 text-xs text-muted-foreground"
                        >
                            Published {{ formatDate(lesson.published_at)
                            }}<span v-if="lesson.author">
                                by {{ lesson.author }}</span
                            >
                        </div>
                    </dl>

                    <div v-if="user" class="grid gap-2">
                        <Button
                            v-if="access.canWatch"
                            :variant="
                                progress.completed ? 'outline' : 'default'
                            "
                            :disabled="busy"
                            data-test="toggle-complete"
                            @click="post(complete.url(lesson.slug))"
                        >
                            <CircleCheck />
                            {{
                                progress.completed
                                    ? 'Mark as not completed'
                                    : 'Mark as completed'
                            }}
                        </Button>
                        <Button
                            variant="outline"
                            :disabled="busy"
                            :aria-pressed="progress.favorite"
                            data-test="toggle-favorite"
                            @click="post(favorite.url(lesson.slug))"
                        >
                            <Heart
                                :class="
                                    progress.favorite
                                        ? 'fill-red-500 text-red-500'
                                        : ''
                                "
                            />
                            {{ progress.favorite ? 'Saved' : 'Save for later' }}
                        </Button>
                        <Button
                            v-if="lesson.has_pdf && access.canWatch"
                            variant="outline"
                            as-child
                        >
                            <a
                                :href="worksheet.url(lesson.slug)"
                                data-test="download-worksheet"
                                ><Download /> Download worksheet (PDF)</a
                            >
                        </Button>
                    </div>
                </div>

                <div
                    v-if="navigation.total > 1"
                    class="space-y-2 rounded-xl border p-4 text-sm"
                >
                    <p class="text-muted-foreground">
                        Lesson {{ navigation.position }} of
                        {{ navigation.total }} in {{ lesson.level.code }}
                    </p>
                    <Link
                        v-if="navigation.previous"
                        :href="show(navigation.previous.slug)"
                        class="flex items-center gap-2 font-medium hover:underline"
                        data-test="previous-lesson"
                        ><ArrowLeft class="size-4 shrink-0" />
                        {{ navigation.previous.title }}</Link
                    >
                    <Link
                        v-if="navigation.next"
                        :href="show(navigation.next.slug)"
                        class="flex items-center gap-2 font-medium hover:underline"
                        data-test="next-lesson"
                        ><ArrowRight class="size-4 shrink-0" />
                        {{ navigation.next.title }}</Link
                    >
                </div>
            </aside>
        </div>

        <section v-if="related.length" class="mt-12 space-y-4">
            <h2 class="text-xl font-semibold">Related lessons</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <LessonCard
                    v-for="item in related"
                    :key="item.id"
                    :lesson="item"
                />
            </div>
        </section>
    </article>
</template>
