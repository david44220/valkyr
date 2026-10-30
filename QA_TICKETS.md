# QA_TICKETS.md — Rapport d'Audit Navigateur, Pureté Optique 4K & Géométrie DOM (`Auditor QA`)

- **Statut Global QA :** `✅ APPROUVÉ — TRANSFERT IMMÉDIAT À LA RED TEAM`
- **Horodatage :** `2026-10-30T22:03:30+01:00`
- **Agent Auditeur :** `Agent 3 — Auditor QA` (Playwright Chromium HiDPI `deviceScaleFactor: 2`)
- **Résumé des Vérifications Navigateur & Persistance :**
  - **Erreurs Console / PageError / Réseau :** `0` erreur console, `0` exception JS (`pageerror`), `0` échec réseau (`HTTP 200` sur `/`, `/api/state`, `/api/tournaments` et les 6 assets `public/assets/*.png`).
  - **Pureté Optique 4K/8K & Header Transparent (`scrollY === 0`) :**
    - `public/assets/hero-valkyr-arena.png` (`#hero-artwork`, `1376x768`) : `filter: none`, `transform: none`, `will-change: auto`, `opacity: 1`, zéro calque d'assombrissement sur l'artwork.
    - `public/assets/live-combat-hud.png` (`#live-combat-artwork`, `1376x768`) : `filter: none`, `transform: none`, `opacity: 1`.
    - `public/assets/class-vanguard.png`, `class-spectre.png`, `class-chrono.png`, `class-valkyrie.png` (`#arsenal-class-image`, `1200x896`) : chargés nativement dès `scrollY === 0` (`loading="eager"`), `filter: none`, `transform: none`.
    - `#main-header` : `background-color: rgba(0, 0, 0, 0)` à `scrollY === 0`, transition fluide vers `rgba(3, 4, 7, 0.85)` + `backdrop-filter: blur(16px)` au scroll (`scrollY > 10`).
  - **Loi des 4 Éléments du Hero, Rythme 2 Lignes & Typographie :**
    - Polices chargées et vérifiées via `document.fonts.ready` : `Syne` (`font-weight: 800`), `Instrument Serif` (vrai `font-style: italic` sur `#hero-headline em` en Or Souverain `#E5C158`), `Plus Jakarta Sans`, `JetBrains Mono` (`font-variant-numeric: tabular-nums` universel).
    - Titre `<h1>` (`#hero-headline`) rendu sur **strictement 2 lignes** (`h1Lines: 2`) sur tous les viewports, et sous-titre (`#hero-subtitle`) rendu sur **2 lignes** sur Desktop/Tablette (`1440x900`, `1920x1080`, `1024x768`, `768x1024`).
    - Boutons CTA (`#hero-cta-primary`, `#hero-cta-secondary`, `[data-open-create-tournament]`) à angles vifs (`border-radius: 0px`) avec glyphes directionnels (`↗`, `▶`).
  - **Géométrie `getBoundingClientRect()` sur les 6 Viewports (`375x812`, `390x844`, `768x1024`, `1024x768`, `1440x900`, `1920x1080`) :**
    - `horizontalOverflow: false` (`scrollWidth <= clientWidth`) sur **6/6 viewports**.
    - `bleedCount: 0` (`rect.left >= 0 && rect.right <= window.innerWidth`) sur **6/6 viewports**.
    - `collisionCount: 0` (zéro chevauchement entre contrôles interactifs) sur **6/6 viewports**.
    - `cardHeightMismatch: false` (égalité stricte de hauteur des cartes par rangée) et `cardBtnMisaligned: false` (alignement horizontal au pixel près des boutons jumeaux en pied de carte) sur **6/6 viewports**.
    - Cible tactile Hamburger mobile (`#mobile-menu-btn` sur `375x812` & `390x844`) : `48x48px` (`>= 44x44px`), ouverture/fermeture propre de `#mobile-nav-drawer`.
  - **Parcours E2E Interactifs & Persistance Base de Données (`await page.reload()`) :**
    1. **Switch Bilingue `FR / EN`** : Bascule instantanée sans mélange de langues (`SOUVERAINETÉ TACTIQUE.` ⇄ `TACTICAL SOVEREIGNTY.`).
    2. **Module 1 (Live Match Replay & HUD 60 FPS + Modale 4K)** : `<canvas id="tactical-hud-canvas">` actif à 60 FPS, bascule POV 4 opérateurs (`VKR_NYX`, `KESTREL_ZERO`, `AETHER_9`, `SOVEREIGN_X`), ouverture de `#replay-fullscreen-modal`, contrôles Play/Pause, vitesse (`0.5x`, `1x`, `2x`), timeline interactive (`00:21:09`), et fermeture par touche `Escape`.
    3. **Module 2 (Scrollytelling Pinned Sticky 3 Chapitres)** : Progression fluide `0% ➔ 100%` et sélection directe des chapitres `01`, `02`, `03` pilotant `#escrow-core-canvas`.
    4. **Module 3 (Sélecteur d'Arsenal 4 Classes & Sauvegarde Loadout)** : Bascule entre `VANGUARD`, `SPECTRE`, `CHRONO`, `VALKYRIE`, verrouillage via `POST /api/profile/loadout` et persistance vérifiée après `page.reload()`.
    5. **Module 4 (Catalogue Filtrable des Tournois)** : Filtres par jeu, statut, format, buy-in, recherche textuelle, affichage de `#tournaments-empty-state` et réinitialisation complète (`#empty-reset-filters-btn`).
    6. **Module 5 (Split-Screen Auth 50/50, Bascule Démo 1 Clic, Cockpit & Transactions ACID)** :
       - Bouton `AFFICHER / MASQUER` fonctionnel sur les champs mot de passe.
       - Connexion Démo en 1 clic (`POST /api/auth/demo-login`) et création de compte (`POST /api/auth/register`) persistées après `page.reload()`.
       - Dépôt portefeuille (`POST /api/wallets/deposit`, `+250,00 €`) crédité avec écriture `DEPOSIT` dans `#ledger-entries-tbody` et persisté après `page.reload()`.
       - Inscription Buy-in (`POST /api/tournaments/2/register`) verrouillant les fonds en séquestre (`BUY_IN_ESCROW_DEBIT` / `BUY_IN_ESCROW_CREDIT`) et persistée après `page.reload()`.
       - Création d'une nouvelle ligue (`POST /api/tournaments`), clôture officielle 60/30/10 avec commission 8 % (`POST /api/tournaments/1/settle` : Rake `24,00 €`, Pool Net `276,00 €`) et annulation avec remboursement 100 % (`POST /api/tournaments/3/cancel` : Escrow ramené à `0,00 €`), toutes persistées après `page.reload()`.

---

## Historique des Tickets (Clôturés & Vérifiés)

### `[QA-01] [STATUT: CLOSED & VERIFIED IN BROWSER] [TARGET: FRONT_END] [SÉVÉRITÉ: VISUEL / GÉOMÉTRIE]`
- **Page / Route & Viewports :** `GET /` (`#arenas-catalog` & `#arsenal-section`) sur `375x812`, `390x844` et `768x1024`.
- **Problème initial :** Débordement latéral droit (`Off-Screen Bleed`) sur le bouton secondaire `"Arbitrage ⤢"` en pied de carte sur `375x812`, `390x844` et `768x1024`, et attribut `loading="lazy"` sur `#arsenal-class-image` à `scrollY === 0`.
- **Correctif appliqué par `Front End` & Vérification `Auditor QA` :**
  - Ajout de `min-w-0 overflow-hidden p-4 sm:p-5` sur `<article>`, `flex-wrap` sur la barre de métadonnées, et calibrage des boutons d'action en pied de carte (`min-w-0 px-2.5 sm:px-3.5 text-[11px]`) dans `resources/js/app.js:L1137-L1215`.
  - Passage de `#arsenal-class-image` en `loading="eager" decoding="sync"` dans `resources/views/welcome.blade.php:L501`.
  - **Preuve Playwright après re-test :** `bleedCount: 0` (`bleedEls: []`), `cardHeightMismatch: false`, `cardBtnMisaligned: false` et `arsenalNaturalAtTop: "1200x896"` sur **100 % des 6 viewports**.
