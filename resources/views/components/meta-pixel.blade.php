@props(['eventId' => null, 'sendCapi' => true])
@if(config('services.meta.pixel_id'))
@php
    $pixelEventId = $eventId ?? (string) \Illuminate\Support\Str::uuid();

    if ($sendCapi && config('services.meta.capi_token')) {
        $capiRequest = request();
        \App\Jobs\SendMetaCapiEvent::dispatch(
            eventName: 'PageView',
            userData: [],
            customData: [],
            eventId: $pixelEventId,
            eventSourceUrl: $capiRequest->fullUrl(),
            clientIp: $capiRequest->ip(),
            clientUserAgent: $capiRequest->userAgent(),
            fbp: $capiRequest->cookie('_fbp'),
            fbc: $capiRequest->cookie('_fbc'),
        );
    }
@endphp
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{{ config("services.meta.pixel_id") }}');
fbq('track', 'PageView', {}, { eventID: '{{ $pixelEventId }}' });
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id={{ config('services.meta.pixel_id') }}&ev=PageView&noscript=1&eid={{ $pixelEventId }}"
/></noscript>
<!-- End Meta Pixel Code -->
@endif
