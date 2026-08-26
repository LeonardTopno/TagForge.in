<?php

/**
 * SEO helpers for TagForge public surfaces.
 */

function seo_absolute_url($path = '/', $role = null)
{
    $path = (string) $path;
    if ($path === '') {
        $path = '/';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    $base = '';
    if ($role) {
        $base = url_for_role($role);
    }
    if ($base === '') {
        $base = app_base_url();
    }
    if ($base === '') {
        $scheme = request_is_https() ? 'https' : 'http';
        $host = current_http_host();
        $base = $scheme . '://' . ($host !== '' ? $host : 'localhost');
    }
    return rtrim($base, '/') . $path;
}

function seo_asset_url($relativePath)
{
    $relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
    return seo_absolute_url('/' . $relativePath);
}

function seo_defaults_for_surface($surface)
{
    $appName = app_config('app_name', 'TagForge');
    $tagline = app_config('app_tagline', 'Print tags. Run your shop.');
    $surface = (string) $surface;

    $sharedDescription = 'TagForge is jewellery hang-tag software for Indian jewellery shops. '
        . 'Create barcode tags, print on TVS LP 46 NEO (64×18 mm), manage credits, and run your shop from the browser.';

    if ($surface === 'admin') {
        return array(
            'title' => 'Admin Portal — ' . $appName,
            'description' => 'Secure TagForge admin portal for tenant support, billing, plans, and platform operations.',
            'robots' => 'noindex, nofollow, noarchive',
            'canonical' => seo_absolute_url('/', 'admin'),
            'og_type' => 'website',
            'indexable' => false,
        );
    }

    if ($surface === 'mobile' || $surface === 'app') {
        return array(
            'title' => $appName . ' App — ' . $tagline,
            'description' => $sharedDescription,
            'robots' => 'noindex, follow',
            'canonical' => seo_absolute_url('/', 'shop'),
            'og_type' => 'website',
            'indexable' => false,
        );
    }

    // Default: public shop surface (marketing + login)
    return array(
        'title' => $appName . ' — ' . $tagline,
        'description' => $sharedDescription,
        'robots' => 'index, follow, max-image-preview:large',
        'canonical' => seo_absolute_url('/', 'shop'),
        'og_type' => 'website',
        'indexable' => true,
        'keywords' => 'jewellery tag printer, jewelry hang tag software, TVS LP 46 NEO tags, barcode jewellery tags, TagForge, gold shop tag printing India',
    );
}

function seo_build_meta($overrides = array())
{
    $surface = isset($overrides['surface']) ? $overrides['surface'] : 'shop';
    $meta = seo_defaults_for_surface($surface);
    foreach ($overrides as $key => $value) {
        if ($key === 'surface') {
            continue;
        }
        if ($value !== null && $value !== '') {
            $meta[$key] = $value;
        }
    }

    $appName = app_config('app_name', 'TagForge');
    $tagline = app_config('app_tagline', 'Print tags. Run your shop.');
    $ogImage = isset($meta['og_image']) ? $meta['og_image'] : seo_asset_url('assets/img/logo.png');
    $canonical = isset($meta['canonical']) ? $meta['canonical'] : seo_absolute_url('/');

    $meta['app_name'] = $appName;
    $meta['tagline'] = $tagline;
    $meta['og_image'] = $ogImage;
    $meta['canonical'] = $canonical;
    $meta['og_url'] = $canonical !== '' ? $canonical : seo_absolute_url('/');
    $meta['twitter_card'] = 'summary_large_image';
    $meta['locale'] = 'en_IN';
    $meta['site_name'] = $appName;

    return $meta;
}

function seo_json_ld($meta)
{
    if (empty($meta['indexable'])) {
        return array();
    }

    $appName = $meta['app_name'];
    $url = $meta['og_url'];
    $logo = seo_asset_url('assets/img/logo.png');
    $mark = seo_asset_url('assets/img/mark.png');

    $organization = array(
        '@type' => 'Organization',
        'name' => $appName,
        'url' => $url,
        'logo' => $logo,
        'description' => $meta['description'],
        'email' => app_config('mail_from', 'noreply@tagforge.in'),
        'address' => array(
            '@type' => 'PostalAddress',
            'addressLocality' => 'Bengaluru',
            'addressRegion' => 'Karnataka',
            'addressCountry' => 'IN',
        ),
        'brand' => array(
            '@type' => 'Brand',
            'name' => $appName,
        ),
    );

    $software = array(
        '@type' => 'SoftwareApplication',
        'name' => $appName,
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'url' => $url,
        'image' => array($logo, $mark),
        'description' => $meta['description'],
        'offers' => array(
            '@type' => 'Offer',
            'price' => '599',
            'priceCurrency' => 'INR',
            'category' => 'Subscription',
            'description' => 'Monthly Unlimited jewellery tag printing plan',
        ),
        'featureList' => array(
            'Jewellery hang-tag creation with barcode',
            'Browser printing for TVS LP 46 NEO (64×18 mm)',
            'Shop settings, logo, GST, and item catalog',
            'Credits and monthly unlimited billing',
            'Multi-tenant shop accounts',
        ),
        'publisher' => array(
            '@type' => 'Organization',
            'name' => 'Migids Software LLP',
        ),
    );

    $website = array(
        '@type' => 'WebSite',
        'name' => $appName,
        'url' => $url,
        'description' => $meta['description'],
        'inLanguage' => 'en-IN',
        'publisher' => array(
            '@type' => 'Organization',
            'name' => $appName,
        ),
    );

    return array(
        '@context' => 'https://schema.org',
        '@graph' => array($organization, $software, $website),
    );
}

function seo_render_head_tags($meta)
{
    $h = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };

    $lines = array();
    $lines[] = '<title>' . $h($meta['title']) . '</title>';
    $lines[] = '<meta name="description" content="' . $h($meta['description']) . '">';
    if (!empty($meta['keywords'])) {
        $lines[] = '<meta name="keywords" content="' . $h($meta['keywords']) . '">';
    }
    $lines[] = '<meta name="author" content="' . $h($meta['app_name']) . '">';
    $lines[] = '<meta name="robots" content="' . $h($meta['robots']) . '">';
    $lines[] = '<meta name="googlebot" content="' . $h($meta['robots']) . '">';
    $lines[] = '<meta name="application-name" content="' . $h($meta['app_name']) . '">';
    $lines[] = '<meta name="apple-mobile-web-app-title" content="' . $h($meta['app_name']) . '">';
    if (!empty($meta['canonical'])) {
        $lines[] = '<link rel="canonical" href="' . $h($meta['canonical']) . '">';
    }

    $lines[] = '<meta property="og:locale" content="' . $h($meta['locale']) . '">';
    $lines[] = '<meta property="og:type" content="' . $h($meta['og_type']) . '">';
    $lines[] = '<meta property="og:site_name" content="' . $h($meta['site_name']) . '">';
    $lines[] = '<meta property="og:title" content="' . $h($meta['title']) . '">';
    $lines[] = '<meta property="og:description" content="' . $h($meta['description']) . '">';
    $lines[] = '<meta property="og:url" content="' . $h($meta['og_url']) . '">';
    $lines[] = '<meta property="og:image" content="' . $h($meta['og_image']) . '">';
    $lines[] = '<meta property="og:image:alt" content="' . $h($meta['app_name'] . ' logo') . '">';

    $lines[] = '<meta name="twitter:card" content="' . $h($meta['twitter_card']) . '">';
    $lines[] = '<meta name="twitter:title" content="' . $h($meta['title']) . '">';
    $lines[] = '<meta name="twitter:description" content="' . $h($meta['description']) . '">';
    $lines[] = '<meta name="twitter:image" content="' . $h($meta['og_image']) . '">';

    $jsonLd = seo_json_ld($meta);
    if ($jsonLd) {
        $json = json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            $lines[] = '<script type="application/ld+json">' . $json . '</script>';
        }
    }

    return implode("\n  ", $lines);
}

