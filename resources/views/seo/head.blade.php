<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
<meta name="site-origin" content="{{ $seo['origin'] }}">
<meta name="site-indexable" content="{{ $seo['indexable'] ? 'true' : 'false' }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="Especializa Condutor">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
<meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
@if ($seo['canonical'])
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
@endif
@if ($seo['image'])
<meta property="og:image" content="{{ $seo['image'] }}">
<meta property="og:image:alt" content="{{ $seo['image_alt'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">
<meta name="twitter:image:alt" content="{{ $seo['image_alt'] }}">
@endif
