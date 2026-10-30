<!DOCTYPE html>
<html lang="fr" class="dark" style="color-scheme: dark;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $tournament->title }} — Arène &amp; Séquestre — VALKYR // ARENA</title>
    <meta name="description" content="Salle de tournoi, arbre compétitif, roster des opérateurs inscrits et grand livre de séquestre atomique pour {{ $tournament->title }}.">

    <!-- Bespoke Sovereign Inline SVG Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' fill='%23030407'/%3E%3Crect x='2' y='2' width='60' height='60' fill='none' stroke='%23E5C158' stroke-width='2'/%3E%3Cpath d='M32 10 L38 26 L54 32 L38 38 L32 54 L26 38 L10 32 L26 26 Z' fill='%23E5C158'/%3E%3C/svg%3E">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">

    @php
        $hydratedState = [
            'page' => 'tournament-show',
            'tournament' => $tournament,
            'currentUser' => $currentUser,
            'wallet' => $wallet,
        ];
    @endphp
    <script>
        window.__VALKYR_STATE__ = @json($hydratedState);
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#030407] text-[#F4F7FB] font-sans antialiased selection:bg-[#E5C158] selection:text-[#030407] overflow-x-hidden min-h-dvh flex flex-col">

    <!-- Toast Notification Stack -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-[100] flex flex-col gap-3 pointer-events-none max-w-md w-[calc(100vw-3rem)]"></div>

    <!-- =====================================================================
         TOPBAR & BREADCRUMB NAVIGATION
         ===================================================================== -->
    <header class="sticky top-0 z-50 bg-[#080B11]/95 backdrop-blur-md border-b border-white/[0.08]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <a href="/" class="font-display font-extrabold text-lg tracking-tight text-[#F4F7FB] flex items-center gap-2 whitespace-nowrap">
                    <span class="text-[#E5C158]" aria-hidden="true">✦</span>
                    <span>VALKYR</span>
                </a>
                <span class="text-white/20">/</span>
                <a
                    href="/"
                    class="h-10 px-3.5 bg-[#0C1018] border border-white/15 hover:border-[#D8E5F8] text-[#94A3B8] hover:text-[#F4F7FB] font-mono text-xs font-semibold uppercase tracking-wider inline-flex items-center gap-1.5 rounded-none whitespace-nowrap transition"
                >
                    <span>← Retour aux Arènes</span>
                </a>
                @if($currentUser)
                    <a
                        href="/dashboard"
                        class="h-10 px-3.5 bg-[#121824] border border-[#E5C158]/40 hover:border-[#E5C158] text-[#E5C158] font-mono text-xs font-semibold uppercase tracking-wider inline-flex items-center gap-1.5 rounded-none whitespace-nowrap transition"
                    >
                        <span>Mon Cockpit ↗</span>
                    </a>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if($currentUser)
                    <div class="px-3 py-2 bg-[#0C1018] border border-white/10 font-mono text-xs tabular-nums">
                        <span class="text-[#E5C158] font-bold">{{ $currentUser->gamertag }}</span>
                        <span class="text-[#64748B] mx-1">·</span>
                        <span class="text-[#F4F7FB]">{{ $wallet ? \App\Models\Wallet::formatCents($wallet->balance_cents) : '0,00 €' }}</span>
                    </div>
                    <button
                        type="button"
                        data-logout-btn
                        data-logout-redirect="/"
                        class="h-10 px-3.5 bg-[#080B11] border border-[#EF4444]/40 text-[#EF4444] hover:bg-[#EF4444] hover:text-[#030407] font-mono text-xs font-semibold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                    >
                        Déconnexion
                    </button>
                @else
                    <a href="/login" class="h-10 px-3.5 bg-[#0C1018] border border-white/15 text-[#94A3B8] hover:text-[#F4F7FB] font-mono text-xs uppercase tracking-wider inline-flex items-center whitespace-nowrap">
                        Connexion
                    </a>
                    <a href="/register" class="h-10 px-4 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider inline-flex items-center whitespace-nowrap">
                        Ouvrir un Compte ↗
                    </a>
                @endif
            </div>
        </div>
    </header>

    <!-- =====================================================================
         MAIN ARENA DETAIL CONTENT
         ===================================================================== -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-10">

        <!-- =================================================================
             1. ARENA HERO HEADER & CONTEXTUAL BUY-IN REGISTRATION MODULE
             ================================================================= -->
        <section class="grid grid-cols-1 lg:grid-cols-12 border border-white/[0.08] bg-[#0C1018]">
            <!-- Left 7 Cols: Arena Metadata & Identity -->
            <div class="lg:col-span-7 p-6 sm:p-8 lg:p-10 border-b lg:border-b-0 lg:border-r border-white/[0.08] flex flex-col justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-4 font-mono text-xs">
                        <span class="px-2.5 py-1 bg-[#121824] border border-white/10 text-[#D8E5F8] font-semibold uppercase tracking-wider">
                            {{ $tournament->game }}
                        </span>
                        <span class="px-2.5 py-1 bg-[#080B11] border border-white/10 text-[#94A3B8] uppercase tracking-wider tabular-nums">
                            FORMAT {{ $tournament->format }}
                        </span>
                        @php
                            $statusBadgeClasses = match($tournament->status) {
                                'OPEN' => 'bg-[#10B981]/15 text-[#10B981] border-[#10B981]/40',
                                'IN_PROGRESS' => 'bg-[#F59E0B]/15 text-[#F59E0B] border-[#F59E0B]/40',
                                'SETTLED' => 'bg-[#E5C158]/15 text-[#E5C158] border-[#E5C158]/40',
                                'CANCELLED' => 'bg-[#EF4444]/15 text-[#EF4444] border-[#EF4444]/40',
                                default => 'bg-[#D8E5F8]/15 text-[#D8E5F8] border-[#D8E5F8]/40',
                            };
                        @endphp
                        <span id="tournament-status-badge" class="px-2.5 py-1 border font-bold uppercase tracking-widest {{ $statusBadgeClasses }}">
                            STATUT // {{ $tournament->status }}
                        </span>
                    </div>

                    <h1 class="font-display font-extrabold text-2xl sm:text-3xl lg:text-4xl text-[#F4F7FB] tracking-tight uppercase mb-3">
                        {{ $tournament->title }}
                    </h1>

                    <p class="font-mono text-xs text-[#94A3B8] leading-relaxed mb-6 tabular-nums">
                        ARÈNE ID <strong class="text-[#F4F7FB]">#{{ str_pad((string) $tournament->id, 3, '0', STR_PAD_LEFT) }}</strong>
                        · SLUG <code class="text-[#D8E5F8]">{{ $tournament->slug }}</code>
                        · ARBITRE OFFICIEL : <strong class="text-[#E5C158]">{{ $tournament->organizer?->gamertag ?? 'VKR_ARBITER' }}</strong>
                    </p>
                </div>

                <div class="pt-4 border-t border-white/[0.08] flex flex-wrap items-center justify-between gap-4 font-mono text-xs tabular-nums">
                    <div>
                        <span class="text-[#64748B]">CAPACITÉ DU ROSTER :</span>
                        <strong class="text-[#F4F7FB] ml-1">{{ $tournament->registered_count }} / {{ $tournament->max_participants }} OPÉRATEURS</strong>
                    </div>
                    <div>
                        <span class="text-[#64748B]">PROTOCOLE :</span>
                        <strong class="text-[#10B981] ml-1">SELECT ... FOR UPDATE // ACID</strong>
                    </div>
                </div>
            </div>

            <!-- Right 5 Cols: Contextual Escrow Registration / Status Action Panel -->
            <div class="lg:col-span-5 p-6 sm:p-8 lg:p-10 bg-[#080B11] flex flex-col justify-center">
                @if(!$currentUser)
                    <div class="space-y-4">
                        <div class="text-[10px] font-mono uppercase tracking-widest text-[#E5C158]">
                            AUTHENTIFICATION REQUISE POUR ENGAGER UN BUY-IN
                        </div>
                        <h2 class="font-display font-bold text-xl text-[#F4F7FB] uppercase">
                            REJOINDRE CETTE ARÈNE ({{ \App\Models\Wallet::formatCents($tournament->entry_fee_cents) }})
                        </h2>
                        <p class="text-xs text-[#94A3B8] leading-relaxed">
                            Connectez-vous à votre compte opérateur ou utilisez le simulateur 1-clic pour verrouiller votre Buy-in en séquestre atomique.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3 pt-2">
                            <a
                                href="/login"
                                class="h-12 flex-1 px-5 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider inline-flex items-center justify-center rounded-none whitespace-nowrap transition"
                            >
                                Se connecter pour rejoindre ↗
                            </a>
                            <a
                                href="/register"
                                class="h-12 px-5 bg-[#121824] border border-white/15 hover:border-[#E5C158] text-[#F4F7FB] font-mono text-xs font-semibold uppercase tracking-wider inline-flex items-center justify-center rounded-none whitespace-nowrap transition"
                            >
                                Créer un Compte ↗
                            </a>
                        </div>
                    </div>
                @elseif($myRegistration)
                    <div class="p-5 bg-[#10B981]/10 border border-[#10B981]/40 space-y-3 font-mono">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 bg-[#10B981] text-[#030407] text-[10px] font-bold uppercase tracking-widest">
                                ✓ DÉJÀ INSCRIT EN SÉQUESTRE
                            </span>
                            <span class="text-xs text-[#10B981] font-bold tabular-nums">
                                {{ $myRegistration->status }}
                            </span>
                        </div>
                        <div class="text-sm font-bold text-[#F4F7FB]">
                            OPÉRATEUR : {{ $currentUser->gamertag }} // CLASSE : {{ $myRegistration->loadout_class }}
                        </div>
                        <div class="text-xs text-[#94A3B8] tabular-nums">
                            Enjeu verrouillé : <strong class="text-[#E5C158]">{{ \App\Models\Wallet::formatCents($myRegistration->paid_entry_fee_cents) }}</strong>
                            · Clé : <code>{{ $myRegistration->idempotency_key }}</code>
                        </div>
                    </div>
                @elseif($tournament->status === 'OPEN')
                    <div>
                        <div class="text-[10px] font-mono uppercase tracking-widest text-[#10B981] mb-1">
                            SÉQUESTRE OUVERT // ENGAGEMENT IMMÉDIAT
                        </div>
                        <h2 class="font-display font-bold text-xl text-[#F4F7FB] uppercase mb-3">
                            VERROUILLER MON BUY-IN ({{ \App\Models\Wallet::formatCents($tournament->entry_fee_cents) }})
                        </h2>

                        <form
                            id="form-register-tournament"
                            data-tournament-id="{{ $tournament->id }}"
                            data-reload-on-success="true"
                            method="POST"
                            class="space-y-3.5"
                        >
                            @csrf
                            <div class="grid grid-cols-2 gap-2.5 font-mono text-xs tabular-nums bg-[#0C1018] p-3 border border-white/10">
                                <div>
                                    <span class="text-[10px] text-[#64748B] block">VOTRE SOLDE DISPONIBLE</span>
                                    <strong class="text-[#F4F7FB]">{{ $wallet ? \App\Models\Wallet::formatCents($wallet->balance_cents) : '0,00 €' }}</strong>
                                </div>
                                <div>
                                    <span class="text-[10px] text-[#64748B] block">APRÈS SÉQUESTRE</span>
                                    <strong class="text-[#E5C158]">{{ $wallet ? \App\Models\Wallet::formatCents($wallet->balance_cents - $tournament->entry_fee_cents) : '0,00 €' }}</strong>
                                </div>
                            </div>

                            <div>
                                <label for="register-loadout-select" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">
                                    CLASSE D'ARSENAL ENGAGÉE
                                </label>
                                <select
                                    id="register-loadout-select"
                                    name="loadout_class"
                                    class="w-full h-11 px-3 bg-[#0C1018] border border-white/15 font-mono text-xs text-[#F4F7FB] rounded-none"
                                >
                                    @foreach(['VALKYRIE', 'VANGUARD', 'SPECTRE', 'CHRONO'] as $clsOpt)
                                        <option value="{{ $clsOpt }}" {{ $currentUser->preferred_class === $clsOpt ? 'selected' : '' }}>
                                            {{ $clsOpt }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <input
                                type="hidden"
                                id="register-idempotency-key"
                                name="idempotency_key"
                                value="buyin:t{{ $tournament->id }}:{{ uniqid() }}"
                            />

                            <button
                                type="submit"
                                id="page-register-submit-btn"
                                class="w-full h-12 px-5 bg-[#10B981] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                            >
                                S'inscrire &amp; Verrouiller {{ \App\Models\Wallet::formatCents($tournament->entry_fee_cents) }} en Séquestre ↗
                            </button>
                        </form>
                    </div>
                @else
                    <div class="p-5 bg-[#0C1018] border border-white/10 font-mono text-xs space-y-2">
                        <div class="text-[#E5C158] font-bold uppercase">INSCRIPTIONS CLÔTURÉES (STATUT : {{ $tournament->status }})</div>
                        <p class="text-[#94A3B8]">
                            Cette arène n'accepte plus de nouveaux Buy-ins. Consultez ci-dessous l'arbre de tournoi et les écritures comptables du grand livre.
                        </p>
                    </div>
                @endif
            </div>
        </section>

        <!-- =================================================================
             2. 7-CARD MATHEMATICAL BREAKDOWN GRID (TABULAR-NUMS)
             ================================================================= -->
        <section aria-label="Décomposition Mathématique du Séquestre">
            <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-3">
                TÉLÉMÉTRIE FINANCIÈRE // COMMISSION 800 BPS (8,00 %) &amp; RÉPARTITION 60 / 30 / 10
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 font-mono tabular-nums">
                <div class="bg-[#0C1018] border border-white/[0.08] p-4">
                    <div class="text-[10px] text-[#64748B]">BUY-IN UNITAIRE</div>
                    <div class="text-sm font-bold text-[#F4F7FB] mt-1">
                        {{ \App\Models\Wallet::formatCents($financialBreakdown['entry_fee_cents']) }}
                    </div>
                </div>
                <div class="bg-[#0C1018] border border-[#E5C158]/35 p-4">
                    <div class="text-[10px] text-[#E5C158]">POOL SÉQUESTRÉ</div>
                    <div class="text-sm font-bold text-[#E5C158] mt-1">
                        {{ \App\Models\Wallet::formatCents($financialBreakdown['escrow_pool_cents']) }}
                    </div>
                </div>
                <div class="bg-[#0C1018] border border-white/[0.08] p-4">
                    <div class="text-[10px] text-[#F59E0B]">RAKE PLATEFORME (8%)</div>
                    <div class="text-sm font-bold text-[#F59E0B] mt-1">
                        {{ \App\Models\Wallet::formatCents($financialBreakdown['platform_rake_cents']) }}
                    </div>
                </div>
                <div class="bg-[#0C1018] border border-[#10B981]/35 p-4">
                    <div class="text-[10px] text-[#10B981]">POOL NET (92%)</div>
                    <div class="text-sm font-bold text-[#10B981] mt-1">
                        {{ \App\Models\Wallet::formatCents($financialBreakdown['net_prize_pool_cents']) }}
                    </div>
                </div>
                <div class="bg-[#080B11] border border-[#E5C158]/50 p-4">
                    <div class="text-[10px] text-[#E5C158]">1RE PLACE (60%)</div>
                    <div class="text-sm font-bold text-[#E5C158] mt-1">
                        {{ \App\Models\Wallet::formatCents($financialBreakdown['first_prize_cents']) }}
                    </div>
                </div>
                <div class="bg-[#080B11] border border-white/15 p-4">
                    <div class="text-[10px] text-[#D8E5F8]">2E PLACE (30%)</div>
                    <div class="text-sm font-bold text-[#D8E5F8] mt-1">
                        {{ \App\Models\Wallet::formatCents($financialBreakdown['second_prize_cents']) }}
                    </div>
                </div>
                <div class="bg-[#080B11] border border-white/15 p-4 col-span-2 sm:col-span-1">
                    <div class="text-[10px] text-[#10B981]">3E PLACE (10%)</div>
                    <div class="text-sm font-bold text-[#10B981] mt-1">
                        {{ \App\Models\Wallet::formatCents($financialBreakdown['third_prize_cents']) }}
                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             3. INTERACTIVE 3-COLUMN TOURNAMENT BRACKET ($bracket)
             ================================================================= -->
        <section aria-label="Arbre Compétitif du Tournoi" class="bg-[#0C1018] border border-white/[0.08] p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-white/[0.08] pb-5 mb-6">
                <div>
                    <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-1">
                        PROGRESSION DE L'ARÈNE // 128-TICK VERIFIED
                    </div>
                    <h2 class="font-display font-extrabold text-xl sm:text-2xl text-[#F4F7FB] uppercase">
                        ARBRE DE TOURNOI (DEMIES · PETITE FINALE 10% · GRANDE FINALE 60%/30%)
                    </h2>
                </div>
                <span class="px-3 py-1 bg-[#080B11] border border-white/10 font-mono text-xs text-[#D8E5F8] tabular-nums">
                    STATUT BRACKET : {{ $bracket['grand_final']['status'] }}
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 font-mono">
                <!-- Column 1: Demi-Finales -->
                <div class="space-y-4">
                    <div class="text-xs uppercase tracking-widest text-[#94A3B8] border-b border-white/[0.08] pb-2">
                        PHASE 01 // DEMI-FINALES
                    </div>

                    @foreach([$bracket['semifinal_1'], $bracket['semifinal_2']] as $sf)
                        <div class="bg-[#080B11] border border-white/[0.08] p-4">
                            <div class="flex items-center justify-between text-[10px] text-[#64748B] mb-2 tabular-nums">
                                <span>MATCH #{{ $sf['code'] }} // {{ $sf['label'] }}</span>
                                <span class="text-[#D8E5F8]">[{{ $sf['status'] }}]</span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 border-b border-white/[0.05] text-xs">
                                <span class="font-bold text-[#F4F7FB]">{{ is_array($sf['player_a']) ? $sf['player_a']['gamertag'] : $sf['player_a'] }}</span>
                                <span class="tabular-nums text-[#10B981] font-bold">{{ $sf['score_a'] }}</span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 text-xs">
                                <span class="text-[#94A3B8]">{{ is_array($sf['player_b']) ? $sf['player_b']['gamertag'] : $sf['player_b'] }}</span>
                                <span class="tabular-nums text-[#64748B]">{{ $sf['score_b'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Column 2: Petite Finale (3e Place — 10%) -->
                <div class="flex flex-col justify-between space-y-4">
                    <div class="text-xs uppercase tracking-widest text-[#94A3B8] border-b border-white/[0.08] pb-2">
                        PHASE 02 // PETITE FINALE (3E PLACE · 10%)
                    </div>

                    <div class="bg-[#080B11] border border-white/[0.08] p-4 my-auto">
                        <div class="flex items-center justify-between text-[10px] text-[#64748B] mb-2 tabular-nums">
                            <span>MATCH #{{ $bracket['third_place_match']['code'] }}</span>
                            <span class="text-[#10B981] font-bold">ENJEU : {{ \App\Models\Wallet::formatCents($bracket['third_place_match']['prize_cents']) }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-white/[0.05] text-xs">
                            <span class="font-bold text-[#F4F7FB]">3RD // {{ is_array($bracket['third_place_match']['player_a']) ? $bracket['third_place_match']['player_a']['gamertag'] : $bracket['third_place_match']['player_a'] }}</span>
                            <span class="tabular-nums text-[#10B981] font-bold">{{ $bracket['third_place_match']['score_a'] }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 text-xs">
                            <span class="text-[#94A3B8]">4TH // {{ is_array($bracket['third_place_match']['player_b']) ? $bracket['third_place_match']['player_b']['gamertag'] : $bracket['third_place_match']['player_b'] }}</span>
                            <span class="tabular-nums text-[#64748B]">{{ $bracket['third_place_match']['score_b'] }}</span>
                        </div>
                    </div>
                </div>

                <!-- Column 3: Grande Finale (60% / 30%) -->
                <div class="flex flex-col justify-between space-y-4">
                    <div class="text-xs uppercase tracking-widest text-[#E5C158] border-b border-[#E5C158]/30 pb-2">
                        PHASE 03 // GRANDE FINALE SOUVERAINE
                    </div>

                    <div class="bg-[#080B11] border border-[#E5C158]/45 p-5 my-auto">
                        <div class="flex items-center justify-between text-[10px] text-[#E5C158] mb-3 tabular-nums">
                            <span>MATCH #{{ $bracket['grand_final']['code'] }} // {{ $bracket['grand_final']['label'] }}</span>
                            <span>[{{ $bracket['grand_final']['status'] }}]</span>
                        </div>
                        <div class="flex items-center justify-between py-2.5 px-3 bg-[#121824] border border-[#E5C158]/40 mb-2 text-xs">
                            <div>
                                <span class="text-[10px] text-[#E5C158] block">1RE PLACE // 60% NET POOL</span>
                                <span class="font-bold text-[#F4F7FB]">{{ is_array($bracket['grand_final']['player_a']) ? $bracket['grand_final']['player_a']['gamertag'] : $bracket['grand_final']['player_a'] }}</span>
                            </div>
                            <span class="tabular-nums text-sm font-bold text-[#E5C158]">
                                {{ \App\Models\Wallet::formatCents($bracket['grand_final']['first_prize_cents']) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-2.5 px-3 bg-[#0C1018] border border-white/10 text-xs">
                            <div>
                                <span class="text-[10px] text-[#D8E5F8] block">2E PLACE // 30% NET POOL</span>
                                <span class="font-bold text-[#F4F7FB]">{{ is_array($bracket['grand_final']['player_b']) ? $bracket['grand_final']['player_b']['gamertag'] : $bracket['grand_final']['player_b'] }}</span>
                            </div>
                            <span class="tabular-nums text-sm font-bold text-[#D8E5F8]">
                                {{ \App\Models\Wallet::formatCents($bracket['grand_final']['second_prize_cents']) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- =================================================================
             4. ROSTER DES OPÉRATEURS INSCRITS & CONSOLE D'ARBITRAGE OFFICIEL
             ================================================================= -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left 6 Cols: Registered Operators Roster -->
            <div class="lg:col-span-6 bg-[#0C1018] border border-white/[0.08] p-6 sm:p-8">
                <div class="flex items-center justify-between gap-2 border-b border-white/[0.08] pb-4 mb-5">
                    <div>
                        <div class="text-[10px] font-mono uppercase tracking-widest text-[#D8E5F8]">PARTICIPANTS VERROUILLÉS</div>
                        <h2 class="font-display font-bold text-xl text-[#F4F7FB] uppercase">
                            ROSTER DES OPÉRATEURS ({{ $tournament->registrations->count() }})
                        </h2>
                    </div>
                    <span class="font-mono text-xs text-[#E5C158] tabular-nums">
                        {{ $tournament->registered_count }} / {{ $tournament->max_participants }} SLOTS
                    </span>
                </div>

                <div class="space-y-2.5">
                    @forelse($tournament->registrations as $reg)
                        <div class="p-3.5 bg-[#080B11] border border-white/[0.08] flex items-center justify-between gap-3 font-mono text-xs">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-[#F4F7FB]">{{ $reg->user?->gamertag ?? 'OP #' . $reg->user_id }}</span>
                                    <span class="px-2 py-0.5 text-[10px] bg-[#121824] border border-white/10 text-[#D8E5F8]">{{ $reg->loadout_class }}</span>
                                </div>
                                <span class="text-[10px] text-[#64748B] tabular-nums block mt-0.5">
                                    ELO: {{ $reg->user?->elo_rating ?? 1800 }} MMR · ID #{{ $reg->user_id }}
                                </span>
                            </div>
                            <div class="text-right tabular-nums">
                                <div class="font-bold text-[#E5C158]">{{ \App\Models\Wallet::formatCents($reg->paid_entry_fee_cents) }}</div>
                                <span class="text-[10px] text-[#10B981]">{{ $reg->status }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 bg-[#080B11] border border-white/[0.06] text-center font-mono text-xs text-[#64748B]">
                            Aucun opérateur inscrit dans cette arène pour le moment.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Right 6 Cols: Official Arbitrage Panel ($canManage) -->
            <div class="lg:col-span-6 bg-[#0C1018] border border-white/[0.08] p-6 sm:p-8">
                <div class="flex items-center justify-between gap-2 border-b border-white/[0.08] pb-4 mb-5">
                    <div>
                        <div class="text-[10px] font-mono uppercase tracking-widest text-[#E5C158]">TOURNAMENT POLICY // ANTI-IDOR</div>
                        <h2 class="font-display font-bold text-xl text-[#F4F7FB] uppercase">
                            CONSOLE D'ARBITRAGE &amp; RÈGLEMENT
                        </h2>
                    </div>
                    @if($canManage)
                        <span class="px-2.5 py-1 bg-[#10B981]/15 border border-[#10B981]/40 text-[#10B981] font-mono text-[10px] font-bold">
                            ARBITRE AUTORISÉ ✓
                        </span>
                    @else
                        <span class="px-2.5 py-1 bg-white/5 border border-white/10 text-[#94A3B8] font-mono text-[10px]">
                            LECTURE SEULE
                        </span>
                    @endif
                </div>

                @if($tournament->status === 'SETTLED')
                    <div class="p-5 bg-[#080B11] border border-[#E5C158]/40 space-y-4 font-mono">
                        <div class="text-xs font-bold text-[#E5C158] uppercase">
                            ✓ TOURNOI CLÔTURÉ &amp; CASHPRIZE DISTRIBUÉ SUR GRAND LIVRE
                        </div>
                        <div class="space-y-2 text-xs tabular-nums">
                            <div class="flex justify-between p-2.5 bg-[#0C1018] border border-[#E5C158]/30">
                                <span>🥇 1RE PLACE (60%) : <strong>{{ $tournament->firstPlace?->gamertag ?? 'N/A' }}</strong></span>
                                <strong class="text-[#E5C158]">{{ \App\Models\Wallet::formatCents($financialBreakdown['first_prize_cents']) }}</strong>
                            </div>
                            <div class="flex justify-between p-2.5 bg-[#0C1018] border border-white/10">
                                <span>🥈 2E PLACE (30%) : <strong>{{ $tournament->secondPlace?->gamertag ?? 'N/A' }}</strong></span>
                                <strong class="text-[#D8E5F8]">{{ \App\Models\Wallet::formatCents($financialBreakdown['second_prize_cents']) }}</strong>
                            </div>
                            @if($tournament->thirdPlace)
                                <div class="flex justify-between p-2.5 bg-[#0C1018] border border-white/10">
                                    <span>🥉 3E PLACE (10%) : <strong>{{ $tournament->thirdPlace->gamertag }}</strong></span>
                                    <strong class="text-[#10B981]">{{ \App\Models\Wallet::formatCents($financialBreakdown['third_prize_cents']) }}</strong>
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif($tournament->status === 'CANCELLED')
                    <div class="p-5 bg-[#EF4444]/10 border border-[#EF4444]/40 font-mono text-xs text-[#EF4444]">
                        Cette arène a été annulée et 100 % des fonds séquestrés ont été remboursés aux joueurs inscrits.
                    </div>
                @elseif($canManage)
                    <div class="space-y-6">
                        <!-- Form 1: Official Settlement 60/30/10 -->
                        <form
                            id="form-settle-tournament"
                            data-tournament-id="{{ $tournament->id }}"
                            data-reload-on-success="true"
                            method="POST"
                            class="bg-[#080B11] border border-white/10 p-5 space-y-4"
                        >
                            @csrf
                            <div class="text-xs font-mono font-bold text-[#E5C158] uppercase">
                                1. HOMOLOGUER LE PODIUM &amp; DISTRIBUER LE SÉQUESTRE (60 / 30 / 10)
                            </div>

                            @php
                                $regs = $tournament->registrations;
                            @endphp
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label for="settle-first-place" class="block text-[10px] font-mono text-[#94A3B8] mb-1">1RE PLACE (60%)</label>
                                    <select id="settle-first-place" name="first_place_user_id" class="w-full h-10 px-2 bg-[#0C1018] border border-white/15 font-mono text-xs text-[#F4F7FB]">
                                        @foreach($regs as $idx => $r)
                                            <option value="{{ $r->user_id }}" {{ $idx === 0 ? 'selected' : '' }}>
                                                {{ $r->user?->gamertag ?? 'OP #' . $r->user_id }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="settle-second-place" class="block text-[10px] font-mono text-[#94A3B8] mb-1">2E PLACE (30%)</label>
                                    <select id="settle-second-place" name="second_place_user_id" class="w-full h-10 px-2 bg-[#0C1018] border border-white/15 font-mono text-xs text-[#F4F7FB]">
                                        @foreach($regs as $idx => $r)
                                            <option value="{{ $r->user_id }}" {{ $idx === 1 ? 'selected' : '' }}>
                                                {{ $r->user?->gamertag ?? 'OP #' . $r->user_id }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="settle-third-place" class="block text-[10px] font-mono text-[#94A3B8] mb-1">3E PLACE (10%)</label>
                                    <select id="settle-third-place" name="third_place_user_id" class="w-full h-10 px-2 bg-[#0C1018] border border-white/15 font-mono text-xs text-[#F4F7FB]">
                                        <option value="">-- Aucun 3e (70/30) --</option>
                                        @foreach($regs as $idx => $r)
                                            <option value="{{ $r->user_id }}" {{ $idx === 2 ? 'selected' : '' }}>
                                                {{ $r->user?->gamertag ?? 'OP #' . $r->user_id }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <input type="hidden" id="settle-idempotency-key" name="idempotency_key" value="settle:t{{ $tournament->id }}:{{ uniqid() }}" />

                            <button type="submit" class="w-full h-11 bg-[#E5C158] text-[#030407] hover:bg-[#D8E5F8] font-mono text-xs font-bold uppercase tracking-wider cursor-pointer">
                                Homologuer le Podium &amp; Distribuer le Séquestre ↗
                            </button>
                        </form>

                        <!-- Form 2: Cancel & 100% Refund -->
                        <form
                            id="form-cancel-tournament"
                            data-tournament-id="{{ $tournament->id }}"
                            data-reload-on-success="true"
                            method="POST"
                            class="bg-[#080B11] border border-[#EF4444]/30 p-5 space-y-3"
                        >
                            @csrf
                            <div class="text-xs font-mono font-bold text-[#EF4444] uppercase">
                                2. ANNULATION D'URGENCE &amp; REMBOURSEMENT INTÉGRAL (100% ESCROW)
                            </div>
                            <input type="hidden" id="cancel-idempotency-key" name="idempotency_key" value="cancel:t{{ $tournament->id }}:{{ uniqid() }}" />
                            <input type="text" name="reason" value="Annulation officielle par Arbitre — remboursement intégral 100%" class="w-full h-10 px-3 bg-[#0C1018] border border-white/10 font-mono text-xs text-[#94A3B8]" />
                            <button type="submit" class="w-full h-11 bg-[#EF4444]/20 border border-[#EF4444]/60 text-[#EF4444] hover:bg-[#EF4444] hover:text-[#030407] font-mono text-xs font-bold uppercase tracking-wider transition cursor-pointer">
                                Annuler le Tournoi &amp; Rembourser 100% aux Joueurs ↺
                            </button>
                        </form>
                    </div>
                @else
                    <div class="p-5 bg-[#080B11] border border-white/10 space-y-4 font-mono text-xs">
                        <div class="text-[#D8E5F8] font-bold uppercase">
                            PROTECTION ANTI-IDOR &amp; TOURNAMENT POLICY ACTIVE
                        </div>
                        <p class="text-[#94A3B8] leading-relaxed">
                            Seul l'organisateur propriétaire (<strong class="text-[#E5C158]">{{ $tournament->organizer?->gamertag ?? 'VKR_ARBITER' }}</strong>) ou le Commandement (<strong class="text-[#E5C158]">ADMIN</strong>) est habilité à clôturer ou annuler ce séquestre.
                        </p>
                        <div class="pt-2">
                            <a href="/dashboard?tab=organizer" class="h-10 px-4 bg-[#121824] border border-[#E5C158]/40 text-[#E5C158] hover:bg-[#E5C158] hover:text-[#030407] font-bold uppercase inline-flex items-center transition">
                                Ouvrir la Console Organisateur (/dashboard) ↗
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <!-- =================================================================
             5. ARENA ESCROW AUDIT LEDGER TABLE ($tournament->ledgerEntries)
             ================================================================= -->
        <section aria-label="Grand Livre du Tournoi" class="bg-[#0C1018] border border-white/[0.08] p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
                <div>
                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#10B981]">PISTE D'AUDIT FINANCIÈRE // APPEND-ONLY</div>
                    <h2 class="font-display font-bold text-xl text-[#F4F7FB] uppercase mt-0.5">
                        ÉCRITURES COMPTABLES LIÉES À CETTE ARÈNE ({{ $tournament->ledgerEntries->count() }})
                    </h2>
                </div>
                <span class="px-3 py-1 bg-[#080B11] border border-white/10 font-mono text-[11px] text-[#94A3B8] tabular-nums">
                    SHA-256 // DOUBLE-ENTRY LEDGER
                </span>
            </div>

            <div class="w-full overflow-x-auto border border-white/[0.08] bg-[#080B11]">
                <table class="w-full min-w-[820px] text-left border-collapse font-mono text-xs tabular-nums">
                    <thead>
                        <tr class="border-b border-white/10 bg-[#0C1018] text-[10px] uppercase tracking-wider text-[#64748B] whitespace-nowrap tabular-nums">
                            <th class="px-4 py-3 whitespace-nowrap tabular-nums">ID</th>
                            <th class="px-4 py-3 whitespace-nowrap tabular-nums">TYPE OPÉRATION</th>
                            <th class="px-4 py-3 min-w-[220px] max-w-[300px] whitespace-nowrap tabular-nums">DESCRIPTION</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap tabular-nums">DÉBIT</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap tabular-nums">CRÉDIT</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap tabular-nums">SOLDE APRÈS</th>
                            <th class="pl-4 pr-6 py-3 whitespace-nowrap tabular-nums">CLÉ D'IDEMPOTENCE</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/[0.05] whitespace-nowrap tabular-nums">
                        @forelse($tournament->ledgerEntries as $entry)
                            <tr class="hover:bg-[#121824]/60 transition whitespace-nowrap tabular-nums">
                                <td class="px-4 py-3 text-[#94A3B8] whitespace-nowrap tabular-nums">#{{ str_pad((string) $entry->id, 4, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap tabular-nums">
                                    <span class="px-2 py-0.5 text-[10px] font-bold border whitespace-nowrap tabular-nums {{ $entry->credit_cents > 0 ? 'bg-[#10B981]/15 text-[#10B981] border-[#10B981]/30' : 'bg-[#F59E0B]/15 text-[#F59E0B] border-[#F59E0B]/30' }}">
                                        {{ $entry->entry_type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-[#F4F7FB] min-w-[220px] max-w-[300px] truncate whitespace-nowrap tabular-nums" title="{{ $entry->description }}">{{ $entry->description }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                    @if($entry->debit_cents > 0)
                                        <span class="text-[#EF4444] whitespace-nowrap tabular-nums">-{{ \App\Models\Wallet::formatCents($entry->debit_cents) }}</span>
                                    @else
                                        <span class="text-[#64748B] whitespace-nowrap tabular-nums">0,00&nbsp;€</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                    @if($entry->credit_cents > 0)
                                        <span class="text-[#10B981] whitespace-nowrap tabular-nums">+{{ \App\Models\Wallet::formatCents($entry->credit_cents) }}</span>
                                    @else
                                        <span class="text-[#64748B] whitespace-nowrap tabular-nums">0,00&nbsp;€</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-[#D8E5F8] font-bold whitespace-nowrap tabular-nums">
                                    <span class="whitespace-nowrap tabular-nums">{{ \App\Models\Wallet::formatCents($entry->balance_after_cents) }}</span>
                                </td>
                                <td class="pl-4 pr-6 py-3 text-[11px] text-[#64748B] whitespace-nowrap tabular-nums" title="{{ $entry->idempotency_key }}">
                                    <code class="px-1.5 py-0.5 bg-[#0C1018] border border-white/[0.06] text-[#94A3B8] whitespace-nowrap tabular-nums">{{ $entry->idempotency_key }}</code>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-[#64748B] whitespace-nowrap tabular-nums">
                                    Aucune écriture comptable enregistrée pour cette arène.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-[#030407] py-8 border-t border-white/[0.08] font-mono text-xs text-[#64748B]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 tabular-nums">
            <span>✦ VALKYR // ARENA — SALLE D'ARÈNE #{{ $tournament->id }}</span>
            <span>SÉQUESTRE ATOMIQUE // GRAND LIVRE IMMUABLE</span>
        </div>
    </footer>

</body>
</html>