function seo_crawlable_landing_html($meta)
{
    if (empty($meta['indexable'])) {
        return '';
    }

    $h = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $appName = $h($meta['app_name']);
    $tagline = $h($meta['tagline']);

    return '
  <section id="seo-landing" class="seo-landing" aria-label="TagForge product overview">
    <header class="seo-landing-hero">
      <p class="seo-eyebrow">Jewellery hang-tag software</p>
      <h1>' . $appName . '</h1>
      <p class="seo-tagline">' . $tagline . '</p>
      <p class="seo-lead">Create barcode jewellery tags in your browser and print them on a TVS LP 46 NEO label printer. Built for Indian jewellery shops that need fast tag entry, shop branding, GST details, and simple monthly billing.</p>
      <p><a class="seo-cta" href="#app">Sign in to your shop</a></p>
    </header>
    <div class="seo-grid">
      <article>
        <h2>Print-ready hang tags</h2>
        <p>Design 64×18 mm barbell tags with item name, purity, weights, and shop logo. Fold at 32 mm. Tune millimetre size and offsets for your printer.</p>
      </article>
      <article>
        <h2>Run your jewellery shop</h2>
        <p>Manage shop profile, logo, GSTIN, phone, address, and a custom item catalog so every tag stays consistent.</p>
      </article>
      <article>
        <h2>Credits that fit your volume</h2>
        <p>Start with a free starter pack, then continue with credits or Monthly Unlimited billing through Razorpay.</p>
      </article>
    </div>
    <section class="seo-how">
      <h2>How TagForge works</h2>
      <ol>
        <li>Register your jewellery shop and claim free starter tags.</li>
        <li>Enter weight details and create a barcode hang tag.</li>
        <li>Print or reprint on TVS LP 46 NEO from Chrome or Edge.</li>
        <li>Buy Monthly Unlimited when you outgrow the free pack.</li>
      </ol>
    </section>
    <footer class="seo-footer">
      <p>' . $appName . ' by Migids Software LLP, Bengaluru. Compatible with TVS LP 46 NEO jewellery tag printers.</p>
    </footer>
  </section>';
}
