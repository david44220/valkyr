<!DOCTYPE html>
<html lang="fr" class="dark" style="color-scheme: dark;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Cockpit Opérateur ({{ $currentUser->gamertag }}) — VALKYR // ARENA</title>
    <meta name="description" content="Cockpit financier, portefeuille séquestre SEPA, grand livre immuable en partie double et console organisateur VALKYR // ARENA.">

    <!-- Bespoke Sovereign Inline SVG Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' fill='%23030407'/%3E%3Crect x='2' y='2' width='60' height='60' fill='none' stroke='%23E5C158' stroke-width='2'/%3E%3Cpath d='M32 10 L38 26 L54 32 L38 38 L32 54 L26 38 L10 32 L26 26 Z' fill='%23E5C158'/%3E%3C/svg%3E">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">

    @php
        $activeTabKey = in_array($activeTab ?? 'overview', ['overview', 'arenas', 'ledger', 'organizer'], true)
            ? $activeTab
            : 'overview';

        $hydratedState = [
            'page' => 'dashboard',
            'activeCockpitTab' => $activeTabKey,
            'tournaments' => $tournaments,
            'currentUser' => $currentUser,
            'wallet' => $wallet,
            'ledgerEntries' => $ledgerEntries,
            'myRegistrations' => $myRegistrations,
            'leaderboard' => $leaderboard,
            'platformStats' => $platformStats,
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
         TOPBAR COCKPIT MEMBRE (IDENTITÉ, SOLDE LIVE, BASCULE DÉMO & DÉCONNEXION)
         ===================================================================== -->
    <header class="sticky top-0 z-50 bg-[#080B11]/95 backdrop-blur-md border-b border-white/[0.08]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-wrap items-center justify-between gap-4">
            <!-- Left: Brand + Public Arena Return Link -->
            <div class="flex flex-wrap items-center gap-4">
                <a href="/dashboard" class="font-display font-extrabold text-lg tracking-tight text-[#F4F7FB] flex items-center gap-2 whitespace-nowrap">
                    <span class="text-[#E5C158]" aria-hidden="true">✦</span>
                    <span>VALKYR // COCKPIT</span>
                </a>
                <a
                    href="/"
                    class="h-10 px-3.5 bg-[#0C1018] border border-white/15 hover:border-[#D8E5F8] text-[#94A3B8] hover:text-[#F4F7FB] font-mono text-xs font-semibold uppercase tracking-wider inline-flex items-center gap-1.5 rounded-none whitespace-nowrap transition"
                >
                    <span>← Voir l'Arène Publique</span>
                </a>
            </div>

            <!-- Right: Live Telemetry Balances + 1-Click Demo Switcher + Explicit Logout -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Live Wallet Pill -->
                <div class="hidden md:flex items-center gap-4 px-3.5 py-2 bg-[#0C1018] border border-white/10 font-mono text-xs tabular-nums">
                    <div>
                        <span class="text-[#64748B] mr-1">DISPONIBLE:</span>
                        <strong id="topbar-balance-cents" class="text-[#F4F7FB]">{{ $wallet ? \App\Models\Wallet::formatCents($wallet->balance_cents) : '0,00 €' }}</strong>
                    </div>
                    <span class="text-white/15">|</span>
                    <div>
                        <span class="text-[#64748B] mr-1">SÉQUESTRE:</span>
                        <strong id="topbar-escrow-cents" class="text-[#E5C158]">{{ $wallet ? \App\Models\Wallet::formatCents($wallet->locked_escrow_cents) : '0,00 €' }}</strong>
                    </div>
                </div>

                <!-- 1-Click Demo Operator Switcher Select -->
                <div class="flex items-center gap-2">
                    <label for="dashboard-demo-switcher" class="sr-only">Changer d'opérateur Démo</label>
                    <select
                        id="dashboard-demo-switcher"
                        aria-label="Changer d'opérateur Démo"
                        class="h-10 px-3 bg-[#121824] border border-[#E5C158]/40 text-xs font-mono text-[#F4F7FB] focus:border-[#E5C158] focus:outline-none rounded-none tabular-nums"
                    >
                        @foreach($demoUsers as $dUser)
                            <option
                                value="{{ $dUser->email }}"
                                {{ $currentUser->id === $dUser->id ? 'selected' : '' }}
                            >
                                {{ $dUser->gamertag }} [{{ $dUser->role }}] — {{ $dUser->wallet ? \App\Models\Wallet::formatCents($dUser->wallet->balance_cents) : '0,00 €' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Explicit Logout Button -->
                <button
                    type="button"
                    id="dashboard-logout-btn"
                    data-logout-btn
                    data-logout-redirect="/"
                    class="h-10 px-4 bg-[#EF4444]/15 border border-[#EF4444]/50 text-[#EF4444] hover:bg-[#EF4444] hover:text-[#030407] font-mono text-xs font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                >
                    Déconnexion ✕
                </button>
            </div>
        </div>
    </header>

    <!-- =====================================================================
         MAIN COCKPIT SHELL: SIDEBAR (4 REAL TABS) + MAIN WORKSPACE PANELS
         ===================================================================== -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div id="operator-cockpit" class="grid grid-cols-1 lg:grid-cols-12 border border-white/[0.08] bg-[#0C1018] min-h-[720px]">

            <!-- =============================================================
                 SIDEBAR NAVIGATION (3 COLS) — 2PX VERTICAL ACTIVE INDICATOR
                 ============================================================= -->
            <aside class="lg:col-span-3 bg-[#080B11] border-b lg:border-b-0 lg:border-r border-white/[0.08] p-6 flex flex-col justify-between">
                <div>
                    <!-- Active Operator Identity Card -->
                    <div class="mb-6 pb-5 border-b border-white/[0.08]">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="text-[10px] font-mono uppercase tracking-widest text-[#64748B]">OPÉRATEUR AUTHENTIFIÉ</span>
                            <span id="sidebar-role-badge" class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase border {{ $currentUser->role === 'ADMIN' ? 'bg-[#E5C158]/15 text-[#E5C158] border-[#E5C158]/40' : ($currentUser->role === 'ORGANIZER' ? 'bg-[#D8E5F8]/15 text-[#D8E5F8] border-[#D8E5F8]/40' : 'bg-[#10B981]/15 text-[#10B981] border-[#10B981]/40') }}">
                                {{ $currentUser->role }}
                            </span>
                        </div>
                        <div id="cockpit-operator-title" class="font-display font-extrabold text-xl text-[#F4F7FB] tracking-tight truncate">
                            {{ $currentUser->gamertag }}
                        </div>
                        <div id="cockpit-operator-email" class="font-mono text-xs text-[#94A3B8] truncate mt-0.5">
                            {{ $currentUser->email }}
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2 font-mono text-[10px] tabular-nums">
                            <span id="kpi-preferred-class" class="px-2 py-0.5 bg-[#121824] border border-white/10 text-[#D8E5F8]">
                                LOADOUT // {{ $currentUser->preferred_class }}
                            </span>
                            <span class="px-2 py-0.5 bg-[#0C1018] border border-white/10 text-[#E5C158]">
                                {{ $currentUser->elo_rating }} MMR
                            </span>
                        </div>
                    </div>

                    <!-- 4 True Panel Switcher Tabs (ZERO scrollIntoView!) -->
                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#64748B] mb-2">
                        MODULES DU COCKPIT
                    </div>
                    <nav aria-label="Onglets du Cockpit" role="tablist" class="space-y-1.5 font-mono text-xs">
                        <button
                            type="button"
                            role="tab"
                            data-cockpit-tab="overview"
                            aria-selected="{{ $activeTabKey === 'overview' ? 'true' : 'false' }}"
                            class="w-full text-left px-4 py-3.5 border-l-2 {{ $activeTabKey === 'overview' ? 'border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-semibold' : 'border-transparent text-[#94A3B8] hover:text-[#F4F7FB] hover:bg-[#121824]/50' }} transition cursor-pointer flex items-center justify-between"
                        >
                            <span>01. Vue d'Ensemble &amp; SEPA</span>
                            <span class="text-[#E5C158]">→</span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            data-cockpit-tab="arenas"
                            aria-selected="{{ $activeTabKey === 'arenas' ? 'true' : 'false' }}"
                            class="w-full text-left px-4 py-3.5 border-l-2 {{ $activeTabKey === 'arenas' ? 'border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-semibold' : 'border-transparent text-[#94A3B8] hover:text-[#F4F7FB] hover:bg-[#121824]/50' }} transition cursor-pointer flex items-center justify-between"
                        >
                            <span>02. Mes Arènes &amp; Séquestres</span>
                            <span id="sidebar-arenas-count" class="px-1.5 py-0.5 text-[10px] bg-[#080B11] border border-white/10 text-[#D8E5F8] tabular-nums">{{ $myRegistrations->count() }}</span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            data-cockpit-tab="ledger"
                            aria-selected="{{ $activeTabKey === 'ledger' ? 'true' : 'false' }}"
                            class="w-full text-left px-4 py-3.5 border-l-2 {{ $activeTabKey === 'ledger' ? 'border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-semibold' : 'border-transparent text-[#94A3B8] hover:text-[#F4F7FB] hover:bg-[#121824]/50' }} transition cursor-pointer flex items-center justify-between"
                        >
                            <span>03. Grand Livre Immuable</span>
                            <span id="sidebar-ledger-count" class="px-1.5 py-0.5 text-[10px] bg-[#080B11] border border-white/10 text-[#10B981] tabular-nums">{{ $ledgerEntries->count() }}</span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            data-cockpit-tab="organizer"
                            aria-selected="{{ $activeTabKey === 'organizer' ? 'true' : 'false' }}"
                            class="w-full text-left px-4 py-3.5 border-l-2 {{ $activeTabKey === 'organizer' ? 'border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-semibold' : 'border-transparent text-[#94A3B8] hover:text-[#F4F7FB] hover:bg-[#121824]/50' }} transition cursor-pointer flex items-center justify-between"
                        >
                            <span>04. Console Organisateur</span>
                            <span class="text-[#D8E5F8]">⚙</span>
                        </button>
                    </nav>
                </div>

                <!-- Sidebar Quick Role Simulator Buttons -->
                <div class="mt-8 pt-6 border-t border-white/[0.08]">
                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#E5C158] mb-2.5">
                        BASCULE RAPIDE DE PROFIL
                    </div>
                    <div class="grid grid-cols-2 gap-2 font-mono text-[10px]">
                        @foreach($demoUsers->take(6) as $dUser)
                            <button
                                type="button"
                                data-demo-email="{{ $dUser->email }}"
                                data-demo-redirect="/dashboard"
                                class="h-9 px-2 bg-[#0C1018] border {{ $currentUser->id === $dUser->id ? 'border-[#E5C158] text-[#E5C158]' : 'border-white/10 text-[#94A3B8] hover:text-[#F4F7FB]' }} truncate text-left cursor-pointer transition"
                                title="{{ $dUser->gamertag }} ({{ $dUser->role }})"
                            >
                                {{ $dUser->gamertag }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </aside>

            <!-- =============================================================
                 MAIN WORKSPACE (9 COLS) — 4 DISTINCT PANELS (ONLY 1 VISIBLE)
                 ============================================================= -->
            <div class="lg:col-span-9 min-w-0 p-6 sm:p-8">

                <!-- =========================================================
                     PANEL 1: VUE D'ENSEMBLE, DÉPÔT & RETRAIT SEPA (#cockpit-panel-overview)
                     ========================================================= -->
                <section
                    id="cockpit-panel-overview"
                    data-cockpit-panel="overview"
                    class="{{ $activeTabKey === 'overview' ? '' : 'hidden' }} space-y-8 min-w-0"
                >
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-white/[0.08] pb-5">
                        <div>
                            <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-1">
                                MODULE COCKPIT 01 // TRÉSORERIE &amp; TÉLÉMÉTRIE OPÉRATEUR
                            </div>
                            <h1 class="font-display font-extrabold text-2xl sm:text-3xl text-[#F4F7FB] uppercase tracking-tight">
                                VUE D'ENSEMBLE &amp; PORTEFEUILLE SÉQUESTRE
                            </h1>
                        </div>
                        <span class="px-3 py-1 bg-[#080B11] border border-[#10B981]/40 text-[#10B981] font-mono text-xs tabular-nums">
                            PROTECTION ACID // BIGINT CENTS
                        </span>
                    </div>

                    <!-- 4 Financial & ELO KPI Cards (Tabular-Nums) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                        <div class="bg-[#080B11] border border-white/[0.08] p-5">
                            <div class="text-[10px] font-mono uppercase tracking-widest text-[#64748B]">SOLDE DISPONIBLE</div>
                            <div id="kpi-balance-cents" class="text-2xl font-mono font-bold text-[#F4F7FB] tabular-nums mt-2">
                                {{ $wallet ? \App\Models\Wallet::formatCents($wallet->balance_cents) : '0,00 €' }}
                            </div>
                            <div class="text-[11px] font-mono text-[#10B981] mt-1">PRÊT POUR BUY-IN &amp; RETRAIT</div>
                        </div>

                        <div class="bg-[#080B11] border border-[#E5C158]/35 p-5">
                            <div class="text-[10px] font-mono uppercase tracking-widest text-[#E5C158]">SÉQUESTRE VERROUILLÉ</div>
                            <div id="kpi-locked-escrow-cents" class="text-2xl font-mono font-bold text-[#E5C158] tabular-nums mt-2">
                                {{ $wallet ? \App\Models\Wallet::formatCents($wallet->locked_escrow_cents) : '0,00 €' }}
                            </div>
                            <div class="text-[11px] font-mono text-[#94A3B8] mt-1">ENGAGÉ DANS LES ARÈNES</div>
                        </div>

                        <div class="bg-[#080B11] border border-white/[0.08] p-5">
                            <div class="text-[10px] font-mono uppercase tracking-widest text-[#64748B]">GAINS CUMULÉS (NET 92%)</div>
                            <div id="kpi-lifetime-won-cents" class="text-2xl font-mono font-bold text-[#10B981] tabular-nums mt-2">
                                {{ $wallet ? \App\Models\Wallet::formatCents($wallet->lifetime_won_cents) : '0,00 €' }}
                            </div>
                            <div class="text-[11px] font-mono text-[#94A3B8] mt-1">SPLIT PODIUM 60 / 30 / 10</div>
                        </div>

                        <div class="bg-[#080B11] border border-white/[0.08] p-5">
                            <div class="text-[10px] font-mono uppercase tracking-widest text-[#64748B]">COTE COMPÉTITIVE ELO</div>
                            <div id="kpi-elo-rating" class="text-2xl font-mono font-bold text-[#D8E5F8] tabular-nums mt-2">
                                {{ $currentUser->elo_rating }} MMR
                            </div>
                            <div class="text-[11px] font-mono text-[#D8E5F8] mt-1">DIVISION SOUVERAINE I</div>
                        </div>
                    </div>

                    <!-- Dual Financial Operations: Deposit (Credit) & SEPA Withdrawal (Debit) -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Card A: Instant SEPA Deposit (POST /api/wallets/deposit) -->
                        <div class="bg-[#080B11] border border-white/[0.08] p-6 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-4 pb-3 border-b border-white/[0.08]">
                                    <div>
                                        <span class="text-[10px] font-mono uppercase tracking-widest text-[#10B981] block">CRÉDIT GRAND LIVRE // ENTRÉE</span>
                                        <h2 class="font-display font-bold text-lg text-[#F4F7FB] uppercase">DÉPOSER DES FONDS (SEPA INSTANT)</h2>
                                    </div>
                                    <span class="px-2 py-0.5 bg-[#10B981]/15 border border-[#10B981]/40 text-[#10B981] font-mono text-[10px] font-bold">0% FRAIS</span>
                                </div>

                                <form id="form-wallet-deposit" action="/wallets/deposit" method="POST" class="space-y-4">
                                    @csrf
                                    <div>
                                        <label class="block text-[10px] font-mono uppercase tracking-wider text-[#64748B] mb-1.5">ALLOCATIONS RAPIDES</label>
                                        <div class="grid grid-cols-4 gap-2 font-mono text-xs tabular-nums">
                                            <button type="button" data-deposit-preset="5000" class="h-10 bg-[#0C1018] border border-white/10 hover:border-[#10B981] text-[#F4F7FB] cursor-pointer">+50 €</button>
                                            <button type="button" data-deposit-preset="10000" class="h-10 bg-[#0C1018] border border-white/10 hover:border-[#10B981] text-[#F4F7FB] cursor-pointer">+100 €</button>
                                            <button type="button" data-deposit-preset="25000" class="h-10 bg-[#0C1018] border border-white/10 hover:border-[#10B981] text-[#F4F7FB] cursor-pointer">+250 €</button>
                                            <button type="button" data-deposit-preset="50000" class="h-10 bg-[#0C1018] border border-white/10 hover:border-[#10B981] text-[#F4F7FB] cursor-pointer">+500 €</button>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="deposit-amount-cents" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">
                                            MONTANT EN CENTIMES (<code class="text-[#10B981]">amount_cents</code> — 10000 = 100,00 €)
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
                                            class="w-full h-11 px-3.5 bg-[#0C1018] border border-white/15 font-mono text-sm text-[#F4F7FB] tabular-nums focus:border-[#10B981] focus:outline-none rounded-none"
                                        />
                                    </div>

                                    <div>
                                        <label for="deposit-idempotency-key" class="block text-[10px] font-mono uppercase tracking-wider text-[#64748B] mb-1">
                                            CLÉ D'IDEMPOTENCE (<code class="text-[#D8E5F8]">idempotency_key</code>)
                                        </label>
                                        <input
                                            type="text"
                                            id="deposit-idempotency-key"
                                            name="idempotency_key"
                                            value="dep:{{ uniqid() }}"
                                            required
                                            class="w-full h-10 px-3 bg-[#0C1018] border border-white/10 font-mono text-xs text-[#94A3B8] tabular-nums rounded-none"
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        id="deposit-submit-btn"
                                        class="w-full h-12 px-5 bg-[#10B981] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                                    >
                                        Créditer le Portefeuille (SEPA Instant) ↗
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Card B: Outgoing SEPA Bank Withdrawal (POST /api/wallets/withdraw) -->
                        <div class="bg-[#080B11] border border-white/[0.08] p-6 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-4 pb-3 border-b border-white/[0.08]">
                                    <div>
                                        <span class="text-[10px] font-mono uppercase tracking-widest text-[#E5C158] block">DÉBIT GRAND LIVRE // VIREMENT SORTANT</span>
                                        <h2 class="font-display font-bold text-lg text-[#F4F7FB] uppercase">RETRAIT BANCAIRE SEPA (IBAN)</h2>
                                    </div>
                                    <span class="px-2 py-0.5 bg-[#E5C158]/15 border border-[#E5C158]/40 text-[#E5C158] font-mono text-[10px] font-bold">INSTANT</span>
                                </div>

                                <form id="form-wallet-withdraw" action="/wallets/withdraw" method="POST" class="space-y-4">
                                    @csrf
                                    <div>
                                        <label class="block text-[10px] font-mono uppercase tracking-wider text-[#64748B] mb-1.5">RETRAITS RAPIDES</label>
                                        <div class="grid grid-cols-4 gap-2 font-mono text-xs tabular-nums">
                                            <button type="button" data-withdraw-preset="2500" class="h-10 bg-[#0C1018] border border-white/10 hover:border-[#E5C158] text-[#F4F7FB] cursor-pointer">-25 €</button>
                                            <button type="button" data-withdraw-preset="5000" class="h-10 bg-[#0C1018] border border-white/10 hover:border-[#E5C158] text-[#F4F7FB] cursor-pointer">-50 €</button>
                                            <button type="button" data-withdraw-preset="10000" class="h-10 bg-[#0C1018] border border-white/10 hover:border-[#E5C158] text-[#F4F7FB] cursor-pointer">-100 €</button>
                                            <button type="button" data-withdraw-preset="25000" class="h-10 bg-[#0C1018] border border-white/10 hover:border-[#E5C158] text-[#F4F7FB] cursor-pointer">-250 €</button>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label for="withdraw-amount-cents" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">
                                                MONTANT (<code class="text-[#E5C158]">amount_cents</code>)
                                            </label>
                                            <input
                                                type="number"
                                                id="withdraw-amount-cents"
                                                name="amount_cents"
                                                min="100"
                                                max="10000000"
                                                step="1"
                                                value="5000"
                                                required
                                                class="w-full h-11 px-3 bg-[#0C1018] border border-white/15 font-mono text-sm text-[#F4F7FB] tabular-nums focus:border-[#E5C158] focus:outline-none rounded-none"
                                            />
                                        </div>
                                        <div>
                                            <label for="withdraw-iban" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">
                                                IBAN BÉNÉFICIAIRE (<code class="text-[#D8E5F8]">iban</code>)
                                            </label>
                                            <input
                                                type="text"
                                                id="withdraw-iban"
                                                name="iban"
                                                value="FR76 3000 6000 0112 3456 7890 189"
                                                required
                                                class="w-full h-11 px-3 bg-[#0C1018] border border-white/15 font-mono text-xs text-[#F4F7FB] tabular-nums focus:border-[#E5C158] focus:outline-none rounded-none"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label for="withdraw-idempotency-key" class="block text-[10px] font-mono uppercase tracking-wider text-[#64748B] mb-1">
                                            CLÉ D'IDEMPOTENCE (<code class="text-[#D8E5F8]">idempotency_key</code>)
                                        </label>
                                        <input
                                            type="text"
                                            id="withdraw-idempotency-key"
                                            name="idempotency_key"
                                            value="wd:{{ uniqid() }}"
                                            required
                                            class="w-full h-10 px-3 bg-[#0C1018] border border-white/10 font-mono text-xs text-[#94A3B8] tabular-nums rounded-none"
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        id="withdraw-submit-btn"
                                        class="w-full h-12 px-5 bg-[#E5C158] text-[#030407] hover:bg-[#D8E5F8] font-mono text-xs font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                                    >
                                        Exécuter le Virement SEPA Sortant ↗
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Arsenal Loadout Configurator inside Overview -->
                    <div class="bg-[#080B11] border border-white/[0.08] p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                            <div>
                                <div class="text-[10px] font-mono uppercase tracking-widest text-[#D8E5F8]">CONFIGURATION RAPIDE D'ARSENAL</div>
                                <h3 class="font-display font-bold text-lg text-[#F4F7FB] uppercase">CLASSE TACTIQUE ENGAGÉE PAR DÉFAUT</h3>
                            </div>
                            <span id="overview-active-class-badge" class="px-3 py-1 bg-[#121824] border border-[#E5C158]/40 text-[#E5C158] font-mono text-xs font-bold">
                                CLASSE ACTIVE : {{ $currentUser->preferred_class }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 font-mono text-xs">
                            @foreach(['VALKYRIE', 'VANGUARD', 'SPECTRE', 'CHRONO'] as $clsName)
                                <button
                                    type="button"
                                    data-dashboard-loadout="{{ $clsName }}"
                                    class="h-12 px-4 border {{ $currentUser->preferred_class === $clsName ? 'border-[#E5C158] bg-[#121824] text-[#F4F7FB]' : 'border-white/10 bg-[#0C1018] text-[#94A3B8] hover:text-[#F4F7FB]' }} font-bold uppercase tracking-wider transition cursor-pointer"
                                >
                                    {{ $clsName }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </section>

                <!-- =========================================================
                     PANEL 2: MES ARÈNES & INSCRIPTIONS SÉQUESTRE (#cockpit-panel-arenas)
                     ========================================================= -->
                <section
                    id="cockpit-panel-arenas"
                    data-cockpit-panel="arenas"
                    class="{{ $activeTabKey === 'arenas' ? '' : 'hidden' }} space-y-8"
                >
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-white/[0.08] pb-5">
                        <div>
                            <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-1">
                                MODULE COCKPIT 02 // ENGAGEMENTS COMPÉTITIFS &amp; BUY-INS
                            </div>
                            <h2 class="font-display font-extrabold text-2xl sm:text-3xl text-[#F4F7FB] uppercase tracking-tight">
                                MES ARÈNES &amp; INSCRIPTIONS EN SÉQUESTRE
                            </h2>
                        </div>
                        <a href="/#arenas-catalog" class="h-10 px-4 bg-[#121824] border border-white/15 hover:border-[#E5C158] text-[#F4F7FB] font-mono text-xs font-semibold uppercase tracking-wider inline-flex items-center whitespace-nowrap">
                            Catalogue Complet Public ↗
                        </a>
                    </div>

                    <!-- Operator's Registered Tournaments Table / Cards -->
                    <div>
                        <h3 class="font-mono text-xs uppercase tracking-widest text-[#94A3B8] mb-3">
                            INSCRIPTIONS ACTIVES &amp; HISTORIQUE DE SÉQUESTRE (<span id="my-registrations-count" class="text-[#E5C158] tabular-nums">{{ $myRegistrations->count() }}</span>)
                        </h3>

                        <div id="dashboard-my-registrations-list" class="space-y-3">
                            @forelse($myRegistrations as $reg)
                                @php
                                    $t = $reg->tournament;
                                @endphp
                                @if($t)
                                    <div class="bg-[#080B11] border border-white/[0.08] p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2 mb-1.5 font-mono text-[10px]">
                                                <span class="px-2 py-0.5 bg-[#121824] border border-white/10 text-[#D8E5F8]">{{ $t->game }} // {{ $t->format }}</span>
                                                <span class="px-2 py-0.5 bg-[#E5C158]/15 border border-[#E5C158]/40 text-[#E5C158]">CLASSE : {{ $reg->loadout_class }}</span>
                                                <span class="px-2 py-0.5 bg-[#10B981]/15 border border-[#10B981]/40 text-[#10B981]">{{ $reg->status }}</span>
                                            </div>
                                            <h4 class="font-display font-bold text-lg text-[#F4F7FB]">{{ $t->title }}</h4>
                                            <div class="text-xs font-mono text-[#94A3B8] tabular-nums mt-1">
                                                BUY-IN VERROUILLÉ : <strong class="text-[#E5C158]">{{ \App\Models\Wallet::formatCents($reg->paid_entry_fee_cents) }}</strong>
                                                · POOL TOTAL : <strong class="text-[#10B981]">{{ \App\Models\Wallet::formatCents($t->escrow_pool_cents) }}</strong>
                                                · STATUT ARÈNE : <strong>{{ $t->status }}</strong>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 shrink-0">
                                            <a
                                                href="/tournaments/{{ $t->slug }}"
                                                class="h-11 px-5 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider inline-flex items-center justify-center rounded-none whitespace-nowrap transition"
                                            >
                                                Ouvrir l'Arène ↗
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            @empty
                                <div class="bg-[#080B11] border border-white/[0.08] p-8 text-center font-mono text-xs text-[#64748B]">
                                    Vous n'êtes inscrit à aucun tournoi pour le moment. Rejoignez une arène ouverte ci-dessous.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Open Arenas for Immediate 1-Click Buy-In -->
                    <div class="pt-6 border-t border-white/[0.08]">
                        <h3 class="font-mono text-xs uppercase tracking-widest text-[#10B981] mb-4">
                            ARÈNES OUVERTES AU BUY-IN IMMÉDIAT (SÉQUESTRE ACTIF)
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($tournaments as $t)
                                @php
                                    $alreadyIn = $t->registrations->contains('user_id', $currentUser->id);
                                @endphp
                                <div class="bg-[#080B11] border border-white/[0.08] p-5 flex flex-col justify-between gap-4">
                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-2 font-mono text-[10px]">
                                            <span class="text-[#D8E5F8]">{{ $t->game }} · {{ $t->format }}</span>
                                            <span class="px-2 py-0.5 border {{ $t->status === 'OPEN' ? 'border-[#10B981]/40 text-[#10B981]' : 'border-white/15 text-[#94A3B8]' }}">{{ $t->status }}</span>
                                        </div>
                                        <h4 class="font-display font-bold text-base text-[#F4F7FB] mb-2">{{ $t->title }}</h4>
                                        <div class="grid grid-cols-2 gap-2 font-mono text-xs tabular-nums bg-[#0C1018] p-3 border border-white/[0.06]">
                                            <div>
                                                <span class="text-[10px] text-[#64748B] block">BUY-IN</span>
                                                <strong class="text-[#F4F7FB]">{{ \App\Models\Wallet::formatCents($t->entry_fee_cents) }}</strong>
                                            </div>
                                            <div>
                                                <span class="text-[10px] text-[#64748B] block">POOL SÉQUESTRÉ</span>
                                                <strong class="text-[#E5C158]">{{ \App\Models\Wallet::formatCents($t->escrow_pool_cents) }}</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2.5">
                                        @if($t->status === 'OPEN' && !$alreadyIn)
                                            <button
                                                type="button"
                                                data-open-register="{{ $t->id }}"
                                                class="h-11 flex-1 px-3 bg-[#10B981] text-[#030407] hover:bg-[#E5C158] font-mono text-[11px] font-bold uppercase tracking-wider whitespace-nowrap truncate cursor-pointer transition"
                                            >
                                                S'inscrire ({{ \App\Models\Wallet::formatCents($t->entry_fee_cents) }}) ↗
                                            </button>
                                        @elseif($alreadyIn)
                                            <span class="h-11 flex-1 px-3 bg-[#10B981]/15 border border-[#10B981]/40 text-[#10B981] font-mono text-[11px] font-semibold uppercase inline-flex items-center justify-center whitespace-nowrap">
                                                ✓ Inscrit en Séquestre
                                            </span>
                                        @endif
                                        <a
                                            href="/tournaments/{{ $t->slug }}"
                                            class="h-11 px-3.5 bg-[#121824] border border-white/15 hover:border-[#D8E5F8] text-[#F4F7FB] font-mono text-[11px] font-semibold uppercase tracking-wider inline-flex items-center justify-center whitespace-nowrap transition"
                                        >
                                            Fiche Arène ↗
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <!-- =========================================================
                     PANEL 3: GRAND LIVRE IMMUABLE & FILTRES (#cockpit-panel-ledger)
                     ========================================================= -->
                <section
                    id="cockpit-panel-ledger"
                    data-cockpit-panel="ledger"
                    class="{{ $activeTabKey === 'ledger' ? '' : 'hidden' }} space-y-6"
                >
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-white/[0.08] pb-5">
                        <div>
                            <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#10B981] mb-1">
                                MODULE COCKPIT 03 // COMPTABILITÉ EN PARTIE DOUBLE (APPEND-ONLY)
                            </div>
                            <h2 class="font-display font-extrabold text-2xl sm:text-3xl text-[#F4F7FB] uppercase tracking-tight">
                                GRAND LIVRE IMMUABLE (<code class="text-lg text-[#E5C158]">ledger_entries</code>)
                            </h2>
                        </div>
                        <span class="px-3 py-1 bg-[#080B11] border border-white/10 font-mono text-[11px] text-[#94A3B8] tabular-nums">
                            INVARIANT: BALANCE_AFTER = BEFORE + CREDIT - DEBIT
                        </span>
                    </div>

                    <!-- Instant Filter Bar by Entry Type -->
                    <div class="flex flex-wrap items-center gap-2 font-mono text-xs">
                        <span class="text-[#64748B] mr-1">FILTRER PAR TYPE :</span>
                        <button type="button" data-ledger-filter="ALL" class="h-9 px-3 border border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-bold uppercase cursor-pointer">
                            TOUTES
                        </button>
                        <button type="button" data-ledger-filter="DEPOSIT" class="h-9 px-3 border border-white/10 bg-[#080B11] text-[#94A3B8] hover:text-[#F4F7FB] uppercase cursor-pointer">
                            DÉPÔTS (DEPOSIT)
                        </button>
                        <button type="button" data-ledger-filter="WITHDRAWAL" class="h-9 px-3 border border-white/10 bg-[#080B11] text-[#94A3B8] hover:text-[#F4F7FB] uppercase cursor-pointer">
                            RETRAITS (WITHDRAWAL)
                        </button>
                        <button type="button" data-ledger-filter="BUY_IN" class="h-9 px-3 border border-white/10 bg-[#080B11] text-[#94A3B8] hover:text-[#F4F7FB] uppercase cursor-pointer">
                            BUY-INS (SÉQUESTRE)
                        </button>
                        <button type="button" data-ledger-filter="PAYOUT" class="h-9 px-3 border border-white/10 bg-[#080B11] text-[#94A3B8] hover:text-[#F4F7FB] uppercase cursor-pointer">
                            GAINS (PAYOUT)
                        </button>
                        <button type="button" data-ledger-filter="REFUND" class="h-9 px-3 border border-white/10 bg-[#080B11] text-[#94A3B8] hover:text-[#F4F7FB] uppercase cursor-pointer">
                            REMBOURSEMENTS (REFUND)
                        </button>
                    </div>

                    <!-- Ledger Table -->
                    <div class="w-full overflow-x-auto border border-white/[0.08] bg-[#080B11]">
                        <table class="w-full min-w-[820px] text-left border-collapse tabular-nums">
                            <thead>
                                <tr class="border-b border-white/10 bg-[#0C1018] font-mono text-[10px] uppercase tracking-wider text-[#64748B] whitespace-nowrap tabular-nums">
                                    <th class="px-3.5 py-3 whitespace-nowrap tabular-nums">ID</th>
                                    <th class="px-3.5 py-3 whitespace-nowrap tabular-nums">TYPE OPÉRATION</th>
                                    <th class="px-3.5 py-3 min-w-[220px] max-w-[300px] whitespace-nowrap tabular-nums">DESCRIPTION</th>
                                    <th class="px-3.5 py-3 text-right whitespace-nowrap tabular-nums">SOLDE AVANT</th>
                                    <th class="px-3.5 py-3 text-right whitespace-nowrap tabular-nums">DÉBIT</th>
                                    <th class="px-3.5 py-3 text-right whitespace-nowrap tabular-nums">CRÉDIT</th>
                                    <th class="px-3.5 py-3 text-right whitespace-nowrap tabular-nums">SOLDE APRÈS</th>
                                    <th class="pl-3.5 pr-6 py-3 whitespace-nowrap tabular-nums">CLÉ D'IDEMPOTENCE</th>
                                </tr>
                            </thead>
                            <tbody id="ledger-entries-tbody" class="divide-y divide-white/[0.05] font-mono text-xs whitespace-nowrap tabular-nums">
                                <!-- Hydrated & filtered by app.js -->
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- =========================================================
                     PANEL 4: CONSOLE ORGANISATEUR & ARBITRAGE (#cockpit-panel-organizer)
                     ========================================================= -->
                <section
                    id="cockpit-panel-organizer"
                    data-cockpit-panel="organizer"
                    class="{{ $activeTabKey === 'organizer' ? '' : 'hidden' }} space-y-8"
                >
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-white/[0.08] pb-5">
                        <div>
                            <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#D8E5F8] mb-1">
                                MODULE COCKPIT 04 // CRÉATION DE LIGUES &amp; ARBITRAGE 60/30/10
                            </div>
                            <h2 class="font-display font-extrabold text-2xl sm:text-3xl text-[#F4F7FB] uppercase tracking-tight">
                                CONSOLE ORGANISATEUR &amp; HOMOLOGATION
                            </h2>
                        </div>
                        <span class="px-3 py-1 bg-[#080B11] border border-[#E5C158]/40 text-[#E5C158] font-mono text-xs">
                            RÔLE ACTUEL : {{ $currentUser->role }}
                        </span>
                    </div>

                    @if($currentUser->role === 'PLAYER')
                        <div class="p-4 bg-[#F59E0B]/10 border border-[#F59E0B]/40 flex flex-col sm:flex-row sm:items-center justify-between gap-4 font-mono text-xs">
                            <div>
                                <strong class="text-[#F59E0B] block uppercase">MODE JOUEUR DÉTECTÉ (PROTECTION ANTI-BFLA ACTIVE)</strong>
                                <span class="text-[#94A3B8]">Un compte <code>PLAYER</code> recevra un refus strict <code>403 Forbidden</code> s'il tente de créer ou clôturer un tournoi. Basculez sur un compte Organisateur ou Admin en 1 clic :</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button" data-demo-email="organizer@valkyr.arena" data-demo-redirect="/dashboard?tab=organizer" class="h-10 px-3.5 bg-[#D8E5F8] text-[#030407] font-bold uppercase cursor-pointer">
                                    VKR_ARBITER (ORG) ↗
                                </button>
                                <button type="button" data-demo-email="admin@valkyr.arena" data-demo-redirect="/dashboard?tab=organizer" class="h-10 px-3.5 bg-[#E5C158] text-[#030407] font-bold uppercase cursor-pointer">
                                    VKR_COMMANDER (ADMIN) ↗
                                </button>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
                        <!-- Left 5 Cols: Create New League / Tournament Form -->
                        <div class="xl:col-span-5 bg-[#080B11] border border-white/[0.08] p-6">
                            <div class="text-[10px] font-mono uppercase tracking-widest text-[#E5C158] mb-1">NOUVEAU SÉQUESTRE</div>
                            <h3 class="font-display font-bold text-lg text-[#F4F7FB] uppercase mb-4">OUVRIR UNE ARÈNE / LIGUE PRIVÉE</h3>

                            <form id="form-create-tournament" action="/tournaments" method="POST" class="space-y-4">
                                @csrf
                                <div>
                                    <label for="create-title" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">TITRE OFFICIEL DE L'ARÈNE</label>
                                    <input
                                        type="text"
                                        id="create-title"
                                        name="title"
                                        value="VALKYR INVITATIONAL // DIVISION PRO"
                                        required
                                        class="w-full h-11 px-3.5 bg-[#0C1018] border border-white/15 font-mono text-xs text-[#F4F7FB] focus:border-[#D8E5F8] focus:outline-none rounded-none"
                                    />
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="create-game" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">DISCIPLINE</label>
                                        <select id="create-game" name="game" class="w-full h-11 px-3 bg-[#0C1018] border border-white/15 font-mono text-xs text-[#F4F7FB] rounded-none">
                                            <option value="VALORANT // PROTOCOL">VALORANT // PROTOCOL</option>
                                            <option value="COUNTER-STRIKE 2">COUNTER-STRIKE 2</option>
                                            <option value="APEX // SOVEREIGN">APEX // SOVEREIGN</option>
                                            <option value="LEAGUE OF LEGENDS">LEAGUE OF LEGENDS</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="create-format" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">FORMAT</label>
                                        <select id="create-format" name="format" class="w-full h-11 px-3 bg-[#0C1018] border border-white/15 font-mono text-xs text-[#F4F7FB] rounded-none">
                                            <option value="5v5">5v5 Escouade</option>
                                            <option value="1v1">1v1 Duel</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="create-fee" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">BUY-IN (CENTIMES)</label>
                                        <input
                                            type="number"
                                            id="create-fee"
                                            name="entry_fee_cents"
                                            min="100"
                                            step="100"
                                            value="5000"
                                            required
                                            class="w-full h-11 px-3 bg-[#0C1018] border border-white/15 font-mono text-sm text-[#E5C158] tabular-nums rounded-none"
                                        />
                                    </div>
                                    <div>
                                        <label for="create-max" class="block text-[10px] font-mono uppercase tracking-wider text-[#94A3B8] mb-1">SLOTS MAX</label>
                                        <input
                                            type="number"
                                            id="create-max"
                                            name="max_participants"
                                            min="2"
                                            max="128"
                                            value="16"
                                            required
                                            class="w-full h-11 px-3 bg-[#0C1018] border border-white/15 font-mono text-sm text-[#F4F7FB] tabular-nums rounded-none"
                                        />
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    id="create-tournament-submit-btn"
                                    class="w-full h-12 px-5 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                                >
                                    Armer le Séquestre &amp; Publier l'Arène ↗
                                </button>
                            </form>
                        </div>

                        <!-- Right 7 Cols: Manage & Settle Existing Arenas -->
                        <div class="xl:col-span-7 bg-[#080B11] border border-white/[0.08] p-6">
                            <div class="text-[10px] font-mono uppercase tracking-widest text-[#10B981] mb-1">SUPERVISION &amp; ARBITRAGE</div>
                            <h3 class="font-display font-bold text-lg text-[#F4F7FB] uppercase mb-4">CLÔTURE 60/30/10 &amp; REMBOURSEMENT INTÉGRAL</h3>

                            <div class="space-y-3">
                                @foreach($tournaments as $t)
                                    @php
                                        $canManageT = $currentUser->role === 'ADMIN' || ($currentUser->role === 'ORGANIZER' && (int) $t->organizer_id === (int) $currentUser->id);
                                    @endphp
                                    <div class="p-4 bg-[#0C1018] border border-white/[0.08] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2 mb-1 font-mono text-[10px]">
                                                <span class="px-2 py-0.5 bg-[#080B11] border border-white/10 text-[#D8E5F8]">{{ $t->status }}</span>
                                                @if($canManageT)
                                                    <span class="px-2 py-0.5 bg-[#E5C158]/15 border border-[#E5C158]/40 text-[#E5C158]">AUTORISÉ // PROPRIÉTAIRE OU ADMIN</span>
                                                @else
                                                    <span class="px-2 py-0.5 bg-white/5 border border-white/10 text-[#64748B]">LECTURE SEULE // ORG: {{ $t->organizer?->gamertag }}</span>
                                                @endif
                                            </div>
                                            <h4 class="font-display font-bold text-base text-[#F4F7FB] truncate">{{ $t->title }}</h4>
                                            <div class="text-xs font-mono text-[#94A3B8] tabular-nums mt-0.5">
                                                Pool Séquestré : <strong class="text-[#E5C158]">{{ \App\Models\Wallet::formatCents($t->escrow_pool_cents) }}</strong>
                                                · Inscrits : <strong>{{ $t->registered_count }}/{{ $t->max_participants }}</strong>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 shrink-0">
                                            <button
                                                type="button"
                                                data-open-detail="{{ $t->id }}"
                                                class="h-10 px-3.5 bg-[#E5C158] text-[#030407] hover:bg-[#D8E5F8] font-mono text-[11px] font-bold uppercase tracking-wider whitespace-nowrap cursor-pointer transition"
                                            >
                                                Arbitrer 60/30/10 ⤢
                                            </button>
                                            <a
                                                href="/tournaments/{{ $t->slug }}"
                                                class="h-10 px-3.5 bg-[#080B11] border border-white/15 hover:border-[#D8E5F8] text-[#F4F7FB] font-mono text-[11px] font-semibold uppercase tracking-wider inline-flex items-center whitespace-nowrap transition"
                                            >
                                                Page Arène ↗
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </main>

    <!-- =====================================================================
         MODAL: INSCRIPTION BUY-IN SÉQUESTRE (POST /api/tournaments/{id}/register)
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
         MODAL: DÉTAIL DU TOURNOI, CLÔTURE 60/30/10 (settle) & ANNULATION (cancel)
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
                <div class="lg:col-span-5">
                    <div class="text-[11px] font-mono uppercase tracking-wider text-[#94A3B8] mb-2">OPÉRATEURS INSCRITS EN SÉQUESTRE</div>
                    <div id="detail-modal-roster" class="space-y-2 max-h-64 overflow-y-auto pr-1"></div>
                </div>

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

                    <form id="form-cancel-tournament" data-tournament-id="1" method="POST" class="bg-[#080B11] border border-[#EF4444]/30 p-4 space-y-3">
                        @csrf
                        <div class="text-xs font-mono font-bold text-[#EF4444] uppercase">
                            ANNULATION D'URGENCE &amp; REMBOURSEMENT INTÉGRAL (100% ESCROW)
                        </div>
                        <input type="hidden" id="cancel-idempotency-key" name="idempotency_key" value="cancel:{{ uniqid() }}" />
                        <input type="text" name="reason" value="Annulation administrative — remboursement intégral 100%" class="w-full h-9 px-2.5 bg-[#0C1018] border border-white/10 font-mono text-xs text-[#94A3B8]" />
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
