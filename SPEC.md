# SPEC.md — VALKYR // ARENA (v2 — Architecture Multi-Pages & Espace Membre Complet)
**Plateforme Compétitive E-Sport de Ligues Privées, Tournois & Défis 1v1/5v5 avec Séquestre Atomique (Escrow), Retrait SEPA & Grand Livre en Partie Double**

---

## 1. Analyse de l'Espace de Travail & Inventaire SSOT Immuable (4K / Code Existant)

### 1.1 Diagnostic & Conservation du Socle v1 ("Do No Harm")
- **Répertoire racine :** `c:\Users\David\Desktop\Antigravity\bench6agents`
- **Socle Technique Actif :** **PHP 8.5 + Laravel 13 (`v13.39.0`) + SQLite (`database/database.sqlite`) + Tailwind CSS v4 + Vite**.
- **Inventaire SSOT Immuable (Verrouillage Strict — Interdiction de supprimer ou dégrader) :**
  1. [`DESIGN_SYSTEM.md`](file:///c:/Users/David/Desktop/Antigravity/bench6agents/DESIGN_SYSTEM.md) : Palette OKLCH (`#030407` Obsidienne Abyssal, `#D8E5F8` Titane Glacé, `#E5C158` Or Souverain, `#10B981` Émeraude Télémétrie), Typographie à 3 niveaux (`Syne` + `Instrument Serif` *Vrai Italique* + `Plus Jakarta Sans` + `JetBrains Mono` `tabular-nums`), grilles `1px` (`rgba(255,255,255,0.08)`).
  2. **Les 6 Visuels 4K/8K Sur-Mesure existants dans `public/assets/` (SSOT Verrouillé) :**
     - `public/assets/hero-valkyr-arena.png` (1376x768 — Hero 8K rim-light studio avec vide abyssal à gauche)
     - `public/assets/live-combat-hud.png` (1376x768 — Toile de fond du HUD Tactique 60 FPS)
     - `public/assets/class-vanguard.png` (1200x896 — Archétype VANGUARD // AEGIS-01)
     - `public/assets/class-spectre.png` (1200x896 — Archétype SPECTRE // KESTREL-V)
     - `public/assets/class-chrono.png` (1200x896 — Archétype CHRONO // ORION-IX)
     - `public/assets/class-valkyrie.png` (1200x896 — Archétype VALKYRIE // SOVEREIGN-PRIME)
  3. **Moteur Financier [`EscrowLedgerService.php`](file:///c:/Users/David/Desktop/Antigravity/bench6agents/app/Services/EscrowLedgerService.php) & Triggers SQLite d'Immuabilité :**
     - Conservés à 100 % et enrichis de l'opération atomique de **Retrait Bancaire SEPA (`withdraw`)**.

---

## 2. Architecture Multi-Pages & Fonctionnelle Exigée (4 Vraies Pages Distinctes)

### 2.1 Diagnostic du Problème Résolu par la v2
Dans la v1, toute l'application était empilée sur une seule page monolithique (`GET /`). La v2 découpe l'application en **4 vraies pages distinctes** avec un routage Laravel authentique (`guest` / `auth` middlewares), une navigation multi-vues par onglets réels dans le Cockpit (zéro `scrollIntoView` !), des pages dédiées par tournoi (`/tournaments/{slug}`) et le retrait bancaire SEPA (`POST /api/wallets/withdraw`).

---

### 2.2 Page 1 — Vitrine Publique & Découverte (`GET /` — `resources/views/welcome.blade.php`, route `home`)
- **Contenu exclusif de `GET /` :**
  1. **Header `100% bg-transparent` à `scrollY === 0` avec état dynamique `@guest` vs `@auth` :**
     - Marque `✦ VALKYR` sur une seule ligne + 4 liens non numérotés (`Arènes`, `Live Replay`, `Arsenal`, `Classement`) + switch bilingue `FR / EN`.
     - **Si non connecté (`@guest`) :** Affiche le lien `Connexion` (pointant vers `GET /login`) et le bouton CTA à angles vifs `0px` `Ouvrir un Compte ↗` (pointant vers `GET /register`).
     - **Si connecté (`@auth`) :** Remplace `"Connexion"` par le badge opérateur (`VKR_NYX [PLAYER]` + solde en `tabular-nums`), le bouton primaire `Mon Cockpit ↗` (pointant vers `GET /dashboard`) et un bouton explicite `Déconnexion` (`POST /logout`).
  2. **Hero 8K Pur** : Limité aux 4 éléments stricts (Kicker, `<h1>` sur exactement 2 lignes avec Ligne 1 en Romain droit `Syne` et Ligne 2 en *Vrai Italique* `Instrument Serif` `#E5C158`, sous-titre sur 2 lignes max, et 2 boutons CTA à angles vifs `0px` avec flèches). Image `<img src="/assets/hero-valkyr-arena.png" fetchpriority="high" decoding="sync">` avec `filter: none`, `transform: none` et zéro calque d'assombrissement.
  3. **Ruban de Télémétrie Globale** (`#telemetry-ribbon`).
  4. **Module 1 : Live Match Replay & HUD Tactique 60 FPS** (`#live-replay-section`) + Modale Replay 4K plein écran (`#replay-fullscreen-modal`).
  5. **Module 2 : Scrollytelling Pinned Sticky en 3 Chapitres** (`#scrollytelling-track`).
  6. **Module 3 : Sélecteur Interactif d'Arsenal (4 Classes)** (`#arsenal-section`).
  7. **Module 4 : Catalogue Filtrable des Tournois & Ligues Privées** (`#arenas-catalog` — où chaque carte permet soit d'ouvrir la page dédiée de l'arène `GET /tournaments/{slug}`, soit de s'inscrire via Buy-in) + **Classement Général ELO** (`#leaderboard-section`).
- **INTERDICTION FORMELLE sur `GET /` :** `#auth-split-module` et `#operator-cockpit` sont **strictement supprimés** de la page d'accueil `GET /` !

---

### 2.3 Page 2 — Authentification Split-Screen 50/50 Dédiée (`GET /login` & `GET /register` — `resources/views/auth/login.blade.php`)
- **Accès & Protection :**
  - Routes `GET /login` (route nommée `login`) et `GET /register` (route nommée `register`) protégées par le middleware `guest` (tout utilisateur déjà authentifié est redirigé automatiquement vers `GET /dashboard`).
- **Mise en page 50/50 Asymmetric Split-Screen plein écran (`min-h-dvh`) :**
  - **Moitié Gauche (50%) :** Artwork 4K/8K pur sans flou (`/assets/hero-valkyr-arena.png` ou `/assets/class-valkyrie.png` avec `filter: none`, `transform: none`), déclaration éditoriale souveraine, télémétrie du Grand Livre en direct, et **Simulateur de Rôles Démo en 1 Clic** (`ADMIN` `VKR_COMMANDER`, `ORGANIZER` `VKR_ARBITER`, `ORGANIZER` `RIVAL_HOST`, `PLAYER` `VKR_NYX`, `PLAYER` `KESTREL_ZERO`, `PLAYER` `AETHER_9`, `PLAYER` `SOVEREIGN_X`).
  - **Moitié Droite (50%) :**
    * Lien de navigation `← Retour à l'Arène (/)` en haut.
    * Onglets soulignés `Connexion (Sign in)` ⇄ `Créer un compte (Register)` (l'onglet initial actif dépend de l'URL `/login` ou `/register`, avec bascule instantanée sans rechargement).
    * Formulaire d'inscription incluant le sélecteur de rôle initial (`account_type` : `PLAYER` ou `ORGANIZER` pour la démo, tout en bloquant strictement `ADMIN` côté serveur contre le Mass-Assignment) et la classe d'arsenal préférée.
    * Bouton `AFFICHER / MASQUER` (`SHOW / HIDE`) fonctionnel sur les champs mot de passe.
- **Comportement à la connexion / inscription :**
  - Dès que la connexion (classique `POST /api/auth/login` ou `/login`, ou Démo 1-clic `POST /api/auth/demo-login`) ou l'inscription (`POST /api/auth/register` ou `/register`) réussit, **le navigateur est redirigé automatiquement vers `GET /dashboard`**.

---

### 2.4 Page 3 — Espace Membre & Cockpit Opérateur Protégé (`GET /dashboard` — `resources/views/dashboard/index.blade.php`, route `dashboard`)
- **Protection :**
  - Protégé par le middleware `auth` : tout visiteur non connecté qui tente d'accéder à `GET /dashboard` est automatiquement redirigé vers `GET /login`.
- **Topbar & Sidebar du Cockpit :**
  - Identité complète de l'opérateur connecté (`gamertag`, `email`, badge `role` [`ADMIN`, `ORGANIZER`, `PLAYER`], `elo_rating` en `tabular-nums`, `preferred_class`).
  - Rappel permanent du `Solde Disponible` et du `Séquestre Verrouillé` en `tabular-nums`.
  - Lien `← Voir l'Arène Publique (/)`.
  - **Sélecteur rapide de profil Démo en 1 clic** dans le Cockpit (permettant de basculer entre `ADMIN`, `ORGANIZER`, `RIVAL_HOST`, `VKR_NYX`, `KESTREL_ZERO`, `AETHER_9`, `SOVEREIGN_X` et de recharger immédiatement `/dashboard` sous la nouvelle identité).
  - Bouton explicite **`Déconnexion`** (`POST /logout` ou `/api/auth/logout` qui invalide la session et redirige vers `GET /`).
- **Vraie Navigation par Vues/Onglets dans la Sidebar (Zéro `scrollIntoView` !) :**
  - La Sidebar comporte **4 entrées d'onglets** avec barre verticale active `2px` Or Souverain (`#E5C158`).
  - Cliquer sur un onglet bascule instantanément l'affichage entre **4 panneaux/vues distincts** (`#cockpit-panel-overview`, `#cockpit-panel-arenas`, `#cockpit-panel-ledger`, `#cockpit-panel-organizer` — **un seul panneau visible à la fois**, les 3 autres ayant la classe `hidden`) :
  1. **Vue 1 (`overview`) — `Vue d'Ensemble & Portefeuille (Dépôt / Retrait)` :**
     - Les 4 cartes KPI en grand format (`Solde Disponible`, `Séquestre Verrouillé`, `Gains Cumulés`, `Cote ELO`).
     - Sélecteur rapide de Loadout actif (`VANGUARD`, `SPECTRE`, `CHRONO`, `VALKYRIE` via `POST /api/profile/loadout`).
     - Formulaire / Modale de **Dépôt Instantané (`POST /api/wallets/deposit`)** avec boutons de montants rapides (`+50 €`, `+100 €`, `+250 €`, `+1 000 €`) et clé d'idempotence.
     - Nouveau Formulaire / Modale de **Retrait Bancaire SEPA (`POST /api/wallets/withdraw`)** avec saisie du montant en euros/centimes (`amount_cents`), IBAN de destination (ex: `FR76 3000 6000 0112 3456 7890 189`), clé d'idempotence, débitant atomiquement `balance_cents` avec écriture `WITHDRAWAL` dans `ledger_entries` après verrou `SELECT ... FOR UPDATE`.
  2. **Vue 2 (`arenas`) — `Mes Arènes, Matchs & Inscriptions` :**
     - Liste des tournois auxquels l'opérateur connecté est inscrit (`tournament_registrations`), statut de son séquestre (`REGISTERED`, `WINNER_1ST`, `WINNER_2ND`, `WINNER_3RD`, `REFUNDED`), prochains matchs à jouer, et lien direct `Ouvrir l'Arène ↗` (`GET /tournaments/{slug}`).
     - Catalogue rapide des tournois `OPEN` permettant de s'inscrire (Buy-in) en 1 clic ou d'accéder à la fiche détaillée `/tournaments/{slug}`.
  3. **Vue 3 (`ledger`) — `Grand Livre Immuable (Ledger Double-Entrée)` :**
     - Tableau complet des écritures comptables (`ledger_entries`) du portefeuille avec **filtrage instantané par type d'opération** (`ALL`, `DEPOSIT`, `WITHDRAWAL`, `BUY_IN`, `PAYOUT`, `REFUND`).
     - Colonnes en `tabular-nums` : `ID`, `Date`, `Type`, `Description / Tournoi`, `Débit`, `Crédit`, `Solde Avant`, `Solde Après` et `Clé d'Idempotence`.
  4. **Vue 4 (`organizer`) — `Console Organisateur & Arbitrage Officiel` :**
     - Création d'une nouvelle Ligue / Tournoi (`POST /api/tournaments`).
     - Liste de gestion des tournois avec indicateur de permission (`PROPRIÉTAIRE / ADMIN` vs `LECTURE SEULE`).
     - Simulateur / Saisie des scores des matchs de l'arbre (Demi-finales, Petite Finale 3e place, Grande Finale).
     - Exécution directe de l'**Homologation du Podium & Distribution 60/30/10 (`POST /api/tournaments/{id}/settle`)** avec aperçu mathématique en direct (`Rake 8%`, `Pool Net 92%`, `1er 60%`, `2e 30%`, `3e 10%`) et de l'**Annulation & Remboursement 100 % (`POST /api/tournaments/{id}/cancel`)**.

---

### 2.5 Page 4 — Page Dédiée d'une Arène / Ligue (`GET /tournaments/{tournament:slug}` — `resources/views/tournaments/show.blade.php`, route `tournaments.show`)
- **En-tête Complet de l'Arène :**
  - Fil d'Ariane (`← Retour aux Arènes (/)` et `Mon Cockpit (/dashboard)` si connecté).
  - Titre du tournoi, Jeu (`VALORANT // PROTOCOL`, `COUNTER-STRIKE 2`, `APEX // SOVEREIGN`, `LEAGUE OF LEGENDS`), Format (`5v5` / `1v1`), Badge de Statut (`OPEN`, `LOCKED`, `IN_PROGRESS`, `SETTLED`, `CANCELLED`), Gamertag de l'Organisateur.
  - Panneau financier en `tabular-nums` : Buy-in unitaire (`entry_fee_cents`), Cagnotte Séquestrée (`escrow_pool_cents`), et décomposition mathématique exacte :
    * **Commission Plateforme (`Rake 8% / 800 bps`)**
    * **Pool Net Joueurs (`92%`)**
    * **Dotation 1re Place (`60%` + reliquat d'arrondi entier)**
    * **Dotation 2e Place (`30%`)**
    * **Dotation 3e Place (`10%`)**
- **Bouton d'Action Contextuel :**
  - Si `OPEN` et connecté (non inscrit) : Bouton primaire `S'inscrire au Tournoi (Buy-in) ↗` avec sélecteur de classe d'arsenal (`VANGUARD`, `SPECTRE`, `CHRONO`, `VALKYRIE`).
  - Si `OPEN` et non connecté (`@guest`) : Bouton `Se connecter pour rejoindre ↗` redirigeant vers `/login`.
  - Si l'opérateur connecté est déjà inscrit : Badge émeraude/or `✓ DÉJÀ INSCRIT EN SÉQUESTRE` avec montant verrouillé et classe choisie.
- **Arbre de Tournoi Interactif (Bracket Complet) :**
  - Visualisation structurée des **Demi-finales (Match 1 & Match 2)**, de la **Petite Finale (Match 3e Place — 10% du Pool Net)** et de la **Grande Finale (1re Place 60% / 2e Place 30%)** avec les opérateurs inscrits, leurs cotes ELO, les scores des matchs et le podium final couronné si le statut est `SETTLED`.
- **Liste des Opérateurs Inscrits (Roster) :**
  - Grille/Tableau des participants inscrits avec numéro de seed, Gamertag, classe d'arsenal, cote ELO, statut (`REGISTERED`, `WINNER_1ST`, `WINNER_2ND`, `WINNER_3RD`, `REFUNDED`), montant payé en séquestre et cashprize remporté.
- **Journal d'Audit Séquestre de l'Arène :**
  - Tableau des écritures `ledger_entries` rattachées à ce `tournament_id` (`BUY_IN_ESCROW_DEBIT`, `BUY_IN_ESCROW_CREDIT`, `ESCROW_RELEASE_DEBIT`, `PLATFORM_RAKE_CREDIT`, `PRIZE_PAYOUT_1ST`, `PRIZE_PAYOUT_2ND`, `PRIZE_PAYOUT_3RD`, `TOURNAMENT_REFUND_*`).
- **Panneau d'Arbitrage Officiel (visible et actif pour l'`ORGANIZER` propriétaire ou `ADMIN`) :**
  - Formulaire de sélection du Podium (`1st`, `2nd`, `3rd`) et bouton `Homologuer & Distribuer le Cashprize (60/30/10)` (`POST /api/tournaments/{id}/settle`).
  - Formulaire d'annulation d'urgence `Annuler l'Arène & Rembourser 100 % du Séquestre` (`POST /api/tournaments/{id}/cancel`).

---

## 3. Contrat Backend ⇄ Front-End & Spécification du Retrait SEPA (`withdraw`)

### 3.1 Nouvel Endpoint de Retrait Bancaire SEPA (`POST /api/wallets/withdraw` & `POST /wallets/withdraw`)
- **Validation (`WithdrawWalletRequest`) :**
  - `amount_cents` : `required`, entier strict (`preg_match('/^-?\d+$/')`, rejetant tout `float` comme `125.50`), `min:100` (`1,00 €`), `max:50000000`.
  - `iban` : `nullable|string|max:64` (défaut : `'FR76 3000 6000 0112 3456 7890 189'`).
  - `idempotency_key` : `required|string|max:140`.
- **Exécution Atomique (`EscrowLedgerService::withdraw`) :**
  1. Ouvrir `DB::transaction()`.
  2. Vérifier l'idempotence via `LedgerEntry::where('idempotency_key', $key)->first()` :
     - Si la clé existe déjà pour le même `wallet_id`, `entry_type === WITHDRAWAL` et `debit_cents === $amountCents`, retourner `{ wallet, ledger_entry, idempotent_replay: true }` en `200 OK` sans re-débiter.
     - Si la clé existe avec des paramètres différents, lever `IdempotencyConflictException` (`409 Conflict`).
  3. Verrouiller le portefeuille de l'opérateur avec `Wallet::whereKey($userWallet->id)->lockForUpdate()->firstOrFail()`.
  4. **Strictement APRÈS l'acquisition du verrou**, vérifier `$lockedWallet->balance_cents >= $amountCents` (sinon lever `InsufficientFundsException` ➔ `422 Unprocessable Entity`).
  5. Débiter `$lockedWallet->balance_cents -= $amountCents` et insérer l'écriture immuable `LedgerEntry::TYPE_WITHDRAWAL` (`debit_cents = $amountCents`, `credit_cents = 0`, `balance_before_cents`, `balance_after_cents`).
  6. Invariant global mis à jour :
     - Pour chaque portefeuille : `wallet.balance_cents === SUM(credit_cents) - SUM(debit_cents)`.
     - Sur l'ensemble de la plateforme : `SUM(wallets.balance_cents) === SUM(DEPOSIT.credit_cents) - SUM(WITHDRAWAL.debit_cents)`.

### 3.2 Récapitulatif Complet des Routes Web & API
| Méthode & URI | Nom de Route | Middleware | Rôle / Comportement |
| :--- | :--- | :--- | :--- |
| `GET /` | `home` | `web` | Page 1 : Vitrine publique 8K (Hero, Live HUD 60 FPS, Scrollytelling, Arsenal, Catalogue, Leaderboard — **sans Auth ni Cockpit en bas de page**) |
| `GET /login` | `login` | `web`, `guest` | Page 2 : Split-Screen Auth 50/50 (onglet Connexion actif par défaut + Démo 1-clic) |
| `GET /register` | `register` | `web`, `guest` | Page 2 : Split-Screen Auth 50/50 (onglet Créer un compte actif par défaut + Démo 1-clic) |
| `GET /dashboard` | `dashboard` | `web`, `auth` | Page 3 : Cockpit Membre protégé avec 4 vues par onglets (`overview`, `arenas`, `ledger`, `organizer`) |
| `GET /tournaments/{tournament:slug}` | `tournaments.show` | `web` | Page 4 : Page dédiée d'une Arène (Bracket, Roster, Audit Ledger, Inscription Buy-in & Arbitrage) |
| `POST /api/auth/login` & `POST /login` | `api.auth.login` | `web`, `throttle:auth` | Authentification opérateur ➔ redirige vers `/dashboard` |
| `POST /api/auth/register` & `POST /register` | `api.auth.register` | `web`, `throttle:auth` | Création de compte (`PLAYER` ou `ORGANIZER` si `account_type=ORGANIZER`, jamais `ADMIN`) ➔ redirige vers `/dashboard` |
| `POST /api/auth/demo-login` & `POST /demo-login` | `api.auth.demo` | `web`, `throttle:auth` | Connexion rapide Démo 1-clic (liste blanche) ➔ redirige vers `/dashboard` |
| `POST /api/auth/logout` & `POST /logout` | `api.auth.logout` / `logout` | `web` | Déconnexion ➔ redirige vers `/` |
| `POST /api/wallets/deposit` & `POST /wallets/deposit` | `api.wallets.deposit` | `web`, `auth`, `throttle:financial` | Dépôt en centimes entiers avec clé d'idempotence |
| `POST /api/wallets/withdraw` & `POST /wallets/withdraw` | `api.wallets.withdraw` | `web`, `auth`, `throttle:financial` | Retrait bancaire SEPA en centimes entiers avec verrou pessimiste & clé d'idempotence |
| `GET /api/wallets/{wallet}` | `api.wallets.show` | `web`, `auth` | Inspection d'un portefeuille (protégé par `WalletPolicy::view` Anti-IDOR) |
| `GET /api/tournaments` | `api.tournaments.index` | `web` | Catalogue filtrable en JSON |
| `POST /api/tournaments` & `POST /tournaments` | `api.tournaments.store` | `web`, `auth`, `throttle:financial` | Création de tournoi (`ORGANIZER` ou `ADMIN` via `TournamentPolicy::create`) |
| `POST /api/tournaments/{tournament}/register` | `api.tournaments.register` | `web`, `auth`, `throttle:financial` | Buy-in atomique vers séquestre (`ORDER BY id ASC` + `lockForUpdate()`) |
| `POST /api/tournaments/{tournament}/settle` | `api.tournaments.settle` | `web`, `auth`, `throttle:financial` | Clôture officielle : Rake 8 % (`800 bps`) + Distribution 60 % / 30 % / 10 % (`TournamentPolicy::settle`) |
| `POST /api/tournaments/{tournament}/cancel` | `api.tournaments.cancel` | `web`, `auth`, `throttle:financial` | Annulation & Remboursement 100 % du séquestre (`TournamentPolicy::cancel`) |
