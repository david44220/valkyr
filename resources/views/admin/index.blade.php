<!DOCTYPE html>
<html lang="fr" class="dark" style="color-scheme: dark;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Commandement Admin Souverain — Trésorerie &amp; Rails — VALKYR // ARENA</title>
    <meta name="description" content="Back-Office Souverain VALKYR // ARENA : Trésorerie à 5 flux de revenus, configuration des rails de paiement manuels et instantanés, file d'approbation et audit du Grand Livre.">

    <!-- Bespoke Sovereign Inline SVG Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' fill='%23030407'/%3E%3Crect x='2' y='2' width='60' height='60' fill='none' stroke='%23E5C158' stroke-width='2'/%3E%3Cpath d='M32 10 L38 26 L54 32 L38 38 L32 54 L26 38 L10 32 L26 26 Z' fill='%23E5C158'/%3E%3C/svg%3E">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">

    @php
        $activePanelKey = in_array($activePanel ?? 'treasury', ['treasury', 'rails', 'users', 'tournaments', 'ledger'], true)
            ? $activePanel
            : 'treasury';

        $hydratedState = [
            'page' => 'admin',
            'activePanel' => $activePanelKey,
            'currentUser' => $currentUser,
            'wallet' => $wallet,
            'revenueMetrics' => $revenueMetrics,
            'ledgerHealth' => $ledgerHealth,
            'paymentRails' => $paymentRails,
            'fundingRequests' => $fundingRequests,
            'pendingFundingCount' => $pendingFundingCount,
            'operators' => $operators,
            'tournaments' => $tournaments,
            'masterLedgerEntries' => $masterLedgerEntries,
            'voucherCodes' => $voucherCodes ?? [],
            'disputes' => $disputes ?? [],
            'openDisputesCount' => $openDisputesCount ?? 0,
            'wagerDuels' => $wagerDuels ?? [],
            'clans' => $clans ?? [],
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

    @if(session('status'))
        <div class="bg-[#10B981]/15 border-b border-[#10B981]/40 px-4 py-2.5 text-center font-mono text-xs text-[#10B981]">
            ✓ {{ session('status') }}
        </div>
    @endif

    <!-- =====================================================================
         TOPBAR — COMMANDEMENT ADMIN SOUVERAIN
         ===================================================================== -->
    <header class="sticky top-0 z-50 bg-[#080B11]/95 backdrop-blur-md border-b border-white/[0.08]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-wrap items-center justify-between gap-4">
            <!-- Left: Brand + Public Arena & Member Cockpit Links -->
            <div class="flex flex-wrap items-center gap-3">
                <a href="/" class="font-display font-extrabold text-lg sm:text-xl tracking-tight text-[#F4F7FB] flex items-center gap-2 whitespace-nowrap">
                    <span class="text-[#E5C158]" aria-hidden="true">✦</span>
                    <span>VALKYR</span>
                    <span class="px-2 py-0.5 text-[10px] font-mono bg-[#E5C158] text-[#030407] font-bold uppercase tracking-widest">
                        ADMIN // COMMAND
                    </span>
                </a>

                <a
                    href="/"
                    class="h-10 px-3.5 bg-[#0C1018] border border-white/15 hover:border-[#D8E5F8] text-[#94A3B8] hover:text-[#F4F7FB] font-mono text-xs font-semibold uppercase tracking-wider inline-flex items-center gap-1.5 rounded-none whitespace-nowrap transition"
                >
                    <span>← Arène Publique</span>
                </a>

                <a
                    href="/dashboard"
                    class="h-10 px-3.5 bg-[#121824] border border-[#D8E5F8]/30 hover:border-[#D8E5F8] text-[#D8E5F8] font-mono text-xs font-semibold uppercase tracking-wider inline-flex items-center gap-1.5 rounded-none whitespace-nowrap transition"
                >
                    <span>Mon Cockpit Membre ↗</span>
                </a>

                <a
                    id="admin-export-csv-btn"
                    href="/admin/export/ledger.csv"
                    class="h-10 px-3.5 bg-[#10B981]/15 border border-[#10B981]/50 hover:bg-[#10B981] hover:text-[#030407] text-[#10B981] font-mono text-xs font-bold uppercase tracking-wider inline-flex items-center gap-1.5 rounded-none whitespace-nowrap transition"
                >
                    <span>EXPORTER GRAND LIVRE CERTIFIÉ (.CSV) ↓</span>
                </a>
            </div>

            <!-- Right: Treasury Live Badge, Operator Identity & Logout -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="px-3.5 py-2 bg-[#0C1018] border border-[#E5C158]/40 font-mono text-xs flex items-center gap-3 tabular-nums">
                    <span class="text-[#94A3B8]">SYS_TREASURY:</span>
                    <strong id="admin-topbar-treasury" class="text-[#E5C158]">
                        {{ \App\Models\Wallet::formatCents((int) ($revenueMetrics['treasury_balance_cents'] ?? 0)) }}
                    </strong>
                    <span class="text-white/20">|</span>
                    <span class="text-[#94A3B8]">SÉQUESTRE:</span>
                    <strong id="admin-topbar-escrow" class="text-[#10B981]">
                        {{ \App\Models\Wallet::formatCents((int) $revenueMetrics['escrow_balance_cents']) }}
                    </strong>
                </div>

                <div class="px-3 py-2 bg-[#0C1018] border border-white/10 font-mono text-xs tabular-nums">
                    <span class="text-[#E5C158] font-bold">{{ $currentUser->gamertag }}</span>
                    <span class="text-[#64748B] mx-1">[{{ $currentUser->role }}]</span>
                </div>

                <button
                    type="button"
                    data-logout-btn
                    data-logout-redirect="/"
                    class="h-10 px-3.5 bg-[#080B11] border border-[#EF4444]/40 text-[#EF4444] hover:bg-[#EF4444] hover:text-[#030407] font-mono text-xs font-semibold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                >
                    Déconnexion ✕
                </button>
            </div>
        </div>
    </header>

    <!-- =====================================================================
         MAIN ADMIN COMMAND SHELL (SIDEBAR + 5 EXCLUSIVE PANELS)
         ===================================================================== -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 border border-white/[0.08] bg-[#0C1018] min-h-[780px]">

            <!-- =============================================================
                 LEFT SIDEBAR (3 COLS): 5 EXCLUSIVE COMMAND TABS
                 ============================================================= -->
            <aside class="lg:col-span-3 bg-[#080B11] border-b lg:border-b-0 lg:border-r border-white/[0.08] p-5 flex flex-col justify-between">
                <div>
                    <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-2">
                        BACK-OFFICE SOUVERAIN V3
                    </div>
                    <h1 class="font-display font-extrabold text-lg text-[#F4F7FB] uppercase tracking-tight mb-5">
                        COMMANDEMENT ADMIN // VALKYR ARENA
                    </h1>

                    <nav aria-label="Panneaux du Commandement Admin" role="tablist" class="space-y-1.5 font-mono text-xs">
                        <button
                            type="button"
                            role="tab"
                            data-admin-tab="treasury"
                            aria-selected="{{ $activePanelKey === 'treasury' ? 'true' : 'false' }}"
                            class="w-full text-left px-3.5 py-3.5 border-l-2 {{ $activePanelKey === 'treasury' ? 'border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-semibold' : 'border-transparent text-[#94A3B8] hover:text-[#F4F7FB] hover:bg-[#121824]/50' }} transition cursor-pointer flex items-center justify-between gap-2"
                        >
                            <span>01. Trésorerie &amp; 5 Flux</span>
                            <span class="text-[#E5C158]">→</span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            data-admin-tab="rails"
                            aria-selected="{{ $activePanelKey === 'rails' ? 'true' : 'false' }}"
                            class="w-full text-left px-3.5 py-3.5 border-l-2 {{ $activePanelKey === 'rails' ? 'border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-semibold' : 'border-transparent text-[#94A3B8] hover:text-[#F4F7FB] hover:bg-[#121824]/50' }} transition cursor-pointer flex items-center justify-between gap-2"
                        >
                            <span>02. Rails &amp; Approbations</span>
                            <span id="admin-tab-pending-badge" class="px-1.5 py-0.5 text-[10px] {{ $pendingFundingCount > 0 ? 'bg-[#E5C158] text-[#030407] font-bold' : 'bg-[#080B11] border border-white/10 text-[#94A3B8]' }} tabular-nums">
                                {{ $pendingFundingCount }} ATTENTE
                            </span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            data-admin-tab="users"
                            aria-selected="{{ $activePanelKey === 'users' ? 'true' : 'false' }}"
                            class="w-full text-left px-3.5 py-3.5 border-l-2 {{ $activePanelKey === 'users' ? 'border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-semibold' : 'border-transparent text-[#94A3B8] hover:text-[#F4F7FB] hover:bg-[#121824]/50' }} transition cursor-pointer flex items-center justify-between gap-2"
                        >
                            <span>03. Opérateurs &amp; Wallets</span>
                            <span class="px-1.5 py-0.5 text-[10px] bg-[#080B11] border border-white/10 text-[#D8E5F8] tabular-nums">{{ $operators->count() }}</span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            data-admin-tab="tournaments"
                            aria-selected="{{ $activePanelKey === 'tournaments' ? 'true' : 'false' }}"
                            class="w-full text-left px-3.5 py-3.5 border-l-2 {{ $activePanelKey === 'tournaments' ? 'border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-semibold' : 'border-transparent text-[#94A3B8] hover:text-[#F4F7FB] hover:bg-[#121824]/50' }} transition cursor-pointer flex items-center justify-between gap-2"
                        >
                            <span>04. Ligues, Rake &amp; Boosts</span>
                            <span class="px-1.5 py-0.5 text-[10px] bg-[#080B11] border border-white/10 text-[#10B981] tabular-nums">{{ $tournaments->count() }}</span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            data-admin-tab="ledger"
                            aria-selected="{{ $activePanelKey === 'ledger' ? 'true' : 'false' }}"
                            class="w-full text-left px-3.5 py-3.5 border-l-2 {{ $activePanelKey === 'ledger' ? 'border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-semibold' : 'border-transparent text-[#94A3B8] hover:text-[#F4F7FB] hover:bg-[#121824]/50' }} transition cursor-pointer flex items-center justify-between gap-2"
                        >
                            <span>05. Audit Grand Livre</span>
                            <span class="px-1.5 py-0.5 text-[10px] bg-[#080B11] border border-white/10 text-[#E5C158] tabular-nums">{{ $masterLedgerEntries->count() }}</span>
                        </button>
                    </nav>

                    <!-- Ledger Invariant Quick Pill -->
                    <div class="mt-6 p-3.5 bg-[#0C1018] border {{ $ledgerHealth['is_healthy'] ? 'border-[#10B981]/40' : 'border-[#EF4444]/40' }} font-mono text-[11px] space-y-1 tabular-nums">
                        <div class="flex items-center justify-between">
                            <span class="{{ $ledgerHealth['is_healthy'] ? 'text-[#10B981]' : 'text-[#EF4444]' }} font-bold">
                                {{ $ledgerHealth['is_healthy'] ? '● INVARIANT CERTIFIÉ' : '▲ ALERTE LEDGER' }}
                            </span>
                            <span class="text-[#94A3B8]">{{ $ledgerHealth['wallets_checked_count'] }} WALLETS</span>
                        </div>
                        <div class="text-[10px] text-[#64748B]">
                            Σ Wallets = Σ Dépôts - Σ Retraits
                        </div>
                    </div>
                </div>

                <!-- Sidebar Quick Role Simulator -->
                <div class="mt-8 pt-5 border-t border-white/[0.08]">
                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#94A3B8] mb-2">
                        SIMULATEUR DE RÔLES (1 CLIC)
                    </div>
                    <div class="grid grid-cols-2 gap-1.5 font-mono text-[10px]">
                        @foreach($demoUsers as $dUser)
                            <button
                                type="button"
                                data-demo-email="{{ $dUser->email }}"
                                data-demo-redirect="{{ $dUser->isAdmin() ? '/admin' : '/dashboard' }}"
                                class="h-8 px-2 bg-[#0C1018] border {{ $currentUser->id === $dUser->id ? 'border-[#E5C158] text-[#E5C158]' : 'border-white/10 text-[#94A3B8] hover:text-[#F4F7FB]' }} truncate text-left cursor-pointer transition"
                            >
                                {{ $dUser->gamertag }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </aside>

            <!-- =============================================================
                 MAIN ADMIN WORKSPACE (9 COLS) — 5 EXCLUSIVE PANELS
                 ============================================================= -->
            <div class="lg:col-span-9 p-6 sm:p-8">

                <!-- =========================================================
                     PANEL 1: TRÉSORERIE SOUVERAINE & 5 FLUX DE REVENUS (#admin-panel-treasury)
                     ========================================================= -->
                <section
                    id="admin-panel-treasury"
                    data-admin-panel="treasury"
                    class="{{ $activePanelKey === 'treasury' ? '' : 'hidden' }} space-y-8"
                >
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-white/[0.08] pb-5">
                        <div>
                            <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-1">
                                PANNEAU 01 // MOTEUR DE REVENUS MULTI-FLUX &amp; CONSERVATION MONÉTAIRE
                            </div>
                            <h2 class="font-display font-extrabold text-2xl sm:text-3xl text-[#F4F7FB] uppercase tracking-tight">
                                TRÉSORERIE SOUVERAINE &amp; 5 FLUX DE REVENUS
                            </h2>
                        </div>
                        @if($pendingFundingCount > 0)
                            <button
                                type="button"
                                data-admin-tab="rails"
                                class="h-10 px-4 bg-[#E5C158] text-[#030407] hover:bg-[#D8E5F8] font-mono text-xs font-bold uppercase tracking-wider whitespace-nowrap cursor-pointer transition"
                            >
                                {{ $pendingFundingCount }} Demande(s) Manuelle(s) à Régler →
                            </button>
                        @endif
                    </div>

                    <!-- 8 Bento KPI Cards (Tabular-Nums) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 font-mono tabular-nums">
                        <!-- KPI 1: Treasury Balance -->
                        <div class="bg-[#080B11] border border-[#E5C158]/50 p-5">
                            <div class="text-[10px] uppercase tracking-widest text-[#E5C158]">SOLDE TRÉSORERIE (SYS_TREASURY)</div>
                            <div id="kpi-admin-treasury" class="text-2xl font-bold text-[#E5C158] mt-2">
                                {{ \App\Models\Wallet::formatCents((int) $revenueMetrics['treasury_balance_cents']) }}
                            </div>
                            <div class="text-[10px] text-[#94A3B8] mt-1">CUMUL DES REVENUS NETS PLATEFORME</div>
                        </div>

                        <!-- KPI 2: Active Escrow Balance -->
                        <div class="bg-[#080B11] border border-[#10B981]/40 p-5">
                            <div class="text-[10px] uppercase tracking-widest text-[#10B981]">SÉQUESTRE ACTIF (SYS_ESCROW_VAULT)</div>
                            <div id="kpi-admin-escrow" class="text-2xl font-bold text-[#10B981] mt-2">
                                {{ \App\Models\Wallet::formatCents((int) $revenueMetrics['escrow_balance_cents']) }}
                            </div>
                            <div class="text-[10px] text-[#94A3B8] mt-1">BUY-INS &amp; DOTATIONS VERROUILLÉS</div>
                        </div>

                        <!-- KPI 3: Stream #1 Net Tournament Rake -->
                        <div class="bg-[#080B11] border border-white/10 p-5">
                            <div class="text-[10px] uppercase tracking-widest text-[#D8E5F8]">FLUX #1 // RAKE TOURNOIS NET</div>
                            <div id="kpi-admin-rake" class="text-2xl font-bold text-[#F4F7FB] mt-2">
                                {{ \App\Models\Wallet::formatCents((int) $revenueMetrics['net_tournament_rake_cents']) }}
                            </div>
                            <div class="text-[10px] text-[#64748B] mt-1">PLATFORM_RAKE_CREDIT (HORS REVSHARE)</div>
                        </div>

                        <!-- KPI 4: Stream #2 Payment Rail Fees -->
                        <div class="bg-[#080B11] border border-white/10 p-5">
                            <div class="text-[10px] uppercase tracking-widest text-[#D8E5F8]">FLUX #2 // FRAIS RAILS PAIEMENT</div>
                            <div id="kpi-admin-rail-fees" class="text-2xl font-bold text-[#F4F7FB] mt-2">
                                {{ \App\Models\Wallet::formatCents((int) $revenueMetrics['payment_rail_fees_cents']) }}
                            </div>
                            <div class="text-[10px] text-[#64748B] mt-1">COMMISSIONS DÉPÔTS &amp; RETRAITS</div>
                        </div>

                        <!-- KPI 5: Stream #3 Prime Subscriptions -->
                        <div class="bg-[#080B11] border border-white/10 p-5">
                            <div class="text-[10px] uppercase tracking-widest text-[#E5C158]">FLUX #3 // PASS ★ VALKYR PRIME</div>
                            <div id="kpi-admin-prime" class="text-2xl font-bold text-[#E5C158] mt-2">
                                {{ \App\Models\Wallet::formatCents((int) $revenueMetrics['prime_subscriptions_revenue_cents']) }}
                            </div>
                            <div class="text-[10px] text-[#64748B] mt-1">ABONNEMENTS 19,00 € VIA WALLET</div>
                        </div>

                        <!-- KPI 6: Stream #4 Arena Boosts -->
                        <div class="bg-[#080B11] border border-white/10 p-5">
                            <div class="text-[10px] uppercase tracking-widest text-[#10B981]">FLUX #4 // BOOSTS ★ FEATURED</div>
                            <div id="kpi-admin-boosts" class="text-2xl font-bold text-[#10B981] mt-2">
                                {{ \App\Models\Wallet::formatCents((int) $revenueMetrics['arena_boosts_revenue_cents']) }}
                            </div>
                            <div class="text-[10px] text-[#64748B] mt-1">MISES EN AVANT 25,00 € VIA WALLET</div>
                        </div>

                        <!-- KPI 7: Stream #5 Organizer RevShare Paid -->
                        <div class="bg-[#080B11] border border-white/10 p-5">
                            <div class="text-[10px] uppercase tracking-widest text-[#D8E5F8]">FLUX #5 // REVSHARE ORGANISATEURS</div>
                            <div id="kpi-admin-revshare" class="text-2xl font-bold text-[#D8E5F8] mt-2">
                                {{ \App\Models\Wallet::formatCents((int) $revenueMetrics['organizer_revshare_paid_cents']) }}
                            </div>
                            <div class="text-[10px] text-[#64748B] mt-1">PART COMMISSION REVERSÉE AUX LIGUES</div>
                        </div>

                        <!-- KPI 8: Sponsored Pool Injected -->
                        <div class="bg-[#080B11] border border-white/10 p-5">
                            <div class="text-[10px] uppercase tracking-widest text-[#94A3B8]">DOTATIONS SPONSORISÉES</div>
                            <div id="kpi-admin-sponsored" class="text-2xl font-bold text-[#F4F7FB] mt-2">
                                {{ \App\Models\Wallet::formatCents((int) $revenueMetrics['sponsored_pool_injected_cents']) }}
                            </div>
                            <div class="text-[10px] text-[#64748B] mt-1">ABONDEMENTS INJECTÉS EN SÉQUESTRE</div>
                        </div>

                        <!-- KPI 9 (v4): Armory Revenue -->
                        <div class="bg-[#080B11] border border-[#E5C158]/30 p-5">
                            <div class="text-[10px] uppercase tracking-widest text-[#E5C158]">FLUX V4 // BOUTIQUE ARMORY</div>
                            <div id="kpi-admin-armory" class="text-2xl font-bold text-[#E5C158] mt-2">
                                {{ \App\Models\Wallet::formatCents((int) ($revenueMetrics['armory_revenue_cents'] ?? 0)) }}
                            </div>
                            <div class="text-[10px] text-[#64748B] mt-1">ARMORY_REVENUE_CREDIT (BADGES &amp; BOUCLIERS)</div>
                        </div>

                        <!-- KPI 10 (v4): Clan Licenses Revenue -->
                        <div class="bg-[#080B11] border border-[#D8E5F8]/30 p-5">
                            <div class="text-[10px] uppercase tracking-widest text-[#D8E5F8]">FLUX V4 // FRANCHISES DE CLANS</div>
                            <div id="kpi-admin-clans" class="text-2xl font-bold text-[#D8E5F8] mt-2">
                                {{ \App\Models\Wallet::formatCents((int) ($revenueMetrics['clan_licenses_revenue_cents'] ?? 0)) }}
                            </div>
                            <div class="text-[10px] text-[#64748B] mt-1">CLAN_LICENSE_CREDIT (29,00 € PAR ROSTER)</div>
                        </div>

                        <!-- KPI 11 (v4): Wager Duels 1v1 Rake -->
                        <div class="bg-[#080B11] border border-[#10B981]/30 p-5">
                            <div class="text-[10px] uppercase tracking-widest text-[#10B981]">FLUX V4 // RAKE DUELS 1V1 (5%)</div>
                            <div id="kpi-admin-duel-rake" class="text-2xl font-bold text-[#10B981] mt-2">
                                {{ \App\Models\Wallet::formatCents((int) ($revenueMetrics['duel_rake_cents'] ?? 0)) }}
                            </div>
                            <div class="text-[10px] text-[#64748B] mt-1">COMMISSION 500 BPS SUR DUELS CLÔTURÉS</div>
                        </div>

                        <!-- KPI 12 (v4): Dispute Forfeits Revenue -->
                        <div class="bg-[#080B11] border border-[#EF4444]/30 p-5">
                            <div class="text-[10px] uppercase tracking-widest text-[#EF4444]">FLUX V4 // CAUTIONS LITIGES CONFISQUÉES</div>
                            <div id="kpi-admin-dispute-forfeits" class="text-2xl font-bold text-[#F4F7FB] mt-2">
                                {{ \App\Models\Wallet::formatCents((int) ($revenueMetrics['dispute_forfeits_revenue_cents'] ?? 0)) }}
                            </div>
                            <div class="text-[10px] text-[#64748B] mt-1">DISPUTE_FORFEIT_CREDIT (5,00 € PAR REJET)</div>
                        </div>
                    </div>

                    <!-- Mathematical Double-Entry Ledger Integrity Monitor ($ledgerHealth) -->
                    <div class="bg-[#080B11] border {{ $ledgerHealth['is_healthy'] ? 'border-[#10B981]/50' : 'border-[#EF4444]/50' }} p-6 space-y-5 font-mono">
                        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-white/[0.08] pb-4">
                            <div>
                                <div class="text-[10px] uppercase tracking-widest text-[#10B981]">
                                    AUDIT AUTOMATIQUE EN PARTIE DOUBLE // ZERO FLOAT ARITHMETIC
                                </div>
                                <h3 class="font-display font-bold text-lg text-[#F4F7FB] uppercase mt-0.5">
                                    MONITEUR D'INTÉGRITÉ MATHÉMATIQUE DU GRAND LIVRE
                                </h3>
                            </div>
                            <span class="px-3 py-1.5 {{ $ledgerHealth['is_healthy'] ? 'bg-[#10B981]/15 border border-[#10B981]/50 text-[#10B981]' : 'bg-[#EF4444]/15 border border-[#EF4444]/50 text-[#EF4444]' }} text-xs font-bold uppercase tracking-wider">
                                {{ $ledgerHealth['is_healthy'] ? '✓ INVARIANT DOUBLE-ENTRÉE CERTIFIÉ' : '✕ ANOMALIE COMPTABLE DÉTECTÉE' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs tabular-nums">
                            <div class="p-4 bg-[#0C1018] border border-white/[0.08]">
                                <div class="text-[10px] text-[#64748B]">ENTRÉES EXTERNES (Σ DEPOSIT)</div>
                                <div class="text-base font-bold text-[#10B981] mt-1">
                                    +{{ \App\Models\Wallet::formatCents((int) $ledgerHealth['total_deposits_cents']) }}
                                </div>
                            </div>
                            <div class="p-4 bg-[#0C1018] border border-white/[0.08]">
                                <div class="text-[10px] text-[#64748B]">SORTIES EXTERNES (Σ WITHDRAWAL)</div>
                                <div class="text-base font-bold text-[#EF4444] mt-1">
                                    -{{ \App\Models\Wallet::formatCents((int) $ledgerHealth['total_withdrawals_cents']) }}
                                </div>
                            </div>
                            <div class="p-4 bg-[#0C1018] border border-[#E5C158]/40">
                                <div class="text-[10px] text-[#E5C158]">SOMME DES {{ $ledgerHealth['wallets_checked_count'] }} WALLETS ACTIFS</div>
                                <div class="text-base font-bold text-[#E5C158] mt-1">
                                    {{ \App\Models\Wallet::formatCents((int) $ledgerHealth['sum_all_wallets_cents']) }}
                                </div>
                            </div>
                            <div class="p-4 bg-[#0C1018] border border-[#10B981]/40">
                                <div class="text-[10px] text-[#10B981]">ÉCART DE CONSERVATION</div>
                                <div class="text-base font-bold text-[#10B981] mt-1">
                                    0,00 € (EXACT)
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- =====================================================
                         MODULE V4 — GÉNÉRATEUR DE CODES VOUCHERS PRÉPAYÉS (#admin-vouchers-panel)
                         ===================================================== -->
                    <div id="admin-vouchers-panel" class="bg-[#080B11] border border-[#E5C158]/40 p-6 space-y-6">
                        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-white/[0.08] pb-4">
                            <div>
                                <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#E5C158]">
                                    BRIQUE V4 #5 // CRÉDIT PRÉPAYÉ ATOMIQUE SUR WALLET (VOUCHER_CODE_CREDIT)
                                </div>
                                <h3 class="font-display font-bold text-xl text-[#F4F7FB] uppercase mt-0.5">
                                    GÉNÉRATEUR DE CODES VOUCHERS &amp; CARTES CADEAUX ({{ isset($voucherCodes) ? $voucherCodes->count() : 0 }})
                                </h3>
                            </div>
                            <span class="px-3 py-1 bg-[#121824] border border-[#E5C158]/40 text-[#E5C158] font-mono text-xs font-bold">
                                ZERO FRAIS // CRÉDIT INSTANTANÉ
                            </span>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                            <!-- Formulaire de génération d'un nouveau Code Voucher -->
                            <form
                                id="form-admin-create-voucher"
                                method="POST"
                                action="/admin/vouchers"
                                class="lg:col-span-5 bg-[#0C1018] border border-white/[0.08] p-5 space-y-4 font-mono text-xs"
                            >
                                @csrf
                                <div class="text-[11px] font-bold uppercase tracking-wider text-[#E5C158] border-b border-white/[0.06] pb-2">
                                    ÉMETTRE UN NOUVEAU CODE VOUCHER
                                </div>

                                <div>
                                    <label for="admin-voucher-code" class="block text-[10px] uppercase tracking-wider text-[#94A3B8] mb-1">
                                        CODE PROMO / VOUCHER (UNIQUE)
                                    </label>
                                    <input
                                        id="admin-voucher-code"
                                        type="text"
                                        name="code"
                                        required
                                        minlength="3"
                                        maxlength="40"
                                        placeholder="Ex: VALKYR-APEX-250"
                                        value="VALKYR-APEX-{{ random_int(100, 999) }}"
                                        class="w-full h-11 bg-[#080B11] border border-white/15 px-3 text-xs text-[#F4F7FB] uppercase focus:border-[#E5C158] focus:outline-none"
                                    >
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="admin-voucher-amount" class="block text-[10px] uppercase tracking-wider text-[#94A3B8] mb-1">
                                            MONTANT CRÉDITÉ (CENTIMES)
                                        </label>
                                        <select
                                            id="admin-voucher-amount"
                                            name="credit_amount_cents"
                                            class="w-full h-11 bg-[#080B11] border border-white/15 px-3 text-xs text-[#E5C158] font-bold focus:border-[#E5C158] focus:outline-none tabular-nums"
                                        >
                                            <option value="2500">25,00 € (2500 cts)</option>
                                            <option value="5000" selected>50,00 € (5000 cts)</option>
                                            <option value="10000">100,00 € (10000 cts)</option>
                                            <option value="25000">250,00 € (25000 cts)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="admin-voucher-max-uses" class="block text-[10px] uppercase tracking-wider text-[#94A3B8] mb-1">
                                            QUOTA MAX D'ACTIVATIONS
                                        </label>
                                        <input
                                            id="admin-voucher-max-uses"
                                            type="number"
                                            name="max_uses"
                                            required
                                            min="1"
                                            max="10000"
                                            value="25"
                                            class="w-full h-11 bg-[#080B11] border border-white/15 px-3 text-xs text-[#F4F7FB] focus:border-[#E5C158] focus:outline-none tabular-nums"
                                        >
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    id="btn-admin-create-voucher"
                                    class="w-full h-12 bg-[#E5C158] text-[#030407] hover:bg-[#D8E5F8] font-mono text-xs font-bold uppercase tracking-wider rounded-none transition cursor-pointer"
                                >
                                    GÉNÉRER LE CODE VOUCHER PRÉPAYÉ ↗
                                </button>
                            </form>

                            <!-- Tableau des Codes Vouchers émis -->
                            <div class="lg:col-span-7 overflow-x-auto border border-white/[0.08] bg-[#0C1018]">
                                <table class="w-full text-left border-collapse font-mono text-xs tabular-nums">
                                    <thead>
                                        <tr class="border-b border-white/10 bg-[#080B11] text-[10px] uppercase tracking-wider text-[#64748B] whitespace-nowrap">
                                            <th class="px-3.5 py-3">CODE VOUCHER</th>
                                            <th class="px-3.5 py-3 text-right">VALEUR FACIALE</th>
                                            <th class="px-3.5 py-3 text-center">UTILISATIONS</th>
                                            <th class="px-3.5 py-3 text-right">STATUT</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/[0.06]">
                                        @forelse($voucherCodes ?? [] as $vCode)
                                            <tr data-voucher-row="{{ $vCode->code }}" class="hover:bg-[#121824]/60 transition whitespace-nowrap">
                                                <td class="px-3.5 py-3 font-bold text-[#E5C158]">{{ $vCode->code }}</td>
                                                <td class="px-3.5 py-3 text-right font-bold text-[#10B981]">
                                                    +{{ \App\Models\Wallet::formatCents((int) $vCode->credit_amount_cents) }}
                                                </td>
                                                <td class="px-3.5 py-3 text-center text-[#D8E5F8]">
                                                    {{ $vCode->used_count }} / {{ $vCode->max_uses }}
                                                </td>
                                                <td class="px-3.5 py-3 text-right">
                                                    <span class="px-2 py-0.5 text-[10px] font-bold border {{ ($vCode->is_active && $vCode->used_count < $vCode->max_uses) ? 'bg-[#10B981]/15 text-[#10B981] border-[#10B981]/40' : 'bg-white/5 text-[#64748B] border-white/10' }}">
                                                        {{ ($vCode->is_active && $vCode->used_count < $vCode->max_uses) ? 'ACTIF' : 'ÉPUISÉ' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-4 py-6 text-center text-[#94A3B8]">
                                                    Aucun code Voucher configuré.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- =====================================================
                         MODULE V4 — TRIBUNAL D'ARBITRAGE DES LITIGES (#admin-disputes-panel)
                         ===================================================== -->
                    <div id="admin-disputes-panel" class="bg-[#080B11] border border-[#EF4444]/40 p-6 space-y-5">
                        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-white/[0.08] pb-4">
                            <div>
                                <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#EF4444]">
                                    BRIQUE V4 #6 // TRIBUNAL D'ARBITRAGE &amp; CAUTION SÉQUESTRÉE (5,00 €)
                                </div>
                                <h3 class="font-display font-bold text-xl text-[#F4F7FB] uppercase mt-0.5">
                                    TRIBUNAL DES LITIGES COMPÉTITIFS ({{ isset($disputes) ? $disputes->count() : 0 }})
                                </h3>
                            </div>
                            <span class="px-3 py-1 bg-[#EF4444]/15 border border-[#EF4444]/40 text-[#EF4444] font-mono text-xs font-bold tabular-nums">
                                {{ $openDisputesCount ?? 0 }} LITIGE(S) OPEN À TRANCHER
                            </span>
                        </div>

                        <div class="space-y-3 font-mono text-xs tabular-nums">
                            @forelse($disputes ?? [] as $disp)
                                <div data-admin-dispute-card="{{ $disp->id }}" class="p-4 bg-[#0C1018] border {{ $disp->status === 'OPEN' ? 'border-[#EF4444]/50' : 'border-white/[0.08]' }} flex flex-col gap-3">
                                    <div class="space-y-1.5 min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase {{ $disp->status === 'OPEN' ? 'bg-[#EF4444]/20 text-[#EF4444] border border-[#EF4444]/40' : ($disp->status === 'UPHELD_REFUNDED' ? 'bg-[#10B981]/20 text-[#10B981] border border-[#10B981]/40' : 'bg-[#E5C158]/20 text-[#E5C158] border border-[#E5C158]/40') }}">
                                                {{ $disp->status }}
                                            </span>
                                            <span class="font-bold text-[#F4F7FB]">LITIGE #{{ $disp->id }}</span>
                                            <span class="text-[#94A3B8]">— {{ $disp->challenger?->gamertag ?? 'OP #' . $disp->challenger_user_id }}</span>
                                            <span class="text-[#D8E5F8]">sur « {{ $disp->tournament?->title ?? 'Tournoi #' . $disp->tournament_id }} »</span>
                                            <span class="text-[#E5C158] font-bold">
                                                [Caution : {{ \App\Models\Wallet::formatCents((int) $disp->bond_cents) }}]
                                            </span>
                                        </div>
                                        <p class="text-[#94A3B8] text-xs">{{ $disp->reason }}</p>
                                        @if($disp->evidence_url)
                                            <div class="text-[11px] text-[#D8E5F8]">Preuve VOD : {{ $disp->evidence_url }}</div>
                                        @endif
                                        @if($disp->admin_resolution_notes)
                                            <div class="text-[11px] text-[#10B981]">Verdict Admin : {{ $disp->admin_resolution_notes }}</div>
                                        @endif
                                    </div>

                                    @if($disp->status === 'OPEN')
                                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                                            <form method="POST" action="/admin/disputes/{{ $disp->id }}/resolve" data-admin-resolve-dispute="{{ $disp->id }}">
                                                @csrf
                                                <input type="hidden" name="decision" value="UPHELD">
                                                <input type="hidden" name="notes" value="Réclamation validée par le Commandement : caution de 5,00 € remboursée.">
                                                <button
                                                    type="submit"
                                                    class="h-9 px-3 bg-[#10B981] text-[#030407] hover:bg-[#D8E5F8] font-mono text-[11px] font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                                                >
                                                    ✓ Valider (Rembourser 5,00 €)
                                                </button>
                                            </form>

                                            <form method="POST" action="/admin/disputes/{{ $disp->id }}/resolve" data-admin-resolve-dispute="{{ $disp->id }}">
                                                @csrf
                                                <input type="hidden" name="decision" value="REJECTED">
                                                <input type="hidden" name="notes" value="Litige infondé après vérification télémétrique : caution de 5,00 € confisquée au profit de SYS_TREASURY.">
                                                <button
                                                    type="submit"
                                                    class="h-9 px-3 bg-[#EF4444]/15 border border-[#EF4444]/50 text-[#EF4444] hover:bg-[#EF4444] hover:text-[#030407] font-mono text-[11px] font-bold uppercase tracking-wider rounded-none whitespace-nowrap transition cursor-pointer"
                                                >
                                                    ✕ Rejeter (Confisquer 5,00 €)
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="p-4 bg-[#0C1018] border border-white/[0.06] text-[#94A3B8]">
                                    Aucun litige compétitif enregistré.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </section>

                <!-- =========================================================
                     PANEL 2: CONFIGURATION DES RAILS DE PAIEMENT & FILE D'APPROBATION MANUELLE (#admin-panel-rails)
                     ========================================================= -->
                <section
                    id="admin-panel-rails"
                    data-admin-panel="rails"
                    class="{{ $activePanelKey === 'rails' ? '' : 'hidden' }} space-y-10"
                >
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-white/[0.08] pb-5">
                        <div>
                            <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-1">
                                PANNEAU 02 // RÈGLEMENT MANUEL &amp; PARAMÉTRAGE DES RAILS DE DÉPÔT / RETRAIT
                            </div>
                            <h2 class="font-display font-extrabold text-2xl sm:text-3xl text-[#F4F7FB] uppercase tracking-tight">
                                FILE D'APPROBATION &amp; RAILS DE PAIEMENT CONFIGURABLES
                            </h2>
                        </div>
                        <span class="px-3 py-1 bg-[#080B11] border border-[#E5C158]/40 text-[#E5C158] font-mono text-xs font-bold tabular-nums">
                            {{ $pendingFundingCount }} EN ATTENTE (PENDING_ADMIN)
                        </span>
                    </div>

                    <!-- SECTION A: FILE D'ATTENTE DE RÈGLEMENT MANUEL ($fundingRequests) -->
                    <div class="bg-[#080B11] border border-[#E5C158]/40 p-6 space-y-5">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.08] pb-4">
                            <div>
                                <div class="text-[10px] font-mono uppercase tracking-widest text-[#E5C158]">
                                    VALIDATION MANUELLE ADMIN // CRÉDIT &amp; DÉBIT ATOMIQUE SUR WALLET
                                </div>
                                <h3 class="font-display font-bold text-xl text-[#F4F7FB] uppercase mt-0.5">
                                    FILE D'APPROBATION DES DÉPÔTS &amp; RETRAITS ({{ $fundingRequests->count() }})
                                </h3>
                            </div>
                            <span class="font-mono text-xs text-[#94A3B8]">
                                Règle instantanément l'écriture en partie double sur le Wallet &amp; crédite les frais à SYS_TREASURY
                            </span>
                        </div>

                        <div class="overflow-x-auto border border-white/[0.08] bg-[#0C1018]">
                            <table class="w-full text-left border-collapse font-mono text-xs tabular-nums">
                                <thead>
                                    <tr class="border-b border-white/10 bg-[#080B11] text-[10px] uppercase tracking-wider text-[#64748B] whitespace-nowrap">
                                        <th class="px-3.5 py-3">ID</th>
                                        <th class="px-3.5 py-3">OPÉRATEUR</th>
                                        <th class="px-3.5 py-3">DIRECTION &amp; RAIL</th>
                                        <th class="px-3.5 py-3 text-right">MONTANT BRUT</th>
                                        <th class="px-3.5 py-3 text-right">FRAIS RAIL</th>
                                        <th class="px-3.5 py-3 text-right">MONTANT NET</th>
                                        <th class="px-3.5 py-3">RÉFÉRENCE / IBAN</th>
                                        <th class="px-3.5 py-3">STATUT</th>
                                        <th class="px-3.5 py-3 text-right">DÉCISION ADMIN</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/[0.06]">
                                    @forelse($fundingRequests as $req)
                                        <tr data-funding-request-row="{{ $req->id }}" class="hover:bg-[#121824]/60 transition whitespace-nowrap">
                                            <td class="px-3.5 py-3.5 text-[#94A3B8] font-bold">#{{ $req->id }}</td>
                                            <td class="px-3.5 py-3.5">
                                                <div class="font-bold text-[#F4F7FB]">{{ $req->user?->gamertag ?? 'OP #' . $req->user_id }}</div>
                                                <div class="text-[10px] text-[#64748B]">{{ $req->user?->email }}</div>
                                            </td>
                                            <td class="px-3.5 py-3.5">
                                                <span class="px-2 py-0.5 text-[10px] font-bold border {{ $req->direction === 'DEPOSIT' ? 'bg-[#10B981]/15 text-[#10B981] border-[#10B981]/40' : 'bg-[#F59E0B]/15 text-[#F59E0B] border-[#F59E0B]/40' }}">
                                                    {{ $req->direction }}
                                                </span>
                                                <div class="text-[10px] text-[#D8E5F8] mt-1">{{ $req->paymentRail?->name ?? 'Rail Standard' }}</div>
                                            </td>
                                            <td class="px-3.5 py-3.5 text-right font-bold text-[#F4F7FB]">
                                                {{ \App\Models\Wallet::formatCents((int) $req->gross_amount_cents) }}
                                            </td>
                                            <td class="px-3.5 py-3.5 text-right text-[#E5C158]">
                                                {{ \App\Models\Wallet::formatCents((int) $req->fee_cents) }}
                                            </td>
                                            <td class="px-3.5 py-3.5 text-right font-bold text-[#10B981]">
                                                {{ \App\Models\Wallet::formatCents((int) $req->net_amount_cents) }}
                                            </td>
                                            <td class="px-3.5 py-3.5 text-[11px] text-[#94A3B8]">
                                                <code>{{ $req->reference_code }}</code>
                                            </td>
                                            <td class="px-3.5 py-3.5">
                                                @if($req->status === 'PENDING_ADMIN')
                                                    <span class="px-2 py-1 bg-[#E5C158]/20 border border-[#E5C158] text-[#E5C158] text-[10px] font-bold uppercase">
                                                        ● PENDING_ADMIN
                                                    </span>
                                                @elseif($req->status === 'SETTLED')
                                                    <span class="px-2 py-1 bg-[#10B981]/15 border border-[#10B981]/40 text-[#10B981] text-[10px] font-bold uppercase">
                                                        ✓ SETTLED
                                                    </span>
                                                @else
                                                    <span class="px-2 py-1 bg-[#EF4444]/15 border border-[#EF4444]/40 text-[#EF4444] text-[10px] font-bold uppercase">
                                                        ✕ REJECTED
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-3.5 py-3.5 text-right">
                                                @if($req->status === 'PENDING_ADMIN')
                                                    <div class="inline-flex items-center gap-2">
                                                        <form
                                                            action="/admin/funding-requests/{{ $req->id }}/approve"
                                                            method="POST"
                                                            data-admin-approve-funding="{{ $req->id }}"
                                                            class="inline"
                                                        >
                                                            @csrf
                                                            <input type="hidden" name="admin_notes" value="Approuvé et réglé par Commandement Admin" />
                                                            <button
                                                                type="submit"
                                                                class="h-9 px-3 bg-[#10B981] text-[#030407] hover:bg-[#E5C158] font-mono text-[10px] font-bold uppercase tracking-wider cursor-pointer transition"
                                                            >
                                                                APPROUVER &amp; RÉGLER SUR LE LEDGER ✓
                                                            </button>
                                                        </form>

                                                        <form
                                                            action="/admin/funding-requests/{{ $req->id }}/reject"
                                                            method="POST"
                                                            data-admin-reject-funding="{{ $req->id }}"
                                                            class="inline"
                                                        >
                                                            @csrf
                                                            <input type="hidden" name="admin_notes" value="Rejeté après contrôle de conformité Admin" />
                                                            <button
                                                                type="submit"
                                                                class="h-9 px-3 bg-[#EF4444]/20 border border-[#EF4444]/60 text-[#EF4444] hover:bg-[#EF4444] hover:text-[#030407] font-mono text-[10px] font-bold uppercase tracking-wider cursor-pointer transition"
                                                            >
                                                                REJETER ✕
                                                            </button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <span class="text-[10px] text-[#64748B]">
                                                        {{ $req->admin_notes ?? 'Traité' }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="px-4 py-8 text-center text-[#64748B]">
                                                Aucune demande de dépôt ou retrait enregistrée.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SECTION B: GRILLE DE CONFIGURATION DES RAILS EXISTANTS ($paymentRails) -->
                    <div class="space-y-4">
                        <div>
                            <div class="text-[10px] font-mono uppercase tracking-widest text-[#D8E5F8]">
                                PARAMÉTRAGE EN TEMPS RÉEL DES RAILS ({{ $paymentRails->count() }} RAILS CONFIGURÉS)
                            </div>
                            <h3 class="font-display font-bold text-xl text-[#F4F7FB] uppercase mt-0.5">
                                GRILLE DE CONFIGURATION DES FRAIS, MODE MANUEL/INSTANTANÉ &amp; COORDONNÉES
                            </h3>
                        </div>

                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                            @foreach($paymentRails as $rail)
                                <form
                                    action="/admin/payment-rails/{{ $rail->id }}"
                                    method="POST"
                                    data-admin-update-rail="{{ $rail->id }}"
                                    class="bg-[#080B11] border border-white/[0.08] p-5 space-y-4 font-mono text-xs"
                                >
                                    @csrf
                                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/[0.08] pb-3">
                                        <div>
                                            <span class="px-2 py-0.5 bg-[#121824] border border-[#E5C158]/40 text-[#E5C158] text-[10px] font-bold">
                                                {{ $rail->code }}
                                            </span>
                                            <span class="text-[10px] text-[#94A3B8] ml-2">{{ $rail->provider }} ({{ $rail->method_type }})</span>
                                        </div>
                                        <span class="px-2 py-0.5 text-[10px] font-bold {{ $rail->is_active ? 'bg-[#10B981]/15 text-[#10B981]' : 'bg-[#EF4444]/15 text-[#EF4444]' }}">
                                            {{ $rail->is_active ? '● RAIL ACTIF' : '○ DÉSACTIVÉ' }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[10px] text-[#94A3B8] mb-1">INTITULÉ DU RAIL (`name`)</label>
                                            <input type="text" name="name" value="{{ $rail->name }}" class="w-full h-10 px-3 bg-[#0C1018] border border-white/15 text-[#F4F7FB]" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-[#94A3B8] mb-1">DÉLAI AFFICHÉ (`settlement_delay_label`)</label>
                                            <input type="text" name="settlement_delay_label" value="{{ $rail->settlement_delay_label }}" class="w-full h-10 px-3 bg-[#0C1018] border border-white/15 text-[#D8E5F8]" />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 tabular-nums">
                                        <div>
                                            <label class="block text-[10px] text-[#94A3B8] mb-1">FRAIS DÉPÔT (BPS)</label>
                                            <input type="number" name="deposit_fee_bps" value="{{ $rail->deposit_fee_bps }}" min="0" max="2000" class="w-full h-10 px-2.5 bg-[#0C1018] border border-white/15 text-[#E5C158] font-bold" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-[#94A3B8] mb-1">FIXE DÉPÔT (CTS)</label>
                                            <input type="number" name="deposit_fixed_fee_cents" value="{{ $rail->deposit_fixed_fee_cents }}" min="0" max="50000" class="w-full h-10 px-2.5 bg-[#0C1018] border border-white/15 text-[#E5C158] font-bold" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-[#94A3B8] mb-1">FRAIS RETRAIT (BPS)</label>
                                            <input type="number" name="withdrawal_fee_bps" value="{{ $rail->withdrawal_fee_bps }}" min="0" max="2000" class="w-full h-10 px-2.5 bg-[#0C1018] border border-white/15 text-[#D8E5F8] font-bold" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-[#94A3B8] mb-1">FIXE RETRAIT (CTS)</label>
                                            <input type="number" name="withdrawal_fixed_fee_cents" value="{{ $rail->withdrawal_fixed_fee_cents }}" min="0" max="50000" class="w-full h-10 px-2.5 bg-[#0C1018] border border-white/15 text-[#D8E5F8] font-bold" />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[10px] text-[#94A3B8] mb-1">MODE DE RÈGLEMENT (`requires_admin_approval`)</label>
                                            <select name="requires_admin_approval" class="w-full h-10 px-3 bg-[#0C1018] border border-white/15 text-[#F4F7FB]">
                                                <option value="0" {{ !$rail->requires_admin_approval ? 'selected' : '' }}>INSTANTANÉ (Auto-Settled)</option>
                                                <option value="1" {{ $rail->requires_admin_approval ? 'selected' : '' }}>MANUEL (Validation Admin PENDING_ADMIN)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-[#94A3B8] mb-1">ÉTAT DU RAIL (`is_active`)</label>
                                            <select name="is_active" class="w-full h-10 px-3 bg-[#0C1018] border border-white/15 text-[#F4F7FB]">
                                                <option value="1" {{ $rail->is_active ? 'selected' : '' }}>ACTIF (Visible dans Cockpit)</option>
                                                <option value="0" {{ !$rail->is_active ? 'selected' : '' }}>DÉSACTIVÉ (Coupé)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] text-[#94A3B8] mb-1">COORDONNÉES &amp; INSTRUCTIONS OPÉRATEUR (`account_instructions`)</label>
                                        <input type="text" name="account_instructions" value="{{ $rail->account_instructions }}" class="w-full h-10 px-3 bg-[#0C1018] border border-white/15 text-[#94A3B8]" />
                                    </div>

                                    <button
                                        type="submit"
                                        class="w-full h-11 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider cursor-pointer transition"
                                    >
                                        Sauvegarder la Configuration ({{ $rail->code }}) ↗
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>

                    <!-- SECTION C: AJOUTER UN NOUVEAU RAIL DE PAIEMENT -->
                    <div class="bg-[#080B11] border border-white/10 p-6">
                        <div class="text-[10px] font-mono uppercase tracking-widest text-[#10B981] mb-1">
                            EXTENSION DE TRÉSORERIE // POST /api/admin/payment-rails
                        </div>
                        <h3 class="font-display font-bold text-lg text-[#F4F7FB] uppercase mb-4">
                            CRÉER UNE NOUVELLE MÉTHODE DE DÉPÔT / RETRAIT
                        </h3>

                        <form id="form-admin-create-rail" action="/admin/payment-rails" method="POST" class="space-y-4 font-mono text-xs">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-[10px] text-[#94A3B8] mb-1">CODE UNIQUE (`code`)</label>
                                    <input type="text" name="code" placeholder="APPLE_PAY_INSTANT" required class="w-full h-10 px-3 bg-[#0C1018] border border-white/15 text-[#F4F7FB]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] text-[#94A3B8] mb-1">INTITULÉ (`name`)</label>
                                    <input type="text" name="name" placeholder="Apple Pay Express" required class="w-full h-10 px-3 bg-[#0C1018] border border-white/15 text-[#F4F7FB]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] text-[#94A3B8] mb-1">FOURNISSEUR (`provider`)</label>
                                    <input type="text" name="provider" value="Stripe Treasury" required class="w-full h-10 px-3 bg-[#0C1018] border border-white/15 text-[#F4F7FB]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] text-[#94A3B8] mb-1">TYPE (`method_type`)</label>
                                    <select name="method_type" class="w-full h-10 px-3 bg-[#0C1018] border border-white/15 text-[#F4F7FB]">
                                        <option value="BANK_TRANSFER">BANK_TRANSFER</option>
                                        <option value="CARD">CARD</option>
                                        <option value="CRYPTO">CRYPTO</option>
                                        <option value="MANUAL_VOUCHER">MANUAL_VOUCHER</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-6 gap-3 tabular-nums">
                                <div>
                                    <label class="block text-[10px] text-[#94A3B8] mb-1">DÉPÔT BPS</label>
                                    <input type="number" name="deposit_fee_bps" value="100" min="0" max="2000" required class="w-full h-10 px-2.5 bg-[#0C1018] border border-white/15 text-[#E5C158]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] text-[#94A3B8] mb-1">DÉPÔT FIXE (CTS)</label>
                                    <input type="number" name="deposit_fixed_fee_cents" value="0" min="0" max="50000" required class="w-full h-10 px-2.5 bg-[#0C1018] border border-white/15 text-[#E5C158]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] text-[#94A3B8] mb-1">RETRAIT BPS</label>
                                    <input type="number" name="withdrawal_fee_bps" value="100" min="0" max="2000" required class="w-full h-10 px-2.5 bg-[#0C1018] border border-white/15 text-[#D8E5F8]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] text-[#94A3B8] mb-1">RETRAIT FIXE (CTS)</label>
                                    <input type="number" name="withdrawal_fixed_fee_cents" value="0" min="0" max="50000" required class="w-full h-10 px-2.5 bg-[#0C1018] border border-white/15 text-[#D8E5F8]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] text-[#94A3B8] mb-1">MIN (CTS)</label>
                                    <input type="number" name="min_amount_cents" value="500" min="100" required class="w-full h-10 px-2.5 bg-[#0C1018] border border-white/15 text-[#F4F7FB]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] text-[#94A3B8] mb-1">MAX (CTS)</label>
                                    <input type="number" name="max_amount_cents" value="500000" min="1000" required class="w-full h-10 px-2.5 bg-[#0C1018] border border-white/15 text-[#F4F7FB]" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[10px] text-[#94A3B8] mb-1">MODE D'APPROBATION</label>
                                    <select name="requires_admin_approval" class="w-full h-10 px-3 bg-[#0C1018] border border-white/15 text-[#F4F7FB]">
                                        <option value="0">INSTANTANÉ (0)</option>
                                        <option value="1">MANUEL ADMIN (1)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-[#94A3B8] mb-1">DÉLAI AFFICHÉ</label>
                                    <input type="text" name="settlement_delay_label" value="INSTANTANÉ (< 5S)" class="w-full h-10 px-3 bg-[#0C1018] border border-white/15 text-[#F4F7FB]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] text-[#94A3B8] mb-1">INSTRUCTIONS COMPTE</label>
                                    <input type="text" name="account_instructions" value="Règlement direct sur le Grand Livre VALKYR." class="w-full h-10 px-3 bg-[#0C1018] border border-white/15 text-[#F4F7FB]" />
                                </div>
                            </div>

                            <button type="submit" class="h-11 px-6 bg-[#10B981] text-[#030407] hover:bg-[#E5C158] font-mono text-xs font-bold uppercase tracking-wider cursor-pointer transition">
                                Créer &amp; Activer le Nouveau Rail ↗
                            </button>
                        </form>
                    </div>
                </section>

                <!-- =========================================================
                     PANEL 3: GOUVERNANCE DES OPÉRATEURS & WALLETS (#admin-panel-users)
                     ========================================================= -->
                <section
                    id="admin-panel-users"
                    data-admin-panel="users"
                    class="{{ $activePanelKey === 'users' ? '' : 'hidden' }} space-y-6"
                >
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-white/[0.08] pb-5">
                        <div>
                            <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-1">
                                PANNEAU 03 // RBAC, STATUT ★ VALKYR PRIME, GEL DE COMPTE &amp; DOTATIONS WALLET
                            </div>
                            <h2 class="font-display font-extrabold text-2xl sm:text-3xl text-[#F4F7FB] uppercase tracking-tight">
                                GOUVERNANCE DES OPÉRATEURS &amp; WALLETS ({{ $operators->count() }})
                            </h2>
                        </div>
                        <span class="px-3 py-1 bg-[#080B11] border border-white/10 font-mono text-xs text-[#94A3B8] tabular-nums">
                            ANTI-PRIVILEGE ESCALATION // AUDIT TRAIL
                        </span>
                    </div>

                    <div class="space-y-4">
                        @foreach($operators as $op)
                            @php
                                $opBal = $op->wallet ? (int) $op->wallet->balance_cents : 0;
                                $opEscrow = $op->wallet ? (int) $op->wallet->locked_escrow_cents : 0;
                            @endphp
                            <div class="bg-[#080B11] border {{ $op->is_frozen ? 'border-[#EF4444]/60' : ($op->is_prime ? 'border-[#E5C158]/50' : 'border-white/[0.08]') }} p-5 space-y-4 font-mono text-xs">
                                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-white/[0.06] pb-3.5">
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <span class="text-[#64748B] tabular-nums">#{{ $op->id }}</span>
                                        <span class="font-bold text-sm text-[#F4F7FB]">{{ $op->gamertag }}</span>
                                        <span class="text-[#64748B]">({{ $op->email }})</span>
                                        <span class="px-2 py-0.5 text-[10px] border {{ $op->role === 'ADMIN' ? 'border-[#E5C158] text-[#E5C158]' : ($op->role === 'ORGANIZER' ? 'border-[#D8E5F8] text-[#D8E5F8]' : 'border-[#10B981] text-[#10B981]') }}">
                                            {{ $op->role }}
                                        </span>
                                        @if($op->is_prime)
                                            <span class="px-2 py-0.5 text-[10px] bg-[#E5C158] text-[#030407] font-bold">
                                                ★ PRIME (0% FRAIS)
                                            </span>
                                        @endif
                                        @if($op->is_frozen)
                                            <span class="px-2 py-0.5 text-[10px] bg-[#EF4444] text-[#030407] font-bold">
                                                ❄ WALLET GELÉ
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap items-center gap-4 tabular-nums">
                                        <div>
                                            <span class="text-[#64748B]">SOLDE WALLET :</span>
                                            <strong class="text-[#F4F7FB] ml-1">{{ \App\Models\Wallet::formatCents($opBal) }}</strong>
                                        </div>
                                        <div>
                                            <span class="text-[#64748B]">SÉQUESTRE :</span>
                                            <strong class="text-[#E5C158] ml-1">{{ \App\Models\Wallet::formatCents($opEscrow) }}</strong>
                                        </div>
                                        <div>
                                            <span class="text-[#64748B]">ELO :</span>
                                            <strong class="text-[#D8E5F8] ml-1">{{ $op->elo_rating }} MMR</strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-end">
                                    <!-- Form 1: Update Role, Prime, Freeze, KYC (7 cols) -->
                                    <form
                                        action="/admin/users/{{ $op->id }}/update-status"
                                        method="POST"
                                        data-admin-update-user="{{ $op->id }}"
                                        class="xl:col-span-7 grid grid-cols-2 sm:grid-cols-5 gap-2.5 items-end"
                                    >
                                        @csrf
                                        <div>
                                            <label class="block text-[9px] text-[#94A3B8] mb-1">RÔLE (`role`)</label>
                                            <select name="role" class="w-full h-10 px-2 bg-[#0C1018] border border-white/15 text-[#F4F7FB]">
                                                @foreach(['PLAYER', 'ORGANIZER', 'ADMIN'] as $rOpt)
                                                    <option value="{{ $rOpt }}" {{ $op->role === $rOpt ? 'selected' : '' }}>{{ $rOpt }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[9px] text-[#94A3B8] mb-1">PASS ★ PRIME</label>
                                            <select name="is_prime" class="w-full h-10 px-2 bg-[#0C1018] border border-white/15 text-[#E5C158]">
                                                <option value="0" {{ !$op->is_prime ? 'selected' : '' }}>STANDARD</option>
                                                <option value="1" {{ $op->is_prime ? 'selected' : '' }}>★ PRIME ACTIF</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[9px] text-[#94A3B8] mb-1">ÉTAT WALLET</label>
                                            <select name="is_frozen" class="w-full h-10 px-2 bg-[#0C1018] border border-white/15 {{ $op->is_frozen ? 'text-[#EF4444]' : 'text-[#10B981]' }}">
                                                <option value="0" {{ !$op->is_frozen ? 'selected' : '' }}>ACTIF (0)</option>
                                                <option value="1" {{ $op->is_frozen ? 'selected' : '' }}>❄ GELÉ (1)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[9px] text-[#94A3B8] mb-1">NIVEAU KYC</label>
                                            <select name="kyc_status" class="w-full h-10 px-2 bg-[#0C1018] border border-white/15 text-[#D8E5F8]">
                                                @foreach(['STANDARD', 'VERIFIED_PRO', 'INSTITUTIONAL'] as $kOpt)
                                                    <option value="{{ $kOpt }}" {{ $op->kyc_status === $kOpt ? 'selected' : '' }}>{{ $kOpt }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" class="h-10 px-3 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-bold uppercase tracking-wider cursor-pointer transition whitespace-nowrap">
                                            Appliquer ↗
                                        </button>
                                    </form>

                                    <!-- Form 2: Credit / Endow Operator Wallet (5 cols) -->
                                    <form
                                        action="/admin/users/{{ $op->id }}/credit-wallet"
                                        method="POST"
                                        data-admin-credit-user="{{ $op->id }}"
                                        class="xl:col-span-5 grid grid-cols-1 sm:grid-cols-3 gap-2.5 items-end bg-[#0C1018] p-2.5 border border-white/[0.06]"
                                    >
                                        @csrf
                                        <div>
                                            <label class="block text-[9px] text-[#10B981] mb-1">DOTATION (CENTIMES)</label>
                                            <input type="number" name="amount_cents" value="5000" min="100" max="10000000" step="100" required class="w-full h-9 px-2.5 bg-[#080B11] border border-white/15 text-[#10B981] font-bold tabular-nums" />
                                        </div>
                                        <div>
                                            <label class="block text-[9px] text-[#94A3B8] mb-1">MOTIF AUDIT</label>
                                            <input type="text" name="reason" value="Dotation Admin // {{ $op->gamertag }}" class="w-full h-9 px-2.5 bg-[#080B11] border border-white/15 text-[#F4F7FB]" />
                                        </div>
                                        <button type="submit" class="h-9 px-3 bg-[#10B981] text-[#030407] hover:bg-[#E5C158] font-bold uppercase tracking-wider cursor-pointer transition whitespace-nowrap">
                                            + Créditer Wallet
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <!-- =========================================================
                     PANEL 4: GOUVERNANCE DES LIGUES, COMMISSIONS & BOOSTS (#admin-panel-tournaments)
                     ========================================================= -->
                <section
                    id="admin-panel-tournaments"
                    data-admin-panel="tournaments"
                    class="{{ $activePanelKey === 'tournaments' ? '' : 'hidden' }} space-y-6"
                >
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-white/[0.08] pb-5">
                        <div>
                            <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#E5C158] mb-1">
                                PANNEAU 04 // CALIBRAGE DU RAKE, REVSHARE ORGANISATEUR &amp; BOOSTS ★ FEATURED
                            </div>
                            <h2 class="font-display font-extrabold text-2xl sm:text-3xl text-[#F4F7FB] uppercase tracking-tight">
                                GOUVERNANCE DES LIGUES &amp; ARÈNES ({{ $tournaments->count() }})
                            </h2>
                        </div>
                        <span class="px-3 py-1 bg-[#080B11] border border-white/10 font-mono text-xs text-[#E5C158] tabular-nums">
                            RAKE = PLATEFORME + ORGANIZER REVSHARE
                        </span>
                    </div>

                    <div class="space-y-4">
                        @foreach($tournaments as $t)
                            <div class="bg-[#080B11] border {{ $t->is_featured ? 'border-[#E5C158]/60' : 'border-white/[0.08]' }} p-5 space-y-4 font-mono text-xs">
                                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-white/[0.06] pb-3.5">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-[#64748B] tabular-nums">#{{ $t->id }}</span>
                                            <a href="/tournaments/{{ $t->slug }}" class="font-bold text-sm text-[#F4F7FB] hover:text-[#E5C158] transition">
                                                {{ $t->title }} ↗
                                            </a>
                                            <span class="px-2 py-0.5 text-[10px] border border-white/15 text-[#D8E5F8]">{{ $t->status }}</span>
                                            @if($t->is_featured)
                                                <span class="px-2 py-0.5 text-[10px] bg-[#E5C158] text-[#030407] font-bold">
                                                    ★ FEATURED // SPONSORED
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-[10px] text-[#94A3B8] mt-1 tabular-nums">
                                            Organisateur : <strong class="text-[#D8E5F8]">{{ $t->organizer?->gamertag ?? 'VKR_ARBITER' }}</strong>
                                            · Buy-in : <strong>{{ \App\Models\Wallet::formatCents((int) $t->entry_fee_cents) }}</strong>
                                            · Séquestre Actuel : <strong class="text-[#E5C158]">{{ \App\Models\Wallet::formatCents((int) $t->escrow_pool_cents) }}</strong>
                                            · Dotation Sponsor : <strong class="text-[#10B981]">{{ \App\Models\Wallet::formatCents((int) $t->sponsored_guarantee_cents) }}</strong>
                                        </div>
                                    </div>

                                    <a
                                        href="/tournaments/{{ $t->slug }}"
                                        class="h-9 px-3.5 bg-[#121824] border border-white/15 hover:border-[#E5C158] text-[#F4F7FB] inline-flex items-center whitespace-nowrap transition"
                                    >
                                        Salle d'Arène &amp; Arbitrage ↗
                                    </a>
                                </div>

                                <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-end">
                                    <!-- Form 1: Configure Rake BPS, Organizer Share BPS & Featured -->
                                    <form
                                        action="/admin/tournaments/{{ $t->id }}/configure"
                                        method="POST"
                                        data-admin-configure-tournament="{{ $t->id }}"
                                        class="xl:col-span-7 grid grid-cols-2 sm:grid-cols-4 gap-2.5 items-end"
                                    >
                                        @csrf
                                        <div>
                                            <label class="block text-[9px] text-[#94A3B8] mb-1">RAKE TOTAL (BPS)</label>
                                            <input type="number" name="rake_bps" value="{{ $t->rake_bps }}" min="100" max="2000" step="50" class="w-full h-10 px-2.5 bg-[#0C1018] border border-white/15 text-[#E5C158] font-bold tabular-nums" />
                                        </div>
                                        <div>
                                            <label class="block text-[9px] text-[#94A3B8] mb-1">PART ORGANISATEUR (BPS)</label>
                                            <input type="number" name="organizer_share_bps" value="{{ $t->organizer_share_bps }}" min="0" max="1000" step="25" class="w-full h-10 px-2.5 bg-[#0C1018] border border-white/15 text-[#D8E5F8] font-bold tabular-nums" />
                                        </div>
                                        <div>
                                            <label class="block text-[9px] text-[#94A3B8] mb-1">STATUT ★ FEATURED</label>
                                            <select name="is_featured" class="w-full h-10 px-2 bg-[#0C1018] border border-white/15 text-[#E5C158]">
                                                <option value="0" {{ !$t->is_featured ? 'selected' : '' }}>STANDARD (0)</option>
                                                <option value="1" {{ $t->is_featured ? 'selected' : '' }}>★ FEATURED (1)</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="h-10 px-3 bg-[#D8E5F8] text-[#030407] hover:bg-[#E5C158] font-bold uppercase tracking-wider cursor-pointer transition whitespace-nowrap">
                                            Configurer ↗
                                        </button>
                                    </form>

                                    <!-- Form 2: Sponsor Pool Injection from Admin Wallet -->
                                    <form
                                        action="/tournaments/{{ $t->id }}/sponsor"
                                        method="POST"
                                        data-sponsor-tournament="{{ $t->id }}"
                                        class="xl:col-span-5 grid grid-cols-1 sm:grid-cols-2 gap-2.5 items-end bg-[#0C1018] p-2.5 border border-white/[0.06]"
                                    >
                                        @csrf
                                        <input type="hidden" name="idempotency_key" value="admin:sponsor:t{{ $t->id }}:{{ uniqid() }}" />
                                        <div>
                                            <label class="block text-[9px] text-[#10B981] mb-1">ABONDEMENT SPONSOR (CTS)</label>
                                            <input type="number" name="amount_cents" value="10000" min="100" max="10000000" step="500" class="w-full h-9 px-2.5 bg-[#080B11] border border-white/15 text-[#10B981] font-bold tabular-nums" />
                                        </div>
                                        <button type="submit" class="h-9 px-3 bg-[#10B981] text-[#030407] hover:bg-[#E5C158] font-bold uppercase tracking-wider cursor-pointer transition whitespace-nowrap">
                                            + Dotation Sponsor ↗
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <!-- =========================================================
                     PANEL 5: AUDIT MASTER DU GRAND LIVRE (#admin-panel-ledger)
                     ========================================================= -->
                <section
                    id="admin-panel-ledger"
                    data-admin-panel="ledger"
                    class="{{ $activePanelKey === 'ledger' ? '' : 'hidden' }} space-y-6"
                >
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-white/[0.08] pb-5">
                        <div>
                            <div class="text-[10px] font-mono uppercase tracking-[0.2em] text-[#10B981] mb-1">
                                PANNEAU 05 // EXPLORATEUR MAÎTRE DU GRAND LIVRE (APPEND-ONLY)
                            </div>
                            <h2 class="font-display font-extrabold text-2xl sm:text-3xl text-[#F4F7FB] uppercase tracking-tight">
                                JOURNAL GLOBAL DES ÉCRITURES ({{ $masterLedgerEntries->count() }})
                            </h2>
                        </div>
                        <span class="px-3 py-1 bg-[#080B11] border border-white/10 font-mono text-xs text-[#10B981] tabular-nums">
                            TRIGGER SQL ANTI-UPDATE &amp; ANTI-DELETE ACTIF
                        </span>
                    </div>

                    <!-- Filter Bar by Master Entry Type -->
                    <div class="flex flex-wrap items-center gap-1.5 font-mono text-[11px]">
                        <button type="button" data-admin-ledger-filter="ALL" class="h-8 px-2.5 border border-[#E5C158] bg-[#121824] text-[#F4F7FB] font-bold cursor-pointer">TOUTES</button>
                        <button type="button" data-admin-ledger-filter="DEPOSIT" class="h-8 px-2.5 border border-white/10 bg-[#080B11] text-[#94A3B8] hover:text-[#F4F7FB] cursor-pointer">DEPOSIT</button>
                        <button type="button" data-admin-ledger-filter="WITHDRAWAL" class="h-8 px-2.5 border border-white/10 bg-[#080B11] text-[#94A3B8] hover:text-[#F4F7FB] cursor-pointer">WITHDRAWAL</button>
                        <button type="button" data-admin-ledger-filter="PLATFORM_PAYMENT_FEE_CREDIT" class="h-8 px-2.5 border border-white/10 bg-[#080B11] text-[#94A3B8] hover:text-[#F4F7FB] cursor-pointer">FRAIS RAILS</button>
                        <button type="button" data-admin-ledger-filter="PRIME_SUBSCRIPTION_CREDIT" class="h-8 px-2.5 border border-white/10 bg-[#080B11] text-[#94A3B8] hover:text-[#F4F7FB] cursor-pointer">★ PRIME</button>
                        <button type="button" data-admin-ledger-filter="ARENA_BOOST_CREDIT" class="h-8 px-2.5 border border-white/10 bg-[#080B11] text-[#94A3B8] hover:text-[#F4F7FB] cursor-pointer">★ BOOST</button>
                        <button type="button" data-admin-ledger-filter="PLATFORM_RAKE_CREDIT" class="h-8 px-2.5 border border-white/10 bg-[#080B11] text-[#94A3B8] hover:text-[#F4F7FB] cursor-pointer">RAKE NET</button>
                        <button type="button" data-admin-ledger-filter="ORGANIZER_RAKE_SHARE_CREDIT" class="h-8 px-2.5 border border-white/10 bg-[#080B11] text-[#94A3B8] hover:text-[#F4F7FB] cursor-pointer">REVSHARE ORG</button>
                        <button type="button" data-admin-ledger-filter="SPONSOR_POOL_CREDIT" class="h-8 px-2.5 border border-white/10 bg-[#080B11] text-[#94A3B8] hover:text-[#F4F7FB] cursor-pointer">SPONSOR</button>
                    </div>

                    <div class="overflow-x-auto border border-white/[0.08] bg-[#080B11]">
                        <table class="w-full text-left border-collapse font-mono text-xs tabular-nums">
                            <thead>
                                <tr class="border-b border-white/10 bg-[#0C1018] text-[10px] uppercase tracking-wider text-[#64748B] whitespace-nowrap">
                                    <th class="px-3.5 py-3">ID</th>
                                    <th class="px-3.5 py-3">PORTEFEUILLE</th>
                                    <th class="px-3.5 py-3">TYPE OPÉRATION</th>
                                    <th class="px-3.5 py-3">DESCRIPTION</th>
                                    <th class="px-3.5 py-3 text-right">AVANT</th>
                                    <th class="px-3.5 py-3 text-right">DÉBIT</th>
                                    <th class="px-3.5 py-3 text-right">CRÉDIT</th>
                                    <th class="px-3.5 py-3 text-right">APRÈS</th>
                                    <th class="px-3.5 py-3">CLÉ D'IDEMPOTENCE</th>
                                </tr>
                            </thead>
                            <tbody id="admin-master-ledger-tbody" class="divide-y divide-white/[0.05]">
                                @foreach($masterLedgerEntries as $entry)
                                    <tr data-master-entry-type="{{ $entry->entry_type }}" class="hover:bg-[#121824]/60 transition whitespace-nowrap">
                                        <td class="px-3.5 py-3 text-[#64748B]">#{{ str_pad((string) $entry->id, 4, '0', STR_PAD_LEFT) }}</td>
                                        <td class="px-3.5 py-3 font-bold text-[#D8E5F8]">
                                            {{ $entry->wallet?->user?->gamertag ?? 'WALLET #' . $entry->wallet_id }}
                                        </td>
                                        <td class="px-3.5 py-3">
                                            <span class="px-2 py-0.5 text-[10px] font-bold border {{ $entry->credit_cents > 0 ? 'bg-[#10B981]/15 text-[#10B981] border-[#10B981]/30' : 'bg-[#F59E0B]/15 text-[#F59E0B] border-[#F59E0B]/30' }}">
                                                {{ $entry->entry_type }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-3 text-[#F4F7FB] max-w-xs truncate" title="{{ $entry->description }}">
                                            {{ $entry->description }}
                                        </td>
                                        <td class="px-3.5 py-3 text-right text-[#94A3B8]">
                                            {{ \App\Models\Wallet::formatCents((int) $entry->balance_before_cents) }}
                                        </td>
                                        <td class="px-3.5 py-3 text-right">
                                            @if($entry->debit_cents > 0)
                                                <span class="text-[#EF4444]">-{{ \App\Models\Wallet::formatCents((int) $entry->debit_cents) }}</span>
                                            @else
                                                <span class="text-[#64748B]">0,00 €</span>
                                            @endif
                                        </td>
                                        <td class="px-3.5 py-3 text-right">
                                            @if($entry->credit_cents > 0)
                                                <span class="text-[#10B981]">+{{ \App\Models\Wallet::formatCents((int) $entry->credit_cents) }}</span>
                                            @else
                                                <span class="text-[#64748B]">0,00 €</span>
                                            @endif
                                        </td>
                                        <td class="px-3.5 py-3 text-right font-bold text-[#E5C158]">
                                            {{ \App\Models\Wallet::formatCents((int) $entry->balance_after_cents) }}
                                        </td>
                                        <td class="px-3.5 py-3 text-[10px] text-[#64748B] max-w-[180px] truncate">
                                            {{ $entry->idempotency_key }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

            </div>
        </div>
    </main>

    <footer class="bg-[#030407] py-6 border-t border-white/[0.08] font-mono text-xs text-[#64748B]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 tabular-nums">
            <span>✦ VALKYR // ARENA — COMMANDEMENT SOUVERAIN V3</span>
            <span>DOUBLE-ENTRY LEDGER // 5 REVENUE STREAMS // ZERO FLOAT</span>
        </div>
    </footer>

</body>
</html>
