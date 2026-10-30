<!DOCTYPE html>
<html lang="fr" class="dark" style="color-scheme: dark;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ ($initialTab ?? 'login') === 'register' ? 'Créer un Compte Opérateur — VALKYR // ARENA' : 'Connexion Tactique — VALKYR // ARENA' }}</title>
    <meta name="description" content="Portail d'authentification cryptographique et création de portefeuille séquestre VALKYR // ARENA.">
    <meta property="og:title" content="VALKYR // ARENA — Portail d'Authentification Souverain">
    <meta property="og:type" content="website">
    <meta property="og:image" content="/assets/hero-valkyr-arena.png">

    <!-- Bespoke Sovereign Inline SVG Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' fill='%23030407'/%3E%3Crect x='2' y='2' width='60' height='60' fill='none' stroke='%23E5C158' stroke-width='2'/%3E%3Cpath d='M32 10 L38 26 L54 32 L38 38 L32 54 L26 38 L10 32 L26 26 Z' fill='%23E5C158'/%3E%3C/svg%3E">

    <!-- Google Fonts: Syne (Display Roman), Instrument Serif (True Italic), Plus Jakarta Sans, JetBrains Mono (Tabular-Nums) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">

    @php
        $hydratedState = [
            'page' => 'auth',
            'initialTab' => $initialTab ?? 'login',
            'currentUser' => auth()->user(),
            'platformStats' => $platformStats ?? [
                'total_escrow_locked_cents' => 30000,
                'total_prizes_distributed_cents' => 36800,
                'active_arenas_count' => 3,
                'platform_rake_bps' => 800,
            ],
        ];
    @endphp
    <script>
        window.__VALKYR_STATE__ = @json($hydratedState);
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#030407] text-[#F4F7FB] font-sans antialiased selection:bg-[#E5C158] selection:text-[#030407] overflow-x-hidden">

    <!-- Toast Notification Stack -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-[100] flex flex-col gap-3 pointer-events-none max-w-md w-[calc(100vw-3rem)]"></div>

    <!-- =====================================================================
         PAGE 2: FULLSCREEN 50/50 ASYMMETRIC SPLIT-SCREEN AUTH PORTAL
         ===================================================================== -->
    <main class="min-h-dvh w-full grid grid-cols-1 lg:grid-cols-12 bg-[#030407]">

        <!-- =================================================================
             LEFT 50% (6 COLS): PURE 4K ARTWORK, EDITORIAL STATEMENT & 1-CLICK DEMO SIMULATOR
             ================================================================= -->
        <section aria-label="Télémétrie &amp; Simulateur Démo" class="lg:col-span-6 relative overflow-hidden bg-[#030407] border-b lg:border-b-0 lg:border-r border-white/[0.08] flex flex-col justify-between p-6 sm:p-10 lg:p-12 min-h-[540px] lg:min-h-dvh">
            <!-- Lossless Native 4K/8K Artwork: filter:none, transform:none, opacity:1, ZERO full-screen dark veil -->
            <img
                id="auth-hero-artwork"
                src="/assets/hero-valkyr-arena.png"
                alt="VALKYR Sovereign Arena"
                width="1408"
                height="768"
                fetchpriority="high"
                decoding="sync"
                loading="eager"
                class="absolute inset-0 w-full h-full object-cover object-[74%_center] select-none pointer-events-none"
                style="filter: none; transform: none; will-change: auto; opacity: 1;"
            />

            <!-- Narrow Bottom-Edge Seam Blend ONLY -->
            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-28 bg-gradient-to-t from-[#030407] via-[#030407]/60 to-transparent"></div>

            <!-- Top Bar: Brand Identity + Cryptographic Protocol Status -->
            <div class="relative z-10 flex flex-wrap items-center justify-between gap-4">
                <a href="/" class="font-display font-extrabold text-lg sm:text-xl tracking-tight text-[#F4F7FB] flex items-center gap-2.5 whitespace-nowrap hero-optical-shadow">
                    <span class="text-[#E5C158]" aria-hidden="true">✦</span>
                    <span>VALKYR // ARENA</span>
                </a>
                <span class="px-3 py-1 bg-[#080B11]/90 border border-[#E5C158]/40 text-[#E5C158] font-mono text-[10px] font-semibold uppercase tracking-widest tabular-nums">
                    PROTOCOLE SÉQUESTRE 0.08 // ACID
                </span>
            </div>

            <!-- Middle Editorial Statement + Live Ledger Telemetry -->
            <div class="relative z-10 my-8">
                <div class="flex items-center gap-3 mb-3 hero-optical-shadow">
                    <span class="inline-block w-8 h-px bg-[#E5C158] shrink-0" aria-hidden="true"></span>
                    <span class="text-[10px] sm:text-[11px] font-mono uppercase tracking-[0.22em] text-white/85 tabular-nums">
                        PORTAIL D'AUTHENTIFICATION CRYPTOGRAPHIQUE
                    </span>
                </div>

                <h1 class="hero-optical-shadow font-display font-extrabold tracking-[-0.03em] leading-[1.05] text-[#F4F7FB] text-[clamp(1.5rem,3vw,2.75rem)] mb-4">
                    <span class="block uppercase">VOS ENJEUX VERROUILLÉS.</span>
                    <em class="block font-serif-italic italic font-normal text-[#E5C158] tracking-[-0.015em] text-[clamp(1.7rem,3.3vw,3.1rem)]">Zéro Confiance Aveugle.</em>
                </h1>

                <!-- Live Ledger Telemetry Strip -->
                <div class="grid grid-cols-3 gap-px bg-white/10 border border-white/15 mb-6 max-w-xl">
                    <div class="bg-[#080B11]/95 p-3">
                        <div class="text-[9px] font-mono uppercase tracking-wider text-[#64748B]">SÉQUESTRE ACTIF</div>
                        <div class="text-xs sm:text-sm font-mono font-bold text-[#E5C158] tabular-nums mt-0.5">
                            {{ \App\Models\Wallet::formatCents($platformStats['total_escrow_locked_cents'] ?? 30000) }}
                        </div>
                    </div>
                    <div class="bg-[#080B11]/95 p-3">
                        <div class="text-[9px] font-mono uppercase tracking-wider text-[#64748B]">GAINS DISTRIBUÉS</div>
                        <div class="text-xs sm:text-sm font-mono font-bold text-[#10B981] tabular-nums mt-0.5">
                            {{ \App\Models\Wallet::formatCents($platformStats['total_prizes_distributed_cents'] ?? 36800) }}
                        </div>
                    </div>
                    <div class="bg-[#080B11]/95 p-3">
                        <div class="text-[9px] font-mono uppercase tracking-wider text-[#64748B]">COMMISSION STRICTE</div>
                        <div class="text-xs sm:text-sm font-mono font-bold text-[#D8E5F8] tabular-nums mt-0.5">
                            8,00 % (800 BPS)
                        </div>
                    </div>
                </div>

                <!-- 1-Click Demo Role Simulator Box -->
                <div id="auth-demo-simulator" class="bg-[#080B11]/95 border border-white/15 p-4 sm:p-5 max-w-xl">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <span class="px-2 py-0.5 bg-[#E5C158]/15 border border-[#E5C158]/40 text-[#E5C158] font-mono text-[10px] font-bold uppercase tracking-widest">
                            SIMULATEUR DE RÔLES // CONNEXION DÉMO EN 1 CLIC
                        </span>
                        <span class="text-[10px] font-mono text-[#94A3B8] tabular-nums">
                            REDIRECTION AUTO ➔ /dashboard
                        </span>
                    </div>
                    <p class="text-xs text-[#94A3B8] mb-3.5 leading-relaxed">
                        Testez instantanément l'Espace Membre en incarnant le Commandement (<code class="text-[#E5C158]">ADMIN</code>), un Arbitre (<code class="text-[#D8E5F8]">ORGANIZER</code>) ou un Joueur Compétitif (<code class="text-[#10B981]">PLAYER</code>) :
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        @foreach($demoUsers as $demoUser)
                            @php
                                $roleColor = $demoUser->role === 'ADMIN'
                                    ? 'text-[#E5C158] border-[#E5C158]/40'
                                    : ($demoUser->role === 'ORGANIZER' ? 'text-[#D8E5F8] border-[#D8E5F8]/40' : 'text-[#10B981] border-[#10B981]/40');
                                $balCents = $demoUser->wallet ? $demoUser->wallet->balance_cents : 0;
                            @endphp
                            <button
                                type="button"
                                data-demo-email="{{ $demoUser->email }}"
                                data-demo-redirect="/dashboard"
                                class="h-12 px-3 bg-[#0C1018] border border-white/10 hover:border-[#E5C158] text-left flex items-center justify-between font-mono text-xs transition cursor-pointer min-w-0"
                            >
                                <div class="min-w-0 pr-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-[#F4F7FB] truncate">{{ $demoUser->gamertag }}</span>
                                        <span class="px-1.5 py-0.2 text-[9px] border {{ $roleColor }}">{{ $demoUser->role }}</span>
                                    </div>
                                    <span class="text-[10px] text-[#94A3B8] tabular-nums block truncate">
                                        {{ \App\Models\Wallet::formatCents($balCents) }} · {{ $demoUser->elo_rating }} MMR
                                    </span>
                                </div>
                                <span class="text-[#E5C158] shrink-0 font-bold">↗</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Bottom Left Note -->
            <div class="relative z-10 text-[11px] font-mono text-[#94A3B8] hero-optical-shadow flex flex-wrap items-center justify-between gap-2 tabular-nums">
                <span>MOT DE PASSE UNIVERSEL DÉMO : <code class="text-[#E5C158] font-bold">Password123!</code></span>
                <span>DOUBLE-ENTRY LEDGER // SHA-256</span>
            </div>
        </section>

        <!-- =================================================================
             RIGHT 50% (6 COLS): PURE OBSIDIAN WORKSPACE (SIGN IN <-> REGISTER)
             ================================================================= -->
        <section aria-label="Formulaires d'Authentification et d'Inscription" class="lg:col-span-6 bg-[#080B11] flex flex-col justify-between p-6 sm:p-10 lg:p-12">
            <!-- Top Navigation Bar -->
            <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-white/[0.08]">
                <a
                    href="/"
                    class="h-11 px-4 bg-[#0C1018] border border-white/15 hover:border-[#D8E5F8] text-[#F4F7FB] font-mono text-xs font-semibold uppercase tracking-wider inline-flex items-center gap-2 rounded-none whitespace-nowrap transition"
                >
                    <span>← Retour à l'Arène Publique</span>
                </a>

                <div class="flex items-center gap-3">
                    @auth
                        <a href="/dashboard" class="h-11 px-4 bg-[#E5C158] text-[#030407] font-mono text-xs font-bold uppercase tracking-wider inline-flex items-center rounded-none whitespace-nowrap">
                            Mon Cockpit ({{ auth()->user()->gamertag }}) ↗
                        </a>
                    @endauth
                    <div class="flex items-center gap-1.5 px-3 py-2 border border-white/10 bg-[#0C1018] font-mono text-xs">
                        <button type="button" data-lang-btn="fr" class="px-1 text-[#E5C158] font-bold cursor-pointer">FR</button>
                        <span class="text-white/20">/</span>
                        <button type="button" data-lang-btn="en" class="px-1 text-[#64748B] hover:text-[#F4F7FB] cursor-pointer">EN</button>
                    </div>
                </div>
            </div>

            <!-- Centered Form Workspace -->
            <div class="w-full max-w-xl mx-auto my-auto py-8">
                <div class="mb-6">
                    <div class="text-[11px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-1.5">
                        ESPACE MEMBRE SOUVERAIN // ACCÈS COCKPIT
                    </div>
                    <h2 class="font-display font-extrabold text-2xl sm:text-3xl text-[#F4F7FB] tracking-tight uppercase">
                        IDENTIFICATION OPÉRATEUR &amp; SÉQUESTRE
                    </h2>
                </div>

                <!-- Underline Tabs: Connexion (Sign in) <-> Créer un compte (Register) -->
                @php
                    $isRegisterTab = ($initialTab ?? 'login') === 'register';
                @endphp
                <div class="flex items-center gap-3 sm:gap-6 border-b border-white/10 mb-8" role="tablist">
                    <button
                        type="button"
                        id="tab-auth-login"
                        role="tab"
                        aria-selected="{{ $isRegisterTab ? 'false' : 'true' }}"
                        class="pb-3.5 {{ $isRegisterTab ? 'text-[#64748B] hover:text-[#F4F7FB]' : 'border-b-2 border-[#E5C158] text-[#F4F7FB]' }} font-mono text-[11px] sm:text-sm font-bold uppercase tracking-normal sm:tracking-wider transition cursor-pointer whitespace-nowrap"
                    >
                        Connexion (Sign in)
                    </button>
                    <button
                        type="button"
                        id="tab-auth-register"
                        role="tab"
                        aria-selected="{{ $isRegisterTab ? 'true' : 'false' }}"
                        class="pb-3.5 {{ $isRegisterTab ? 'border-b-2 border-[#E5C158] text-[#F4F7FB]' : 'text-[#64748B] hover:text-[#F4F7FB]' }} font-mono text-[11px] sm:text-sm font-bold uppercase tracking-normal sm:tracking-wider transition cursor-pointer whitespace-nowrap"
                    >
                        Créer un compte (Register)
                    </button>
                </div>

                <!-- =========================================================
                     PANEL 1: CONNEXION (SIGN IN)
                     ========================================================= -->
                <div id="panel-auth-login" class="{{ $isRegisterTab ? 'hidden' : '' }}">
                    <form id="form-auth-login" action="/login" method="POST" data-auth-redirect="/dashboard" class="space-y-5">
                        @csrf
                        <div>
                            <label for="login-email" class="block text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-2">
                                ADRESSE EMAIL OPÉRATEUR (<code class="text-[#D8E5F8]">email</code>)
                            </label>
                            <input
                                type="email"
                                id="login-email"
                                name="email"
                                value="nyx@valkyr.arena"
                                required
                                autocomplete="email"
                                class="w-full h-12 px-4 bg-[#0C1018] border border-white/15 text-sm font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none rounded-none"
                            />
                        </div>

                        <div>
                            <label for="login-password" class="block text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-2">
                                MOT DE PASSE TACTIQUE (<code class="text-[#D8E5F8]">password</code>)
                            </label>
                            <div class="flex items-stretch">
                                <input
                                    type="password"
                                    id="login-password"
                                    name="password"
                                    value="Password123!"
                                    required
                                    autocomplete="current-password"
                                    class="flex-1 h-12 px-4 bg-[#0C1018] border border-r-0 border-white/15 text-sm font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none rounded-none min-w-0"
                                />
                                <button
                                    type="button"
                                    data-toggle-password="login-password"
                                    class="h-12 px-4 bg-[#121824] border border-white/15 text-xs font-mono font-semibold text-[#D8E5F8] hover:text-[#E5C158] rounded-none whitespace-nowrap cursor-pointer"
                                >
                                    AFFICHER
                                </button>
                            </div>
                        </div>

                        <div class="p-3.5 bg-[#0C1018] border border-white/[0.08] font-mono text-xs text-[#94A3B8] flex items-center justify-between gap-2 tabular-nums">
                            <span>Compte pré-rempli : <strong class="text-[#10B981]">VKR_NYX (PLAYER)</strong></span>
                            <span>Solde : <strong class="text-[#E5C158]">1 500,00 €</strong></span>
                        </div>

                        <button
                            type="submit"
                            id="login-submit-btn"
                            class="w-full h-12 px-6 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                        >
                            Initialiser la Connexion &amp; Ouvrir le Cockpit ↗
                        </button>
                    </form>
                </div>

                <!-- =========================================================
                     PANEL 2: CRÉER UN COMPTE OPÉRATEUR (REGISTER)
                     ========================================================= -->
                <div id="panel-auth-register" class="{{ $isRegisterTab ? '' : 'hidden' }}">
                    <form id="form-auth-register" action="/register" method="POST" data-auth-redirect="/dashboard" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="reg-name" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1.5">
                                    NOM COMPLET D'OPÉRATEUR
                                </label>
                                <input
                                    type="text"
                                    id="reg-name"
                                    name="name"
                                    placeholder="Ex: Astrid Lindholm"
                                    required
                                    class="w-full h-11 px-3.5 bg-[#0C1018] border border-white/15 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none rounded-none"
                                />
                            </div>
                            <div>
                                <label for="reg-gamertag" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1.5">
                                    GAMERTAG UNIQUE (<code class="text-[#E5C158]">A-Z0-9_-</code>)
                                </label>
                                <input
                                    type="text"
                                    id="reg-gamertag"
                                    name="gamertag"
                                    placeholder="Ex: VKR_ASTRID"
                                    required
                                    class="w-full h-11 px-3.5 bg-[#0C1018] border border-white/15 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none rounded-none"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="reg-email" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1.5">
                                    ADRESSE EMAIL
                                </label>
                                <input
                                    type="email"
                                    id="reg-email"
                                    name="email"
                                    placeholder="astrid@valkyr.arena"
                                    required
                                    class="w-full h-11 px-3.5 bg-[#0C1018] border border-white/15 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none rounded-none"
                                />
                            </div>
                            <div>
                                <label for="reg-account-type" class="block text-[10px] font-mono uppercase tracking-wider text-[#E5C158] mb-1.5">
                                    ACCRÉDITATION / RÔLE (<code class="text-[#E5C158]">account_type</code>)
                                </label>
                                <select
                                    id="reg-account-type"
                                    name="account_type"
                                    class="w-full h-11 px-3 bg-[#0C1018] border border-[#E5C158]/40 text-xs font-mono text-[#F4F7FB] focus:border-[#E5C158] focus:outline-none rounded-none"
                                >
                                    <option value="PLAYER">PLAYER — Joueur Compétitif (1 000,00 € Démo)</option>
                                    <option value="ORGANIZER">ORGANIZER — Arbitre &amp; Créateur de Ligues</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="reg-class" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1.5">
                                CLASSE D'ARSENAL PAR DÉFAUT (<code class="text-[#D8E5F8]">preferred_class</code>)
                            </label>
                            <select
                                id="reg-class"
                                name="preferred_class"
                                class="w-full h-11 px-3 bg-[#0C1018] border border-white/15 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none rounded-none"
                            >
                                <option value="VALKYRIE">VALKYRIE // SOVEREIGN-PRIME (Assaut Aérien)</option>
                                <option value="VANGUARD">VANGUARD // AEGIS-01 (Rempart Balistique)</option>
                                <option value="SPECTRE">SPECTRE // KESTREL-V (Reconnaissance Furtive)</option>
                                <option value="CHRONO">CHRONO // ORION-IX (Distorsion Temporelle)</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="reg-password" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1.5">
                                    MOT DE PASSE (MIN 8 CAR.)
                                </label>
                                <div class="flex items-stretch">
                                    <input
                                        type="password"
                                        id="reg-password"
                                        name="password"
                                        required
                                        class="flex-1 h-11 px-3 bg-[#0C1018] border border-r-0 border-white/15 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none rounded-none min-w-0"
                                    />
                                    <button
                                        type="button"
                                        data-toggle-password="reg-password"
                                        class="h-11 px-3 bg-[#121824] border border-white/15 text-[10px] font-mono font-semibold text-[#D8E5F8] hover:text-[#E5C158] rounded-none whitespace-nowrap cursor-pointer"
                                    >
                                        AFFICHER
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label for="reg-password-confirm" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1.5">
                                    CONFIRMATION MOT DE PASSE
                                </label>
                                <input
                                    type="password"
                                    id="reg-password-confirm"
                                    name="password_confirmation"
                                    required
                                    class="w-full h-11 px-3.5 bg-[#0C1018] border border-white/15 text-xs font-mono text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none rounded-none"
                                />
                            </div>
                        </div>

                        <div class="p-3 bg-[#0C1018] border border-[#10B981]/30 font-mono text-[11px] text-[#10B981] tabular-nums">
                            ✓ Allocation initiale automatique : <strong>1 000,00 € (100 000 centimes)</strong> crédités sur le grand livre à l'ouverture du compte.
                        </div>

                        <button
                            type="submit"
                            id="register-submit-btn"
                            class="w-full h-12 px-6 bg-[#E5C158] text-[#030407] hover:bg-[#D8E5F8] font-mono text-xs font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                        >
                            Créer mon Compte Opérateur &amp; Portefeuille ↗
                        </button>
                    </form>
                </div>
            </div>

            <!-- Bottom Security Footer -->
            <div class="pt-6 border-t border-white/[0.08] flex flex-col sm:flex-row items-center justify-between gap-3 font-mono text-[11px] text-[#64748B] tabular-nums">
                <span>PROTECTION ANTI-BFLA // SESSIONS CHIFFRÉES</span>
                <span>GRAND LIVRE EN PARTIE DOUBLE // ZERO FLOAT</span>
            </div>
        </section>
    </main>

</body>
</html>
