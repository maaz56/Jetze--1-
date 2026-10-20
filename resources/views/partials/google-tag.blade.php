<!-- Google tag (gtag.js) -->
@php
    $isJetzeAe = in_array(strtolower(request()->getHost()), ['jetze.ae', 'www.jetze.ae'], true);
    $googleAnalyticsId = $isJetzeAe ? 'G-W5M3NMZCW0' : 'G-Q5SVLZ3VZM';
@endphp
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleAnalyticsId }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', '{{ $googleAnalyticsId }}');
</script>
