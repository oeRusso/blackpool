import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import LessonCard from '@/components/learn/LessonCard.vue';
import type { LessonCard as LessonCardType } from '@/types';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: ['href'],
        setup:
            (props, { slots, attrs }) =>
            () =>
                h(
                    'a',
                    {
                        ...attrs,
                        href:
                            typeof props.href === 'string'
                                ? props.href
                                : props.href?.url,
                    },
                    slots.default?.(),
                ),
    }),
}));

const lesson = (overrides: Partial<LessonCardType> = {}): LessonCardType => ({
    id: 1,
    slug: 'present-perfect',
    title: 'Present perfect',
    summary: 'Experiences and recent news.',
    level: 'B1',
    categories: ['Grammar'],
    is_free: true,
    duration_seconds: 300,
    image_url: null,
    has_video: true,
    completed: false,
    ...overrides,
});

describe('LessonCard', () => {
    it('links to the lesson and shows its metadata', () => {
        const wrapper = mount(LessonCard, { props: { lesson: lesson() } });

        expect(wrapper.find('a').attributes('href')).toBe(
            '/lessons/present-perfect',
        );
        expect(wrapper.text()).toContain('Present perfect');
        expect(wrapper.text()).toContain('Free');
        expect(wrapper.text()).toContain('5 min');
        expect(wrapper.text()).toContain('Grammar');
    });

    it('uses a band-coloured placeholder when there is no image', () => {
        const wrapper = mount(LessonCard, {
            props: { lesson: lesson({ level: 'C1' }) },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.html()).toContain('from-violet-500');
    });

    it('marks premium and completed lessons', () => {
        expect(
            mount(LessonCard, { props: { lesson: lesson({ is_free: false }) } })
                .find('[aria-label="Premium lesson"]')
                .exists(),
        ).toBe(true);
        expect(
            mount(LessonCard, {
                props: { lesson: lesson({ completed: true }) },
            })
                .find('[aria-label="Completed"]')
                .exists(),
        ).toBe(true);
    });
});
