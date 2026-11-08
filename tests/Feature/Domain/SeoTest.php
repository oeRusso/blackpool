<?php

use App\Enums\RoleName;
use App\Models\Lesson;
use App\Models\Level;
use Illuminate\Support\Facades\Http;

it('lists the public pages and only published lessons in the sitemap', function () {
    Level::factory()->create(['code' => 'B1']);
    $published = Lesson::factory()->published()->create();
    $draft = Lesson::factory()->create();
    $scheduled = Lesson::factory()->create(['status' => 'published', 'published_at' => now()->addWeek()]);
    $trashed = Lesson::factory()->published()->create();
    $trashed->delete();

    $response = $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $urls = [];
    foreach (simplexml_load_string($response->getContent())->url as $url) {
        $urls[] = (string) $url->loc;
    }

    expect($urls)
        ->toContain(route('home'), route('lessons.index'), route('lessons.index', ['level' => 'B1']), route('lessons.show', $published))
        ->not->toContain(route('lessons.show', $draft), route('lessons.show', $scheduled), route('lessons.show', $trashed));
});

it('refreshes the cached sitemap when a lesson is published', function () {
    $this->get(route('sitemap'))->assertOk();
    $lesson = Lesson::factory()->create();

    $this->get(route('sitemap'))->assertDontSee(route('lessons.show', $lesson));

    $lesson->update(['status' => 'published', 'published_at' => now()->subMinute()]);

    $this->get(route('sitemap'))->assertSee(route('lessons.show', $lesson));
});

it('lets search engines index production but keeps private areas out', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get(route('robots'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /admin')
        ->assertSee('Sitemap: '.route('sitemap'));
});

it('blocks indexing outside production', function () {
    $this->get(route('robots'))
        ->assertOk()
        ->assertSee('Disallow: /')
        ->assertDontSee('Sitemap:');
});

it('describes published lessons with schema.org structured data', function () {
    $lesson = Lesson::factory()->published()->free()->create(['title' => 'Phrasal verbs', 'duration_seconds' => 600]);

    $html = $this->get(route('lessons.show', $lesson->slug))->assertOk()->getContent();

    preg_match('#<script type="application/ld\+json" nonce="[^"]+">(.+?)</script>#s', $html, $match);
    $data = json_decode($match[1] ?? '', true);

    expect($data)
        ->toMatchArray([
            '@type' => 'LearningResource',
            'name' => 'Phrasal verbs',
            'url' => route('lessons.show', $lesson),
            'isAccessibleForFree' => true,
            'timeRequired' => 'PT10M',
            'educationalLevel' => 'CEFR '.$lesson->level->code,
        ]);
});

it('does not publish structured data for draft previews', function () {
    $draft = Lesson::factory()->create();

    $this->actingAs(userWithRole(RoleName::Editor))
        ->get(route('lessons.show', $draft->slug))
        ->assertOk()
        ->assertDontSee('application/ld+json', false);
});

it('server-renders public pages but not the signed-in areas', function () {
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false]);
    Http::fake(['*/render' => Http::response(['head' => ['<title>SSR</title>'], 'body' => '<div id="app" data-server-rendered="true">Pre-rendered catalog</div>'])]);

    $this->get(route('lessons.index'))->assertOk()->assertSee('Pre-rendered catalog');
    Http::assertSentCount(1);

    $this->actingAs(userWithRole(RoleName::Admin))->get(route('admin.dashboard'))->assertOk()->assertDontSee('Pre-rendered catalog');
    $this->get(route('dashboard'))->assertOk()->assertDontSee('Pre-rendered catalog');
    Http::assertSentCount(1);
});
