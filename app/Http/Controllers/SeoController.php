<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Level;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    /**
     * Public pages only: the landing page, the catalog (plus one page per CEFR
     * level) and every published lesson. Drafts, scheduled and trashed lessons
     * never appear. The cache is cleared whenever a lesson changes.
     */
    public function sitemap(): Response
    {
        $xml = Cache::remember(Lesson::SITEMAP_CACHE_KEY, now()->addDay(), fn (): string => view('seo.sitemap', [
            'lessons' => Lesson::published()->orderBy('id')->get(['id', 'slug', 'updated_at']),
            'levels' => Level::orderBy('position')->pluck('code'),
            'lastModified' => Lesson::published()->max('updated_at'),
        ])->render());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Only production is indexable, so staging or a local copy never competes
     * with the real site in search results.
     */
    public function robots(): Response
    {
        return response()
            ->view('seo.robots', ['indexable' => app()->isProduction()])
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
