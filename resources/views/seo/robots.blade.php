User-agent: *
@if ($indexable)
Disallow: /admin
Disallow: /dashboard
Disallow: /settings
Disallow: /pulse
Disallow: /lessons/*/video/
Disallow: /lessons/*/worksheet

Sitemap: {{ route('sitemap') }}
@else
Disallow: /
@endif
