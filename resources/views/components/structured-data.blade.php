@php
use App\Helpers\SeoHelper;

$includeOrganization = $includeOrganization ?? true;
$includeWebsite = $includeWebsite ?? true;
$includeFaq = $includeFaq ?? false;
@endphp

@if($includeOrganization)
<!-- Structured Data - Organization -->
{!! SeoHelper::renderStructuredData(SeoHelper::getOrganizationStructuredData()) !!}
@endif

@if($includeWebsite)
<!-- Structured Data - WebSite -->
{!! SeoHelper::renderStructuredData(SeoHelper::getWebsiteStructuredData()) !!}
@endif

@if($includeFaq)
<!-- Structured Data - FAQ -->
{!! SeoHelper::renderStructuredData(SeoHelper::getFaqStructuredData()) !!}
@endif
