@php
use App\Helpers\SeoHelper;

$page = $page ?? 'landing';
$params = $params ?? [];

$metaTags = SeoHelper::getMetaTags($page, $params);
$hreflangTags = SeoHelper::getHreflangTags();
@endphp

<!-- Primary Meta Tags -->
<title>{{ $metaTags['title'] }}</title>
<meta name="title" content="{{ $metaTags['title'] }}">
<meta name="description" content="{{ $metaTags['description'] }}">
<meta name="keywords" content="{{ $metaTags['keywords'] }}">
<meta name="author" content="Aditya Bintang Fadila">
<meta name="robots" content="index, follow">
<meta name="language" content="{{ app()->getLocale() === 'id' ? 'Indonesian' : 'English' }}">
<meta name="revisit-after" content="7 days">

<!-- Canonical URL -->
<link rel="canonical" href="{{ $metaTags['canonical'] }}">

<!-- Hreflang Tags for Language Versions -->
@foreach($hreflangTags as $tag)
<link rel="alternate" hreflang="{{ $tag['locale'] }}" href="{{ $tag['url'] }}">
@endforeach

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="{{ $metaTags['og_url'] }}">
<meta property="og:title" content="{{ $metaTags['og_title'] }}">
<meta property="og:description" content="{{ $metaTags['og_description'] }}">
<meta property="og:image" content="{{ $metaTags['og_image'] }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $metaTags['og_title'] }}">
<meta property="og:locale" content="{{ $metaTags['og_locale'] }}">
<meta property="og:site_name" content="QashierWise">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="{{ $metaTags['og_url'] }}">
<meta name="twitter:title" content="{{ $metaTags['twitter_title'] }}">
<meta name="twitter:description" content="{{ $metaTags['twitter_description'] }}">
<meta name="twitter:image" content="{{ $metaTags['twitter_image'] }}">
<meta name="twitter:image:alt" content="{{ $metaTags['twitter_title'] }}">

<!-- Additional SEO Meta -->
<meta name="geo.region" content="ID-JT">
<meta name="geo.placename" content="Salatiga">
<meta name="geo.position" content="-7.3305;110.5084">
<meta name="ICBM" content="-7.3305, 110.5084">
