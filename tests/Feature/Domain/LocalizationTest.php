<?php

use App\Enums\RoleName;
use App\Http\Middleware\SetLocale;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Support\SessionKey;

it('shares the current and supported locales with every page', function () {
    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'en')
            ->where('locales', ['en' => 'English', 'es' => 'Español']));
});

it('detects the interface language from the browser', function () {
    $this->withHeader('Accept-Language', 'es-AR,es;q=0.9,en;q=0.5')
        ->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('locale', 'es'))
        ->assertSee('<html lang="es"', false);
});

it('prefers the language cookie over the browser and the account over both', function () {
    $this->withHeader('Accept-Language', 'en-US')
        ->withUnencryptedCookie(SetLocale::COOKIE, 'es')
        ->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('locale', 'es'));

    $user = userWithRole(RoleName::Student);
    $user->update(['locale' => 'en']);

    $this->actingAs($user)
        ->withUnencryptedCookie(SetLocale::COOKIE, 'es')
        ->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

it('ignores unsupported languages', function () {
    $this->withHeader('Accept-Language', 'fr-FR,fr')
        ->withUnencryptedCookie(SetLocale::COOKIE, 'xx')
        ->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

it('lets guests switch the language with a cookie', function () {
    $this->from(route('lessons.index'))
        ->put(route('locale.update'), ['locale' => 'es'])
        ->assertRedirect(route('lessons.index'))
        ->assertPlainCookie(SetLocale::COOKIE, 'es');
});

it('rejects languages that are not supported', function () {
    $this->put(route('locale.update'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale')
        ->assertCookieMissing(SetLocale::COOKIE);
});

it('saves the chosen language on the account', function () {
    $user = userWithRole(RoleName::Student);

    $this->actingAs($user)->put(route('locale.update'), ['locale' => 'es'])->assertRedirect();

    expect($user->fresh()->locale)->toBe('es');
});

it('translates server messages into the selected language', function () {
    $lesson = Lesson::factory()->published()->free()->create();
    $user = userWithRole(RoleName::Student);
    $user->update(['locale' => 'es']);

    $this->actingAs($user)->post(route('lessons.favorite', $lesson->slug))->assertRedirect();

    expect(session(SessionKey::FLASH_DATA)['toast']['message'])->toBe('Guardada en tus favoritos.');
});

it('translates validation errors', function () {
    $this->withUnencryptedCookie(SetLocale::COOKIE, 'es')
        ->post(route('login.store'), ['email' => '', 'password' => ''])
        ->assertSessionHasErrors(['email' => 'El campo correo electrónico es obligatorio.']);
});

it('sends account emails in the language the user picked', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create(['locale' => 'es']);

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, fn ($notification, $channels, $notifiable, $locale) => $locale === 'es');
});

it('has a Spanish translation for every server-side message', function () {
    $translations = json_decode(file_get_contents(lang_path('es.json')), true);

    $keys = collect(['app', 'config', 'routes', 'resources/views'])
        ->flatMap(fn (string $dir) => File::allFiles(base_path($dir)))
        ->filter(fn ($file) => $file->getExtension() === 'php')
        ->flatMap(function ($file) {
            preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)*)'/", $file->getContents(), $matches);

            return $matches[1];
        })
        ->map(fn (string $key) => stripslashes($key))
        ->unique();

    $missing = $keys->reject(fn (string $key) => array_key_exists($key, $translations))->values()->all();

    expect($missing)->toBe([]);
});
