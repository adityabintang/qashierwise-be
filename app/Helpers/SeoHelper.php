<?php

namespace App\Helpers;

class SeoHelper
{
    /**
     * Get localized meta tags for SEO
     *
     * @param  string  $page  Page identifier (e.g., 'landing', 'dashboard')
     * @param  array  $params  Additional parameters for dynamic content
     */
    public static function getMetaTags(string $page = 'landing', array $params = []): array
    {
        $locale = app()->getLocale();
        $baseUrl = config('app.url');
        $currentUrl = url()->current();

        // Get translated meta content
        $title = __("{$page}.meta_title", $params);
        $description = __("{$page}.meta_description", $params);
        $keywords = __("{$page}.meta_keywords", $params);

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $currentUrl,
            'og_locale' => $locale === 'id' ? 'id_ID' : 'en_US',
            'og_title' => $title,
            'og_description' => $description,
            'og_url' => $currentUrl,
            'og_image' => asset('images/og-image.png'),
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => asset('images/og-image.png'),
        ];
    }

    /**
     * Generate hreflang tags for all supported locales
     *
     * @param  string|null  $path  Optional path for the URL
     */
    public static function getHreflangTags(?string $path = null): array
    {
        $supportedLocales = config('app.supported_locales', ['en', 'id']);
        $baseUrl = config('app.url');
        $currentPath = $path ?? request()->path();

        // Remove leading slash if present
        $currentPath = ltrim($currentPath, '/');

        $hreflangTags = [];

        foreach ($supportedLocales as $locale) {
            // For root path, just use base URL
            if (empty($currentPath) || $currentPath === '/') {
                $url = $baseUrl;
            } else {
                $url = "{$baseUrl}/{$currentPath}";
            }

            $hreflangTags[] = [
                'locale' => $locale,
                'url' => $url,
            ];
        }

        // Add x-default for the default locale
        $defaultLocale = config('app.locale', 'en');
        $defaultUrl = empty($currentPath) || $currentPath === '/'
            ? $baseUrl
            : "{$baseUrl}/{$currentPath}";

        $hreflangTags[] = [
            'locale' => 'x-default',
            'url' => $defaultUrl,
        ];

        return $hreflangTags;
    }

    /**
     * Get structured data for organization
     */
    public static function getOrganizationStructuredData(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'QashierWise',
            'url' => config('app.url'),
            'logo' => asset('images/logo.png'),
            'description' => __('landing.meta_description'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Jl. Widosari No. 55, Tegalrejo Raya',
                'addressLocality' => 'Salatiga',
                'addressRegion' => 'Jawa Tengah',
                'postalCode' => '50733',
                'addressCountry' => 'ID',
            ],
            'founder' => [
                '@type' => 'Person',
                'name' => 'Aditya Bintang Fadila',
            ],
            'foundingDate' => '2025',
            'sameAs' => [],
        ];
    }

    /**
     * Get structured data for website
     */
    public static function getWebsiteStructuredData(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'QashierWise',
            'url' => config('app.url'),
            'description' => __('landing.meta_description'),
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'QashierWise',
            ],
        ];
    }

    /**
     * Get structured data for FAQ
     */
    public static function getFaqStructuredData(): array
    {
        $faqItems = __('landing.faq.items');

        $mainEntity = [];
        foreach ($faqItems as $item) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
    }

    /**
     * Render meta tags as HTML
     */
    public static function renderMetaTags(array $metaTags): string
    {
        $html = '';

        // Primary meta tags
        $html .= '<title>'.e($metaTags['title']).'</title>'."\n";
        $html .= '<meta name="title" content="'.e($metaTags['title']).'">'."\n";
        $html .= '<meta name="description" content="'.e($metaTags['description']).'">'."\n";
        $html .= '<meta name="keywords" content="'.e($metaTags['keywords']).'">'."\n";

        // Canonical URL
        $html .= '<link rel="canonical" href="'.e($metaTags['canonical']).'">'."\n";

        // Open Graph tags
        $html .= '<meta property="og:type" content="website">'."\n";
        $html .= '<meta property="og:url" content="'.e($metaTags['og_url']).'">'."\n";
        $html .= '<meta property="og:title" content="'.e($metaTags['og_title']).'">'."\n";
        $html .= '<meta property="og:description" content="'.e($metaTags['og_description']).'">'."\n";
        $html .= '<meta property="og:image" content="'.e($metaTags['og_image']).'">'."\n";
        $html .= '<meta property="og:locale" content="'.e($metaTags['og_locale']).'">'."\n";

        // Twitter tags
        $html .= '<meta name="twitter:card" content="summary_large_image">'."\n";
        $html .= '<meta name="twitter:url" content="'.e($metaTags['og_url']).'">'."\n";
        $html .= '<meta name="twitter:title" content="'.e($metaTags['twitter_title']).'">'."\n";
        $html .= '<meta name="twitter:description" content="'.e($metaTags['twitter_description']).'">'."\n";
        $html .= '<meta name="twitter:image" content="'.e($metaTags['twitter_image']).'">'."\n";

        return $html;
    }

    /**
     * Render hreflang tags as HTML
     */
    public static function renderHreflangTags(array $hreflangTags): string
    {
        $html = '';

        foreach ($hreflangTags as $tag) {
            $html .= '<link rel="alternate" hreflang="'.e($tag['locale']).'" href="'.e($tag['url']).'">'."\n";
        }

        return $html;
    }

    /**
     * Render structured data as JSON-LD
     */
    public static function renderStructuredData(array $structuredData): string
    {
        return '<script type="application/ld+json">'."\n".
               json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n".
               '</script>';
    }

    /**
     * Generate LocalBusiness structured data
     *
     * @param  array  $options  Configuration options
     */
    public static function getLocalBusinessStructuredData(array $options = []): array
    {
        $defaults = [
            'name' => config('app.name'),
            'description' => 'AI-powered WhatsApp chatbot for restaurants with QRIS payment integration',
            'url' => config('app.url'),
            'telephone' => env('BUSINESS_PHONE', '+62882003235019'),
            'email' => env('BUSINESS_EMAIL', 'support@qashierwise.com'),
            'priceRange' => 'Rp 249,000 - Rp 499,000',
            'address' => [
                '@type' => 'PostalAddress',
                'addressCountry' => 'ID',
                'addressLocality' => 'Jakarta',
                'addressRegion' => 'DKI Jakarta',
            ],
            'openingHours' => 'Mo-Su 00:00-23:59',
            'sameAs' => [
                'https://www.youtube.com/@Qashierwisecom',
                'https://www.threads.com/@qashierwisecom',
                'https://www.instagram.com/qashierwisecom',
            ],
        ];

        $config = array_merge($defaults, $options);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $config['name'],
            'description' => $config['description'],
            'url' => $config['url'],
            'telephone' => $config['telephone'],
            'email' => $config['email'],
            'priceRange' => $config['priceRange'],
            'address' => $config['address'],
            'openingHoursSpecification' => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => [
                    'Monday',
                    'Tuesday',
                    'Wednesday',
                    'Thursday',
                    'Friday',
                    'Saturday',
                    'Sunday',
                ],
                'opens' => '00:00',
                'closes' => '23:59',
            ],
            'sameAs' => $config['sameAs'],
            'logo' => asset('images/logo.png'),
            'image' => asset('images/og-image.png'),
        ];
    }

    /**
     * Generate breadcrumb items for current page
     */
    public static function getBreadcrumbItems(string $pageTitle, array $options = []): array
    {
        $breadcrumbs = [
            [
                'label' => 'Home',
                'url' => config('app.url'),
            ],
        ];

        // Add current page
        $breadcrumbs[] = [
            'label' => $pageTitle,
            'url' => request()->fullUrl(),
        ];

        return $breadcrumbs;
    }
}
