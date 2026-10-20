<!-- Google Tag Manager (noscript) -->
@php
    $isJetzeAe = in_array(strtolower(request()->getHost()), ['jetze.ae', 'www.jetze.ae'], true);
    $googleTagManagerId = $isJetzeAe ? 'GTM-T3PXSQF8' : 'GTM-P2N344TJ';
@endphp
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $googleTagManagerId }}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
