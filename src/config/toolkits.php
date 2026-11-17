<?php

return [

    // Quota period & display timezone (DB stays UTC).
    'timezone' => 'Asia/Jakarta',

    // Hard caps for "unlimited" (null) plan limits; file_ops falls back to plan limit.
    'fair_use' => [
        'links_per_day' => 1000,
        'file_ops_per_day' => null,
    ],

    'reserved_aliases' => [
        'admin', 'api', 'login', 'logout', 'register', 'dashboard', 'tools', 'pricing',
        's', 'd', 'user', 'livewire', 'up', 'terms', 'privacy', 'email', 'password',
        'forgot-password', 'reset-password', 'billing', 'storage', 'assets', 'build',
        'static', 'help', 'support', 'about', 'contact', 'report', 'abuse', 'www',
    ],

    'bot_user_agent_regex' => '/bot|crawl|spider|slurp|facebookexternalhit|preview|curl|wget|python-requests|httpclient|headless|monitor|uptime/i',

    // Catalog; default status (App\Enums\ToolStatus), admin can override per tool.
    'tools' => [
        [
            'slug' => 'shorten',
            'name' => 'Pemendek URL',
            'route' => 'tools.shorten',
            'category' => 'link',
            'description' => 'Ubah URL panjang menjadi link pendek yang mudah dibagikan.',
            'accept' => null,
            'status' => 'live',
            'features' => [
                'custom_alias' => 'Alias kustom',
            ],
        ],
        [
            'slug' => 'qr-code',
            'name' => 'QR Code Generator',
            'route' => 'tools.qr',
            'category' => 'link',
            'description' => 'Buat QR code dari teks atau URL.',
            'accept' => null,
            'status' => 'live',
            // Gated features: allowed per plan in the admin plan matrix (plans.limits, key tool_{slug}_{feature}).
            'features' => [
                'svg' => 'Unduh format SVG',
                'premium_template' => 'Template desain premium',
                'premium_frame' => 'Bingkai premium',
                'custom_color' => 'Pemilih warna kustom',
            ],
        ],
        [
            'slug' => 'image-convert',
            'name' => 'Konversi Gambar',
            'route' => 'tools.image-convert',
            'category' => 'image',
            'description' => 'Konversi gambar antara JPG, PNG, dan WebP.',
            'accept' => 'image/jpeg,image/png,image/webp',
            'status' => 'live',
        ],
        [
            'slug' => 'image-compress',
            'name' => 'Kompres Gambar',
            'route' => 'tools.image-compress',
            'category' => 'image',
            'description' => 'Perkecil ukuran file gambar tanpa banyak kehilangan kualitas.',
            'accept' => 'image/jpeg,image/png,image/webp',
            'status' => 'live',
        ],
        [
            'slug' => 'image-resize',
            'name' => 'Ubah Ukuran Gambar',
            'route' => 'tools.image-resize',
            'category' => 'image',
            'description' => 'Ubah dimensi gambar berdasarkan persen atau piksel.',
            'accept' => 'image/jpeg,image/png,image/webp',
            'status' => 'live',
        ],
        [
            'slug' => 'remove-bg',
            'name' => 'Hapus Background',
            'route' => 'tools.remove-bg',
            'category' => 'image',
            'description' => 'Hapus latar belakang foto otomatis, hasilnya PNG transparan.',
            'accept' => 'image/jpeg,image/png,image/webp',
            'status' => 'live',
            'features' => [
                'crop' => 'Pengaturan crop',
            ],
        ],
        [
            'slug' => 'pdf-merge',
            'name' => 'Gabung PDF',
            'route' => 'tools.pdf-merge',
            'category' => 'pdf',
            'description' => 'Gabungkan beberapa file PDF menjadi satu.',
            'accept' => 'application/pdf',
            'status' => 'live',
        ],
        [
            'slug' => 'pdf-split',
            'name' => 'Pisah PDF',
            'route' => 'tools.pdf-split',
            'category' => 'pdf',
            'description' => 'Ambil halaman tertentu atau pisahkan setiap halaman PDF.',
            'accept' => 'application/pdf',
            'status' => 'live',
        ],
        [
            'slug' => 'pdf-compress',
            'name' => 'Kompres PDF',
            'route' => 'tools.pdf-compress',
            'category' => 'pdf',
            'description' => 'Perkecil ukuran file PDF.',
            'accept' => 'application/pdf',
            'status' => 'live',
        ],
        [
            'slug' => 'image-to-pdf',
            'name' => 'Gambar ke PDF',
            'route' => 'tools.image-to-pdf',
            'category' => 'pdf',
            'description' => 'Ubah satu atau beberapa gambar menjadi file PDF.',
            'accept' => 'image/jpeg,image/png',
            'status' => 'live',
        ],
    ],

    // Read only by SuperadminSeeder.
    'superadmin' => [
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

    'qr' => [
        'max_text' => 1000,
    ],

    'interstitial_seconds' => 5,

    // File tools: uploads and results live on a private disk, never under public/.
    'files' => [
        'disk' => 'local',
        'directory' => 'file-ops',
        // Decompression-bomb guard for GD (width x height).
        'max_pixels' => 50_000_000,
        'process_timeout' => 85,
        'ghostscript' => env('GHOSTSCRIPT_BIN', 'gs'),
        'rembg' => env('REMBG_BIN', 'rembg'),
    ],

    // Full-screen ad shown after a successful tool operation (plans with ads).
    'ad_gate_seconds' => 5,

    'support_email' => env('SUPPORT_EMAIL'),

    // Store for app_settings ciphertext cache (file in production).
    'settings_cache_store' => env('SETTINGS_CACHE_STORE', 'file'),

    // Uploaded brand images (path on the public disk), set from admin settings; null = built-in defaults.
    'branding' => [
        'logo' => null,
        'favicon' => null,
    ],

];
