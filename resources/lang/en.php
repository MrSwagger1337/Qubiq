<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Qubiq',
        'tagline' => 'Free, Privacy-First Open-Source QR Code Studio',
        'subtitle' => 'Generate crisp static & anonymous dynamic QR codes in PNG, SVG, PDF, EPS, WebP, and JPEG — zero accounts, zero watermarks, zero limits.',
    ],
    'nav' => [
        'home' => 'Generator',
        'features' => 'Features',
        'api' => 'Developer API',
        'docs' => 'Documentation',
        'github' => 'GitHub',
        'theme_toggle' => 'Toggle color theme',
    ],
    'panels' => [
        'content' => 'Enter Content',
        'colors' => 'Set Colors',
        'logo' => 'Add Logo Image',
        'customize' => 'Customize Design',
        'advanced' => 'Advanced Options & Presets',
        'dynamic' => 'Dynamic QR & Analytics (Optional)',
        'preview' => 'Live Preview & Export',
    ],
    'actions' => [
        'create_qr' => 'Create QR Code',
        'download_png' => 'Download PNG',
        'download_svg' => 'Download SVG',
        'download_pdf' => 'Download PDF',
        'download_eps' => 'Download EPS',
        'more_exports' => 'More Formats',
        'copy_payload' => 'Copy Payload',
        'copy_image' => 'Copy Image',
        'reset_all' => 'Reset Default',
        'random_palette' => 'Random Palette',
        'save_preset' => 'Save Preset',
        'export_json' => 'Export JSON',
        'import_json' => 'Import JSON',
    ],
    'notices' => [
        'static_info' => 'Static QR codes encode your data directly into the image. They never expire, require no database, and work forever — even offline — but cannot be edited after download.',
        'dynamic_info' => 'Dynamic QR codes encode a short redirect URL hosted on this server. You receive a one-time secret ownership token to edit the destination URL and view privacy-respecting scan counts without creating an account.',
        'ecc_info' => 'Higher error correction restores damaged or logo-covered modules (~7% L to ~30% H), but increases matrix density. Always test customized QR codes with real cameras before printing.',
    ],
    'errors' => [
        'invalid_json' => 'Request body must be valid JSON.',
        'payload_too_large' => 'Request body exceeds the configured maximum size limit.',
        'unsupported_type' => 'Unsupported QR content type ":type".',
        'capacity_exceeded' => 'Content length (:bytes bytes) exceeds the capacity of the selected QR version and error correction level.',
        'rate_limited' => 'Too many requests. Please wait :seconds seconds before trying again.',
        'unauthorized' => 'Valid ownership token is required to manage this dynamic QR link.',
        'not_found' => 'The requested resource could not be found.',
        'dynamic_disabled' => 'Dynamic QR codes are disabled on this server.',
        'svg_logo_rejected' => 'SVG logo uploads are rejected for security and print/raster export consistency. Please upload a PNG, JPEG, or WebP image.',
        'invalid_logo' => 'Uploaded logo must be a valid PNG, JPEG, or WebP image within size and pixel limits.',
    ],
    'warnings' => [
        'low_contrast' => 'Low contrast ratio (:ratio:1) between foreground and background may prevent cameras from scanning reliably (recommended >= 4.0:1).',
        'inverted_contrast' => 'Foreground appears lighter than background. Many QR scanners cannot decode light-on-dark inverted codes.',
        'high_logo_coverage' => 'Logo covers :percent% of the QR matrix. Keep coverage at or below 22% and use Error Correction H for reliable scanning.',
        'logo_needs_ecc_h' => 'A logo is enabled while Error Correction is set to :ecc. Switching to level H (30%) is strongly recommended.',
        'small_quiet_zone' => 'Quiet zone margin is set below 4 modules (:qz). Surrounding clutter may interfere with scanner detection.',
        'dense_matrix' => 'High matrix density (Version :version, :modulesx:modules modules). Export at >= 1000 px and avoid tiny print sizes.',
        'transparent_dark_surface' => 'Transparent background selected: ensure the physical or digital surface behind the QR code is light and uniform.',
    ],
];
