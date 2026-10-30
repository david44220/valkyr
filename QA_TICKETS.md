# QA_TICKETS.md — Rapport d'Audit Navigateur, Pureté Optique 4K & Géométrie DOM (`Auditor QA` — Cycle v2)

- **Statut Global QA :** `✅ APPROUVÉ — TRANSFERT IMMÉDIAT À LA RED TEAM`
- **Horodatage :** `2026-10-30T23:55:30+01:00`
- **Agent Auditeur :** `Agent 3 — Auditor QA` (Playwright Chromium HiDPI `deviceScaleFactor: 2`)
- **Résumé des Vérifications Navigateur, Parcours Multi-Pages & Persistance (Cycle v2) :**
  - **Erreurs Console / PageError / Réseau :** `0` erreur console, `0` exception JS (`pageerror`), `0` échec réseau (`HTTP 200` sur `/`, `/login`, `/register`, `/dashboard`, `/tournaments/valkyr-sovereign-series-s04`, `/api/state` et les 6 assets `public/assets/*.png`).
  - **Architecture 4 Vraies Pages Distinctes & Cycle d'Authentification (`100 % VALIDÉ`) :**
    1. **Page 1 — Vitrine Publique (`GET /`) :**
       - Absence stricte de `#auth-split-module` et `#operator-cockpit` sur la page d'accueil.
       - Header `#main-header` `rgba(0, 0, 0, 0)` à `scrollY === 0`, bascule dynamique `@guest` (`Connexion` -> `/login`, `Ouvrir un Compte ↗` -> `/register`) vs `@auth` (`VKR_NYX [PLAYER] · 1 570,80 €`, `Mon Cockpit ↗` -> `/dashboard`, `Déconnexion`).
       - Pureté optique 4K/8K (`1376x768` et `1200x896`, `filter: none`, `transform: none`, `opacity: 1`, `will-change: auto`), Hero `<h1>` et sous-titre sur `2 lignes` à `1440x900`.
       - Live HUD 60 FPS (`#tactical-hud-canvas`) + Modale 4K (`#replay-fullscreen-modal`), Scrollytelling 3 chapitres, Sélecteur d'Arsenal 4 classes, Catalogue Filtrable des Tournois lié à `/tournaments/{slug}` et Classement Général ELO.
    2. **Page 2 — Authentification Split-Screen 50/50 Dédiée (`GET /login` & `GET /register`) :**
       - Split-Screen 50/50 plein écran (`min-h-dvh`), onglet initial actif selon la route (`/login` vs `/register`), bascule `AFFICHER / MASQUER` mot de passe (`password` ➔ `text`), et **Simulateur de Rôles Démo en 1 clic** connectant l'opérateur et redirigeant automatiquement vers `/dashboard`.
    3. **Page 3 — Espace Membre & Cockpit Opérateur Protégé (`GET /dashboard`) :**
       - Redirection automatique vers `/login` pour un visiteur `@guest`.
       - Navigation par **4 vrais onglets exclusifs dans la Sidebar (`overview`, `arenas`, `ledger`, `organizer` — zéro `scrollIntoView`)** où seul le panneau actif (`#cockpit-panel-*`) est visible et les 3 autres sont masqués (`.hidden`).
       - **Dépôt SEPA (`POST /api/wallets/deposit`)** : `+100,00 €` (`1 520,80 €` ➔ `1 620,80 €`) persisté après `page.reload()`.
       - **Nouveau Retrait Bancaire SEPA (`POST /api/wallets/withdraw`)** : `-50,00 €` (`1 620,80 €` ➔ `1 570,80 €`) persisté après `page.reload()` avec écriture immuable `WITHDRAWAL` vérifiée et filtrable dans l'onglet Grand Livre (`#cockpit-panel-ledger`).
       - Bouton **`Déconnexion`** invalidant la session et redirigeant vers `/` (avec `/dashboard` à nouveau protégé par redirection vers `/login`).
    4. **Page 4 — Page Dédiée d'une Arène (`GET /tournaments/valkyr-sovereign-series-s04`) :**
       - Grille mathématique en 7 cartes `tabular-nums` (`Rake 8% / 800 bps`, `Pool Net 92%`, `1er 60%`, `2e 30%`, `3e 10%`), Arbre de Tournoi Interactif en 3 colonnes (Demi-finales, Petite Finale 3e place 10%, Grande Finale 60%/30%), Roster des inscrits, Journal d'Audit Séquestre et Console d'Arbitrage Officiel (`settle` / `cancel`).
  - **Géométrie Multi-Viewports (`375x812`, `390x844`, `768x1024`, `1024x768`, `1440x900`, `1920x1080`) sur 30 états de pages/onglets :**
    - `anyOverflow: false` (`scrollWidth <= clientWidth` sur 30/30 vérifications).
    - `totalBleed: 0` (`rect.left >= 0 && rect.right <= window.innerWidth` sur 30/30 vérifications).
    - `totalCollisions: 0` (zéro chevauchement entre contrôles interactifs sur 30/30 vérifications).
    - `totalWrappedCells: 0` (`whiteSpace === 'nowrap'`, `tabular-nums` et `lines === 1` sur 100 % des cellules `<th>` et `<td>` des 3 tableaux de données sur les 6 viewports).

