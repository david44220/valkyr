<!DOCTYPE html>
<html lang="fr" class="dark" style="color-scheme: dark;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>VALKYR // ARENA — Souveraineté Tactique &amp; Séquestre Atomique E-Sport</title>
    <meta name="description" content="Plateforme compétitive E-Sport de ligues privées et tournois 1v1/5v5 avec séquestre atomique (Escrow) et grand livre immuable en partie double.">
    <meta property="og:title" content="VALKYR // ARENA — Souveraineté Tactique &amp; Séquestre Atomique">
    <meta property="og:description" content="Ligues privées et tournois haute fréquence. Vos enjeux verrouillés sur grand livre immuable, réglés à la milliseconde.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="/assets/hero-valkyr-arena.png">

    <!-- Bespoke Sovereign Inline SVG Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' fill='%23030407'/%3E%3Crect x='2' y='2' width='60' height='60' fill='none' stroke='%23E5C158' stroke-width='2'/%3E%3Cpath d='M32 10 L38 26 L54 32 L38 38 L32 54 L26 38 L10 32 L26 26 Z' fill='%23E5C158'/%3E%3C/svg%3E">

    <!-- Google Fonts: Syne (Display Roman), Instrument Serif (True Italic), Plus Jakarta Sans, JetBrains Mono (Tabular-Nums) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">

    <!-- Hydrate Initial Server State for Instant 60 FPS Client Interactivity -->
    @php
        $hydratedState = [
            'tournaments' => $tournaments,
            'currentUser' => $currentUser,
            'wallet' => $wallet,
            'ledgerEntries' => $ledgerEntries,
            'leaderboard' => $leaderboard,
            'platformStats' => $platformStats,
        ];
    @endphp
    <script>
        window.__VALKYR_STATE__ = @json($hydratedState);
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#030407] text-[#F4F7FB] font-sans antialiased selection:bg-[#E5C158] selection:text-[#030407] overflow-x-hidden">

    <!-- =====================================================================
         TOAST NOTIFICATION STACK
         ===================================================================== -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-[100] flex flex-col gap-3 pointer-events-none max-w-md w-[calc(100vw-3rem)]"></div>

    <!-- =====================================================================
         CONSTANT LAW 1: SEAMLESS TRANSPARENT HEADER & UNCLUTTERED NAVIGATION
         ===================================================================== -->
    <header id="main-header" class="fixed top-0 inset-x-0 z-50 border-b border-white/[0.08] transition-colors duration-300" style="background-color: rgba(0, 0, 0, 0);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4">
            <!-- Left: Crisp Brand Logo + Emblem on a Single Clean Line (Zero Sub-Slogans) -->
            <a href="#hero-section" class="font-display font-extrabold text-lg sm:text-xl tracking-tight text-[#F4F7FB] flex items-center gap-2.5 whitespace-nowrap">
                <span class="text-[#E5C158]" aria-hidden="true">✦</span>
                <span>VALKYR</span>
            </a>

            <!-- Center: 4 Short Unnumbered Navigation Links -->
            <nav aria-label="Navigation principale" class="hidden lg:flex items-center gap-8">
                <a href="#arenas-catalog" data-i18n="nav.arenas" class="text-sm font-medium text-[#94A3B8] hover:text-[#F4F7FB] transition whitespace-nowrap">Arènes</a>
                <a href="#live-replay-section" data-i18n="nav.replay" class="text-sm font-medium text-[#94A3B8] hover:text-[#F4F7FB] transition whitespace-nowrap">Live Replay</a>
                <a href="#arsenal-section" data-i18n="nav.arsenal" class="text-sm font-medium text-[#94A3B8] hover:text-[#F4F7FB] transition whitespace-nowrap">Arsenal</a>
                <a href="#leaderboard-section" data-i18n="nav.leaderboard" class="text-sm font-medium text-[#94A3B8] hover:text-[#F4F7FB] transition whitespace-nowrap">Classement</a>
            </nav>

            <!-- Right: Minimalist Language Toggle (FR / EN), Discreet Sign-In Link & 1 Sharp Primary CTA -->
            <div class="hidden sm:flex items-center gap-4">
                <div class="flex items-center gap-1.5 px-2.5 py-1.5 border border-white/[0.08] bg-[#080B11]/70 font-mono text-xs">
                    <button type="button" data-lang-btn="fr" class="px-1 text-[#E5C158] font-bold cursor-pointer">FR</button>
                    <span class="text-white/20">/</span>
                    <button type="button" data-lang-btn="en" class="px-1 text-[#64748B] hover:text-[#F4F7FB] cursor-pointer">EN</button>
                </div>

                <span id="header-operator-badge" class="{{ $currentUser ? '' : 'hidden' }} px-2.5 py-1 bg-[#121824] border border-[#E5C158]/40 text-[#E5C158] font-mono text-xs font-semibold whitespace-nowrap">
                    {{ $currentUser ? $currentUser->gamertag . ' [' . $currentUser->role . ']' : '' }}
                </span>

                <a href="#cockpit-section" data-i18n="nav.login" class="text-xs font-mono uppercase tracking-wider text-[#94A3B8] hover:text-[#F4F7FB] px-2 py-2 whitespace-nowrap transition">
                    Connexion
                </a>

                <button type="button" data-open-create-tournament class="h-11 px-5 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer">
                    <span data-i18n="nav.create_league">Créer une Ligue ↗</span>
                </button>
            </div>

            <!-- Mobile Hamburger Button (48x48 Touch Target) -->
            <button type="button" id="mobile-menu-btn" aria-label="Menu" class="sm:hidden h-12 w-12 inline-flex items-center justify-center border border-white/15 bg-[#080B11]/90 text-[#F4F7FB] font-mono text-sm rounded-none">
                ☰
            </button>
        </div>

        <!-- Mobile Navigation Drawer -->
        <div id="mobile-nav-drawer" class="hidden sm:hidden bg-[#080B11] border-b border-white/10 px-4 py-5 space-y-4">
            <div class="flex flex-col space-y-3">
                <a href="#arenas-catalog" data-i18n="nav.arenas" class="py-2 text-sm font-mono uppercase tracking-wider text-[#F4F7FB] border-b border-white/[0.06]">Arènes</a>
                <a href="#live-replay-section" data-i18n="nav.replay" class="py-2 text-sm font-mono uppercase tracking-wider text-[#F4F7FB] border-b border-white/[0.06]">Live Replay</a>
                <a href="#arsenal-section" data-i18n="nav.arsenal" class="py-2 text-sm font-mono uppercase tracking-wider text-[#F4F7FB] border-b border-white/[0.06]">Arsenal</a>
                <a href="#leaderboard-section" data-i18n="nav.leaderboard" class="py-2 text-sm font-mono uppercase tracking-wider text-[#F4F7FB] border-b border-white/[0.06]">Classement</a>
                <a href="#cockpit-section" data-i18n="nav.login" class="py-2 text-sm font-mono uppercase tracking-wider text-[#E5C158]">Connexion / Cockpit</a>
            </div>
            <div class="flex items-center justify-between gap-3 pt-2">
                <div class="flex items-center gap-2 px-3 py-2 border border-white/10 bg-[#0C1018] font-mono text-xs">
                    <button type="button" data-lang-btn="fr" class="text-[#E5C158] font-bold">FR</button>
                    <span class="text-white/20">/</span>
                    <button type="button" data-lang-btn="en" class="text-[#64748B]">EN</button>
                </div>
                <button type="button" data-open-create-tournament class="h-12 flex-1 px-4 bg-[#D8E5F8] text-[#030407] font-mono text-xs font-bold uppercase tracking-wider rounded-none whitespace-nowrap">
                    Créer une Ligue ↗
                </button>
            </div>
        </div>
    </header>

    <main>
        <!-- =================================================================
             CONSTANT LAW 2: HERO SECTION (8K UNFILTERED ARTWORK + 4 ELEMENTS)
             ================================================================= -->
        <section id="hero-section" class="relative min-h-dvh w-full flex items-center overflow-hidden bg-[#030407]">
            <!-- Lossless Native 8K/4K Hero Artwork: filter:none, transform:none, opacity:1, ZERO full-screen dark overlay -->
            <img
                id="hero-artwork"
                src="/assets/hero-valkyr-arena.png"
                alt="VALKYR Sovereign Tactical Arena"
                width="1408"
                height="768"
                fetchpriority="high"
                decoding="sync"
                loading="eager"
                class="absolute inset-0 w-full h-full object-cover object-[74%_center] lg:object-center select-none pointer-events-none"
                style="filter: none; transform: none; will-change: auto; opacity: 1;"
            />

            <!-- Seamless Bottom-Seam Horizon Blend ONLY (h-28 bottom-0) -->
            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-28 bg-gradient-to-t from-[#030407] via-[#030407]/60 to-transparent"></div>

            <!-- Left-Aligned Asymmetric Content Over Natural Abyssal Obsidian Negative Space -->
            <div class="relative z-10 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-28 pb-20">
                <div class="max-w-2xl">
                    <!-- Element 1: Surgical Kicker -->
                    <div class="flex items-center gap-3 mb-5 hero-optical-shadow">
                        <span class="inline-block w-8 h-px bg-[#E5C158] shrink-0" aria-hidden="true"></span>
                        <span id="hero-kicker" data-i18n="hero.kicker" class="text-[10px] sm:text-[11px] font-mono uppercase tracking-[0.22em] text-white/80 tabular-nums">
                            PROTOCOLE D'ESCORTE COMPÉTITIF // SÉQUESTRE ATOMIQUE 0.08
                        </span>
                    </div>

                    <!-- Element 2: Strictly 2-Line Headline (Line 1 Upright Roman Syne + Line 2 True Italic Instrument Serif) -->
                    <h1 id="hero-headline" class="hero-optical-shadow font-display font-extrabold tracking-[-0.035em] leading-[1.04] text-[#F4F7FB] text-[clamp(1.32rem,3.8vw,3.85rem)] mb-6">
                        <span data-i18n="hero.line1" class="block uppercase whitespace-nowrap">SOUVERAINETÉ TACTIQUE.</span>
                        <em data-i18n="hero.line2" class="block font-serif-italic italic font-normal text-[#E5C158] tracking-[-0.015em] text-[clamp(1.55rem,4.2vw,4.2rem)] whitespace-nowrap">L'Arène du Séquestre Absolu.</em>
                    </h1>

                    <!-- Element 3: Strictly 2-Line Subtitle (17 Words Max) -->
                    <p id="hero-subtitle" data-i18n="hero.subtitle" class="hero-optical-shadow font-sans text-[15px] sm:text-[16px] leading-[1.55] text-[#F4F7FB]/90 max-w-[540px] mb-9">
                        Ligues privées et tournois haute fréquence. Vos enjeux verrouillés sur grand livre immuable, réglés à la milliseconde.
                    </p>

                    <!-- Element 4: Dual Architectural CTA Group (Sharp 0px Edges + Directional Glyphs) -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3.5">
                        <a
                            href="#arenas-catalog"
                            id="hero-cta-primary"
                            class="h-12 px-7 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider inline-flex items-center justify-center whitespace-nowrap rounded-none transition"
                        >
                            <span data-i18n="hero.cta_primary">Rejoindre l'Arène ↗</span>
                        </a>
                        <a
                            href="#live-replay-section"
                            id="hero-cta-secondary"
                            class="h-12 px-7 bg-[#030407]/80 border border-white/25 text-[#F4F7FB] hover:border-[#D8E5F8] font-mono text-xs font-semibold uppercase tracking-wider inline-flex items-center justify-center whitespace-nowrap rounded-none transition"
                        >
                            <span data-i18n="hero.cta_secondary">Voir un Match Live ▶</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             GLOBAL TELEMETRY RIBBON (TABULAR-NUMS FINANCIAL & ARENA METRICS)
             ================================================================= -->
        <section aria-label="Télémétrie Globale du Protocole" class="border-y border-white/[0.08] bg-[#080B11]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 grid grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="border-l-2 border-[#E5C158] pl-4">
                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#64748B]">SÉQUESTRE ACTIF VERROUILLÉ</div>
                    <div class="text-xl sm:text-2xl font-mono font-bold text-[#E5C158] tabular-nums mt-0.5">
                        {{ \App\Models\Wallet::formatCents($platformStats['total_escrow_locked_cents']) }}
                    </div>
                </div>
                <div class="border-l-2 border-[#10B981] pl-4">
                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#64748B]">CASHPRIZES NETS DISTRIBUÉS (92%)</div>
                    <div class="text-xl sm:text-2xl font-mono font-bold text-[#10B981] tabular-nums mt-0.5">
                        {{ \App\Models\Wallet::formatCents($platformStats['total_prizes_distributed_cents']) }}
                    </div>
                </div>
                <div class="border-l-2 border-[#D8E5F8] pl-4">
                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#64748B]">COMMISSION PLATEFORME STRICTE</div>
                    <div class="text-xl sm:text-2xl font-mono font-bold text-[#D8E5F8] tabular-nums mt-0.5">
                        8,00 % <span class="text-xs font-normal text-[#94A3B8]">(800 BPS)</span>
                    </div>
                </div>
                <div class="border-l-2 border-white/25 pl-4">
                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#64748B]">CADENCE ANTI-TRICHE &amp; LEDGER</div>
                    <div class="text-xl sm:text-2xl font-mono font-bold text-[#F4F7FB] tabular-nums mt-0.5">
                        128 HZ <span class="text-xs font-normal text-[#10B981]">// ACID</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             MODULE 1: LIVE MATCH REPLAY & HUD TACTIQUE 60 FPS + FULLSCREEN MODAL
             ================================================================= -->
        <section id="live-replay-section" class="py-20 lg:py-24 border-b border-white/[0.08] bg-[#030407] tactical-grid-bg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-10">
                    <div>
                        <div class="flex items-center gap-2.5 mb-2">
                            <span class="w-2 h-2 bg-[#EF4444] inline-block"></span>
                            <span data-i18n="replay.section_kicker" class="text-[11px] font-mono uppercase tracking-[0.2em] text-[#94A3B8] tabular-nums">
                                MODULE 01 // TÉLÉMÉTRIE DE COMBAT &amp; CAPTURE 128-TICK
                            </span>
                        </div>
                        <h2 data-i18n="replay.section_title" class="font-display font-extrabold text-2xl sm:text-3xl lg:text-4xl text-[#F4F7FB] tracking-tight uppercase">
                            LIVE MATCH REPLAY &amp; HUD TACTIQUE 60 FPS
                        </h2>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            id="open-replay-modal-btn"
                            class="h-12 px-6 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                        >
                            <span data-i18n="replay.open_modal">Ouvrir le Replay Tactique 4K ⤢</span>
                        </button>
                    </div>
                </div>

                <!-- 1px Tactical HUD Frame -->
                <div class="bg-[#0C1018] border border-white/[0.08] rounded-none overflow-hidden">
                    <!-- Top Telemetry Status Bar -->
                    <div class="px-4 sm:px-6 py-3 bg-[#080B11] border-b border-white/[0.08] flex flex-wrap items-center justify-between gap-3 font-mono text-xs tabular-nums">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center gap-1.5 text-[#EF4444] font-bold">
                                <span class="w-2 h-2 bg-[#EF4444] inline-block animate-pulse"></span>
                                REC ●
                            </span>
                            <span data-live-timecode class="text-[#F4F7FB] font-semibold">00:42:18</span>
                            <span class="text-white/20">//</span>
                            <span class="text-[#10B981] font-semibold">60 FPS</span>
                            <span class="text-white/20 hidden sm:inline">//</span>
                            <span class="text-[#D8E5F8] hidden sm:inline">4K CAPTURE</span>
                            <span class="text-white/20 hidden md:inline">//</span>
                            <span class="text-[#10B981] hidden md:inline">ANTI-CHEAT VERIFIED</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-[#94A3B8]">ARÈNE: <strong class="text-[#F4F7FB]">ORION GRAND SLAM</strong></span>
                            <span class="px-2 py-0.5 bg-[#E5C158]/15 border border-[#E5C158]/40 text-[#E5C158] font-bold">
                                ESCROW: 1 000,00 €
                            </span>
                        </div>
                    </div>

                    <!-- Main Optical Feed + 60 FPS HTML5 Canvas Overlay -->
                    <div class="relative aspect-[16/9] w-full bg-[#030407] overflow-hidden">
                        <img
                            id="live-combat-artwork"
                            src="/assets/live-combat-hud.png"
                            alt="5v5 Competitive Arena Tactical Feed"
                            width="1408"
                            height="768"
                            loading="eager"
                            class="w-full h-full object-cover select-none pointer-events-none"
                            style="filter: none; transform: none; opacity: 1;"
                        />
                        <canvas
                            id="tactical-hud-canvas"
                            width="1280"
                            height="720"
                            class="absolute inset-0 w-full h-full pointer-events-none"
                        ></canvas>

                        <!-- Bottom-Right Live Combat Feed Overlay Box -->
                        <div class="hidden md:block absolute bottom-4 right-4 bg-[#030407]/90 border border-white/15 p-3.5 max-w-sm font-mono text-[11px] tabular-nums">
                            <div class="text-[10px] uppercase tracking-widest text-[#E5C158] mb-1.5">JOURNAL DE COMBAT // 128-TICK</div>
                            <div class="space-y-1 text-[#94A3B8]">
                                <div>[00:42:11] <span class="text-[#F4F7FB]">VKR_NYX</span> ──[LANCE VP-1]──▶ <span class="text-[#EF4444]">RIVAL_VEX</span></div>
                                <div>[00:42:14] <span class="text-[#10B981]">KESTREL_ZERO</span> ──[COIL KV-9]──▶ <span class="text-[#EF4444]">NEXUS_04</span></div>
                                <div>[00:42:18] <span class="text-[#E5C158]">SÉQUESTRE VÉRIFIÉ</span> // HASH: 0x9F4A...82C1</div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Operator POV Switcher & Vitals Readout Bar -->
                    <div class="p-4 sm:p-6 bg-[#080B11] border-t border-white/[0.08] grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                        <!-- POV Selector Buttons (4 Pro Operators) -->
                        <div class="lg:col-span-7">
                            <div class="text-[10px] font-mono uppercase tracking-widest text-[#64748B] mb-2">
                                SÉLECTEUR DE CAMÉRA POV OPÉRATEUR (TEMPS RÉEL)
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <button type="button" data-pov-select="VKR_NYX" class="h-11 px-3 border border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-mono text-xs font-semibold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer">
                                    VKR_NYX
                                </button>
                                <button type="button" data-pov-select="KESTREL_ZERO" class="h-11 px-3 border border-white/[0.08] bg-[#080B11] text-[#94A3B8] font-mono text-xs font-semibold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer">
                                    KESTREL_ZERO
                                </button>
                                <button type="button" data-pov-select="AETHER_9" class="h-11 px-3 border border-white/[0.08] bg-[#080B11] text-[#94A3B8] font-mono text-xs font-semibold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer">
                                    AETHER_9
                                </button>
                                <button type="button" data-pov-select="SOVEREIGN_X" class="h-11 px-3 border border-white/[0.08] bg-[#080B11] text-[#94A3B8] font-mono text-xs font-semibold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer">
                                    SOVEREIGN_X
                                </button>
                            </div>
                        </div>

                        <!-- Active Operator Vitals & Telemetry Readout (Tabular-Nums) -->
                        <div class="lg:col-span-5 grid grid-cols-3 gap-3 bg-[#0C1018] border border-white/[0.08] p-3.5 font-mono tabular-nums">
                            <div>
                                <div class="text-[10px] text-[#64748B] uppercase">OPÉRATEUR POV</div>
                                <div id="hud-active-operator" class="text-xs font-bold text-[#E5C158] mt-0.5">VKR_NYX</div>
                                <div id="hud-active-weapon" class="text-[10px] text-[#94A3B8] truncate">LANCE PLASMA VP-1</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-[#64748B] uppercase">INTÉGRITÉ / BOUCLIER</div>
                                <div class="text-xs font-bold text-[#10B981] mt-0.5">
                                    <span id="hud-active-hp">100 HP</span> // <span id="hud-active-shield" class="text-[#D8E5F8]">125 AP</span>
                                </div>
                                <div class="text-[10px] text-[#94A3B8]">ARMURE NOMINALE</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-[#64748B] uppercase">KDA // LATENCE</div>
                                <div id="hud-active-kda" class="text-xs font-bold text-[#F4F7FB] mt-0.5">18 / 4 / 9</div>
                                <div id="hud-active-ping" class="text-[10px] text-[#10B981]">6 ms</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             MODULE 2: SCROLLYTELLING "PINNED STICKY" EN 3 CHAPITRES
             ================================================================= -->
        <section id="scrollytelling-section" class="border-b border-white/[0.08] bg-[#080B11]">
            <div id="scrollytelling-track" class="relative min-h-[240vh] max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                <div class="sticky top-24 min-h-[calc(100dvh-7rem)] flex flex-col justify-between py-4">
                    <!-- Top Header & Clickable Chapter Indicators -->
                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-white/[0.08] pb-6">
                        <div>
                            <div data-i18n="scrolly.kicker" class="text-[11px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-2">
                                MODULE 02 // ARCHITECTURE FINANCIÈRE EN PARTIE DOUBLE
                            </div>
                            <h2 data-i18n="scrolly.title" class="font-display font-extrabold text-2xl sm:text-3xl lg:text-4xl text-[#F4F7FB] tracking-tight uppercase">
                                PROTOCOLE DE SÉQUESTRE ATOMIQUE
                            </h2>
                        </div>

                        <!-- Clickable Chapter Pills (01, 02, 03) + Scroll Progress Bar -->
                        <div class="flex items-center gap-3">
                            <button type="button" data-chapter-pill="0" class="h-10 px-3.5 border border-[#E5C158] bg-[#E5C158] text-[#030407] font-mono text-xs font-bold tabular-nums rounded-none cursor-pointer">
                                01
                            </button>
                            <button type="button" data-chapter-pill="1" class="h-10 px-3.5 border border-white/10 bg-[#080B11] text-[#94A3B8] font-mono text-xs font-bold tabular-nums rounded-none cursor-pointer">
                                02
                            </button>
                            <button type="button" data-chapter-pill="2" class="h-10 px-3.5 border border-white/10 bg-[#080B11] text-[#94A3B8] font-mono text-xs font-bold tabular-nums rounded-none cursor-pointer">
                                03
                            </button>
                            <div class="hidden sm:flex items-center gap-2 pl-3 border-l border-white/10">
                                <div class="w-24 h-1.5 bg-white/10 overflow-hidden">
                                    <div id="scrolly-progress-bar" class="h-full bg-[#E5C158] transition-all duration-150" style="width: 33%;"></div>
                                </div>
                                <span id="scrolly-progress-pct" class="font-mono text-xs text-[#94A3B8] tabular-nums">033%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Center Grid: 3 Narrative Chapters (Left) + Cryptographic Escrow Core Canvas (Right) -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center my-auto py-8">
                        <!-- 3 Narrative Chapters -->
                        <div class="lg:col-span-6 space-y-4">
                            <!-- Chapter 01 -->
                            <div data-chapter-card="0" class="p-6 border border-white/[0.08] border-l-2 border-l-[#E5C158] bg-[#0C1018] opacity-100 transition-all duration-300 cursor-pointer">
                                <div class="flex items-center justify-between text-[11px] font-mono text-[#E5C158] mb-1.5 tabular-nums">
                                    <span>PHASE 01 // PESSIMISTIC DATABASE LOCK</span>
                                    <span>SELECT ... FOR UPDATE</span>
                                </div>
                                <h3 data-i18n="scrolly.ch1_title" class="font-display font-bold text-lg text-[#F4F7FB] mb-2">
                                    01. Verrouillage Séquestre Atomique
                                </h3>
                                <p data-i18n="scrolly.ch1_desc" class="text-sm text-[#94A3B8] leading-relaxed">
                                    Verrou pessimiste SELECT ... FOR UPDATE acquis par ordre d'ID croissant strict (ORDER BY id ASC). Zéro condition de course TOCTOU, zéro deadlock concurrent.
                                </p>
                            </div>

                            <!-- Chapter 02 -->
                            <div data-chapter-card="1" class="p-6 border border-white/[0.08] bg-[#080B11]/60 opacity-55 transition-all duration-300 cursor-pointer">
                                <div class="flex items-center justify-between text-[11px] font-mono text-[#10B981] mb-1.5 tabular-nums">
                                    <span>PHASE 02 // SERVER AUTHORITATIVE TELEMETRY</span>
                                    <span>128-TICK // ANTI-BFLA</span>
                                </div>
                                <h3 data-i18n="scrolly.ch2_title" class="font-display font-bold text-lg text-[#F4F7FB] mb-2">
                                    02. Télémétrie Anti-Triche Temps Réel
                                </h3>
                                <p data-i18n="scrolly.ch2_desc" class="text-sm text-[#94A3B8] leading-relaxed">
                                    Validation serveur 128-tick et empreinte déterministe du match. Les Policies Anti-IDOR et Anti-BFLA bloquent toute usurpation d'organisateur ou d'accès portefeuille croisé.
                                </p>
                            </div>

                            <!-- Chapter 03 -->
                            <div data-chapter-card="2" class="p-6 border border-white/[0.08] bg-[#080B11]/60 opacity-55 transition-all duration-300 cursor-pointer">
                                <div class="flex items-center justify-between text-[11px] font-mono text-[#D8E5F8] mb-1.5 tabular-nums">
                                    <span>PHASE 03 // BIGINT INTEGER SETTLEMENT</span>
                                    <span>800 BPS RAKE // 60·30·10</span>
                                </div>
                                <h3 data-i18n="scrolly.ch3_title" class="font-display font-bold text-lg text-[#F4F7FB] mb-2">
                                    03. Distribution Instantanée du Cashprize
                                </h3>
                                <p data-i18n="scrolly.ch3_desc" class="text-sm text-[#94A3B8] leading-relaxed">
                                    Prélèvement mathématique entier de 800 bps (8,00 %) via intdiv et distribution atomique 60 % / 30 % / 10 % au centime près sur le grand livre immuable.
                                </p>
                            </div>
                        </div>

                        <!-- Central Vector/Canvas Artifact: Cryptographic Escrow Core -->
                        <div class="lg:col-span-6 bg-[#0C1018] border border-white/[0.08] p-6 flex flex-col items-center justify-between">
                            <div class="w-full flex items-center justify-between border-b border-white/[0.08] pb-3 mb-4 font-mono text-xs tabular-nums">
                                <span id="core-stage-code" class="text-[#E5C158] font-bold">CHAPITRE 01 // ACID LOCK</span>
                                <span id="core-stage-badge" class="text-[#94A3B8]">SELECT ... FOR UPDATE // ORDER BY ID ASC</span>
                            </div>

                            <div class="relative w-full flex items-center justify-center py-4">
                                <canvas id="escrow-core-canvas" width="320" height="300" class="max-w-full h-auto"></canvas>
                            </div>

                            <div class="w-full grid grid-cols-2 gap-3 pt-4 border-t border-white/[0.08] font-mono tabular-nums">
                                <div class="bg-[#080B11] p-3 border border-white/[0.05]">
                                    <div id="core-stage-metric-label" class="text-[10px] text-[#64748B] uppercase">ISOLATION TRANSACTIONNELLE</div>
                                    <div id="core-stage-metric-value" class="text-xs font-bold text-[#F4F7FB] mt-1">0 DEADLOCK // 100% ATOMIQUE</div>
                                </div>
                                <div class="bg-[#080B11] p-3 border border-white/[0.05]">
                                    <div class="text-[10px] text-[#64748B] uppercase">TRIGGERS SQLITE / PGSQL</div>
                                    <div class="text-xs font-bold text-[#10B981] mt-1">APPEND-ONLY IMMUTABLE</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             MODULE 3: SÉLECTEUR INTERACTIF DE CLASSE / LOADOUT (ARSENAL 4K)
             ================================================================= -->
        <section id="arsenal-section" class="py-20 lg:py-24 border-b border-white/[0.08] bg-[#030407]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-10">
                    <div data-i18n="arsenal.kicker" class="text-[11px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-2">
                        MODULE 03 // DOTATION OPÉRATEUR &amp; MÉTA COMPÉTITIVE
                    </div>
                    <h2 data-i18n="arsenal.title" class="font-display font-extrabold text-2xl sm:text-3xl lg:text-4xl text-[#F4F7FB] tracking-tight uppercase">
                        SÉLECTEUR D'ARSENAL &amp; CLASSES TACTIQUES
                    </h2>
                </div>

                <!-- 4 Archetype Selector Tabs (Minimum 12px gap-3, sharp 0px corners) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
                    <button type="button" data-class-tab="VANGUARD" class="h-14 px-4 border border-white/[0.08] bg-[#080B11] text-[#94A3B8] font-mono text-xs font-bold uppercase tracking-wider rounded-none flex items-center justify-between transition cursor-pointer">
                        <span>VANGUARD // AEGIS-01</span>
                        <span class="text-[10px] text-[#64748B]">[TANK]</span>
                    </button>
                    <button type="button" data-class-tab="SPECTRE" class="h-14 px-4 border border-white/[0.08] bg-[#080B11] text-[#94A3B8] font-mono text-xs font-bold uppercase tracking-wider rounded-none flex items-center justify-between transition cursor-pointer">
                        <span>SPECTRE // KESTREL-V</span>
                        <span class="text-[10px] text-[#64748B]">[RECON]</span>
                    </button>
                    <button type="button" data-class-tab="CHRONO" class="h-14 px-4 border border-white/[0.08] bg-[#080B11] text-[#94A3B8] font-mono text-xs font-bold uppercase tracking-wider rounded-none flex items-center justify-between transition cursor-pointer">
                        <span>CHRONO // ORION-IX</span>
                        <span class="text-[10px] text-[#64748B]">[ORBITAL]</span>
                    </button>
                    <button type="button" data-class-tab="VALKYRIE" class="h-14 px-4 border border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-mono text-xs font-bold uppercase tracking-wider rounded-none flex items-center justify-between transition cursor-pointer">
                        <span>VALKYRIE // SOVEREIGN-PRIME</span>
                        <span class="text-[10px] text-[#E5C158]">[ASSAULT]</span>
                    </button>
                </div>

                <!-- Active Class Showcase Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-12 bg-[#0C1018] border border-white/[0.08]">
                    <!-- Left 7 Cols: Pure Unfiltered 4K Studio Portrait -->
                    <div class="lg:col-span-7 relative aspect-[4/3] bg-[#030407] border-b lg:border-b-0 lg:border-r border-white/[0.08] overflow-hidden">
                        <img
                            id="arsenal-class-image"
                            src="/assets/class-valkyrie.png"
                            alt="VALKYRIE // SOVEREIGN-PRIME"
                            width="1024"
                            height="768"
                            loading="eager"
                            decoding="sync"
                            class="w-full h-full object-cover select-none"
                            style="filter: none; transform: none; opacity: 1;"
                        />
                        <div class="absolute bottom-4 left-4 bg-[#030407]/90 border border-white/15 px-3.5 py-2 font-mono text-xs">
                            <span id="arsenal-class-code" class="text-[#E5C158] font-bold">VALKYRIE // SOVEREIGN-PRIME</span>
                        </div>
                    </div>

                    <!-- Right 5 Cols: Weapon, Ability & 4 Animated Tabular-Nums Stat Bars -->
                    <div class="lg:col-span-5 p-6 sm:p-8 flex flex-col justify-between">
                        <div>
                            <div class="text-xs font-mono uppercase tracking-widest text-[#E5C158] mb-1" id="arsenal-class-role">
                                Commandement d'Assaut Aérien &amp; Suprématie
                            </div>
                            <p id="arsenal-class-desc" class="text-sm text-[#94A3B8] leading-relaxed mb-6">
                                Commandante d'assaut aérien dotée de propulseurs vectoriels en titane glacé et d'une armure cérémonielle aux accents d'or souverain.
                            </p>

                            <!-- Primary Weapon & Tactical Ability -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
                                <div class="bg-[#080B11] border border-white/[0.08] p-3.5">
                                    <div class="text-[10px] font-mono uppercase tracking-wider text-[#64748B]">ARME PRINCIPALE</div>
                                    <div id="arsenal-class-weapon" class="text-xs font-mono font-bold text-[#F4F7FB] mt-1">LANCE PLASMA SOUVERAINE VP-1</div>
                                </div>
                                <div class="bg-[#080B11] border border-white/[0.08] p-3.5">
                                    <div class="text-[10px] font-mono uppercase tracking-wider text-[#64748B]">CAPACITÉ TACTIQUE</div>
                                    <div id="arsenal-class-ability" class="text-xs font-mono font-bold text-[#D8E5F8] mt-1">FRAPPE ORBITALE VALKYR (RAYON 18M)</div>
                                </div>
                            </div>

                            <!-- 4 Stat Bars in Tabular-Nums -->
                            <div class="space-y-4 mb-8 font-mono tabular-nums">
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="text-[#94A3B8]">PUISSANCE DE FEU (DPS)</span>
                                        <span id="stat-val-dps" class="text-[#F4F7FB] font-bold">94 / 100</span>
                                    </div>
                                    <div class="w-full h-2 bg-[#080B11] border border-white/[0.06]">
                                        <div id="stat-bar-dps" class="h-full bg-[#E5C158] transition-all duration-300" style="width: 94%;"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="text-[#94A3B8]">MOBILITÉ VECTORIELLE</span>
                                        <span id="stat-val-mobility" class="text-[#F4F7FB] font-bold">92 / 100</span>
                                    </div>
                                    <div class="w-full h-2 bg-[#080B11] border border-white/[0.06]">
                                        <div id="stat-bar-mobility" class="h-full bg-[#D8E5F8] transition-all duration-300" style="width: 92%;"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="text-[#94A3B8]">RÉSISTANCE BALISTIQUE (ARMURE)</span>
                                        <span id="stat-val-armor" class="text-[#F4F7FB] font-bold">82 / 100</span>
                                    </div>
                                    <div class="w-full h-2 bg-[#080B11] border border-white/[0.06]">
                                        <div id="stat-bar-armor" class="h-full bg-[#10B981] transition-all duration-300" style="width: 82%;"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="text-[#94A3B8]">VÉLOCITÉ D'EXÉCUTION</span>
                                        <span id="stat-val-velocity" class="text-[#F4F7FB] font-bold">95 / 100</span>
                                    </div>
                                    <div class="w-full h-2 bg-[#080B11] border border-white/[0.06]">
                                        <div id="stat-bar-velocity" class="h-full bg-[#E5C158] transition-all duration-300" style="width: 95%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            id="lock-loadout-btn"
                            class="w-full h-12 px-6 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                        >
                            <span data-i18n="arsenal.lock_btn">Verrouiller ce Loadout Tactique ↗</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             MODULE 4: CATALOGUE FILTRABLE DES TOURNOIS & LIGUES PRIVÉES
             ================================================================= -->
        <section id="arenas-catalog" class="py-20 lg:py-24 border-b border-white/[0.08] bg-[#080B11]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header + Counter -->
                <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-8">
                    <div>
                        <div data-i18n="catalog.kicker" class="text-[11px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-2">
                            MODULE 04 // LIGUES PRIVÉES &amp; TOURNOIS HAUTE FRÉQUENCE
                        </div>
                        <h2 data-i18n="catalog.title" class="font-display font-extrabold text-2xl sm:text-3xl lg:text-4xl text-[#F4F7FB] tracking-tight uppercase">
                            CATALOGUE DES ARÈNES &amp; SÉQUESTRES ACTIFS
                        </h2>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div id="active-arenas-counter" class="h-11 px-4 bg-[#0C1018] border border-[#E5C158]/40 text-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider inline-flex items-center tabular-nums">
                            {{ $platformStats['active_arenas_count'] }} ARÈNES ACTIVES // {{ $tournaments->count() }} AFFICHÉES
                        </div>
                        <button type="button" data-open-create-tournament class="h-11 px-5 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer">
                            + Nouvelle Arène ↗
                        </button>
                    </div>
                </div>

                <!-- Faceted Filter Bar (Gap >= 12px) -->
                <div class="bg-[#0C1018] border border-white/[0.08] p-4 sm:p-5 mb-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                    <div>
                        <label for="tournament-search-input" class="block text-[10px] font-mono uppercase tracking-wider text-[#64748B] mb-1">RECHERCHE DIRECTE</label>
                        <input
                            type="text"
                            id="tournament-search-input"
                            placeholder="Titre, jeu, organisateur..."
                            class="w-full h-11 px-3 bg-[#080B11] border border-white/10 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none"
                        />
                    </div>

                    <div>
                        <label for="filter-game" class="block text-[10px] font-mono uppercase tracking-wider text-[#64748B] mb-1">DISCIPLINE / JEU</label>
                        <select id="filter-game" class="w-full h-11 px-3 bg-[#080B11] border border-white/10 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none">
                            <option value="ALL">TOUS LES JEUX</option>
                            <option value="VALORANT // PROTOCOL">VALORANT // PROTOCOL</option>
                            <option value="COUNTER-STRIKE 2">COUNTER-STRIKE 2</option>
                            <option value="APEX // SOVEREIGN">APEX // SOVEREIGN</option>
                            <option value="LEAGUE OF LEGENDS">LEAGUE OF LEGENDS</option>
                        </select>
                    </div>

                    <div>
                        <label for="filter-status" class="block text-[10px] font-mono uppercase tracking-wider text-[#64748B] mb-1">STATUT SÉQUESTRE</label>
                        <select id="filter-status" class="w-full h-11 px-3 bg-[#080B11] border border-white/10 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none">
                            <option value="ALL">TOUS LES STATUTS</option>
                            <option value="OPEN">OPEN (INSCRIPTIONS)</option>
                            <option value="IN_PROGRESS">LIVE (EN COURS)</option>
                            <option value="LOCKED">LOCKED (COMPLET)</option>
                            <option value="SETTLED">SETTLED (DISTRIBUÉ)</option>
                            <option value="CANCELLED">CANCELLED (REMBOURSÉ)</option>
                        </select>
                    </div>

                    <div>
                        <label for="filter-format" class="block text-[10px] font-mono uppercase tracking-wider text-[#64748B] mb-1">FORMAT COMBAT</label>
                        <select id="filter-format" class="w-full h-11 px-3 bg-[#080B11] border border-white/10 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none">
                            <option value="ALL">5V5 &amp; 1V1</option>
                            <option value="5v5">5V5 ESCORT / SQUAD</option>
                            <option value="1v1">1V1 DUEL SOUVERAIN</option>
                        </select>
                    </div>

                    <div>
                        <label for="filter-buyin" class="block text-[10px] font-mono uppercase tracking-wider text-[#64748B] mb-1">PLAFOND BUY-IN</label>
                        <select id="filter-buyin" class="w-full h-11 px-3 bg-[#080B11] border border-white/10 text-xs font-mono text-[#F4F7FB] tabular-nums focus:border-[#D8E5F8] focus:outline-none">
                            <option value="ALL">TOUS LES BUY-INS</option>
                            <option value="2500">&lt;= 25,00 €</option>
                            <option value="5000">&lt;= 50,00 €</option>
                            <option value="10000">&lt;= 100,00 €</option>
                            <option value="25000">&lt;= 250,00 €</option>
                        </select>
                    </div>

                    <div class="flex flex-col justify-end">
                        <button
                            type="button"
                            id="reset-filters-btn"
                            class="h-11 w-full px-4 bg-[#121824] border border-white/15 text-[#94A3B8] hover:text-[#F4F7FB] hover:border-[#E5C158] font-mono text-xs font-semibold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                        >
                            Réinitialiser ↺
                        </button>
                    </div>
                </div>

                <!-- Equal-Height Tournament Cards Grid -->
                <div id="tournaments-catalog-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    <!-- Hydrated dynamically by app.js from window.__VALKYR_STATE__ -->
                </div>

                <!-- Zero-Results Empty State -->
                <div id="tournaments-empty-state" class="hidden bg-[#0C1018] border border-white/[0.08] p-12 text-center">
                    <div class="text-xs font-mono uppercase tracking-widest text-[#E5C158] mb-2">AUCUNE ARÈNE CORRESPONDANTE</div>
                    <p class="text-sm text-[#94A3B8] max-w-md mx-auto mb-6">
                        Aucun tournoi ne correspond aux filtres sélectionnés. Réinitialisez les critères pour afficher toutes les arènes du grand livre.
                    </p>
                    <button type="button" id="empty-reset-filters-btn" class="h-11 px-6 bg-[#D8E5F8] text-[#030407] font-mono text-xs font-bold uppercase tracking-wider rounded-none">
                        Réinitialiser les filtres ↺
                    </button>
                </div>
            </div>
        </section>

        <!-- =================================================================
             MODULE 5: SPLIT-SCREEN AUTH 50/50 & COCKPIT JOUEUR / ORGANISATEUR
             ================================================================= -->
        <section id="cockpit-section" class="py-20 lg:py-24 border-b border-white/[0.08] bg-[#030407]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="mb-10">
                    <div data-i18n="cockpit.kicker" class="text-[11px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-2">
                        MODULE 05 // AUTHENTIFICATION &amp; COCKPIT FINANCIER
                    </div>
                    <h2 data-i18n="cockpit.title" class="font-display font-extrabold text-2xl sm:text-3xl lg:text-4xl text-[#F4F7FB] tracking-tight uppercase">
                        COCKPIT OPÉRATEUR, ARBRE DE TOURNOI &amp; GRAND LIVRE
                    </h2>
                </div>

                <!-- PART A: 50/50 ASYMMETRIC SPLIT-SCREEN AUTH & 1-CLICK DEMO SWITCHER -->
                <div id="auth-split-module" class="grid grid-cols-1 lg:grid-cols-12 border border-white/[0.08] bg-[#0C1018] mb-12">
                    <!-- Left 50% (6 Cols): 1-Click Demo Operator Switcher & Cryptographic Security Specs -->
                    <div class="lg:col-span-6 p-6 sm:p-8 lg:p-10 bg-[#0C1018] border-b lg:border-b-0 lg:border-r border-white/[0.08] flex flex-col justify-between">
                        <div>
                            <div class="inline-flex items-center gap-2 px-2.5 py-1 bg-[#E5C158]/15 border border-[#E5C158]/40 text-[#E5C158] font-mono text-[10px] uppercase tracking-widest mb-4">
                                BASCULE OPÉRATEUR INSTANTANÉE // 1-CLICK DEMO LOGIN
                            </div>
                            <h3 class="font-display font-bold text-xl sm:text-2xl text-[#F4F7FB] mb-2">
                                SIMULATEUR DE RÔLES &amp; ACCÈS DIRECT AU GRAND LIVRE
                            </h3>
                            <p class="text-sm text-[#94A3B8] leading-relaxed mb-6">
                                Basculez instantanément entre le Commandement (<code class="text-[#E5C158]">ADMIN</code>), l'Arbitre Officiel (<code class="text-[#D8E5F8]">ORGANIZER</code>) ou les 4 Joueurs Compétitifs (<code class="text-[#10B981]">PLAYER</code>) pour tester les dépôts, les buy-ins et la distribution 60/30/10.
                            </p>

                            <!-- 6 One-Click Demo Login Buttons -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
                                <button type="button" data-demo-email="admin@valkyr.arena" class="h-12 px-3.5 bg-[#080B11] border border-white/10 hover:border-[#E5C158] text-left flex items-center justify-between font-mono text-xs transition cursor-pointer">
                                    <div>
                                        <span class="font-bold text-[#F4F7FB] block">VKR_COMMANDER</span>
                                        <span class="text-[10px] text-[#E5C158]">ADMIN // 5 000,00 €</span>
                                    </div>
                                    <span class="text-[#E5C158]">↗</span>
                                </button>

                                <button type="button" data-demo-email="organizer@valkyr.arena" class="h-12 px-3.5 bg-[#080B11] border border-white/10 hover:border-[#D8E5F8] text-left flex items-center justify-between font-mono text-xs transition cursor-pointer">
                                    <div>
                                        <span class="font-bold text-[#F4F7FB] block">VKR_ARBITER</span>
                                        <span class="text-[10px] text-[#D8E5F8]">ORGANIZER // 2 500,00 €</span>
                                    </div>
                                    <span class="text-[#D8E5F8]">↗</span>
                                </button>

                                <button type="button" data-demo-email="nyx@valkyr.arena" class="h-12 px-3.5 bg-[#080B11] border border-white/10 hover:border-[#10B981] text-left flex items-center justify-between font-mono text-xs transition cursor-pointer">
                                    <div>
                                        <span class="font-bold text-[#F4F7FB] block">VKR_NYX</span>
                                        <span class="text-[10px] text-[#10B981]">PLAYER // 2 365 MMR</span>
                                    </div>
                                    <span class="text-[#10B981]">↗</span>
                                </button>

                                <button type="button" data-demo-email="kestrel@valkyr.arena" class="h-12 px-3.5 bg-[#080B11] border border-white/10 hover:border-[#10B981] text-left flex items-center justify-between font-mono text-xs transition cursor-pointer">
                                    <div>
                                        <span class="font-bold text-[#F4F7FB] block">KESTREL_ZERO</span>
                                        <span class="text-[10px] text-[#10B981]">PLAYER // 2 300 MMR</span>
                                    </div>
                                    <span class="text-[#10B981]">↗</span>
                                </button>

                                <button type="button" data-demo-email="aether@valkyr.arena" class="h-12 px-3.5 bg-[#080B11] border border-white/10 hover:border-[#10B981] text-left flex items-center justify-between font-mono text-xs transition cursor-pointer">
                                    <div>
                                        <span class="font-bold text-[#F4F7FB] block">AETHER_9</span>
                                        <span class="text-[10px] text-[#10B981]">PLAYER // 2 235 MMR</span>
                                    </div>
                                    <span class="text-[#10B981]">↗</span>
                                </button>

                                <button type="button" data-demo-email="sovereign@valkyr.arena" class="h-12 px-3.5 bg-[#080B11] border border-white/10 hover:border-[#10B981] text-left flex items-center justify-between font-mono text-xs transition cursor-pointer">
                                    <div>
                                        <span class="font-bold text-[#F4F7FB] block">SOVEREIGN_X</span>
                                        <span class="text-[10px] text-[#10B981]">PLAYER // 2 195 MMR</span>
                                    </div>
                                    <span class="text-[#10B981]">↗</span>
                                </button>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-white/[0.08] flex items-center justify-between text-xs font-mono text-[#64748B]">
                            <span>MOT DE PASSE UNIVERSEL DÉMO : <code class="text-[#D8E5F8]">Password123!</code></span>
                            <button type="button" data-logout-btn class="text-[#EF4444] hover:underline cursor-pointer">Déconnecter session</button>
                        </div>
                    </div>

                    <!-- Right 50% (6 Cols): Pure Obsidian Workspace with Sign In <-> Create Account Tabs -->
                    <div class="lg:col-span-6 p-6 sm:p-8 lg:p-10 bg-[#080B11]">
                        <!-- Underline Tabs -->
                        <div class="flex items-center gap-6 border-b border-white/10 mb-6">
                            <button
                                type="button"
                                id="tab-auth-login"
                                class="pb-3 border-b-2 border-[#E5C158] text-[#F4F7FB] font-mono text-xs font-bold uppercase tracking-wider cursor-pointer"
                            >
                                Connexion (Sign in)
                            </button>
                            <button
                                type="button"
                                id="tab-auth-register"
                                class="pb-3 text-[#64748B] hover:text-[#F4F7FB] font-mono text-xs font-bold uppercase tracking-wider cursor-pointer"
                            >
                                Créer un compte (Register)
                            </button>
                        </div>

                        <!-- Login Form Panel -->
                        <div id="panel-auth-login">
                            <form id="form-auth-login" action="/login" method="POST" class="space-y-4">
                                @csrf
                                <div>
                                    <label for="login-email" class="block text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1.5">EMAIL OPÉRATEUR</label>
                                    <input
                                        type="email"
                                        id="login-email"
                                        name="email"
                                        value="nyx@valkyr.arena"
                                        required
                                        class="w-full h-11 px-3.5 bg-[#0C1018] border border-white/15 text-sm font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none"
                                    />
                                </div>

                                <div>
                                    <label for="login-password" class="block text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1.5">MOT DE PASSE TACTIQUE</label>
                                    <div class="flex items-center">
                                        <input
                                            type="password"
                                            id="login-password"
                                            name="password"
                                            value="Password123!"
                                            required
                                            class="flex-1 h-11 px-3.5 bg-[#0C1018] border border-r-0 border-white/15 text-sm font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            data-toggle-password="login-password"
                                            class="h-11 px-4 bg-[#121824] border border-white/15 text-[11px] font-mono font-semibold text-[#D8E5F8] hover:text-[#E5C158] cursor-pointer"
                                        >
                                            AFFICHER
                                        </button>
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    class="w-full h-12 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider rounded-none transition cursor-pointer"
                                >
                                    Initialiser la Connexion Tactique ↗
                                </button>
                            </form>
                        </div>

                        <!-- Register Form Panel -->
                        <div id="panel-auth-register" class="hidden">
                            <form id="form-auth-register" action="/register" method="POST" class="space-y-4">
                                @csrf
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="reg-name" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">NOM D'OPÉRATEUR</label>
                                        <input
                                            type="text"
                                            id="reg-name"
                                            name="name"
                                            placeholder="Ex: Astrid Lindholm"
                                            required
                                            class="w-full h-11 px-3 bg-[#0C1018] border border-white/15 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none"
                                        />
                                    </div>
                                    <div>
                                        <label for="reg-gamertag" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">GAMERTAG UNIQUE</label>
                                        <input
                                            type="text"
                                            id="reg-gamertag"
                                            name="gamertag"
                                            placeholder="Ex: VKR_ASTRID"
                                            required
                                            class="w-full h-11 px-3 bg-[#0C1018] border border-white/15 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="reg-email" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">ADRESSE EMAIL</label>
                                        <input
                                            type="email"
                                            id="reg-email"
                                            name="email"
                                            placeholder="astrid@valkyr.arena"
                                            required
                                            class="w-full h-11 px-3 bg-[#0C1018] border border-white/15 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none"
                                        />
                                    </div>
                                    <div>
                                        <label for="reg-class" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">CLASSE D'ARSENAL</label>
                                        <select
                                            id="reg-class"
                                            name="preferred_class"
                                            class="w-full h-11 px-3 bg-[#0C1018] border border-white/15 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none"
                                        >
                                            <option value="VALKYRIE">VALKYRIE // SOVEREIGN-PRIME</option>
                                            <option value="VANGUARD">VANGUARD // AEGIS-01</option>
                                            <option value="SPECTRE">SPECTRE // KESTREL-V</option>
                                            <option value="CHRONO">CHRONO // ORION-IX</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="reg-password" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">MOT DE PASSE (MIN 8 CAR.)</label>
                                        <div class="flex items-center">
                                            <input
                                                type="password"
                                                id="reg-password"
                                                name="password"
                                                required
                                                class="flex-1 h-11 px-3 bg-[#0C1018] border border-r-0 border-white/15 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none"
                                            />
                                            <button
                                                type="button"
                                                data-toggle-password="reg-password"
                                                class="h-11 px-2.5 bg-[#121824] border border-white/15 text-[10px] font-mono text-[#D8E5F8]"
                                            >
                                                AFFICHER
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="reg-password-confirm" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">CONFIRMATION</label>
                                        <input
                                            type="password"
                                            id="reg-password-confirm"
                                            name="password_confirmation"
                                            required
                                            class="w-full h-11 px-3 bg-[#0C1018] border border-white/15 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    class="w-full h-12 bg-[#E5C158] text-[#030407] hover:bg-[#D8E5F8] font-mono text-xs font-bold uppercase tracking-wider rounded-none transition cursor-pointer"
                                >
                                    Créer mon Compte Opérateur &amp; Portefeuille ↗
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- PART B: COCKPIT SHELL (SIDEBAR WITH 2PX ACTIVE INDICATOR + MAIN WORKSPACE) -->
                <div id="operator-cockpit" class="grid grid-cols-1 lg:grid-cols-12 border border-white/[0.08] bg-[#0C1018]">
                    <!-- Sidebar Navigation (3 Cols) -->
                    <aside class="lg:col-span-3 bg-[#080B11] border-b lg:border-b-0 lg:border-r border-white/[0.08] p-6 flex flex-col justify-between">
                        <div>
                            <div class="mb-6 pb-4 border-b border-white/[0.08]">
                                <div class="text-[10px] font-mono uppercase tracking-widest text-[#64748B]">SESSION OPÉRATEUR</div>
                                <div id="cockpit-operator-title" class="font-mono text-sm font-bold text-[#E5C158] mt-1">
                                    {{ $currentUser ? $currentUser->gamertag . ' // [' . $currentUser->role . ']' : 'MODE APERÇU PUBLIC' }}
                                </div>
                                <div id="cockpit-operator-email" class="font-mono text-xs text-[#94A3B8] truncate mt-0.5">
                                    {{ $currentUser ? $currentUser->email : 'Sélectionnez un profil Démo ci-dessus' }}
                                </div>
                                <div id="kpi-preferred-class" class="mt-2 inline-block px-2 py-0.5 bg-[#121824] border border-white/10 text-[10px] font-mono text-[#D8E5F8]">
                                    LOADOUT ACTIF // {{ $currentUser ? $currentUser->preferred_class : 'VALKYRIE' }}
                                </div>
                            </div>

                            <!-- Sidebar Links with 2px Vertical Active Indicator (border-l-2 border-[#E5C158]) -->
                            <nav aria-label="Modules du Cockpit" class="space-y-1.5 font-mono text-xs">
                                <button
                                    type="button"
                                    data-cockpit-nav="cockpit-kpi-panel"
                                    class="w-full text-left px-4 py-3 border-l-2 border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-semibold transition cursor-pointer"
                                >
                                    Portefeuille &amp; Solde Séquestre
                                </button>
                                <button
                                    type="button"
                                    data-cockpit-nav="cockpit-bracket-panel"
                                    class="w-full text-left px-4 py-3 text-[#94A3B8] hover:text-[#F4F7FB] hover:bg-[#121824]/50 transition cursor-pointer"
                                >
                                    Arbre de Tournoi (Bracket)
                                </button>
                                <button
                                    type="button"
                                    data-cockpit-nav="cockpit-ledger-panel"
                                    class="w-full text-left px-4 py-3 text-[#94A3B8] hover:text-[#F4F7FB] hover:bg-[#121824]/50 transition cursor-pointer"
                                >
                                    Grand Livre Immuable (Ledger)
                                </button>
                            </nav>
                        </div>

                        <!-- Transactional Action Triggers -->
                        <div class="mt-8 pt-6 border-t border-white/[0.08] space-y-3">
                            <button
                                type="button"
                                data-open-deposit-modal
                                class="w-full h-11 px-4 bg-[#E5C158] text-[#030407] hover:bg-[#D8E5F8] font-mono text-xs font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                            >
                                + Déposer des Fonds (SEPA)
                            </button>
                            <button
                                type="button"
                                data-open-create-tournament
                                class="w-full h-11 px-4 bg-[#121824] border border-white/15 text-[#F4F7FB] hover:border-[#E5C158] font-mono text-xs font-semibold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                            >
                                Créer une Ligue Privée ↗
                            </button>
                        </div>
                    </aside>

                    <!-- Cockpit Main Workspace (9 Cols) -->
                    <div class="lg:col-span-9 p-6 sm:p-8 space-y-10">
                        <!-- 1. 4 Financial & ELO KPI Cards (Tabular-Nums) -->
                        <div id="cockpit-kpi-panel" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                            <div class="bg-[#080B11] border border-white/[0.08] p-5">
                                <div class="text-[10px] font-mono uppercase tracking-widest text-[#64748B]">SOLDE DISPONIBLE</div>
                                <div id="kpi-balance-cents" class="text-2xl font-mono font-bold text-[#F4F7FB] tabular-nums mt-2">
                                    {{ $wallet ? \App\Models\Wallet::formatCents($wallet->balance_cents) : \App\Models\Wallet::formatCents($platformStats['total_escrow_locked_cents']) }}
                                </div>
                                <div class="text-[11px] font-mono text-[#10B981] mt-1">PRÊT POUR BUY-IN INSTANTANÉ</div>
                            </div>

                            <div class="bg-[#080B11] border border-[#E5C158]/35 p-5">
                                <div class="text-[10px] font-mono uppercase tracking-widest text-[#E5C158]">SÉQUESTRE VERROUILLÉ</div>
                                <div id="kpi-locked-escrow-cents" class="text-2xl font-mono font-bold text-[#E5C158] tabular-nums mt-2">
                                    {{ $wallet ? \App\Models\Wallet::formatCents($wallet->locked_escrow_cents) : \App\Models\Wallet::formatCents($platformStats['total_escrow_locked_cents']) }}
                                </div>
                                <div class="text-[11px] font-mono text-[#94A3B8] mt-1">PROTECTION ACID ACTIVE</div>
                            </div>

                            <div class="bg-[#080B11] border border-white/[0.08] p-5">
                                <div class="text-[10px] font-mono uppercase tracking-widest text-[#64748B]">GAINS CUMULÉS (NET 92%)</div>
                                <div id="kpi-lifetime-won-cents" class="text-2xl font-mono font-bold text-[#10B981] tabular-nums mt-2">
                                    {{ $wallet ? \App\Models\Wallet::formatCents($wallet->lifetime_won_cents) : \App\Models\Wallet::formatCents($platformStats['total_prizes_distributed_cents']) }}
                                </div>
                                <div class="text-[11px] font-mono text-[#94A3B8] mt-1">SPLIT 60% / 30% / 10%</div>
                            </div>

                            <div class="bg-[#080B11] border border-white/[0.08] p-5">
                                <div class="text-[10px] font-mono uppercase tracking-widest text-[#64748B]">COTE COMPÉTITIVE ELO</div>
                                <div id="kpi-elo-rating" class="text-2xl font-mono font-bold text-[#D8E5F8] tabular-nums mt-2">
                                    {{ $currentUser ? $currentUser->elo_rating . ' MMR' : '2 365 MMR' }}
                                </div>
                                <div class="text-[11px] font-mono text-[#D8E5F8] mt-1">DIVISION SOUVERAINE I</div>
                            </div>
                        </div>

                        <!-- 2. Interactive Tournament Bracket (Quarts, Demies, Petite Finale 3e Place, Grande Finale) -->
                        <div id="cockpit-bracket-panel" class="border-t border-white/[0.08] pt-8">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                                <div>
                                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#E5C158]">ARBRE COMPÉTITIF &amp; ALLOCATION CASHPRIZE</div>
                                    <h3 class="font-display font-bold text-xl text-[#F4F7FB] uppercase mt-0.5">
                                        BRACKET INTERACTIF (DEMIES · PETITE FINALE 10% · GRANDE FINALE 60%/30%)
                                    </h3>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @foreach($tournaments->take(3) as $tItem)
                                        <button
                                            type="button"
                                            data-bracket-select="{{ $tItem->id }}"
                                            class="h-9 px-3 border {{ $loop->first ? 'border-[#E5C158] bg-[#121824] text-[#F4F7FB]' : 'border-white/10 bg-[#080B11] text-[#94A3B8]' }} font-mono text-[11px] uppercase tracking-wider cursor-pointer"
                                        >
                                            #{{ $tItem->id }} {{ \Illuminate\Support\Str::limit($tItem->title, 18) }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <div id="interactive-bracket-container">
                                <!-- Hydrated by app.js -->
                            </div>
                        </div>

                        <!-- 3. Immutable Double-Entry Ledger Table (ledger_entries) -->
                        <div id="cockpit-ledger-panel" class="border-t border-white/[0.08] pt-8">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                                <div>
                                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#10B981]">GRAND LIVRE EN PARTIE DOUBLE // APPEND-ONLY</div>
                                    <h3 class="font-display font-bold text-xl text-[#F4F7FB] uppercase mt-0.5">
                                        HISTORIQUE DES ÉCRITURES COMPTABLES (<code class="text-sm text-[#E5C158]">ledger_entries</code>)
                                    </h3>
                                </div>
                                <span class="px-3 py-1 bg-[#080B11] border border-white/10 font-mono text-[11px] text-[#94A3B8] tabular-nums">
                                    INVARIANT: BALANCE_AFTER = BEFORE + CREDIT - DEBIT
                                </span>
                            </div>

                            <div class="overflow-x-auto border border-white/[0.08] bg-[#080B11]">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b border-white/10 bg-[#0C1018] font-mono text-[10px] uppercase tracking-wider text-[#64748B]">
                                            <th class="px-4 py-3">ID</th>
                                            <th class="px-4 py-3">TYPE OPÉRATION</th>
                                            <th class="px-4 py-3">DESCRIPTION</th>
                                            <th class="px-4 py-3 text-right">DÉBIT</th>
                                            <th class="px-4 py-3 text-right">CRÉDIT</th>
                                            <th class="px-4 py-3 text-right">SOLDE APRÈS</th>
                                            <th class="px-4 py-3">CLÉ D'IDEMPOTENCE</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ledger-entries-tbody" class="divide-y divide-white/[0.05]">
                                        <!-- Hydrated by app.js -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             CLASSEMENT GÉNÉRAL ELO & GAINS VÉRIFIÉS (LEADERBOARD)
             ================================================================= -->
        <section id="leaderboard-section" class="py-20 lg:py-24 bg-[#080B11] border-b border-white/[0.08]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-8">
                    <div data-i18n="leaderboard.kicker" class="text-[11px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-2">
                        HIÉRARCHIE SOUVERAINE // CLASSEMENT ELO &amp; GAINS VÉRIFIÉS
                    </div>
                    <h2 data-i18n="leaderboard.title" class="font-display font-extrabold text-2xl sm:text-3xl lg:text-4xl text-[#F4F7FB] tracking-tight uppercase">
                        CLASSEMENT GÉNÉRAL DES OPÉRATEURS
                    </h2>
                </div>

                <div class="overflow-x-auto border border-white/[0.08] bg-[#0C1018]">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-white/10 bg-[#080B11] font-mono text-[11px] uppercase tracking-wider text-[#64748B]">
                                <th class="px-4 py-3.5">RANG</th>
                                <th class="px-4 py-3.5">OPÉRATEUR</th>
                                <th class="px-4 py-3.5">RÔLE</th>
                                <th class="px-4 py-3.5">CLASSE ACTIVE</th>
                                <th class="px-4 py-3.5 text-right">COTE ELO / MMR</th>
                                <th class="px-4 py-3.5 text-right">GAINS CUMULÉS</th>
                                <th class="px-4 py-3.5 text-right">ENJEU SÉQUESTRÉ</th>
                            </tr>
                        </thead>
                        <tbody id="leaderboard-tbody">
                            <!-- Hydrated by app.js -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <!-- =====================================================================
         ARCHITECTURAL FOOTER
         ===================================================================== -->
    <footer class="bg-[#030407] py-12 border-t border-white/[0.08] font-mono text-xs text-[#64748B]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="text-[#E5C158] font-bold">✦ VALKYR // ARENA</span>
                <span>—</span>
                <span class="tabular-nums">PROTOCOLE SÉQUESTRE 0.08 (800 BPS)</span>
            </div>
            <div class="tabular-nums text-center sm:text-right">
                GRAND LIVRE IMMUABLE EN PARTIE DOUBLE // ZERO FLOAT ARITHMETIC
            </div>
        </div>
    </footer>

    <!-- =====================================================================
         MODAL 1: FULLSCREEN 4K LIVE REPLAY MODAL
         ===================================================================== -->
    <div id="replay-fullscreen-modal" data-modal-shell data-modal-backdrop class="hidden fixed inset-0 z-[80] bg-black/90 backdrop-blur-md flex items-center justify-center p-4 sm:p-6">
        <div class="w-full max-w-6xl bg-[#0C1018] border border-white/15 rounded-none overflow-hidden shadow-2xl">
            <div class="px-6 py-4 bg-[#080B11] border-b border-white/10 flex items-center justify-between gap-4 font-mono text-xs tabular-nums">
                <div class="flex items-center gap-3">
                    <span class="text-[#EF4444] font-bold">● REPLAY 4K PLEIN ÉCRAN</span>
                    <span data-live-timecode class="text-[#F4F7FB] font-bold">00:42:18</span>
                    <span class="text-[#10B981]">128-TICK VERIFIED</span>
                </div>
                <button type="button" data-close-modal="replay-fullscreen-modal" class="h-9 px-3 bg-[#121824] border border-white/15 text-[#F4F7FB] hover:border-[#E5C158] cursor-pointer">
                    FERMER [ESC] ✕
                </button>
            </div>

            <div class="relative aspect-[16/9] max-h-[62vh] w-full bg-[#030407] overflow-hidden">
                <img src="/assets/live-combat-hud.png" alt="4K Replay Frame" width="1408" height="768" class="w-full h-full object-cover" style="filter: none; transform: none;" />
                <canvas id="modal-replay-canvas" width="1280" height="720" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>
            </div>

            <!-- Interactive Timeline Scrubber, Speed Controls & POV Selector -->
            <div class="p-6 bg-[#080B11] border-t border-white/10 space-y-4 font-mono text-xs tabular-nums">
                <div class="flex items-center gap-4">
                    <button type="button" id="replay-play-pause-btn" class="h-10 px-4 bg-[#E5C158] text-[#030407] font-bold uppercase tracking-wider cursor-pointer">
                        PAUSE ❚❚
                    </button>
                    <input type="range" id="replay-timeline-slider" min="0" max="100" value="64" class="flex-1 tactical-range" aria-label="Timeline du Replay" />
                    <div class="flex items-center gap-1.5">
                        <button type="button" data-replay-speed="0.5" class="h-9 px-2.5 border border-white/10 text-[#94A3B8] cursor-pointer">0.5x</button>
                        <button type="button" data-replay-speed="1" class="h-9 px-2.5 border border-white/10 bg-[#E5C158] text-[#030407] font-bold cursor-pointer">1x</button>
                        <button type="button" data-replay-speed="2" class="h-9 px-2.5 border border-white/10 text-[#94A3B8] cursor-pointer">2x</button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4 pt-2 border-t border-white/[0.06]">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[#64748B] mr-1">POV CAMÉRA :</span>
                        <button type="button" data-pov-select="VKR_NYX" class="h-9 px-3 border border-[#E5C158] bg-[#121824] text-[#F4F7FB] cursor-pointer">VKR_NYX</button>
                        <button type="button" data-pov-select="KESTREL_ZERO" class="h-9 px-3 border border-white/10 bg-[#080B11] text-[#94A3B8] cursor-pointer">KESTREL_ZERO</button>
                        <button type="button" data-pov-select="AETHER_9" class="h-9 px-3 border border-white/10 bg-[#080B11] text-[#94A3B8] cursor-pointer">AETHER_9</button>
                        <button type="button" data-pov-select="SOVEREIGN_X" class="h-9 px-3 border border-white/10 bg-[#080B11] text-[#94A3B8] cursor-pointer">SOVEREIGN_X</button>
                    </div>
                    <div class="flex items-center gap-4 text-[#94A3B8]">
                        <span>OP: <strong id="modal-pov-operator" class="text-[#E5C158]">VKR_NYX</strong></span>
                        <span>ARME: <strong id="modal-pov-weapon" class="text-[#F4F7FB]">LANCE PLASMA VP-1</strong></span>
                        <span>KDA: <strong id="modal-pov-kda" class="text-[#10B981]">18 / 4 / 9</strong></span>
                        <span>ELO: <strong id="modal-pov-elo" class="text-[#D8E5F8]">2365 MMR</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================================
         MODAL 2: DÉPÔT PORTEFEUILLE (POST /api/wallets/deposit)
         ===================================================================== -->
    <div id="modal-deposit" data-modal-shell data-modal-backdrop class="hidden fixed inset-0 z-[80] bg-black/85 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-[#0C1018] border border-white/15 p-6 sm:p-8 rounded-none shadow-2xl">
            <div class="flex items-center justify-between border-b border-white/10 pb-4 mb-6">
                <div>
                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#E5C158]">CRÉDIT GRAND LIVRE // SEPA INSTANT</div>
                    <h3 class="font-display font-bold text-xl text-[#F4F7FB] uppercase mt-0.5">DÉPOSER DES FONDS SUR LE PORTEFEUILLE</h3>
                </div>
                <button type="button" data-close-modal="modal-deposit" class="h-9 px-3 bg-[#121824] border border-white/15 font-mono text-xs text-[#94A3B8] hover:text-[#F4F7FB] cursor-pointer">✕</button>
            </div>

            <form id="form-wallet-deposit" action="/wallets/deposit" method="POST" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-[10px] font-mono uppercase tracking-wider text-[#64748B] mb-2">MONTANTS RAPIDES (CENTIMES ENTIERS)</label>
                    <div class="grid grid-cols-4 gap-2.5 font-mono text-xs tabular-nums">
                        <button type="button" data-deposit-preset="5000" class="h-10 bg-[#080B11] border border-white/10 hover:border-[#E5C158] text-[#F4F7FB] cursor-pointer">+50,00 €</button>
                        <button type="button" data-deposit-preset="10000" class="h-10 bg-[#080B11] border border-white/10 hover:border-[#E5C158] text-[#F4F7FB] cursor-pointer">+100,00 €</button>
                        <button type="button" data-deposit-preset="25000" class="h-10 bg-[#080B11] border border-white/10 hover:border-[#E5C158] text-[#F4F7FB] cursor-pointer">+250,00 €</button>
                        <button type="button" data-deposit-preset="50000" class="h-10 bg-[#080B11] border border-white/10 hover:border-[#E5C158] text-[#F4F7FB] cursor-pointer">+500,00 €</button>
                    </div>
                </div>

                <div>
                    <label for="deposit-amount-cents" class="block text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1.5">
                        MONTANT EN CENTIMES ENTIERS (<code class="text-[#E5C158]">amount_cents</code> — EX: 10000 = 100,00 €)
                    </label>
                    <input
                        type="number"
                        id="deposit-amount-cents"
                        name="amount_cents"
                        min="100"
                        max="10000000"
                        step="1"
                        value="10000"
                        required
                        class="w-full h-11 px-3.5 bg-[#080B11] border border-white/15 font-mono text-sm text-[#F4F7FB] tabular-nums focus:border-[#E5C158] focus:outline-none"
                    />
                </div>

                <div>
                    <label for="deposit-idempotency-key" class="block text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1.5">
                        CLÉ D'IDEMPOTENCE ANTI-DOUBLON (<code class="text-[#D8E5F8]">idempotency_key</code>)
                    </label>
                    <input
                        type="text"
                        id="deposit-idempotency-key"
                        name="idempotency_key"
                        value="dep:{{ uniqid() }}"
                        required
                        class="w-full h-11 px-3.5 bg-[#080B11] border border-white/15 font-mono text-xs text-[#94A3B8] tabular-nums focus:border-[#D8E5F8] focus:outline-none"
                    />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" data-close-modal="modal-deposit" class="h-11 px-5 bg-[#080B11] border border-white/15 font-mono text-xs uppercase text-[#94A3B8] cursor-pointer">
                        Annuler
                    </button>
                    <button type="submit" class="h-11 px-6 bg-[#E5C158] text-[#030407] hover:bg-[#D8E5F8] font-mono text-xs font-bold uppercase tracking-wider cursor-pointer">
                        Créditer le Portefeuille ↗
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =====================================================================
         MODAL 3: CRÉER UNE LIGUE / TOURNOI (POST /api/tournaments)
         ===================================================================== -->
    <div id="modal-create-tournament" data-modal-shell data-modal-backdrop class="hidden fixed inset-0 z-[80] bg-black/85 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="w-full max-w-xl bg-[#0C1018] border border-white/15 p-6 sm:p-8 rounded-none shadow-2xl">
            <div class="flex items-center justify-between border-b border-white/10 pb-4 mb-6">
                <div>
                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#D8E5F8]">CONSOLE ORGANISATEUR // TOURNAMENT POLICY</div>
                    <h3 class="font-display font-bold text-xl text-[#F4F7FB] uppercase mt-0.5">CRÉER UNE LIGUE PRIVÉE OU UN TOURNOI</h3>
                </div>
                <button type="button" data-close-modal="modal-create-tournament" class="h-9 px-3 bg-[#121824] border border-white/15 font-mono text-xs text-[#94A3B8] hover:text-[#F4F7FB] cursor-pointer">✕</button>
            </div>

            <div class="mb-5 p-3.5 bg-[#080B11] border border-white/10 flex flex-wrap items-center justify-between gap-2 font-mono text-xs">
                <span class="text-[#94A3B8]">Rôle requis : <strong class="text-[#E5C158]">ORGANIZER</strong> ou <strong class="text-[#E5C158]">ADMIN</strong></span>
                <button type="button" data-demo-email="organizer@valkyr.arena" class="px-2.5 py-1 bg-[#121824] border border-[#D8E5F8]/40 text-[#D8E5F8] hover:bg-[#D8E5F8] hover:text-[#030407] transition cursor-pointer">
                    Activer VKR_ARBITER (1 clic) ↗
                </button>
            </div>

            <form id="form-create-tournament" action="/tournaments" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="create-title" class="block text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">TITRE OFFICIEL DE L'ARÈNE</label>
                    <input
                        type="text"
                        id="create-title"
                        name="title"
                        value="VALKYR INVITATIONAL // DIVISION PRO"
                        required
                        class="w-full h-11 px-3.5 bg-[#080B11] border border-white/15 font-mono text-xs text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="create-game" class="block text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">DISCIPLINE</label>
                        <select id="create-game" name="game" class="w-full h-11 px-3 bg-[#080B11] border border-white/15 font-mono text-xs text-[#F4F7FB]">
                            <option value="VALORANT // PROTOCOL">VALORANT // PROTOCOL</option>
                            <option value="COUNTER-STRIKE 2">COUNTER-STRIKE 2</option>
                            <option value="APEX // SOVEREIGN">APEX // SOVEREIGN</option>
                            <option value="LEAGUE OF LEGENDS">LEAGUE OF LEGENDS</option>
                        </select>
                    </div>
                    <div>
                        <label for="create-format" class="block text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">FORMAT</label>
                        <select id="create-format" name="format" class="w-full h-11 px-3 bg-[#080B11] border border-white/15 font-mono text-xs text-[#F4F7FB]">
                            <option value="5v5">5v5 (Escouade Tactique)</option>
                            <option value="1v1">1v1 (Duel Souverain)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="create-fee" class="block text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">BUY-IN EN CENTIMES (EX: 5000 = 50 €)</label>
                        <input
                            type="number"
                            id="create-fee"
                            name="entry_fee_cents"
                            min="100"
                            step="100"
                            value="5000"
                            required
                            class="w-full h-11 px-3.5 bg-[#080B11] border border-white/15 font-mono text-sm text-[#E5C158] tabular-nums"
                        />
                    </div>
                    <div>
                        <label for="create-max" class="block text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1"> CAPACITÉ MAX OPÉRATEURS</label>
                        <input
                            type="number"
                            id="create-max"
                            name="max_participants"
                            min="2"
                            max="128"
                            value="16"
                            required
                            class="w-full h-11 px-3.5 bg-[#080B11] border border-white/15 font-mono text-sm text-[#F4F7FB] tabular-nums"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3">
                    <button type="button" data-close-modal="modal-create-tournament" class="h-11 px-5 bg-[#080B11] border border-white/15 font-mono text-xs uppercase text-[#94A3B8] cursor-pointer">
                        Annuler
                    </button>
                    <button type="submit" class="h-11 px-6 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider cursor-pointer">
                        Armer le Séquestre &amp; Publier ↗
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =====================================================================
         MODAL 4: INSCRIPTION BUY-IN SÉQUESTRE (POST /api/tournaments/{id}/register)
         ===================================================================== -->
    <div id="modal-register-tournament" data-modal-shell data-modal-backdrop class="hidden fixed inset-0 z-[80] bg-black/85 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-[#0C1018] border border-white/15 p-6 sm:p-8 rounded-none shadow-2xl">
            <div class="flex items-center justify-between border-b border-white/10 pb-4 mb-6">
                <div>
                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#10B981]">VERROUILLAGE ACID // SELECT ... FOR UPDATE</div>
                    <h3 id="reg-modal-title" class="font-display font-bold text-xl text-[#F4F7FB] uppercase mt-0.5">INSCRIPTION AU TOURNOI</h3>
                    <div id="reg-modal-game" class="text-xs font-mono text-[#94A3B8]">VALORANT // 5v5</div>
                </div>
                <button type="button" data-close-modal="modal-register-tournament" class="h-9 px-3 bg-[#121824] border border-white/15 font-mono text-xs text-[#94A3B8] hover:text-[#F4F7FB] cursor-pointer">✕</button>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-5 font-mono tabular-nums">
                <div class="bg-[#080B11] border border-white/10 p-3">
                    <div class="text-[10px] text-[#64748B]">FRAIS D'ENTRÉE (BUY-IN)</div>
                    <div id="reg-modal-fee" class="text-base font-bold text-[#E5C158] mt-0.5">100,00 €</div>
                </div>
                <div class="bg-[#080B11] border border-white/10 p-3">
                    <div class="text-[10px] text-[#64748B]">POOL SÉQUESTRÉ ACTUEL</div>
                    <div id="reg-modal-pool" class="text-base font-bold text-[#10B981] mt-0.5">300,00 €</div>
                </div>
                <div class="bg-[#080B11] border border-white/10 p-3">
                    <div class="text-[10px] text-[#64748B]">VOTRE SOLDE ACTUEL</div>
                    <div id="reg-modal-balance" class="text-sm font-bold text-[#F4F7FB] mt-0.5">1 500,00 €</div>
                </div>
                <div class="bg-[#080B11] border border-white/10 p-3">
                    <div class="text-[10px] text-[#64748B]">SOLDE APRÈS SÉQUESTRE</div>
                    <div id="reg-modal-balance-after" class="text-sm font-bold text-[#D8E5F8] mt-0.5">1 400,00 €</div>
                </div>
            </div>

            <form id="form-register-tournament" data-tournament-id="1" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="register-loadout-select" class="block text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">CLASSE D'ARSENAL ENGAGÉE</label>
                    <select id="register-loadout-select" name="loadout_class" class="w-full h-11 px-3 bg-[#080B11] border border-white/15 font-mono text-xs text-[#F4F7FB]">
                        <option value="VALKYRIE">VALKYRIE // SOVEREIGN-PRIME</option>
                        <option value="VANGUARD">VANGUARD // AEGIS-01</option>
                        <option value="SPECTRE">SPECTRE // KESTREL-V</option>
                        <option value="CHRONO">CHRONO // ORION-IX</option>
                    </select>
                </div>

                <div>
                    <label for="register-idempotency-key" class="block text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">CLÉ D'IDEMPOTENCE (<code class="text-[#D8E5F8]">idempotency_key</code>)</label>
                    <input
                        type="text"
                        id="register-idempotency-key"
                        name="idempotency_key"
                        value="buyin:{{ uniqid() }}"
                        required
                        class="w-full h-11 px-3 bg-[#080B11] border border-white/15 font-mono text-xs text-[#94A3B8] tabular-nums"
                    />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" data-close-modal="modal-register-tournament" class="h-11 px-5 bg-[#080B11] border border-white/15 font-mono text-xs uppercase text-[#94A3B8] cursor-pointer">
                        Annuler
                    </button>
                    <button type="submit" class="h-11 px-6 bg-[#10B981] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider cursor-pointer">
                        Verrouiller le Buy-in en Séquestre ↗
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =====================================================================
         MODAL 5: DÉTAIL DU TOURNOI, CLÔTURE 60/30/10 (settle) & ANNULATION (cancel)
         ===================================================================== -->
    <div id="modal-tournament-detail" data-modal-shell data-modal-backdrop class="hidden fixed inset-0 z-[80] bg-black/85 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
        <div class="w-full max-w-3xl bg-[#0C1018] border border-white/15 p-6 sm:p-8 rounded-none shadow-2xl my-8">
            <div class="flex items-start justify-between border-b border-white/10 pb-4 mb-6 gap-4">
                <div>
                    <div id="detail-modal-meta" class="text-[10px] font-mono uppercase tracking-widest text-[#E5C158]">VALORANT // 5V5</div>
                    <h3 id="detail-modal-title" class="font-display font-bold text-xl sm:text-2xl text-[#F4F7FB] uppercase mt-0.5">DÉTAIL &amp; ARBITRAGE DU SÉQUESTRE</h3>
                </div>
                <button type="button" data-close-modal="modal-tournament-detail" class="h-9 px-3 bg-[#121824] border border-white/15 font-mono text-xs text-[#94A3B8] hover:text-[#F4F7FB] cursor-pointer">✕</button>
            </div>

            <!-- Mathematical Breakdown Preview (8% Rake + 60/30/10 Net Pool) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 mb-6 font-mono tabular-nums">
                <div class="bg-[#080B11] border border-white/10 p-3">
                    <div class="text-[9px] text-[#64748B]">POOL SÉQUESTRÉ</div>
                    <div id="detail-modal-pool" class="text-xs font-bold text-[#F4F7FB] mt-1">300,00 €</div>
                </div>
                <div class="bg-[#080B11] border border-white/10 p-3">
                    <div class="text-[9px] text-[#64748B]">RAKE (800 BPS / 8%)</div>
                    <div id="detail-modal-rake" class="text-xs font-bold text-[#F59E0B] mt-1">24,00 €</div>
                </div>
                <div class="bg-[#080B11] border border-white/10 p-3">
                    <div class="text-[9px] text-[#64748B]">POOL NET (92%)</div>
                    <div id="detail-modal-net" class="text-xs font-bold text-[#10B981] mt-1">276,00 €</div>
                </div>
                <div class="bg-[#080B11] border border-[#E5C158]/40 p-3">
                    <div class="text-[9px] text-[#E5C158]">1ER PRIX (60%)</div>
                    <div id="detail-modal-1st" class="text-xs font-bold text-[#E5C158] mt-1">165,60 €</div>
                </div>
                <div class="bg-[#080B11] border border-white/10 p-3">
                    <div class="text-[9px] text-[#D8E5F8]">2E PRIX (30%)</div>
                    <div id="detail-modal-2nd" class="text-xs font-bold text-[#D8E5F8] mt-1">82,80 €</div>
                </div>
                <div class="bg-[#080B11] border border-white/10 p-3">
                    <div class="text-[9px] text-[#10B981]">3E PRIX (10%)</div>
                    <div id="detail-modal-3rd" class="text-xs font-bold text-[#10B981] mt-1">27,60 €</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left 5 Cols: Registered Roster -->
                <div class="lg:col-span-5">
                    <div class="text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-2">OPÉRATEURS INSCRITS EN SÉQUESTRE</div>
                    <div id="detail-modal-roster" class="space-y-2 max-h-64 overflow-y-auto pr-1">
                        <!-- Hydrated by app.js -->
                    </div>
                </div>

                <!-- Right 7 Cols: Official Settlement Form (60/30/10) & Cancellation Form (100% Refund) -->
                <div class="lg:col-span-7 space-y-6">
                    <form id="form-settle-tournament" data-tournament-id="1" method="POST" class="bg-[#080B11] border border-white/10 p-4 space-y-3">
                        @csrf
                        <div class="text-xs font-mono font-bold text-[#E5C158] uppercase">
                            CLÔTURE OFFICIELLE &amp; DISTRIBUTION DU CASHPRIZE (60/30/10)
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <div>
                                <label for="settle-first-place" class="block text-[10px] font-mono text-[#94A3B8] mb-1">1RE PLACE (60%)</label>
                                <select id="settle-first-place" name="first_place_user_id" class="w-full h-10 px-2 bg-[#0C1018] border border-white/15 font-mono text-xs text-[#F4F7FB]"></select>
                            </div>
                            <div>
                                <label for="settle-second-place" class="block text-[10px] font-mono text-[#94A3B8] mb-1">2E PLACE (30%)</label>
                                <select id="settle-second-place" name="second_place_user_id" class="w-full h-10 px-2 bg-[#0C1018] border border-white/15 font-mono text-xs text-[#F4F7FB]"></select>
                            </div>
                            <div>
                                <label for="settle-third-place" class="block text-[10px] font-mono text-[#94A3B8] mb-1">3E PLACE (10%)</label>
                                <select id="settle-third-place" name="third_place_user_id" class="w-full h-10 px-2 bg-[#0C1018] border border-white/15 font-mono text-xs text-[#F4F7FB]"></select>
                            </div>
                        </div>
                        <div>
                            <label for="settle-idempotency-key" class="block text-[10px] font-mono text-[#64748B] mb-1">CLÉ D'IDEMPOTENCE DE RÈGLEMENT</label>
                            <input type="text" id="settle-idempotency-key" name="idempotency_key" value="settle:{{ uniqid() }}" required class="w-full h-9 px-2.5 bg-[#0C1018] border border-white/10 font-mono text-xs text-[#94A3B8] tabular-nums" />
                        </div>
                        <button type="submit" class="w-full h-11 bg-[#E5C158] text-[#030407] hover:bg-[#D8E5F8] font-mono text-xs font-bold uppercase tracking-wider cursor-pointer">
                            Homologuer le Podium &amp; Distribuer le Séquestre ↗
                        </button>
                    </form>

                    <!-- Cancel & 100% Escrow Refund Form -->
                    <form id="form-cancel-tournament" data-tournament-id="1" method="POST" class="bg-[#080B11] border border-[#EF4444]/30 p-4 space-y-3">
                        @csrf
                        <div class="text-xs font-mono font-bold text-[#EF4444] uppercase">
                            ANNULATION D'URGENCE &amp; REMBOURSEMENT INTÉGRAL (100% ESCROW)
                        </div>
                        <input type="hidden" id="cancel-idempotency-key" name="idempotency_key" value="cancel:{{ uniqid() }}" />
                        <input type="text" name="reason" placeholder="Motif officiel de l'annulation..." value="Annulation administrative — remboursement intégral 100%" class="w-full h-9 px-2.5 bg-[#0C1018] border border-white/10 font-mono text-xs text-[#94A3B8]" />
                        <button type="submit" class="w-full h-10 bg-[#EF4444]/20 border border-[#EF4444]/60 text-[#EF4444] hover:bg-[#EF4444] hover:text-[#030407] font-mono text-xs font-bold uppercase tracking-wider transition cursor-pointer">
                            Annuler le Tournoi &amp; Rembourser 100% aux Joueurs ↺
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
