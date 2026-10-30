/**
 * VALKYR // ARENA — High-Frequency Tactical Front-End Controller
 * 60 FPS Canvas Telemetry HUD, Pinned Sticky 3-Chapter Scrollytelling,
 * Arsenal Loadout Configurator, Faceted Arena Catalog, and ACID Escrow Cockpit.
 */

(function () {
    'use strict';

    // =========================================================================
    // 1. STATE & INTEGER CENTS FORMATTER (ZERO FLOAT ARITHMETIC)
    // =========================================================================
    const initialState = window.__VALKYR_STATE__ || {
        tournaments: [],
        currentUser: null,
        wallet: null,
        ledgerEntries: [],
        leaderboard: [],
        platformStats: {
            total_escrow_locked_cents: 0,
            total_prizes_distributed_cents: 0,
            total_platform_rake_cents: 0,
            active_arenas_count: 0,
            settled_arenas_count: 0,
            rake_bps: 800,
        },
    };

    const state = {
        lang: 'fr',
        tournaments: Array.isArray(initialState.tournaments) ? initialState.tournaments : [],
        currentUser: initialState.currentUser || null,
        wallet: initialState.wallet || null,
        ledgerEntries: Array.isArray(initialState.ledgerEntries) ? initialState.ledgerEntries : [],
        leaderboard: Array.isArray(initialState.leaderboard) ? initialState.leaderboard : [],
        platformStats: initialState.platformStats || {},
        activeClass: (initialState.currentUser && initialState.currentUser.preferred_class) || 'VALKYRIE',
        activePov: 'VKR_NYX',
        replayPlaying: true,
        replaySpeed: 1,
        replayProgress: 64,
        scrollChapter: 0,
        activeBracketTournamentId: null,
        filters: {
            game: 'ALL',
            status: 'ALL',
            format: 'ALL',
            maxBuyInCents: 'ALL',
            minPrizeCents: 'ALL',
            search: '',
        },
    };

    /**
     * Format integer minor units (cents) into EUR currency string without floating-point drift.
     * Example: 150000 -> "1 500,00 €" (FR) or "€1,500.00" (EN)
     */
    function formatCents(centsInput) {
        const cents = Number.isFinite(Number(centsInput)) ? Math.trunc(Number(centsInput)) : 0;
        const sign = cents < 0 ? '-' : '';
        const abs = Math.abs(cents);
        const euros = Math.floor(abs / 100);
        const rem = String(abs % 100).padStart(2, '0');

        if (state.lang === 'en') {
            const euroFormatted = euros.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return `${sign}€${euroFormatted}.${rem}`;
        }
        const euroFormatted = euros.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        return `${sign}${euroFormatted},${rem} €`;
    }

    function generateIdempotencyKey(prefix) {
        const rand = Math.random().toString(36).substring(2, 10);
        return `${prefix}:${Date.now()}:${rand}`;
    }

    function escapeHtml(value) {
        const str = String(value ?? '');
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // =========================================================================
    // 2. TOAST NOTIFICATION ENGINE
    // =========================================================================
    function showToast(message, type = 'success', detail = '') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        const borderColor =
            type === 'error'
                ? 'border-[#EF4444]'
                : type === 'warning'
                  ? 'border-[#F59E0B]'
                  : 'border-[#10B981]';
        const badgeColor =
            type === 'error'
                ? 'text-[#EF4444] bg-[#EF4444]/10'
                : type === 'warning'
                  ? 'text-[#F59E0B] bg-[#F59E0B]/10'
                  : 'text-[#10B981] bg-[#10B981]/10';
        const badgeLabel = type === 'error' ? 'ERREUR // REJET' : type === 'warning' ? 'ALERTE // ESCROW' : 'ACID // CONFIRMÉ';

        toast.className = `pointer-events-auto w-full max-w-md bg-[#0C1018] border border-white/10 border-l-2 ${borderColor} p-4 shadow-2xl transition-all duration-300`;
        toast.innerHTML = `
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-widest ${badgeColor}">${escapeHtml(badgeLabel)}</span>
                        <span class="text-[11px] font-mono text-[#64748B] tabular-nums">${new Date().toLocaleTimeString()}</span>
                    </div>
                    <p class="text-sm font-medium text-[#F4F7FB]">${escapeHtml(message)}</p>
                    ${detail ? `<p class="mt-1 text-xs font-mono text-[#94A3B8] tabular-nums break-all">${escapeHtml(detail)}</p>` : ''}
                </div>
                <button type="button" class="text-[#64748B] hover:text-[#F4F7FB] text-xs font-mono px-2 py-1" aria-label="Close">✕</button>
            </div>
        `;

        const closeBtn = toast.querySelector('button');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => toast.remove());
        }

        container.appendChild(toast);
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 5500);
    }

    // =========================================================================
    // 3. BILINGUAL DICTIONARY (FR / EN) — ZERO MIXED LANGUAGE
    // =========================================================================
    const i18n = {
        fr: {
            'nav.arenas': 'Arènes',
            'nav.replay': 'Live Replay',
            'nav.arsenal': 'Arsenal',
            'nav.leaderboard': 'Classement',
            'nav.login': 'Connexion',
            'nav.create_league': 'Créer une Ligue ↗',
            'hero.kicker': "PROTOCOLE D'ESCORTE COMPÉTITIF // SÉQUESTRE ATOMIQUE 0.08",
            'hero.line1': 'SOUVERAINETÉ TACTIQUE.',
            'hero.line2': "L'Arène du Séquestre Absolu.",
            'hero.subtitle': 'Ligues privées et tournois haute fréquence. Vos enjeux verrouillés sur grand livre immuable, réglés à la milliseconde.',
            'hero.cta_primary': "Rejoindre l'Arène ↗",
            'hero.cta_secondary': 'Voir un Match Live ▶',
            'replay.section_kicker': 'MODULE 01 // TÉLÉMÉTRIE DE COMBAT & CAPTURE 128-TICK',
            'replay.section_title': 'LIVE MATCH REPLAY & HUD TACTIQUE 60 FPS',
            'replay.open_modal': 'Ouvrir le Replay Tactique 4K ⤢',
            'scrolly.kicker': 'MODULE 02 // ARCHITECTURE FINANCIÈRE EN PARTIE DOUBLE',
            'scrolly.title': 'PROTOCOLE DE SÉQUESTRE ATOMIQUE',
            'scrolly.ch1_title': '01. Verrouillage Séquestre Atomique',
            'scrolly.ch1_desc': "Verrou pessimiste SELECT ... FOR UPDATE acquis par ordre d'ID croissant strict (ORDER BY id ASC). Zéro condition de course TOCTOU, zéro deadlock concurrent.",
            'scrolly.ch2_title': '02. Télémétrie Anti-Triche Temps Réel',
            'scrolly.ch2_desc': "Validation serveur 128-tick et empreinte déterministe du match. Les Policies Anti-IDOR et Anti-BFLA bloquent toute usurpation d'organisateur ou d'accès portefeuille croisé.",
            'scrolly.ch3_title': '03. Distribution Instantanée du Cashprize',
            'scrolly.ch3_desc': 'Prélèvement mathématique entier de 800 bps (8,00 %) via intdiv et distribution atomique 60 % / 30 % / 10 % au centime près sur le grand livre immuable.',
            'arsenal.kicker': 'MODULE 03 // DOTATION OPÉRATEUR & MÉTA COMPÉTITIVE',
            'arsenal.title': "SÉLECTEUR D'ARSENAL & CLASSES TACTIQUES",
            'arsenal.lock_btn': 'Verrouiller ce Loadout Tactique ↗',
            'catalog.kicker': 'MODULE 04 // LIGUES PRIVÉES & TOURNOIS HAUTE FRÉQUENCE',
            'catalog.title': 'CATALOGUE DES ARÈNES & SÉQUESTRES ACTIFS',
            'cockpit.kicker': 'MODULE 05 // AUTHENTIFICATION & COCKPIT FINANCIER',
            'cockpit.title': 'COCKPIT OPÉRATEUR, ARBRE DE TOURNOI & GRAND LIVRE',
            'leaderboard.kicker': 'HIÉRARCHIE SOUVERAINE // CLASSEMENT ELO & GAINS VÉRIFIÉS',
            'leaderboard.title': 'CLASSEMENT GÉNÉRAL DES OPÉRATEURS',
        },
        en: {
            'nav.arenas': 'Arenas',
            'nav.replay': 'Live Replay',
            'nav.arsenal': 'Arsenal',
            'nav.leaderboard': 'Leaderboard',
            'nav.login': 'Sign in',
            'nav.create_league': 'Create League ↗',
            'hero.kicker': 'COMPETITIVE ESCORT PROTOCOL // ATOMIC ESCROW 0.08',
            'hero.line1': 'TACTICAL SOVEREIGNTY.',
            'hero.line2': 'The Absolute Escrow Arena.',
            'hero.subtitle': 'High-frequency private leagues and tournaments. Your stakes locked on an immutable ledger, settled to the millisecond.',
            'hero.cta_primary': 'Enter the Arena ↗',
            'hero.cta_secondary': 'Watch Live Match ▶',
            'replay.section_kicker': 'MODULE 01 // COMBAT TELEMETRY & 128-TICK CAPTURE',
            'replay.section_title': 'LIVE MATCH REPLAY & 60 FPS TACTICAL HUD',
            'replay.open_modal': 'Open 4K Tactical Replay ⤢',
            'scrolly.kicker': 'MODULE 02 // DOUBLE-ENTRY FINANCIAL ARCHITECTURE',
            'scrolly.title': 'ATOMIC ESCROW PROTOCOL',
            'scrolly.ch1_title': '01. Atomic Escrow Lock',
            'scrolly.ch1_desc': 'Pessimistic SELECT ... FOR UPDATE lock acquired in strict ascending ID order (ORDER BY id ASC). Zero TOCTOU race conditions, zero concurrent deadlocks.',
            'scrolly.ch2_title': '02. Real-Time Anti-Cheat Telemetry',
            'scrolly.ch2_desc': '128-tick server validation and deterministic match hash. Anti-IDOR and Anti-BFLA Policies block unauthorized organizer settlement or cross-wallet inspection.',
            'scrolly.ch3_title': '03. Instant Cashprize Settlement',
            'scrolly.ch3_desc': 'Exact integer 800 bps (8.00%) platform rake via intdiv and atomic 60% / 30% / 10% distribution down to the exact cent on the immutable ledger.',
            'arsenal.kicker': 'MODULE 03 // OPERATOR LOADOUT & COMPETITIVE META',
            'arsenal.title': 'ARSENAL SELECTOR & TACTICAL CLASSES',
            'arsenal.lock_btn': 'Lock Tactical Loadout ↗',
            'catalog.kicker': 'MODULE 04 // PRIVATE LEAGUES & HIGH-FREQUENCY TOURNAMENTS',
            'catalog.title': 'ARENA CATALOG & ACTIVE ESCROW POOLS',
            'cockpit.kicker': 'MODULE 05 // AUTHENTICATION & FINANCIAL COCKPIT',
            'cockpit.title': 'OPERATOR COCKPIT, TOURNAMENT BRACKET & LEDGER',
            'leaderboard.kicker': 'SOVEREIGN HIERARCHY // ELO RANKING & VERIFIED WINNINGS',
            'leaderboard.title': 'GLOBAL OPERATOR LEADERBOARD',
        },
    };

    function applyLanguage(lang) {
        state.lang = lang === 'en' ? 'en' : 'fr';
        document.documentElement.lang = state.lang;

        const dict = i18n[state.lang];
        document.querySelectorAll('[data-i18n]').forEach((el) => {
            const key = el.getAttribute('data-i18n');
            if (key && dict[key]) {
                el.textContent = dict[key];
            }
        });

        document.querySelectorAll('[data-lang-btn]').forEach((btn) => {
            const btnLang = btn.getAttribute('data-lang-btn');
            if (btnLang === state.lang) {
                btn.classList.add('text-[#E5C158]', 'font-bold');
                btn.classList.remove('text-[#64748B]');
            } else {
                btn.classList.remove('text-[#E5C158]', 'font-bold');
                btn.classList.add('text-[#64748B]');
            }
        });

        renderTournamentsCatalog();
        renderCockpitState();
    }

    // =========================================================================
    // 4. HEADER SCROLL & MOBILE DRAWER
    // =========================================================================
    function initHeader() {
        const header = document.getElementById('main-header');
        if (!header) return;

        function updateHeaderOnScroll() {
            if (window.scrollY <= 10) {
                header.style.backgroundColor = 'rgba(0, 0, 0, 0)';
                header.style.backdropFilter = 'none';
                header.style.webkitBackdropFilter = 'none';
            } else {
                header.style.backgroundColor = 'rgba(3, 4, 7, 0.85)';
                header.style.backdropFilter = 'blur(16px)';
                header.style.webkitBackdropFilter = 'blur(16px)';
            }
        }

        updateHeaderOnScroll();
        window.addEventListener('scroll', updateHeaderOnScroll, { passive: true });

        const mobileBtn = document.getElementById('mobile-menu-btn');
        const mobileDrawer = document.getElementById('mobile-nav-drawer');
        if (mobileBtn && mobileDrawer) {
            mobileBtn.addEventListener('click', () => {
                const isHidden = mobileDrawer.classList.contains('hidden');
                if (isHidden) {
                    mobileDrawer.classList.remove('hidden');
                } else {
                    mobileDrawer.classList.add('hidden');
                }
            });

            mobileDrawer.querySelectorAll('a, button').forEach((link) => {
                link.addEventListener('click', () => {
                    mobileDrawer.classList.add('hidden');
                });
            });
        }

        document.querySelectorAll('[data-lang-btn]').forEach((btn) => {
            btn.addEventListener('click', () => {
                applyLanguage(btn.getAttribute('data-lang-btn'));
            });
        });
    }

    // =========================================================================
    // 5. MODULE 1: 60 FPS TACTICAL HUD CANVAS & FULLSCREEN 4K REPLAY MODAL
    // =========================================================================
    const povProfiles = {
        VKR_NYX: {
            gamertag: 'VKR_NYX',
            archetype: 'VALKYRIE // SOVEREIGN-PRIME',
            weapon: 'LANCE PLASMA VP-1',
            hp: 100,
            shield: 125,
            elo: 2365,
            kda: '18 / 4 / 9',
            hs: '68.4%',
            ping: '6 ms',
            color: '#E5C158',
            targetX: 0.61,
            targetY: 0.44,
        },
        KESTREL_ZERO: {
            gamertag: 'KESTREL_ZERO',
            archetype: 'SPECTRE // KESTREL-V',
            weapon: 'COIL SNIPER KV-9',
            hp: 88,
            shield: 75,
            elo: 2300,
            kda: '15 / 6 / 7',
            hs: '81.2%',
            ping: '8 ms',
            color: '#10B981',
            targetX: 0.74,
            targetY: 0.56,
        },
        AETHER_9: {
            gamertag: 'AETHER_9',
            archetype: 'CHRONO // ORION-IX',
            weapon: 'IMPULSION ORION-4',
            hp: 94,
            shield: 100,
            elo: 2235,
            kda: '12 / 5 / 14',
            hs: '59.0%',
            ping: '7 ms',
            color: '#D8E5F8',
            targetX: 0.49,
            targetY: 0.48,
        },
        SOVEREIGN_X: {
            gamertag: 'SOVEREIGN_X',
            archetype: 'VANGUARD // AEGIS-01',
            weapon: 'RAIL-CANNON M88',
            hp: 100,
            shield: 150,
            elo: 2195,
            kda: '11 / 7 / 16',
            hs: '52.5%',
            ping: '9 ms',
            color: '#F59E0B',
            targetX: 0.46,
            targetY: 0.52,
        },
    };

    function drawTacticalOverlay(canvas, ctx, frameTick, isModal) {
        const w = canvas.width;
        const h = canvas.height;
        ctx.clearRect(0, 0, w, h);

        const pov = povProfiles[state.activePov] || povProfiles.VKR_NYX;
        const speedMult = state.replayPlaying ? state.replaySpeed : 0;
        const t = frameTick * 0.025 * (speedMult || 0.001);

        // 1. Tactical Peripheral Grid Lines
        ctx.strokeStyle = 'rgba(216, 229, 248, 0.09)';
        ctx.lineWidth = 1;
        const gridStep = isModal ? 96 : 72;
        for (let x = gridStep; x < w; x += gridStep) {
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, 14);
            ctx.moveTo(x, h - 14);
            ctx.lineTo(x, h);
            ctx.stroke();
        }

        // 2. Radar Sweep in Top-Left
        const radarX = isModal ? 92 : 74;
        const radarY = isModal ? 92 : 74;
        const radarR = isModal ? 56 : 44;

        ctx.save();
        ctx.fillStyle = 'rgba(3, 4, 7, 0.78)';
        ctx.beginPath();
        ctx.arc(radarX, radarY, radarR, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = 'rgba(216, 229, 248, 0.28)';
        ctx.lineWidth = 1;
        ctx.stroke();

        ctx.beginPath();
        ctx.arc(radarX, radarY, radarR * 0.55, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.1)';
        ctx.stroke();

        // Radar Sweep Cone
        const sweepAngle = (frameTick * 0.035) % (Math.PI * 2);
        const grad = ctx.createRadialGradient(radarX, radarY, 2, radarX, radarY, radarR);
        grad.addColorStop(0, 'rgba(16, 185, 129, 0.38)');
        grad.addColorStop(1, 'rgba(16, 185, 129, 0.0)');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.moveTo(radarX, radarY);
        ctx.arc(radarX, radarY, radarR, sweepAngle - 0.55, sweepAngle);
        ctx.closePath();
        ctx.fill();

        // Radar Blips (Allies & Hostiles)
        const radarBlips = [
            { dx: -14, dy: 10, color: '#10B981' },
            { dx: -4, dy: 18, color: '#E5C158' },
            { dx: 12, dy: -11, color: '#EF4444' },
            { dx: 22, dy: -6, color: '#EF4444' },
        ];
        radarBlips.forEach((b, idx) => {
            const bx = radarX + b.dx + Math.sin(t + idx) * 3;
            const by = radarY + b.dy + Math.cos(t + idx) * 3;
            ctx.fillStyle = b.color;
            ctx.beginPath();
            ctx.arc(bx, by, 3, 0, Math.PI * 2);
            ctx.fill();
        });
        ctx.restore();

        // 3. Squad Trajectory Vectors & Operator Lock Boxes
        const cx = w * pov.targetX + Math.sin(t * 0.9) * 18;
        const cy = h * pov.targetY + Math.cos(t * 0.7) * 10;

        // Trajectory line from breach point
        ctx.save();
        ctx.setLineDash([6, 4]);
        ctx.strokeStyle = pov.color;
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.moveTo(w * 0.28, h * 0.78);
        ctx.lineTo(cx, cy);
        ctx.lineTo(w * 0.82, h * 0.36);
        ctx.stroke();
        ctx.restore();

        // Active POV Target Reticle
        const boxSize = isModal ? 68 : 52;
        const half = boxSize / 2;
        const corner = 12;

        ctx.strokeStyle = pov.color;
        ctx.lineWidth = 1.75;

        // Top-left corner
        ctx.beginPath();
        ctx.moveTo(cx - half, cy - half + corner);
        ctx.lineTo(cx - half, cy - half);
        ctx.lineTo(cx - half + corner, cy - half);
        // Top-right corner
        ctx.moveTo(cx + half - corner, cy - half);
        ctx.lineTo(cx + half, cy - half);
        ctx.lineTo(cx + half, cy - half + corner);
        // Bottom-right corner
        ctx.moveTo(cx + half, cy + half - corner);
        ctx.lineTo(cx + half, cy + half);
        ctx.lineTo(cx + half - corner, cy + half);
        // Bottom-left corner
        ctx.moveTo(cx - half + corner, cy + half);
        ctx.lineTo(cx - half, cy + half);
        ctx.lineTo(cx - half, cy + half - corner);
        ctx.stroke();

        // Center precision dot
        ctx.fillStyle = pov.color;
        ctx.beginPath();
        ctx.arc(cx, cy, 2.5, 0, Math.PI * 2);
        ctx.fill();

        // Telemetry tag next to reticle
        ctx.fillStyle = 'rgba(3, 4, 7, 0.85)';
        ctx.fillRect(cx + half + 8, cy - half, 172, 42);
        ctx.strokeStyle = pov.color;
        ctx.lineWidth = 1;
        ctx.strokeRect(cx + half + 8, cy - half, 172, 42);

        ctx.fillStyle = '#F4F7FB';
        ctx.font = '600 11px "JetBrains Mono", monospace';
        ctx.fillText(`POV // ${pov.gamertag}`, cx + half + 15, cy - half + 16);
        ctx.fillStyle = pov.color;
        ctx.font = '500 10px "JetBrains Mono", monospace';
        const dist = (18.4 + Math.sin(t) * 1.2).toFixed(1);
        ctx.fillText(`DIST: ${dist}m | HS: ${pov.hs}`, cx + half + 15, cy - half + 32);

        // Secondary Hostile Marker
        const hx = w * 0.82 + Math.cos(t * 1.1) * 12;
        const hy = h * 0.36 + Math.sin(t * 1.3) * 8;
        ctx.strokeStyle = '#EF4444';
        ctx.lineWidth = 1.25;
        ctx.strokeRect(hx - 18, hy - 18, 36, 36);
        ctx.fillStyle = '#EF4444';
        ctx.font = '600 10px "JetBrains Mono", monospace';
        ctx.fillText('HOSTILE // LOCKED', hx - 32, hy - 24);
    }

    function updatePovTelemetryDom() {
        const pov = povProfiles[state.activePov] || povProfiles.VKR_NYX;
        document.querySelectorAll('[data-pov-select]').forEach((btn) => {
            const key = btn.getAttribute('data-pov-select');
            if (key === state.activePov) {
                btn.classList.add('border-[#E5C158]', 'bg-[#121824]', 'text-[#F4F7FB]');
                btn.classList.remove('border-white/[0.08]', 'bg-[#080B11]', 'text-[#94A3B8]');
            } else {
                btn.classList.remove('border-[#E5C158]', 'bg-[#121824]', 'text-[#F4F7FB]');
                btn.classList.add('border-white/[0.08]', 'bg-[#080B11]', 'text-[#94A3B8]');
            }
        });

        const setElText = (id, text) => {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        };

        setElText('hud-active-operator', pov.gamertag);
        setElText('hud-active-archetype', pov.archetype);
        setElText('hud-active-weapon', pov.weapon);
        setElText('hud-active-hp', `${pov.hp} HP`);
        setElText('hud-active-shield', `${pov.shield} AP`);
        setElText('hud-active-kda', pov.kda);
        setElText('hud-active-ping', pov.ping);

        setElText('modal-pov-operator', pov.gamertag);
        setElText('modal-pov-weapon', pov.weapon);
        setElText('modal-pov-kda', pov.kda);
        setElText('modal-pov-elo', `${pov.elo} MMR`);
    }

    function initTacticalReplayHud() {
        const mainCanvas = document.getElementById('tactical-hud-canvas');
        const modalCanvas = document.getElementById('modal-replay-canvas');
        if (!mainCanvas) return;

        const mainCtx = mainCanvas.getContext('2d');
        const modalCtx = modalCanvas ? modalCanvas.getContext('2d') : null;

        let isMainVisible = true;
        let frameTick = 0;

        function resizeCanvases() {
            const rect = mainCanvas.parentElement.getBoundingClientRect();
            mainCanvas.width = Math.max(640, Math.floor(rect.width));
            mainCanvas.height = Math.max(360, Math.floor(rect.height));

            if (modalCanvas && modalCanvas.parentElement) {
                const mRect = modalCanvas.parentElement.getBoundingClientRect();
                modalCanvas.width = Math.max(640, Math.floor(mRect.width || 960));
                modalCanvas.height = Math.max(360, Math.floor(mRect.height || 500));
            }
        }

        resizeCanvases();
        window.addEventListener('resize', resizeCanvases, { passive: true });

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        isMainVisible = entry.isIntersecting;
                    });
                },
                { threshold: 0.05 }
            );
            observer.observe(mainCanvas);
        }

        // Bind POV Selector Buttons
        document.querySelectorAll('[data-pov-select]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const targetPov = btn.getAttribute('data-pov-select');
                if (targetPov && povProfiles[targetPov]) {
                    state.activePov = targetPov;
                    updatePovTelemetryDom();
                }
            });
        });

        // Bind Fullscreen Replay Modal Controls
        const replayModal = document.getElementById('replay-fullscreen-modal');
        const openBtn = document.getElementById('open-replay-modal-btn');
        const playPauseBtn = document.getElementById('replay-play-pause-btn');
        const timelineSlider = document.getElementById('replay-timeline-slider');
        const timecodeEls = document.querySelectorAll('[data-live-timecode]');

        if (openBtn && replayModal) {
            openBtn.addEventListener('click', () => {
                openModal('replay-fullscreen-modal');
                setTimeout(resizeCanvases, 30);
            });
        }

        if (playPauseBtn) {
            playPauseBtn.addEventListener('click', () => {
                state.replayPlaying = !state.replayPlaying;
                playPauseBtn.textContent = state.replayPlaying ? 'PAUSE ❚❚' : 'PLAY ▶';
            });
        }

        if (timelineSlider) {
            timelineSlider.addEventListener('input', (e) => {
                state.replayProgress = Number(e.target.value);
                const totalSeconds = Math.floor((state.replayProgress / 100) * 2538);
                const mins = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
                const secs = String(totalSeconds % 60).padStart(2, '0');
                timecodeEls.forEach((el) => {
                    el.textContent = `00:${mins}:${secs}`;
                });
            });
        }

        document.querySelectorAll('[data-replay-speed]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.replaySpeed = Number(btn.getAttribute('data-replay-speed')) || 1;
                document.querySelectorAll('[data-replay-speed]').forEach((b) => {
                    const active = Number(b.getAttribute('data-replay-speed')) === state.replaySpeed;
                    b.classList.toggle('bg-[#E5C158]', active);
                    b.classList.toggle('text-[#030407]', active);
                    b.classList.toggle('text-[#94A3B8]', !active);
                });
            });
        });

        updatePovTelemetryDom();

        function animateLoop() {
            if (state.replayPlaying) {
                frameTick += 1;
            }
            if (isMainVisible && mainCtx) {
                drawTacticalOverlay(mainCanvas, mainCtx, frameTick, false);
            }
            if (replayModal && !replayModal.classList.contains('hidden') && modalCtx) {
                drawTacticalOverlay(modalCanvas, modalCtx, frameTick, true);
            }
            requestAnimationFrame(animateLoop);
        }

        requestAnimationFrame(animateLoop);
    }

    // =========================================================================
    // 6. MODULE 2: PINNED STICKY 3-CHAPTER SCROLLYTELLING
    // =========================================================================
    function initScrollytelling() {
        const track = document.getElementById('scrollytelling-track');
        const coreCanvas = document.getElementById('escrow-core-canvas');
        if (!track || !coreCanvas) return;

        const ctx = coreCanvas.getContext('2d');
        let isVisible = true;
        let animTick = 0;

        const chapterMeta = [
            {
                code: 'CHAPITRE 01 // ACID LOCK',
                stateBadge: 'SELECT ... FOR UPDATE // ORDER BY ID ASC',
                metricLabel: 'ISOLATION TRANSACTIONNELLE',
                metricValue: '0 DEADLOCK // 100% ATOMIQUE',
                accent: '#D8E5F8',
            },
            {
                code: 'CHAPITRE 02 // 128-TICK TELEMETRY',
                stateBadge: 'SHA-256 MATCH VERIFICATION // ANTI-IDOR',
                metricLabel: 'INTÉGRITÉ DES POLITIQUES',
                metricValue: '128 HZ // ZERO BFLA',
                accent: '#10B981',
            },
            {
                code: 'CHAPITRE 03 // INSTANT SETTLEMENT',
                stateBadge: 'RAKE 800 BPS (8.00%) // SPLIT 60 / 30 / 10',
                metricLabel: 'INVARIANT DU GRAND LIVRE',
                metricValue: '∑ CRÉDITS - ∑ DÉBITS = SOLDE',
                accent: '#E5C158',
            },
        ];

        function setActiveChapter(chapterIndex) {
            const idx = Math.max(0, Math.min(2, chapterIndex));
            state.scrollChapter = idx;

            document.querySelectorAll('[data-chapter-card]').forEach((card) => {
                const cIdx = Number(card.getAttribute('data-chapter-card'));
                if (cIdx === idx) {
                    card.classList.add('border-l-2', 'border-l-[#E5C158]', 'bg-[#0C1018]', 'opacity-100');
                    card.classList.remove('bg-[#080B11]/60', 'opacity-55');
                } else {
                    card.classList.remove('border-l-2', 'border-l-[#E5C158]', 'bg-[#0C1018]', 'opacity-100');
                    card.classList.add('bg-[#080B11]/60', 'opacity-55');
                }
            });

            document.querySelectorAll('[data-chapter-pill]').forEach((pill) => {
                const pIdx = Number(pill.getAttribute('data-chapter-pill'));
                if (pIdx === idx) {
                    pill.classList.add('bg-[#E5C158]', 'text-[#030407]', 'border-[#E5C158]');
                    pill.classList.remove('bg-[#080B11]', 'text-[#94A3B8]', 'border-white/10');
                } else {
                    pill.classList.remove('bg-[#E5C158]', 'text-[#030407]', 'border-[#E5C158]');
                    pill.classList.add('bg-[#080B11]', 'text-[#94A3B8]', 'border-white/10');
                }
            });

            const meta = chapterMeta[idx];
            const codeEl = document.getElementById('core-stage-code');
            const badgeEl = document.getElementById('core-stage-badge');
            const metricLabelEl = document.getElementById('core-stage-metric-label');
            const metricValEl = document.getElementById('core-stage-metric-value');

            if (codeEl) codeEl.textContent = meta.code;
            if (badgeEl) badgeEl.textContent = meta.stateBadge;
            if (metricLabelEl) metricLabelEl.textContent = meta.metricLabel;
            if (metricValEl) metricValEl.textContent = meta.metricValue;
        }

        function onScroll() {
            const rect = track.getBoundingClientRect();
            const scrollable = Math.max(1, rect.height - window.innerHeight);
            const rawProgress = -rect.top / scrollable;
            const progress = Math.max(0, Math.min(1, rawProgress));

            const progressBar = document.getElementById('scrolly-progress-bar');
            const progressPct = document.getElementById('scrolly-progress-pct');
            if (progressBar) {
                progressBar.style.width = `${Math.round(progress * 100)}%`;
            }
            if (progressPct) {
                progressPct.textContent = `${String(Math.round(progress * 100)).padStart(3, '0')}%`;
            }

            if (rect.top <= window.innerHeight * 0.35 && rect.bottom >= window.innerHeight * 0.35) {
                const ch = progress < 0.34 ? 0 : progress < 0.68 ? 1 : 2;
                if (ch !== state.scrollChapter) {
                    setActiveChapter(ch);
                }
            }
        }

        window.addEventListener('scroll', onScroll, { passive: true });

        document.querySelectorAll('[data-chapter-card], [data-chapter-pill]').forEach((el) => {
            el.addEventListener('click', () => {
                const rawIdx =
                    el.getAttribute('data-chapter-card') ?? el.getAttribute('data-chapter-pill');
                const idx = Number(rawIdx);
                setActiveChapter(idx);
            });
        });

        if ('IntersectionObserver' in window) {
            const obs = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        isVisible = entry.isIntersecting;
                    });
                },
                { threshold: 0.05 }
            );
            obs.observe(coreCanvas);
        }

        function drawEscrowCore() {
            if (isVisible && ctx) {
                animTick += 1;
                const w = coreCanvas.width;
                const h = coreCanvas.height;
                const cx = w / 2;
                const cy = h / 2;
                ctx.clearRect(0, 0, w, h);

                const idx = state.scrollChapter;
                const accent = chapterMeta[idx].accent;
                const angle = animTick * 0.012;

                // Outer Orbital Telemetry Ring
                ctx.save();
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.1)';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.arc(cx, cy, 128, 0, Math.PI * 2);
                ctx.stroke();

                // Dashed Rotating Ring
                ctx.save();
                ctx.translate(cx, cy);
                ctx.rotate(angle);
                ctx.setLineDash([12, 8]);
                ctx.strokeStyle = accent;
                ctx.lineWidth = 1.75;
                ctx.beginPath();
                ctx.arc(0, 0, 102, 0, Math.PI * 2);
                ctx.stroke();
                ctx.restore();

                // Counter-Rotating Hexagonal Escrow Vault Core
                ctx.save();
                ctx.translate(cx, cy);
                ctx.rotate(-angle * 1.4);
                ctx.strokeStyle = idx === 2 ? '#E5C158' : '#D8E5F8';
                ctx.lineWidth = 2;
                ctx.beginPath();
                const hexR = 64;
                for (let i = 0; i < 6; i++) {
                    const a = (Math.PI / 3) * i;
                    const x = Math.cos(a) * hexR;
                    const y = Math.sin(a) * hexR;
                    if (i === 0) ctx.moveTo(x, y);
                    else ctx.lineTo(x, y);
                }
                ctx.closePath();
                ctx.stroke();
                ctx.restore();

                // Chapter-Specific Internal Geometry
                if (idx === 0) {
                    // Chapter 1: Dual Lock Vectors (Player Debit -> Escrow Credit)
                    ctx.strokeStyle = '#D8E5F8';
                    ctx.lineWidth = 1.5;
                    ctx.strokeRect(cx - 28, cy - 28, 56, 56);
                    ctx.fillStyle = '#E5C158';
                    ctx.font = '700 12px "JetBrains Mono", monospace';
                    ctx.textAlign = 'center';
                    ctx.fillText('LOCK // ACID', cx, cy + 4);
                } else if (idx === 1) {
                    // Chapter 2: 128-Tick Sine Telemetry Waveform
                    ctx.strokeStyle = '#10B981';
                    ctx.lineWidth = 2;
                    ctx.beginPath();
                    for (let x = -48; x <= 48; x += 4) {
                        const y = Math.sin((x + animTick * 3) * 0.12) * 18;
                        if (x === -48) ctx.moveTo(cx + x, cy + y);
                        else ctx.lineTo(cx + x, cy + y);
                    }
                    ctx.stroke();
                    ctx.fillStyle = '#10B981';
                    ctx.font = '700 11px "JetBrains Mono", monospace';
                    ctx.textAlign = 'center';
                    ctx.fillText('128-TICK OK', cx, cy + 36);
                } else {
                    // Chapter 3: 60 / 30 / 10 Podium Split + 800 bps Rake Rays
                    const vectors = [
                        { label: '1ST // 60%', angle: -Math.PI / 2, color: '#E5C158' },
                        { label: '2ND // 30%', angle: Math.PI / 6, color: '#D8E5F8' },
                        { label: '3RD // 10%', angle: (5 * Math.PI) / 6, color: '#10B981' },
                    ];
                    vectors.forEach((v) => {
                        const vx = cx + Math.cos(v.angle) * 112;
                        const vy = cy + Math.sin(v.angle) * 112;
                        ctx.strokeStyle = v.color;
                        ctx.lineWidth = 2;
                        ctx.beginPath();
                        ctx.moveTo(cx, cy);
                        ctx.lineTo(vx, vy);
                        ctx.stroke();

                        ctx.fillStyle = v.color;
                        ctx.beginPath();
                        ctx.arc(vx, vy, 5, 0, Math.PI * 2);
                        ctx.fill();
                    });
                    ctx.fillStyle = '#E5C158';
                    ctx.font = '700 12px "JetBrains Mono", monospace';
                    ctx.textAlign = 'center';
                    ctx.fillText('60 / 30 / 10', cx, cy + 4);
                }
                ctx.restore();
            }
            requestAnimationFrame(drawEscrowCore);
        }

        setActiveChapter(0);
        requestAnimationFrame(drawEscrowCore);
    }

    // =========================================================================
    // 7. MODULE 3: ARSENAL LOADOUT CONFIGURATOR (4 ARCHETYPES)
    // =========================================================================
    const classSpecs = {
        VANGUARD: {
            code: 'VANGUARD // AEGIS-01',
            name: 'VANGUARD',
            role: 'Briseur de Ligne & Rempart Cinétique',
            image: '/assets/class-vanguard.png',
            weapon: 'RAIL-CANNON M88 // LOURD',
            ability: 'DÔME HEXAGONAL AEGIS (1 400 HP)',
            description:
                "Armure balistique lourde en titane mat conçue pour verrouiller les goulets d'étranglement et absorber les salves orbitales adverses.",
            stats: { dps: 78, mobility: 62, armor: 98, velocity: 68 },
        },
        SPECTRE: {
            code: 'SPECTRE // KESTREL-V',
            name: 'SPECTRE',
            role: 'Reconnaissance Furtive & Élimination Longue Portée',
            image: '/assets/class-spectre.png',
            weapon: 'FUSIL COIL SUPPRIMÉ KV-9',
            ability: 'VOILE OPTIQUE THERMIQUE (8.5S)',
            description:
                "Opérateur furtif haute vélocité équipé d'une visière télémétrique émeraude et d'un fusil magnétique silencieux à pénétration d'armure.",
            stats: { dps: 96, mobility: 94, armor: 54, velocity: 97 },
        },
        CHRONO: {
            code: 'CHRONO // ORION-IX',
            name: 'CHRONO',
            role: 'Télémétrie Orbitale & Distorsion Temporelle',
            image: '/assets/class-chrono.png',
            weapon: 'CARABINE À IMPULSION ORION-4',
            ability: 'ANCRE DE RAPPEL CHRONO (128-TICK)',
            description:
                'Spécialiste cyber-tactique manipulant des anneaux gyroscopiques en or souverain pour prédire les rotations adverses à la milliseconde.',
            stats: { dps: 84, mobility: 88, armor: 72, velocity: 91 },
        },
        VALKYRIE: {
            code: 'VALKYRIE // SOVEREIGN-PRIME',
            name: 'VALKYRIE',
            role: "Commandement d'Assaut Aérien & Suprématie",
            image: '/assets/class-valkyrie.png',
            weapon: 'LANCE PLASMA SOUVERAINE VP-1',
            ability: 'FRAPPE ORBITALE VALKYR (RAYON 18M)',
            description:
                "Commandante d'assaut aérien dotée de propulseurs vectoriels en titane glacé et d'une armure cérémonielle aux accents d'or souverain.",
            stats: { dps: 94, mobility: 92, armor: 82, velocity: 95 },
        },
    };

    function selectLoadoutClass(classKey) {
        const spec = classSpecs[classKey] || classSpecs.VALKYRIE;
        state.activeClass = spec.name;

        document.querySelectorAll('[data-class-tab]').forEach((tab) => {
            const key = tab.getAttribute('data-class-tab');
            if (key === spec.name) {
                tab.classList.add('border-[#E5C158]', 'bg-[#121824]', 'text-[#F4F7FB]');
                tab.classList.remove('border-white/[0.08]', 'bg-[#080B11]', 'text-[#94A3B8]');
            } else {
                tab.classList.remove('border-[#E5C158]', 'bg-[#121824]', 'text-[#F4F7FB]');
                tab.classList.add('border-white/[0.08]', 'bg-[#080B11]', 'text-[#94A3B8]');
            }
        });

        const imgEl = document.getElementById('arsenal-class-image');
        if (imgEl) {
            imgEl.src = spec.image;
            imgEl.alt = spec.code;
        }

        const setEl = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };

        setEl('arsenal-class-code', spec.code);
        setEl('arsenal-class-role', spec.role);
        setEl('arsenal-class-weapon', spec.weapon);
        setEl('arsenal-class-ability', spec.ability);
        setEl('arsenal-class-desc', spec.description);

        setEl('stat-val-dps', `${spec.stats.dps} / 100`);
        setEl('stat-val-mobility', `${spec.stats.mobility} / 100`);
        setEl('stat-val-armor', `${spec.stats.armor} / 100`);
        setEl('stat-val-velocity', `${spec.stats.velocity} / 100`);

        const setBar = (id, pct) => {
            const bar = document.getElementById(id);
            if (bar) bar.style.width = `${pct}%`;
        };
        setBar('stat-bar-dps', spec.stats.dps);
        setBar('stat-bar-mobility', spec.stats.mobility);
        setBar('stat-bar-armor', spec.stats.armor);
        setBar('stat-bar-velocity', spec.stats.velocity);

        // Also sync registration modal loadout select if present
        const regLoadoutSelect = document.getElementById('register-loadout-select');
        if (regLoadoutSelect) {
            regLoadoutSelect.value = spec.name;
        }
    }

    function initArsenalSection() {
        document.querySelectorAll('[data-class-tab]').forEach((tab) => {
            tab.addEventListener('click', () => {
                selectLoadoutClass(tab.getAttribute('data-class-tab'));
            });
        });

        const lockBtn = document.getElementById('lock-loadout-btn');
        if (lockBtn) {
            lockBtn.addEventListener('click', async () => {
                lockBtn.disabled = true;
                try {
                    if (state.currentUser) {
                        const res = await fetch('/api/profile/loadout', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                            },
                            body: JSON.stringify({
                                preferred_class: state.activeClass,
                            }),
                        });
                        const data = await res.json();
                        if (res.ok) {
                            state.currentUser.preferred_class = state.activeClass;
                            renderCockpitState();
                            showToast(
                                data.message || `Loadout tactique verrouillé sur ${state.activeClass}.`,
                                'success',
                                `OPÉRATEUR: ${state.currentUser.gamertag} // CLASSE: ${state.activeClass}`
                            );
                        } else {
                            showToast(data.message || 'Impossible de verrouiller le loadout.', 'error');
                        }
                    } else {
                        showToast(
                            `Classe ${state.activeClass} pré-sélectionnée pour votre prochain Buy-in.`,
                            'success',
                            'Connectez-vous en 1 clic dans le Cockpit pour persister sur votre profil.'
                        );
                    }
                } catch (err) {
                    showToast('Erreur réseau lors de la mise à jour du loadout.', 'error');
                } finally {
                    lockBtn.disabled = false;
                }
            });
        }

        selectLoadoutClass(state.activeClass);
    }

    // =========================================================================
    // 8. MODULE 4: FILTERABLE TOURNAMENT CATALOG & INTERACTIVE BRACKET
    // =========================================================================
    function statusBadgeHtml(status) {
        const map = {
            OPEN: {
                cls: 'bg-[#10B981]/15 text-[#10B981] border-[#10B981]/40',
                label: 'OPEN // SÉQUESTRE ACTIF',
            },
            IN_PROGRESS: {
                cls: 'bg-[#F59E0B]/15 text-[#F59E0B] border-[#F59E0B]/40',
                label: 'LIVE // EN COURS',
            },
            LOCKED: {
                cls: 'bg-[#D8E5F8]/15 text-[#D8E5F8] border-[#D8E5F8]/40',
                label: 'LOCKED // COMPLET',
            },
            SETTLED: {
                cls: 'bg-[#E5C158]/15 text-[#E5C158] border-[#E5C158]/40',
                label: 'SETTLED // DISTRIBUÉ',
            },
            CANCELLED: {
                cls: 'bg-[#EF4444]/15 text-[#EF4444] border-[#EF4444]/40',
                label: 'CANCELLED // REMBOURSÉ',
            },
        };
        const cfg = map[status] || map.OPEN;
        return `<span class="px-2.5 py-1 text-[10px] font-mono font-semibold uppercase tracking-widest border ${cfg.cls}">${cfg.label}</span>`;
    }

    function filterTournamentsList() {
        return state.tournaments.filter((t) => {
            if (state.filters.game !== 'ALL' && t.game !== state.filters.game) return false;
            if (state.filters.status !== 'ALL' && t.status !== state.filters.status) return false;
            if (state.filters.format !== 'ALL' && t.format !== state.filters.format) return false;
            if (
                state.filters.maxBuyInCents !== 'ALL' &&
                Number(t.entry_fee_cents) > Number(state.filters.maxBuyInCents)
            ) {
                return false;
            }
            if (
                state.filters.minPrizeCents !== 'ALL' &&
                Number(t.escrow_pool_cents) < Number(state.filters.minPrizeCents)
            ) {
                return false;
            }
            if (state.filters.search.trim() !== '') {
                const q = state.filters.search.trim().toLowerCase();
                const hay = `${t.title} ${t.game} ${t.slug} ${t.organizer ? t.organizer.gamertag : ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });
    }

    function renderTournamentsCatalog() {
        const grid = document.getElementById('tournaments-catalog-grid');
        const emptyState = document.getElementById('tournaments-empty-state');
        const counterEl = document.getElementById('active-arenas-counter');
        if (!grid) return;

        const filtered = filterTournamentsList();
        const activeCount = filtered.filter((t) =>
            ['OPEN', 'IN_PROGRESS', 'LOCKED'].includes(t.status)
        ).length;

        if (counterEl) {
            counterEl.textContent =
                state.lang === 'en'
                    ? `${activeCount} ACTIVE ARENAS // ${filtered.length} SHOWN`
                    : `${activeCount} ARÈNES ACTIVES // ${filtered.length} AFFICHÉES`;
        }

        if (filtered.length === 0) {
            grid.classList.add('hidden');
            if (emptyState) emptyState.classList.remove('hidden');
            return;
        }

        grid.classList.remove('hidden');
        if (emptyState) emptyState.classList.add('hidden');

        grid.innerHTML = filtered
            .map((t) => {
                const isRegistered =
                    state.currentUser &&
                    Array.isArray(t.registrations) &&
                    t.registrations.some((r) => Number(r.user_id) === Number(state.currentUser.id));

                const canManage =
                    state.currentUser &&
                    (state.currentUser.role === 'ADMIN' ||
                        (state.currentUser.role === 'ORGANIZER' &&
                            Number(t.organizer_id) === Number(state.currentUser.id)));

                const totalPool = Number(t.escrow_pool_cents || 0);
                const rakeCents = Math.floor((totalPool * 800) / 10000);
                const netPoolCents = totalPool - rakeCents;

                const regs = Array.isArray(t.registrations) ? t.registrations : [];
                const regTags = regs
                    .slice(0, 4)
                    .map(
                        (r) =>
                            `<span class="px-2 py-0.5 text-[11px] font-mono bg-[#121824] border border-white/[0.06] text-[#D8E5F8]">${escapeHtml(r.user ? r.user.gamertag : 'OP')}</span>`
                    )
                    .join('');

                let primaryBtnHtml = '';
                if (t.status === 'OPEN') {
                    if (isRegistered) {
                        primaryBtnHtml = `
                            <button type="button" data-open-detail="${t.id}" class="h-11 flex-1 min-w-0 px-2.5 sm:px-3.5 bg-[#10B981]/20 border border-[#10B981]/50 text-[#10B981] font-mono text-[11px] font-semibold uppercase tracking-normal sm:tracking-wider whitespace-nowrap truncate hover:bg-[#10B981]/30 transition">
                                INSCRIT // SÉQUESTRE ✓
                            </button>
                        `;
                    } else {
                        primaryBtnHtml = `
                            <button type="button" data-open-register="${t.id}" class="h-11 flex-1 min-w-0 px-2.5 sm:px-3.5 bg-[#D8E5F8] text-[#030407] font-mono text-[11px] font-bold uppercase tracking-normal sm:tracking-wider whitespace-nowrap truncate hover:bg-[#E5C158] transition">
                                S'inscrire (${formatCents(t.entry_fee_cents)}) ↗
                            </button>
                        `;
                    }
                } else {
                    primaryBtnHtml = `
                        <button type="button" data-open-detail="${t.id}" class="h-11 flex-1 min-w-0 px-2.5 sm:px-3.5 bg-[#121824] border border-white/15 text-[#F4F7FB] font-mono text-[11px] font-semibold uppercase tracking-normal sm:tracking-wider whitespace-nowrap truncate hover:border-[#E5C158] transition">
                            Voir Grand Livre ↗
                        </button>
                    `;
                }

                return `
                    <article class="min-w-0 overflow-hidden flex flex-col justify-between h-full bg-[#0C1018] border border-white/[0.08] hover:border-white/20 p-4 sm:p-5 transition">
                        <div class="min-w-0">
                            <!-- Top Meta Row -->
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="px-2 py-0.5 text-[10px] font-mono uppercase tracking-widest bg-[#121824] border border-white/10 text-[#D8E5F8]">${escapeHtml(t.game)}</span>
                                    <span class="px-2 py-0.5 text-[10px] font-mono uppercase tracking-widest bg-[#080B11] border border-white/10 text-[#94A3B8] tabular-nums">${escapeHtml(t.format)}</span>
                                </div>
                                ${statusBadgeHtml(t.status)}
                            </div>

                            <!-- Title & Organizer -->
                            <h3 class="font-display font-bold text-lg text-[#F4F7FB] tracking-tight mb-1 break-words">${escapeHtml(t.title)}</h3>
                            <p class="text-xs font-mono text-[#64748B] mb-4 tabular-nums">
                                ORG // <span class="text-[#94A3B8]">${escapeHtml(t.organizer ? t.organizer.gamertag : 'VKR_ARBITER')}</span>
                                ${canManage ? '<span class="ml-2 text-[#E5C158]">[PROPRIÉTAIRE // ARBITRE]</span>' : ''}
                            </p>

                            <!-- Financial Escrow Telemetry Grid -->
                            <div class="grid grid-cols-2 gap-px bg-white/[0.06] border border-white/[0.08] mb-4">
                                <div class="bg-[#080B11] p-2.5 sm:p-3 min-w-0">
                                    <div class="text-[10px] font-mono uppercase tracking-wider text-[#64748B] truncate">BUY-IN ENTRÉE</div>
                                    <div class="text-sm font-mono font-bold text-[#F4F7FB] tabular-nums mt-0.5">${formatCents(t.entry_fee_cents)}</div>
                                </div>
                                <div class="bg-[#080B11] p-2.5 sm:p-3 min-w-0">
                                    <div class="text-[10px] font-mono uppercase tracking-wider text-[#64748B] truncate">SÉQUESTRE VERROUILLÉ</div>
                                    <div class="text-sm font-mono font-bold text-[#E5C158] tabular-nums mt-0.5">${formatCents(t.escrow_pool_cents)}</div>
                                </div>
                                <div class="bg-[#080B11] p-2.5 sm:p-3 min-w-0">
                                    <div class="text-[10px] font-mono uppercase tracking-wider text-[#64748B] truncate">CASHPRIZE NET (92%)</div>
                                    <div class="text-xs font-mono font-semibold text-[#10B981] tabular-nums mt-0.5">${formatCents(t.status === 'SETTLED' ? t.net_prize_pool_cents : netPoolCents)}</div>
                                </div>
                                <div class="bg-[#080B11] p-2.5 sm:p-3 min-w-0">
                                    <div class="text-[10px] font-mono uppercase tracking-wider text-[#64748B] truncate">OPÉRATEURS INSCRITS</div>
                                    <div class="text-xs font-mono font-semibold text-[#D8E5F8] tabular-nums mt-0.5">${t.registered_count} / ${t.max_participants} SLOTS</div>
                                </div>
                            </div>

                            <!-- Operators / Podium Preview -->
                            <div class="min-h-[36px] flex flex-wrap items-center gap-1.5 mb-4">
                                <span class="text-[10px] font-mono uppercase tracking-wider text-[#64748B] mr-1">ROSTER:</span>
                                ${regTags || '<span class="text-xs font-mono text-[#64748B]">Aucun inscrit</span>'}
                            </div>
                        </div>

                        <!-- Bottom Aligned Action Row -->
                        <div class="mt-auto pt-4 border-t border-white/[0.08] flex items-center gap-2 sm:gap-3 min-w-0">
                            ${primaryBtnHtml}
                            <button type="button" data-open-detail="${t.id}" class="h-11 shrink-0 px-2.5 sm:px-3.5 bg-[#080B11] border border-white/15 text-[#94A3B8] hover:text-[#F4F7FB] hover:border-[#D8E5F8] font-mono text-[11px] font-semibold uppercase tracking-normal sm:tracking-wider whitespace-nowrap transition">
                                Arbitrage ⤢
                            </button>
                        </div>
                    </article>
                `;
            })
            .join('');

        // Bind card action buttons
        grid.querySelectorAll('[data-open-register]').forEach((btn) => {
            btn.addEventListener('click', () => {
                openRegisterTournamentModal(Number(btn.getAttribute('data-open-register')));
            });
        });

        grid.querySelectorAll('[data-open-detail]').forEach((btn) => {
            btn.addEventListener('click', () => {
                openTournamentDetailModal(Number(btn.getAttribute('data-open-detail')));
            });
        });
    }

    function initCatalogFilters() {
        const gameSelect = document.getElementById('filter-game');
        const statusSelect = document.getElementById('filter-status');
        const formatSelect = document.getElementById('filter-format');
        const buyInSelect = document.getElementById('filter-buyin');
        const prizeSelect = document.getElementById('filter-prize');
        const searchInput = document.getElementById('tournament-search-input');
        const resetBtn = document.getElementById('reset-filters-btn');
        const emptyResetBtn = document.getElementById('empty-reset-filters-btn');

        const bindFilter = (el, key) => {
            if (!el) return;
            el.addEventListener('change', () => {
                state.filters[key] = el.value;
                renderTournamentsCatalog();
            });
        };

        bindFilter(gameSelect, 'game');
        bindFilter(statusSelect, 'status');
        bindFilter(formatSelect, 'format');
        bindFilter(buyInSelect, 'maxBuyInCents');
        bindFilter(prizeSelect, 'minPrizeCents');

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                state.filters.search = searchInput.value;
                renderTournamentsCatalog();
            });
        }

        const resetAllFilters = () => {
            state.filters = {
                game: 'ALL',
                status: 'ALL',
                format: 'ALL',
                maxBuyInCents: 'ALL',
                minPrizeCents: 'ALL',
                search: '',
            };
            if (gameSelect) gameSelect.value = 'ALL';
            if (statusSelect) statusSelect.value = 'ALL';
            if (formatSelect) formatSelect.value = 'ALL';
            if (buyInSelect) buyInSelect.value = 'ALL';
            if (prizeSelect) prizeSelect.value = 'ALL';
            if (searchInput) searchInput.value = '';
            renderTournamentsCatalog();
        };

        if (resetBtn) resetBtn.addEventListener('click', resetAllFilters);
        if (emptyResetBtn) emptyResetBtn.addEventListener('click', resetAllFilters);
    }

    // =========================================================================
    // 9. MODAL SYSTEM (ESCAPE KEY, BACKDROP CLICK, SCROLL LOCK)
    // =========================================================================
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.add('hidden');
        const anyOpen = Array.from(document.querySelectorAll('[data-modal-shell]')).some(
            (m) => !m.classList.contains('hidden')
        );
        if (!anyOpen) {
            document.body.style.overflow = '';
        }
    }

    function closeAllModals() {
        document.querySelectorAll('[data-modal-shell]').forEach((m) => {
            m.classList.add('hidden');
        });
        document.body.style.overflow = '';
    }

    function initModals() {
        document.querySelectorAll('[data-close-modal]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-close-modal');
                if (target) closeModal(target);
                else closeAllModals();
            });
        });

        document.querySelectorAll('[data-modal-backdrop]').forEach((backdrop) => {
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) {
                    closeAllModals();
                }
            });
        });

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllModals();
            }
        });

        // Header & Cockpit Modal Triggers
        document.querySelectorAll('[data-open-create-tournament]').forEach((btn) => {
            btn.addEventListener('click', () => {
                openModal('modal-create-tournament');
            });
        });

        document.querySelectorAll('[data-open-deposit-modal]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const keyInput = document.getElementById('deposit-idempotency-key');
                if (keyInput) keyInput.value = generateIdempotencyKey('dep');
                openModal('modal-deposit');
            });
        });
    }

    // =========================================================================
    // 10. TRANSACTIONAL MODALS & LIVE STATE SYNC (/api/state)
    // =========================================================================
    async function refreshServerState() {
        try {
            const res = await fetch('/api/state', {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) return;
            const data = await res.json();
            state.tournaments = Array.isArray(data.tournaments) ? data.tournaments : state.tournaments;
            state.currentUser = data.currentUser || null;
            state.wallet = data.wallet || null;
            state.ledgerEntries = Array.isArray(data.ledgerEntries) ? data.ledgerEntries : [];
            state.leaderboard = Array.isArray(data.leaderboard) ? data.leaderboard : state.leaderboard;
            state.platformStats = data.platformStats || state.platformStats;

            renderTournamentsCatalog();
            renderCockpitState();
            renderBracket(state.activeBracketTournamentId);
            renderLeaderboard();
        } catch (err) {
            // Silent fallback
        }
    }

    function openRegisterTournamentModal(tournamentId) {
        const t = state.tournaments.find((item) => Number(item.id) === Number(tournamentId));
        if (!t) return;

        const form = document.getElementById('form-register-tournament');
        if (form) form.setAttribute('data-tournament-id', String(t.id));

        const setEl = (id, text) => {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        };

        setEl('reg-modal-title', t.title);
        setEl('reg-modal-game', `${t.game} // ${t.format}`);
        setEl('reg-modal-fee', formatCents(t.entry_fee_cents));
        setEl('reg-modal-pool', formatCents(t.escrow_pool_cents));

        const currentBalance = state.wallet ? Number(state.wallet.balance_cents) : 0;
        const afterBalance = currentBalance - Number(t.entry_fee_cents);
        setEl('reg-modal-balance', state.wallet ? formatCents(currentBalance) : 'Non connecté');
        setEl(
            'reg-modal-balance-after',
            state.wallet ? formatCents(afterBalance) : 'Connexion requise'
        );

        const keyInput = document.getElementById('register-idempotency-key');
        if (keyInput) keyInput.value = generateIdempotencyKey(`buyin:t${t.id}`);

        const loadoutSelect = document.getElementById('register-loadout-select');
        if (loadoutSelect) {
            loadoutSelect.value = state.activeClass || 'VALKYRIE';
        }

        openModal('modal-register-tournament');
    }

    function openTournamentDetailModal(tournamentId) {
        const t = state.tournaments.find((item) => Number(item.id) === Number(tournamentId));
        if (!t) return;

        const setEl = (id, text) => {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        };

        const totalPool = Number(t.escrow_pool_cents || 0);
        const rakeCents =
            t.status === 'SETTLED'
                ? Number(t.platform_rake_cents)
                : Math.floor((totalPool * 800) / 10000);
        const netPool =
            t.status === 'SETTLED' ? Number(t.net_prize_pool_cents) : totalPool - rakeCents;
        const secondPrize = Math.floor((netPool * 3000) / 10000);
        const thirdPrize = Math.floor((netPool * 1000) / 10000);
        const firstPrize = netPool - secondPrize - thirdPrize;

        setEl('detail-modal-title', t.title);
        setEl(
            'detail-modal-meta',
            `${t.game} // FORMAT ${t.format} // STATUT: ${t.status} // ORG: ${t.organizer ? t.organizer.gamertag : 'VKR_ARBITER'}`
        );
        setEl('detail-modal-pool', formatCents(totalPool));
        setEl('detail-modal-rake', formatCents(rakeCents));
        setEl('detail-modal-net', formatCents(netPool));
        setEl('detail-modal-1st', formatCents(firstPrize));
        setEl('detail-modal-2nd', formatCents(secondPrize));
        setEl('detail-modal-3rd', formatCents(thirdPrize));

        const regs = Array.isArray(t.registrations) ? t.registrations : [];
        const rosterContainer = document.getElementById('detail-modal-roster');
        if (rosterContainer) {
            if (regs.length === 0) {
                rosterContainer.innerHTML =
                    '<p class="text-xs font-mono text-[#64748B]">Aucun opérateur inscrit pour le moment.</p>';
            } else {
                rosterContainer.innerHTML = regs
                    .map(
                        (r) => `
                        <div class="flex items-center justify-between p-2.5 bg-[#080B11] border border-white/[0.06] text-xs font-mono">
                            <div>
                                <span class="font-bold text-[#F4F7FB]">${escapeHtml(r.user ? r.user.gamertag : 'OP #' + r.user_id)}</span>
                                <span class="ml-2 text-[#94A3B8]">[${escapeHtml(r.loadout_class)}]</span>
                            </div>
                            <div class="tabular-nums text-[#E5C158]">
                                ${formatCents(r.paid_entry_fee_cents)} // ${escapeHtml(r.status)}
                            </div>
                        </div>
                    `
                    )
                    .join('');
            }
        }

        // Populate Settle Winner Dropdowns
        const select1 = document.getElementById('settle-first-place');
        const select2 = document.getElementById('settle-second-place');
        const select3 = document.getElementById('settle-third-place');

        const optionsHtml = regs
            .map(
                (r) =>
                    `<option value="${r.user_id}">${escapeHtml(r.user ? r.user.gamertag : 'User #' + r.user_id)} (ID: ${r.user_id})</option>`
            )
            .join('');

        if (select1) select1.innerHTML = optionsHtml;
        if (select2) select2.innerHTML = optionsHtml;
        if (select3) {
            select3.innerHTML = `<option value="">-- Aucun 3e (Duel 1v1 70/30) --</option>${optionsHtml}`;
        }

        if (regs.length >= 1 && select1) select1.value = String(regs[0].user_id);
        if (regs.length >= 2 && select2) select2.value = String(regs[1].user_id);
        if (regs.length >= 3 && select3) select3.value = String(regs[2].user_id);

        const settleForm = document.getElementById('form-settle-tournament');
        const cancelForm = document.getElementById('form-cancel-tournament');
        if (settleForm) settleForm.setAttribute('data-tournament-id', String(t.id));
        if (cancelForm) cancelForm.setAttribute('data-tournament-id', String(t.id));

        const settleKey = document.getElementById('settle-idempotency-key');
        if (settleKey) settleKey.value = generateIdempotencyKey(`settle:t${t.id}`);

        const cancelKey = document.getElementById('cancel-idempotency-key');
        if (cancelKey) cancelKey.value = generateIdempotencyKey(`cancel:t${t.id}`);

        openModal('modal-tournament-detail');
    }

    // =========================================================================
    // 11. COCKPIT, LEDGER TABLE, BRACKET & LEADERBOARD RENDERERS
    // =========================================================================
    function renderCockpitState() {
        const setEl = (id, text) => {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        };

        // Header Auth Status
        const headerOperatorBadge = document.getElementById('header-operator-badge');
        if (headerOperatorBadge) {
            if (state.currentUser) {
                headerOperatorBadge.textContent = `${state.currentUser.gamertag} [${state.currentUser.role}]`;
                headerOperatorBadge.classList.remove('hidden');
            } else {
                headerOperatorBadge.classList.add('hidden');
            }
        }

        // Cockpit KPI Cards
        if (state.currentUser && state.wallet) {
            setEl('cockpit-operator-title', `${state.currentUser.gamertag} // [${state.currentUser.role}]`);
            setEl('cockpit-operator-email', state.currentUser.email);
            setEl('kpi-balance-cents', formatCents(state.wallet.balance_cents));
            setEl('kpi-locked-escrow-cents', formatCents(state.wallet.locked_escrow_cents));
            setEl('kpi-lifetime-won-cents', formatCents(state.wallet.lifetime_won_cents));
            setEl('kpi-elo-rating', `${state.currentUser.elo_rating} MMR`);
            setEl('kpi-preferred-class', `LOADOUT ACTIF // ${state.currentUser.preferred_class}`);
        } else {
            setEl('cockpit-operator-title', 'MODE APERÇU PUBLIC // NON CONNECTÉ');
            setEl(
                'cockpit-operator-email',
                'Sélectionnez un profil Démo en 1 clic ci-dessus pour activer les transactions'
            );
            setEl(
                'kpi-balance-cents',
                formatCents(state.platformStats.total_escrow_locked_cents || 155000)
            );
            setEl(
                'kpi-locked-escrow-cents',
                formatCents(state.platformStats.total_escrow_locked_cents || 155000)
            );
            setEl(
                'kpi-lifetime-won-cents',
                formatCents(state.platformStats.total_prizes_distributed_cents || 36800)
            );
            setEl('kpi-elo-rating', '2 365 MMR');
            setEl('kpi-preferred-class', `LOADOUT PRÉ-SÉLECTIONNÉ // ${state.activeClass}`);
        }

        // Render Immutable Double-Entry Ledger Table
        const tbody = document.getElementById('ledger-entries-tbody');
        if (tbody) {
            if (!state.ledgerEntries || state.ledgerEntries.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-xs font-mono text-[#64748B]">
                            Aucune écriture comptable enregistrée pour ce portefeuille.
                        </td>
                    </tr>
                `;
            } else {
                tbody.innerHTML = state.ledgerEntries
                    .map((entry) => {
                        const isCredit = Number(entry.credit_cents) > 0;
                        const typeBadgeCls = isCredit
                            ? 'bg-[#10B981]/15 text-[#10B981] border-[#10B981]/30'
                            : 'bg-[#F59E0B]/15 text-[#F59E0B] border-[#F59E0B]/30';
                        const debitStr =
                            Number(entry.debit_cents) > 0
                                ? `<span class="text-[#EF4444]">-${formatCents(entry.debit_cents)}</span>`
                                : '<span class="text-[#64748B]">0,00 €</span>';
                        const creditStr =
                            Number(entry.credit_cents) > 0
                                ? `<span class="text-[#10B981]">+${formatCents(entry.credit_cents)}</span>`
                                : '<span class="text-[#64748B]">0,00 €</span>';

                        return `
                            <tr class="border-b border-white/[0.05] hover:bg-[#121824]/60 transition">
                                <td class="px-4 py-3 text-xs font-mono text-[#94A3B8] tabular-nums">#${String(entry.id).padStart(4, '0')}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 text-[10px] font-mono font-semibold border ${typeBadgeCls}">${escapeHtml(entry.entry_type)}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-[#F4F7FB] max-w-xs truncate" title="${escapeHtml(entry.description)}">
                                    ${escapeHtml(entry.description)}
                                </td>
                                <td class="px-4 py-3 text-xs font-mono text-right tabular-nums">${debitStr}</td>
                                <td class="px-4 py-3 text-xs font-mono text-right tabular-nums">${creditStr}</td>
                                <td class="px-4 py-3 text-xs font-mono text-right text-[#D8E5F8] font-semibold tabular-nums">
                                    ${formatCents(entry.balance_after_cents)}
                                </td>
                                <td class="px-4 py-3 text-[11px] font-mono text-[#64748B] tabular-nums truncate max-w-[180px]" title="${escapeHtml(entry.idempotency_key)}">
                                    ${escapeHtml(entry.idempotency_key)}
                                </td>
                            </tr>
                        `;
                    })
                    .join('');
            }
        }

        // Highlight active demo button if logged in
        document.querySelectorAll('[data-demo-email]').forEach((btn) => {
            const email = btn.getAttribute('data-demo-email');
            const isCurrent = state.currentUser && state.currentUser.email === email;
            btn.classList.toggle('border-[#E5C158]', isCurrent);
            btn.classList.toggle('bg-[#121824]', isCurrent);
        });
    }

    function renderBracket(tournamentId) {
        const container = document.getElementById('interactive-bracket-container');
        if (!container) return;

        const targetT =
            state.tournaments.find((t) => Number(t.id) === Number(tournamentId)) ||
            state.tournaments[0];
        if (!targetT) return;

        state.activeBracketTournamentId = targetT.id;

        document.querySelectorAll('[data-bracket-select]').forEach((btn) => {
            const bId = Number(btn.getAttribute('data-bracket-select'));
            const active = bId === Number(targetT.id);
            btn.classList.toggle('border-[#E5C158]', active);
            btn.classList.toggle('bg-[#121824]', active);
            btn.classList.toggle('text-[#F4F7FB]', active);
        });

        const regs = Array.isArray(targetT.registrations) ? targetT.registrations : [];
        const p1 = regs[0]?.user?.gamertag || 'VKR_NYX';
        const p2 = regs[1]?.user?.gamertag || 'KESTREL_ZERO';
        const p3 = regs[2]?.user?.gamertag || 'AETHER_9';
        const p4 = regs[3]?.user?.gamertag || 'SOVEREIGN_X';

        const totalPool = Number(targetT.escrow_pool_cents || 0);
        const netPool = totalPool - Math.floor((totalPool * 800) / 10000);
        const prize2 = Math.floor((netPool * 3000) / 10000);
        const prize3 = Math.floor((netPool * 1000) / 10000);
        const prize1 = netPool - prize2 - prize3;

        const isSettled = targetT.status === 'SETTLED';
        const statusTag = isSettled ? '[SETTLED]' : targetT.status === 'IN_PROGRESS' ? '[LIVE]' : '[ESCROW LOCKED]';
        const statusColor = isSettled
            ? 'text-[#E5C158]'
            : targetT.status === 'IN_PROGRESS'
              ? 'text-[#10B981]'
              : 'text-[#D8E5F8]';

        container.innerHTML = `
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Column 1: Demi-Finales -->
                <div class="flex flex-col justify-between gap-4">
                    <div class="text-[11px] font-mono uppercase tracking-widest text-[#94A3B8] border-b border-white/[0.08] pb-2">
                        PHASE 01 // DEMI-FINALES <span class="${statusColor} ml-1">${statusTag}</span>
                    </div>
                    <div class="bg-[#080B11] border border-white/[0.08] p-4">
                        <div class="flex items-center justify-between text-[10px] font-mono text-[#64748B] mb-2 tabular-nums">
                            <span>MATCH #SF-01 // 128-TICK</span>
                            <span class="${statusColor}">${statusTag}</span>
                        </div>
                        <div class="flex items-center justify-between py-1.5 border-b border-white/[0.05] font-mono text-xs">
                            <span class="font-bold text-[#F4F7FB]">${escapeHtml(p1)}</span>
                            <span class="tabular-nums text-[#10B981] font-bold">13</span>
                        </div>
                        <div class="flex items-center justify-between py-1.5 font-mono text-xs">
                            <span class="text-[#94A3B8]">${escapeHtml(p4)}</span>
                            <span class="tabular-nums text-[#64748B]">09</span>
                        </div>
                    </div>

                    <div class="bg-[#080B11] border border-white/[0.08] p-4">
                        <div class="flex items-center justify-between text-[10px] font-mono text-[#64748B] mb-2 tabular-nums">
                            <span>MATCH #SF-02 // 128-TICK</span>
                            <span class="${statusColor}">${statusTag}</span>
                        </div>
                        <div class="flex items-center justify-between py-1.5 border-b border-white/[0.05] font-mono text-xs">
                            <span class="font-bold text-[#F4F7FB]">${escapeHtml(p2)}</span>
                            <span class="tabular-nums text-[#10B981] font-bold">13</span>
                        </div>
                        <div class="flex items-center justify-between py-1.5 font-mono text-xs">
                            <span class="text-[#94A3B8]">${escapeHtml(p3)}</span>
                            <span class="tabular-nums text-[#64748B]">11</span>
                        </div>
                    </div>
                </div>

                <!-- Column 2: Petite Finale (3e Place — 10% Cashprize) -->
                <div class="flex flex-col justify-between gap-4">
                    <div class="text-[11px] font-mono uppercase tracking-widest text-[#94A3B8] border-b border-white/[0.08] pb-2">
                        PHASE 02 // PETITE FINALE (3E PLACE · 10%)
                    </div>
                    <div class="bg-[#080B11] border border-white/[0.08] p-4 my-auto">
                        <div class="flex items-center justify-between text-[10px] font-mono text-[#64748B] mb-2 tabular-nums">
                            <span>MATCH #BRONZE // ALLOCATION 10%</span>
                            <span class="text-[#10B981] font-bold tabular-nums">${formatCents(prize3)}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-white/[0.05] font-mono text-xs">
                            <span class="font-bold text-[#F4F7FB]">3RD // ${escapeHtml(p3)}</span>
                            <span class="tabular-nums text-[#10B981] font-bold">13</span>
                        </div>
                        <div class="flex items-center justify-between py-2 font-mono text-xs">
                            <span class="text-[#94A3B8]">4TH // ${escapeHtml(p4)}</span>
                            <span class="tabular-nums text-[#64748B]">07</span>
                        </div>
                    </div>
                </div>

                <!-- Column 3: Grande Finale (1st 60% / 2nd 30%) -->
                <div class="flex flex-col justify-between gap-4">
                    <div class="text-[11px] font-mono uppercase tracking-widest text-[#E5C158] border-b border-[#E5C158]/30 pb-2">
                        PHASE 03 // GRANDE FINALE SOUVERAINE (60% / 30%)
                    </div>
                    <div class="bg-[#080B11] border border-[#E5C158]/45 p-5 my-auto">
                        <div class="flex items-center justify-between text-[10px] font-mono text-[#E5C158] mb-3 tabular-nums">
                            <span>GRANDE FINALE // ${escapeHtml(targetT.title)}</span>
                            <span>${statusTag}</span>
                        </div>
                        <div class="flex items-center justify-between py-2.5 px-3 bg-[#121824] border border-[#E5C158]/40 mb-2 font-mono text-xs">
                            <div>
                                <span class="text-[10px] text-[#E5C158] block">1ST PLACE // 60% NET POOL</span>
                                <span class="font-bold text-[#F4F7FB]">${escapeHtml(targetT.first_place?.gamertag || p1)}</span>
                            </div>
                            <span class="tabular-nums text-sm font-bold text-[#E5C158]">${formatCents(prize1)}</span>
                        </div>
                        <div class="flex items-center justify-between py-2.5 px-3 bg-[#0C1018] border border-white/10 font-mono text-xs">
                            <div>
                                <span class="text-[10px] text-[#D8E5F8] block">2ND PLACE // 30% NET POOL</span>
                                <span class="font-bold text-[#F4F7FB]">${escapeHtml(targetT.second_place?.gamertag || p2)}</span>
                            </div>
                            <span class="tabular-nums text-sm font-bold text-[#D8E5F8]">${formatCents(prize2)}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderLeaderboard() {
        const tbody = document.getElementById('leaderboard-tbody');
        if (!tbody || !Array.isArray(state.leaderboard)) return;

        tbody.innerHTML = state.leaderboard
            .map((u, idx) => {
                const rank = String(idx + 1).padStart(2, '0');
                const wonCents = u.wallet ? Number(u.wallet.lifetime_won_cents) : 0;
                const escrowCents = u.wallet ? Number(u.wallet.locked_escrow_cents) : 0;
                const isTop3 = idx < 3;
                return `
                    <tr class="border-b border-white/[0.06] hover:bg-[#121824]/60 transition">
                        <td class="px-4 py-3.5 font-mono text-xs font-bold tabular-nums ${isTop3 ? 'text-[#E5C158]' : 'text-[#64748B]'}">#${rank}</td>
                        <td class="px-4 py-3.5 font-mono text-sm font-bold text-[#F4F7FB]">${escapeHtml(u.gamertag)}</td>
                        <td class="px-4 py-3.5">
                            <span class="px-2 py-0.5 text-[10px] font-mono uppercase tracking-wider bg-[#121824] border border-white/10 text-[#D8E5F8]">${escapeHtml(u.role)}</span>
                        </td>
                        <td class="px-4 py-3.5 font-mono text-xs text-[#94A3B8]">${escapeHtml(u.preferred_class)}</td>
                        <td class="px-4 py-3.5 font-mono text-sm font-bold text-right text-[#D8E5F8] tabular-nums">${u.elo_rating} MMR</td>
                        <td class="px-4 py-3.5 font-mono text-sm font-bold text-right text-[#10B981] tabular-nums">${formatCents(wonCents)}</td>
                        <td class="px-4 py-3.5 font-mono text-xs text-right text-[#E5C158] tabular-nums">${formatCents(escrowCents)}</td>
                    </tr>
                `;
            })
            .join('');
    }

    // =========================================================================
    // 12. AUTHENTICATION, DEMO SWITCHER & TRANSACTIONAL FORM HANDLERS
    // =========================================================================
    function initFormsAndActions() {
        // 1. Auth Split-Screen Tabs (Sign In <-> Create Account)
        const tabLoginBtn = document.getElementById('tab-auth-login');
        const tabRegisterBtn = document.getElementById('tab-auth-register');
        const loginPanel = document.getElementById('panel-auth-login');
        const registerPanel = document.getElementById('panel-auth-register');

        if (tabLoginBtn && tabRegisterBtn && loginPanel && registerPanel) {
            tabLoginBtn.addEventListener('click', () => {
                loginPanel.classList.remove('hidden');
                registerPanel.classList.add('hidden');
                tabLoginBtn.classList.add('border-b-2', 'border-[#E5C158]', 'text-[#F4F7FB]');
                tabLoginBtn.classList.remove('text-[#64748B]');
                tabRegisterBtn.classList.remove('border-b-2', 'border-[#E5C158]', 'text-[#F4F7FB]');
                tabRegisterBtn.classList.add('text-[#64748B]');
            });

            tabRegisterBtn.addEventListener('click', () => {
                registerPanel.classList.remove('hidden');
                loginPanel.classList.add('hidden');
                tabRegisterBtn.classList.add('border-b-2', 'border-[#E5C158]', 'text-[#F4F7FB]');
                tabRegisterBtn.classList.remove('text-[#64748B]');
                tabLoginBtn.classList.remove('border-b-2', 'border-[#E5C158]', 'text-[#F4F7FB]');
                tabLoginBtn.classList.add('text-[#64748B]');
            });
        }

        // 2. Password Show / Hide Toggle Buttons
        document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const targetId = btn.getAttribute('data-toggle-password');
                const input = document.getElementById(targetId);
                if (!input) return;
                const isPwd = input.type === 'password';
                input.type = isPwd ? 'text' : 'password';
                btn.textContent = isPwd ? 'MASQUER' : 'AFFICHER';
            });
        });

        // 3. 1-Click Demo Login Switcher
        document.querySelectorAll('[data-demo-email]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const email = btn.getAttribute('data-demo-email');
                if (!email) return;
                btn.disabled = true;
                try {
                    const res = await fetch('/api/auth/demo-login', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({ email }),
                    });
                    const data = await res.json();
                    if (res.ok) {
                        await refreshServerState();
                        showToast(
                            data.message || `Profil actif : ${email}`,
                            'success',
                            `SOLDE: ${state.wallet ? formatCents(state.wallet.balance_cents) : ''}`
                        );
                    } else {
                        showToast(data.message || 'Erreur lors de la bascule démo.', 'error');
                    }
                } catch (err) {
                    showToast('Erreur réseau.', 'error');
                } finally {
                    btn.disabled = false;
                }
            });
        });

        // 4. Login Form Submission
        const loginForm = document.getElementById('form-auth-login');
        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = loginForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                const formData = new FormData(loginForm);
                try {
                    const res = await fetch('/api/auth/login', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            email: formData.get('email'),
                            password: formData.get('password'),
                        }),
                    });
                    const data = await res.json();
                    if (res.ok) {
                        await refreshServerState();
                        showToast(data.message || 'Session tactique ouverte.', 'success');
                    } else {
                        showToast(data.message || 'Identifiants tactiques invalides.', 'error');
                    }
                } catch (err) {
                    showToast('Erreur réseau lors de la connexion.', 'error');
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }

        // 5. Register Form Submission
        const registerForm = document.getElementById('form-auth-register');
        if (registerForm) {
            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = registerForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                const formData = new FormData(registerForm);
                try {
                    const res = await fetch('/api/auth/register', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            name: formData.get('name'),
                            gamertag: formData.get('gamertag'),
                            email: formData.get('email'),
                            password: formData.get('password'),
                            password_confirmation: formData.get('password_confirmation'),
                            preferred_class: formData.get('preferred_class'),
                        }),
                    });
                    const data = await res.json();
                    if (res.ok) {
                        await refreshServerState();
                        showToast(data.message || 'Opérateur créé et portefeuille initialisé.', 'success');
                    } else {
                        showToast(data.message || 'Erreur de validation du formulaire.', 'error');
                    }
                } catch (err) {
                    showToast('Erreur réseau.', 'error');
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }

        // 6. Logout Button
        document.querySelectorAll('[data-logout-btn]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                btn.disabled = true;
                try {
                    await fetch('/api/auth/logout', {
                        method: 'POST',
                        headers: { Accept: 'application/json' },
                    });
                    await refreshServerState();
                    showToast('Session opérateur déconnectée.', 'warning');
                } finally {
                    btn.disabled = false;
                }
            });
        });

        // 7. Deposit Preset Buttons & Deposit Form (/api/wallets/deposit)
        document.querySelectorAll('[data-deposit-preset]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const cents = Number(btn.getAttribute('data-deposit-preset'));
                const input = document.getElementById('deposit-amount-cents');
                if (input && cents) {
                    input.value = String(cents);
                }
            });
        });

        const depositForm = document.getElementById('form-wallet-deposit');
        if (depositForm) {
            depositForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = depositForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                const formData = new FormData(depositForm);
                const amountCents = Math.trunc(Number(formData.get('amount_cents')));
                const idempotencyKey =
                    String(formData.get('idempotency_key') || '').trim() ||
                    generateIdempotencyKey('dep');

                try {
                    const res = await fetch('/api/wallets/deposit', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            amount_cents: amountCents,
                            idempotency_key: idempotencyKey,
                        }),
                    });
                    const data = await res.json();
                    if (res.ok) {
                        await refreshServerState();
                        closeModal('modal-deposit');
                        showToast(
                            data.message || 'Dépôt crédité sur le grand livre.',
                            'success',
                            `MONTANT: +${formatCents(amountCents)} // KEY: ${idempotencyKey}`
                        );
                    } else {
                        showToast(
                            data.message || 'Dépôt refusé (authentification requise ou montant invalide).',
                            'error'
                        );
                    }
                } catch (err) {
                    showToast('Erreur réseau lors du dépôt.', 'error');
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }

        // 8. Create Tournament Form (/api/tournaments)
        const createTournamentForm = document.getElementById('form-create-tournament');
        if (createTournamentForm) {
            createTournamentForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = createTournamentForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                const formData = new FormData(createTournamentForm);

                try {
                    const res = await fetch('/api/tournaments', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            title: formData.get('title'),
                            game: formData.get('game'),
                            format: formData.get('format'),
                            entry_fee_cents: Math.trunc(Number(formData.get('entry_fee_cents'))),
                            max_participants: Math.trunc(Number(formData.get('max_participants'))),
                        }),
                    });
                    const data = await res.json();
                    if (res.ok) {
                        await refreshServerState();
                        closeModal('modal-create-tournament');
                        showToast(
                            data.message || 'Nouvelle arène ouverte aux inscriptions.',
                            'success',
                            `ARÈNE: ${data.tournament ? data.tournament.title : ''}`
                        );
                    } else {
                        showToast(
                            data.message ||
                                'Accès refusé (Rôle ORGANIZER ou ADMIN requis — 403 Anti-BFLA).',
                            'error'
                        );
                    }
                } catch (err) {
                    showToast('Erreur réseau lors de la création de ligue.', 'error');
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }

        // 9. Register Tournament Buy-In Form (/api/tournaments/{id}/register)
        const registerTournamentForm = document.getElementById('form-register-tournament');
        if (registerTournamentForm) {
            registerTournamentForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const tId = registerTournamentForm.getAttribute('data-tournament-id');
                if (!tId) return;
                const submitBtn = registerTournamentForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                const formData = new FormData(registerTournamentForm);
                const idempotencyKey =
                    String(formData.get('idempotency_key') || '').trim() ||
                    generateIdempotencyKey(`buyin:t${tId}`);

                try {
                    const res = await fetch(`/api/tournaments/${tId}/register`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            idempotency_key: idempotencyKey,
                            loadout_class: formData.get('loadout_class') || state.activeClass,
                        }),
                    });
                    const data = await res.json();
                    if (res.ok) {
                        await refreshServerState();
                        closeModal('modal-register-tournament');
                        showToast(
                            data.message || 'Buy-in verrouillé en séquestre atomique.',
                            'success',
                            `IDEMPOTENCY KEY: ${idempotencyKey}`
                        );
                    } else {
                        showToast(
                            data.message || "Échec de l'inscription au tournoi.",
                            'error'
                        );
                    }
                } catch (err) {
                    showToast('Erreur réseau lors du Buy-in.', 'error');
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }

        // 10. Settle Tournament Form (/api/tournaments/{id}/settle)
        const settleForm = document.getElementById('form-settle-tournament');
        if (settleForm) {
            settleForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const tId = settleForm.getAttribute('data-tournament-id');
                if (!tId) return;
                const submitBtn = settleForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                const formData = new FormData(settleForm);
                const thirdVal = formData.get('third_place_user_id');
                const idempotencyKey =
                    String(formData.get('idempotency_key') || '').trim() ||
                    generateIdempotencyKey(`settle:t${tId}`);

                try {
                    const res = await fetch(`/api/tournaments/${tId}/settle`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            first_place_user_id: Number(formData.get('first_place_user_id')),
                            second_place_user_id: Number(formData.get('second_place_user_id')),
                            third_place_user_id: thirdVal ? Number(thirdVal) : null,
                            idempotency_key: idempotencyKey,
                        }),
                    });
                    const data = await res.json();
                    if (res.ok) {
                        await refreshServerState();
                        closeModal('modal-tournament-detail');
                        showToast(
                            data.message || 'Cashprize 60/30/10 distribué et commission 8% prélevée.',
                            'success',
                            `CLÉ DE RÈGLEMENT: ${idempotencyKey}`
                        );
                    } else {
                        showToast(
                            data.message || 'Clôture refusée (Vérifiez vos droits Organisateur/Admin ou les inscrits).',
                            'error'
                        );
                    }
                } catch (err) {
                    showToast('Erreur réseau lors de la clôture.', 'error');
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }

        // 11. Cancel Tournament Form (/api/tournaments/{id}/cancel)
        const cancelForm = document.getElementById('form-cancel-tournament');
        if (cancelForm) {
            cancelForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const tId = cancelForm.getAttribute('data-tournament-id');
                if (!tId) return;
                const submitBtn = cancelForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                const formData = new FormData(cancelForm);
                const idempotencyKey =
                    String(formData.get('idempotency_key') || '').trim() ||
                    generateIdempotencyKey(`cancel:t${tId}`);

                try {
                    const res = await fetch(`/api/tournaments/${tId}/cancel`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            idempotency_key: idempotencyKey,
                            reason: formData.get('reason') || 'Annulation officielle par Arbitre.',
                        }),
                    });
                    const data = await res.json();
                    if (res.ok) {
                        await refreshServerState();
                        closeModal('modal-tournament-detail');
                        showToast(
                            data.message || 'Tournoi annulé et 100% du séquestre remboursé.',
                            'warning',
                            `TOTAL REMBOURSÉ: ${formatCents(data.total_refunded_cents || 0)}`
                        );
                    } else {
                        showToast(data.message || "Refus de l'annulation.", 'error');
                    }
                } catch (err) {
                    showToast("Erreur réseau lors de l'annulation.", 'error');
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }

        // 12. Cockpit Sidebar Active Indicator Switcher
        document.querySelectorAll('[data-cockpit-nav]').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('[data-cockpit-nav]').forEach((b) => {
                    b.classList.remove('border-l-2', 'border-[#E5C158]', 'bg-[#121824]', 'text-[#F4F7FB]');
                    b.classList.add('text-[#94A3B8]');
                });
                btn.classList.add('border-l-2', 'border-[#E5C158]', 'bg-[#121824]', 'text-[#F4F7FB]');
                btn.classList.remove('text-[#94A3B8]');

                const targetId = btn.getAttribute('data-cockpit-nav');
                const targetEl = document.getElementById(targetId);
                if (targetEl) {
                    targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // 13. Bracket Tournament Selector Buttons
        document.querySelectorAll('[data-bracket-select]').forEach((btn) => {
            btn.addEventListener('click', () => {
                renderBracket(Number(btn.getAttribute('data-bracket-select')));
            });
        });
    }

    // =========================================================================
    // 13. BOOTSTRAP ON DOM READY
    // =========================================================================
    document.addEventListener('DOMContentLoaded', () => {
        initHeader();
        initTacticalReplayHud();
        initScrollytelling();
        initArsenalSection();
        initCatalogFilters();
        initModals();
        initFormsAndActions();

        renderTournamentsCatalog();
        renderCockpitState();
        renderBracket(state.tournaments[0]?.id || 1);
        renderLeaderboard();
    });
})();