---

## Historique des Tickets (Tous Clôturés & Vérifiés dans Playwright)

### `[QA-02] [STATUT: CLOSED & VERIFIED IN BROWSER] [TARGET: FRONT_END] [SÉVÉRITÉ: VISUEL / GÉOMÉTRIE]`
- **Page / Route & Viewports :** `GET /login` et `GET /register` sur `375x812` et `390x844`.
- **Problème initial :** Le bouton d'onglet `#tab-auth-register` (`Créer un compte (Register)`) débordait à droite (`rect.right: 399 > 375` et `390`) sur mobile.
- **Correctif appliqué par `Front End` & Vérification `Auditor QA` :**
  - Passage de `gap-6` à `gap-3 sm:gap-6` et calibrage en `text-[11px] sm:text-sm tracking-normal sm:tracking-wider` dans [`resources/views/auth/login.blade.php`](file:///c:/Users/David/Desktop/Antigravity/bench6agents/resources/views/auth/login.blade.php#L212-L231).
  - **Preuve Playwright après re-test :** `bleedCount: 0` sur `GET /login` et `GET /register` pour l'ensemble des 6 viewports (`375x812` ➔ `1920x1080`).

### `[QA-03] [STATUT: CLOSED & VERIFIED IN BROWSER] [TARGET: FRONT_END] [SÉVÉRITÉ: VISUEL / TABLEAU]`
- **Page / Route & Viewports :** `GET /` (`#leaderboard-section`), `GET /dashboard` (`#cockpit-panel-ledger`) et `GET /tournaments/valkyr-sovereign-series-s04` (`Grand Livre du Tournoi`) sur les 6 viewports.
- **Problème initial :** Absence de `whitespace-nowrap` sur les cellules `<th>` et `<td>` des 3 tableaux, provoquant des retours à la ligne sur 2 à 4 lignes pour les en-têtes et les valeurs monétaires/numériques.
- **Correctif appliqué par `Front End` & Vérification `Auditor QA` :**
  - Ajout de `whitespace-nowrap tabular-nums` sur 100 % des `<th>`, `<td>` et `<span>` internes dans [`resources/views/welcome.blade.php`](file:///c:/Users/David/Desktop/Antigravity/bench6agents/resources/views/welcome.blade.php#L735-L752), [`resources/views/dashboard/index.blade.php`](file:///c:/Users/David/Desktop/Antigravity/bench6agents/resources/views/dashboard/index.blade.php#L624-L642), [`resources/views/tournaments/show.blade.php`](file:///c:/Users/David/Desktop/Antigravity/bench6agents/resources/views/tournaments/show.blade.php#L619-L672) et [`resources/js/app.js`](file:///c:/Users/David/Desktop/Antigravity/bench6agents/resources/js/app.js#L1592-L1622).
  - **Preuve Playwright après re-test :** `totalWrappedCells: 0` sur les 30 contrôles multi-viewports (`whiteSpace: "nowrap"`, `tabular-nums`, `lines: 1` sur chaque cellule `<th>` et `<td>`).

### `[QA-01] [STATUT: CLOSED & VERIFIED IN BROWSER] [TARGET: FRONT_END] [SÉVÉRITÉ: VISUEL / GÉOMÉTRIE]`
- **Page / Route & Viewports :** `GET /` (`#arenas-catalog` & `#arsenal-section`) sur `375x812`, `390x844` et `768x1024` (Cycle v1).
- **Correctif vérifié :** `bleedCount: 0`, `cardHeightMismatch: false`, `cardBtnMisaligned: false` et `arsenalNaturalAtTop: "1200x896"` sur **100 % des 6 viewports**.
