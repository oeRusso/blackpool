{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('home') }}</loc>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ route('lessons.index') }}</loc>
@if ($lastModified)
        <lastmod>{{ \Illuminate\Support\Carbon::parse($lastModified)->toAtomString() }}</lastmod>
@endif
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
@foreach ($levels as $level)
    <url>
        <loc>{{ route('lessons.index', ['level' => $level]) }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
@endforeach
@foreach ($lessons as $lesson)
    <url>
        <loc>{{ route('lessons.show', $lesson) }}</loc>
        <lastmod>{{ $lesson->updated_at?->toAtomString() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
@endforeach
</urlset>
