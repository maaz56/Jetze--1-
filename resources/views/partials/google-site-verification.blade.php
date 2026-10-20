@php
    $isJetzeAe = in_array(strtolower(request()->getHost()), ['jetze.ae', 'www.jetze.ae'], true);
@endphp
<meta name="google-site-verification" content="{{ $isJetzeAe ? 'xrwYF-bJowTWRFAZvUaQ5Jp2JR0M_WKSDXIYJl97OZk' : 'G7AsYEl-WnkG-vVSflGVpgQ0Ce4aTV9DT5WA5SL2ns0' }}">
