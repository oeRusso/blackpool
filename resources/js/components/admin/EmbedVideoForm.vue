<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import LessonVideoController from '@/actions/App/Http/Controllers/Admin/LessonVideoController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{ lessonId: number }>();

const url = ref('');
const error = ref<string | undefined>();
const processing = ref(false);

function submit(): void {
    router.post(
        LessonVideoController.embed.url(props.lessonId),
        { url: url.value },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
            onError: (errors) => (error.value = errors.url),
            onSuccess: () => {
                url.value = '';
                error.value = undefined;
            },
        },
    );
}
</script>

<template>
    <form
        class="flex flex-col gap-2 sm:flex-row sm:items-start"
        @submit.prevent="submit"
    >
        <div class="grid flex-1 gap-1">
            <Label for="video-url" class="sr-only">Video URL</Label>
            <Input
                id="video-url"
                v-model="url"
                type="url"
                placeholder="https://www.youtube.com/watch?v=…"
                required
            />
            <InputError :message="error" />
        </div>
        <Button type="submit" :disabled="processing" data-test="link-video"
            >Link video</Button
        >
    </form>
</template>
