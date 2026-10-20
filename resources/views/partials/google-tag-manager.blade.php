<!-- Google Tag Manager -->
@php
    $isJetzeAe = in_array(strtolower(request()->getHost()), ['jetze.ae', 'www.jetze.ae'], true);
    $googleTagManagerId = $isJetzeAe ? 'GTM-T3PXSQF8' : 'GTM-P2N344TJ';
@endphp
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{{ $googleTagManagerId }}');</script>
<!-- End Google Tag Manager -->
