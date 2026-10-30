# SPEC.md — VALKYR // ARENA
**Plateforme Compétitive E-Sport de Ligues Privées, Tournois & Défis 1v1/5v5 avec Séquestre Atomique (Escrow) & Grand Livre en Partie Double**

---

## 1. Analyse de l'Espace de Travail & Inventaire SSOT (4K / Code Existant)

### 1.1 Diagnostic de l'Environnement Local
- **Répertoire racine :** `c:\Users\David\Desktop\Antigravity\bench6agents`
- **État initial :** Dossier vierge (nouveau projet greenfield, aucun fichier préexistant à écraser).
- **Runtimes détectés et vérifiés sur la machine hôte :**
  - **PHP 8.5.8 (CLI / NTS x64)** avec `pdo_sqlite`, `sqlite3`, `pdo_pgsql`, `bcmath`, `mbstring`, `sodium`, `openssl`, `gd`, `fileinfo`, `zip`, `Zend OPcache`.
  - **Composer 2.10.3**
  - **Node.js v24.19.0 & npm 11.17.0**
  - **SQLite (via PDO)** & **PostgreSQL 18 (Service actif)**

### 1.2 Stack Technique Retenue
- **Framework Backend & Full-Stack :** **PHP 8.5 + Laravel 13**
- **Base de Données :** **SQLite** (`database/database.sqlite` par défaut dans `.env` pour une exécution locale instantanée et déterministe, et `:memory:` / SQLite isolé pour `php artisan test`, avec compatibilité totale **PostgreSQL 18**).
- **Front-End & UI :** **Blade + Tailwind CSS v4 (Vite) + Architecture JS/TS Modulaire Haute Fréquence (60 FPS Canvas HUD, Scrollytelling Sticky Track, État Réactif & Fetch JSON/Web Dual)**.
- **Dualité Réponse Web / JSON :** Chaque contrôleur transactionnel répond en **JSON structuré** lorsque la requête contient `Accept: application/json` (ou `X-Requested-With: XMLHttpRequest`) ET supporte la redirection / rendu Web classique avec messages flash, permettant à la fois :
  1. L'exécution ultra-rapide des suites de tests PHPUnit/Pest (`Lead Developer` & `Red Team`).
  2. L'interaction fluide sans rechargement brutal dans le navigateur Playwright (`Front End`, `Auditor QA` & `SRE & Perf`).

### 1.3 Single Source of Truth (SSOT) — Assets Visuels 4K/8K Sur-Mesure
L'agent `Front End` doit générer proactivement (via l'outil `generate_image`) et stocker dans `public/assets/` les visuels suivants (interdiction absolue d'utiliser des URLs Unsplash génériques ou des placeholders cassés) :
1. `public/assets/hero-valkyr-arena.png` : Visuel Hero 8K sur-mesure représentant une arène tactique cyber-fantasy / sci-fi monumentale éclairée en rim-light studio (accents titane glacé `#D8E5F8` et or souverain `#E5C158` sur la moitié droite, et vide d'obsidienne abyssal naturel `#030407` sur la moitié gauche derrière le bloc typographique, zéro flou bokeh, zéro texte incrusté dans l'image).
2. `public/assets/live-combat-hud.png` : Capture d'action tactique 4K/8K d'un affrontement 5v5 en arène compétitive servant de fond optique au module **Live Match Replay & HUD Tactique 60 FPS**.
3. `public/assets/class-vanguard.png` : Portrait studio 4K de l'archétype **VANGUARD // AEGIS-01** (armure balistique lourde en titane mat et bouclier cinétique).
4. `public/assets/class-spectre.png` : Portrait studio 4K de l'archétype **SPECTRE // KESTREL-V** (opérateur furtif de reconnaissance haute vélocité).
5. `public/assets/class-chrono.png` : Portrait studio 4K de l'archétype **CHRONO // ORION-IX** (spécialiste de distorsion tactique et télémétrie orbitale).
6. `public/assets/class-valkyrie.png` : Portrait studio 4K de l'archétype **VALKYRIE // SOVEREIGN-PRIME** (commandante d'assaut aérien aux accents d'or souverain `#E5C158`).

---

## 2. Architecture Base de Données, Ledger en Partie Double & Sécurité

### 2.1 Schéma Relationnel Exact & Index SQL

#### Table `users`
| Colonne | Type SQL | Contraintes & Description |
| :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Primary Key, Auto-increment |
| `name` | `VARCHAR(120)` | Nom complet ou nom d'affichage |
| `gamertag` | `VARCHAR(60)` | `UNIQUE`, `NOT NULL`, ex: `VKR_NYX`, `SOVEREIGN_01` |
| `email` | `VARCHAR(190)` | `UNIQUE`, `NOT NULL` |
| `password` | `VARCHAR(255)` | Hash Bcrypt/Argon2id |
| `role` | `VARCHAR(20)` | `NOT NULL DEFAULT 'PLAYER'` — Valeurs : `'ADMIN'`, `'ORGANIZER'`, `'PLAYER'` |
| `elo_rating` | `INTEGER` | `NOT NULL DEFAULT 1500` (Score MMR/ELO en `tabular-nums`) |
| `preferred_class` | `VARCHAR(40)` | `NOT NULL DEFAULT 'VANGUARD'` (`VANGUARD`, `SPECTRE`, `CHRONO`, `VALKYRIE`) |
| `remember_token` | `VARCHAR(100)` | Nullable |
| `created_at` / `updated_at` | `TIMESTAMP` | Index sur `role`, `elo_rating DESC` |

#### Table `wallets`
| Colonne | Type SQL | Contraintes & Description |
| :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Primary Key, Auto-increment |
| `user_id` | `BIGINT UNSIGNED` | `UNIQUE`, `NOT NULL`, FK `users(id) ON DELETE CASCADE` |
| `balance_cents` | `BIGINT` | `NOT NULL DEFAULT 0` — Solde disponible en centimes entiers (`>= 0`) |
| `locked_escrow_cents` | `BIGINT` | `NOT NULL DEFAULT 0` — Montant engagé en séquestre par ce joueur (`>= 0`) |
| `lifetime_won_cents` | `BIGINT` | `NOT NULL DEFAULT 0` — Cumul historique des cashprizes remportés |
| `currency` | `CHAR(3)` | `NOT NULL DEFAULT 'EUR'` |
| `created_at` / `updated_at` | `TIMESTAMP` | Contrainte logique : `balance_cents >= 0` |

> **Comptes Système Obligatoires (créés par le `DatabaseSeeder` et auto-initialisés par le service Ledger) :**
> - Utilisateur système **Escrow Vault** (`email: escrow.vault@valkyr.arena`, `role: ADMIN`) possédant le portefeuille séquestre central où transitent les fonds verrouillés si besoin de contrepartie globale.
> - Utilisateur système **Platform Treasury** (`email: treasury@valkyr.arena`, `role: ADMIN`) possédant le portefeuille qui reçoit la commission plateforme de **8 % (`800 bps`)** lors de la clôture d'un tournoi.

#### Table `tournaments`
| Colonne | Type SQL | Contraintes & Description |
| :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Primary Key, Auto-increment |
| `organizer_id` | `BIGINT UNSIGNED` | `NOT NULL`, FK `users(id) ON DELETE RESTRICT` |
| `slug` | `VARCHAR(140)` | `UNIQUE`, `NOT NULL` |
| `title` | `VARCHAR(160)` | `NOT NULL` |
| `game` | `VARCHAR(80)` | `NOT NULL` (`VALORANT // PROTOCOL`, `COUNTER-STRIKE 2`, `APEX // SOVEREIGN`, `LEAGUE OF LEGENDS`) |
| `format` | `VARCHAR(20)` | `NOT NULL DEFAULT '5v5'` (`1v1`, `5v5`) |
| `status` | `VARCHAR(24)` | `NOT NULL DEFAULT 'OPEN'` — Machine à états : `'OPEN'`, `'LOCKED'`, `'IN_PROGRESS'`, `'SETTLED'`, `'CANCELLED'` |
| `entry_fee_cents` | `BIGINT` | `NOT NULL` (`> 0`, frais d'entrée / Buy-in par participant en centimes) |
| `escrow_pool_cents` | `BIGINT` | `NOT NULL DEFAULT 0` (Somme exacte actuellement verrouillée en séquestre) |
| `sponsored_Guarantee_cents` | `BIGINT` | `NOT NULL DEFAULT 0` (Dotation additionnelle éventuelle) |
| `rake_bps` | `INTEGER` | `NOT NULL DEFAULT 800` (800 points de base = 8.00 % strict) |
| `platform_rake_cents` | `BIGINT` | `NOT NULL DEFAULT 0` (Commission prélevée à la clôture) |
| `net_prize_pool_cents` | `BIGINT` | `NOT NULL DEFAULT 0` (Cashprize net distribué aux gagnants à la clôture) |
| `max_participants` | `INTEGER` | `NOT NULL DEFAULT 16` (`>= 2`) |
| `registered_count` | `INTEGER` | `NOT NULL DEFAULT 0` |
| `first_place_user_id` | `BIGINT UNSIGNED` | Nullable, FK `users(id)` |
| `second_place_user_id` | `BIGINT UNSIGNED` | Nullable, FK `users(id)` |
| `third_place_user_id` | `BIGINT UNSIGNED` | Nullable, FK `users(id)` |
| `settlement_idempotency_key` | `VARCHAR(120)` | Nullable, `UNIQUE` |
| `starts_at` | `TIMESTAMP` | Nullable |
| `settled_at` | `TIMESTAMP` | Nullable |
| `created_at` / `updated_at` | `TIMESTAMP` | Index sur `(status, game)`, `(entry_fee_cents)`, `(organizer_id)` |

#### Table `tournament_registrations`
| Colonne | Type SQL | Contraintes & Description |
| :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Primary Key, Auto-increment |
| `tournament_id` | `BIGINT UNSIGNED` | `NOT NULL`, FK `tournaments(id) ON DELETE CASCADE` |
| `user_id` | `BIGINT UNSIGNED` | `NOT NULL`, FK `users(id) ON DELETE CASCADE` |
| `loadout_class` | `VARCHAR(40)` | `NOT NULL DEFAULT 'VANGUARD'` |
| `paid_entry_fee_cents` | `BIGINT` | `NOT NULL` |
| `prize_won_cents` | `BIGINT` | `NOT NULL DEFAULT 0` |
| `status` | `VARCHAR(24)` | `NOT NULL DEFAULT 'REGISTERED'` (`REGISTERED`, `WINNER_1ST`, `WINNER_2ND`, `WINNER_3RD`, `ELIMINATED`, `REFUNDED`) |
| `idempotency_key` | `VARCHAR(120)` | `UNIQUE`, `NOT NULL` |
| `created_at` / `updated_at` | `TIMESTAMP` | **Contrainte `UNIQUE(tournament_id, user_id)` obligatoire** |

#### Table `ledger_entries` (Append-Only Immutable Double-Entry Ledger)
| Colonne | Type SQL | Contraintes & Description |
| :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Primary Key, Auto-increment |
| `wallet_id` | `BIGINT UNSIGNED` | `NOT NULL`, FK `wallets(id) ON DELETE RESTRICT` |
| `tournament_id` | `BIGINT UNSIGNED` | Nullable, FK `tournaments(id) ON DELETE RESTRICT` |
| `actor_id` | `BIGINT UNSIGNED` | Nullable, FK `users(id) ON DELETE RESTRICT` (Utilisateur ayant déclenché l'écriture) |
| `entry_type` | `VARCHAR(40)` | `NOT NULL` (`DEPOSIT`, `WITHDRAWAL`, `BUY_IN_ESCROW_DEBIT`, `BUY_IN_ESCROW_CREDIT`, `PRIZE_PAYOUT_1ST`, `PRIZE_PAYOUT_2ND`, `PRIZE_PAYOUT_3RD`, `ESCROW_RELEASE_DEBIT`, `PLATFORM_RAKE_CREDIT`, `TOURNAMENT_REFUND_DEBIT`, `TOURNAMENT_REFUND_CREDIT`) |
| `debit_cents` | `BIGINT` | `NOT NULL DEFAULT 0` (`>= 0`) |
| `credit_cents` | `BIGINT` | `NOT NULL DEFAULT 0` (`>= 0`) |
| `balance_before_cents` | `BIGINT` | `NOT NULL` |
| `balance_after_cents` | `BIGINT` | `NOT NULL` (`balance_before_cents + credit_cents - debit_cents`) |
| `idempotency_key` | `VARCHAR(140)` | `UNIQUE`, `NOT NULL` |
| `description` | `VARCHAR(255)` | `NOT NULL` |
| `metadata` | `JSON / TEXT` | Nullable |
| `created_at` | `TIMESTAMP` | `NOT NULL`, Index sur `(wallet_id, created_at DESC)` et `(tournament_id)` |

---

### 2.2 Invariants Financiers, Machine à États & Anti-TOCTOU

1. **Immuabilité Absolue du Grand Livre (`ledger_entries`) :**
   - Aucune ligne de `ledger_entries` ne peut jamais être modifiée (`UPDATE`) ou supprimée (`DELETE`).
   - Le modèle Eloquent `LedgerEntry` DOIT intercepter `updating` et `deleting` dans `booted()` et lever une exception `DomainException` / `LogicException` (`"Immutable ledger: entries cannot be updated or deleted."`).
   - La migration SQL DOIT également créer deux triggers SQL (`prevent_ledger_entries_update` et `prevent_ledger_entries_delete`) utilisant `RAISE(ABORT, 'Immutable ledger entry')` sur SQLite (ou une fonction trigger sur PostgreSQL) afin que même une requête `DB::table('ledger_entries')->update(...)` ou `delete()` échoue immédiatement au niveau du moteur SQL.

2. **Invariant de Conservation Monétaire & Solde Portefeuille :**
   - Pour tout `wallet` :
     $$\text{wallet.balance\_cents} = \sum \text{credit\_cents} - \sum \text{debit\_cents}$$
   - Pour chaque ligne de `ledger_entries` :
     $$\text{balance\_after\_cents} = \text{balance\_before\_cents} + \text{credit\_cents} - \text{debit\_cents}$$
   - Aucun `wallet.balance_cents` ne peut jamais devenir négatif (`< 0`). Toute tentative de dépôt/retrait/buy-in avec un montant `<= 0` ou non entier est rejetée en `422 Unprocessable Entity`.

3. **Protocole Atomique d'Inscription (Buy-in) & Anti-TOCTOU :**
   - Lors de `POST /api/tournaments/{tournament}/register` (ou `/tournaments/{tournament}/register`) :
     1. Vérifier la présence de `idempotency_key`. Si une inscription avec cette clé existe déjà pour ce joueur et ce tournoi, retourner l'état existant de façon idempotente (`200 OK`) sans re-débiter. Si la clé est réutilisée pour une opération différente, retourner `409 Conflict`.
     2. Ouvrir une transaction ACID (`DB::transaction()`).
     3. Verrouiller la ligne du tournoi (`Tournament::whereKey($id)->lockForUpdate()->firstOrFail()`).
     4. Identifier les portefeuilles à verrouiller : le portefeuille du joueur (`$playerWalletId`) et le portefeuille système `Escrow Vault` (`$escrowWalletId`). **Les verrouiller par ordre d'ID croissant strict (`Wallet::whereIn('id', [$playerWalletId, $escrowWalletId])->orderBy('id', 'asc')->lockForUpdate()->get()`)** pour empêcher tout deadlock concurrent.
     5. **Strictement APRÈS l'acquisition des verrous**, vérifier :
        - `$tournament->status === 'OPEN'` (sinon `409 Conflict` / `422`).
        - `$tournament->registered_count < $tournament->max_participants` (sinon `409 Conflict` : tournoi complet).
        - Non-existence d'une inscription pour `(tournament_id, user_id)` (sinon `409 Conflict` : déjà inscrit).
        - `$playerWallet->balance_cents >= $tournament->entry_fee_cents` (sinon `422 Unprocessable Entity` : fonds insuffisants).
     6. Débiter `$playerWallet` de `entry_fee_cents` (écriture `BUY_IN_ESCROW_DEBIT` avec clé `{$idempotencyKey}:player_debit`), incrémenter `$playerWallet->locked_escrow_cents += entry_fee_cents`.
     7. Créditer `$escrowWallet` de `entry_fee_cents` (écriture `BUY_IN_ESCROW_CREDIT` avec clé `{$idempotencyKey}:escrow_credit`).
     8. Incrémenter `$tournament->escrow_pool_cents += entry_fee_cents` et `$tournament->registered_count += 1` (si `registered_count === max_participants`, passer automatiquement `$tournament->status = 'LOCKED'`).
     9. Créer la ligne dans `tournament_registrations`.

4. **Protocole Atomique de Clôture & Distribution du Cashprize (`settle`) :**
   - Lors de `POST /api/tournaments/{tournament}/settle` :
     1. **Contrôle d'Accès Strict (Anti-IDOR / Anti-BFLA via `TournamentPolicy::settle`) :**
        - Seul un utilisateur ayant `role === 'ADMIN'` OU l'organisateur propriétaire légitime du tournoi (`$user->role === 'ORGANIZER' && (int) $tournament->organizer_id === (int) $user->id`) est autorisé.
        - Tout simple `PLAYER` ou un autre `ORGANIZER` n'étant pas propriétaire du tournoi DOIT recevoir un **`403 Forbidden`** immédiat.
     2. Ouvrir une transaction ACID et verrouiller le tournoi (`lockForUpdate()`).
     3. Si le tournoi est déjà `SETTLED` avec la même `idempotency_key`, retourner la réponse de façon idempotente (`200 OK`). S'il est déjà `SETTLED` avec une autre clé ou `CANCELLED`, rejeter avec **`409 Conflict`** (interdiction absolue de distribuer deux fois le cashprize !).
     4. Vérifier que le tournoi est en statut `OPEN`, `LOCKED` ou `IN_PROGRESS`, que `$tournament->escrow_pool_cents > 0`, et que les gagnants soumis (`first_place_user_id`, `second_place_user_id`, `third_place_user_id`) sont tous distincts et **effectivement inscrits** dans `tournament_registrations` pour ce tournoi (sinon `422 Unprocessable Entity`).
     5. Verrouiller tous les `wallets` impliqués (`Escrow Vault`, `Platform Treasury`, `1st`, `2nd`, `3rd`) par **ordre d'ID croissant (`ORDER BY id ASC`)**.
     6. **Calcul arithmétique entier exact (Zéro Float) :**
        - `$totalPool = (int) $tournament->escrow_pool_cents;`
        - `$rakeCents = intdiv($totalPool * 800, 10000);` (Commission plateforme de **8 %**)
        - `$netPoolCents = $totalPool - $rakeCents;`
        - Si 3 gagnants sont fournis (mode standard 60 % / 30 % / 10 %) :
          * `$secondPrize = intdiv($netPoolCents * 3000, 10000);` (**30 %**)
          * `$thirdPrize = intdiv($netPoolCents * 1000, 10000);` (**10 %**)
          * `$firstPrize = $netPoolCents - $secondPrize - $thirdPrize;` (**60 %** + reliquat d'arrondi éventuel)
        - Si `third_place_user_id` est `null` (ex: défi 1v1 à 2 joueurs uniquement) :
          * `$secondPrize = intdiv($netPoolCents * 3000, 10000);` (**30 %**)
          * `$thirdPrize = 0;`
          * `$firstPrize = $netPoolCents - $secondPrize;` (**70 %**)
        - Vérification d'invariant obligatoire avant commit :
          `assert($rakeCents + $firstPrize + $secondPrize + $thirdPrize === $totalPool)`
     7. Débiter le portefeuille `Escrow Vault` de `$totalPool` (`ESCROW_RELEASE_DEBIT`), créditer `Platform Treasury` de `$rakeCents` (`PLATFORM_RAKE_CREDIT`), et créditer les portefeuilles des gagnants de `$firstPrize` (`PRIZE_PAYOUT_1ST`), `$secondPrize` (`PRIZE_PAYOUT_2ND`) et `$thirdPrize` (`PRIZE_PAYOUT_3RD`).
     8. Mettre à jour `locked_escrow_cents` des participants inscrits, mettre à jour `elo_rating` (`+25` pour le 1er, `+10` pour le 2e, `+5` pour le 3e), passer `$tournament->status = 'SETTLED'`, enregistrer `platform_rake_cents`, `net_prize_pool_cents`, `settled_at`, et `settlement_idempotency_key`.

5. **Protocole Atomique d'Annulation & Remboursement Intégral (`cancel`) :**
   - Lors de `POST /api/tournaments/{tournament}/cancel` :
     - Autorisé uniquement pour `ADMIN` ou l'`ORGANIZER` propriétaire du tournoi (`403 Forbidden` sinon).
     - Si le tournoi est déjà `SETTLED` ou `CANCELLED`, retourner `409 Conflict`.
     - Verrouiller le tournoi et tous les `wallets` des participants inscrits + `Escrow Vault` par `ORDER BY id ASC`.
     - Rembourser **100 %** de `paid_entry_fee_cents` du compte `Escrow Vault` vers le `wallet` de chaque joueur inscrit (zéro commission prélevée sur un tournoi annulé), remettre `escrow_pool_cents = 0` et `status = 'CANCELLED'`.

---

## 3. Contrat Backend ⇄ Front-End & Matrice des Rôles

### 3.1 Matrice des Rôles & Permissions
| Action / Route | `PLAYER` | `ORGANIZER` | `ADMIN` | Règle de Sécurité (Anti-IDOR / Anti-BFLA) |
| :--- | :---: | :---: | :---: | :--- |
| Consulter sa propre wallet & son ledger | ✅ | ✅ | ✅ | Vérification stricte `wallet.user_id === auth()->id()` (sauf `ADMIN`) |
| Consulter la wallet d'un autre joueur (`/api/wallets/{id}`) | ❌ (`403`) | ❌ (`403`) | ✅ | Policy `WalletPolicy::view` bloque tout accès croisé |
| Déposer des fonds sur son portefeuille (`POST /api/wallets/deposit`) | ✅ | ✅ | ✅ | Crédite uniquement `auth()->user()->wallet` |
| S'inscrire (Buy-in) à un tournoi `OPEN` | ✅ | ✅ | ✅ | Transaction ACID + verrou pessimiste + clé d'idempotence |
| Créer un nouveau tournoi / ligue privée | ❌ (`403`) | ✅ | ✅ | `organizer_id` forcé à `auth()->id()` côté serveur (Mass-Assignment protégé) |
| Clôturer un tournoi & distribuer le Cashprize (`settle`) | ❌ (`403`) | ✅ (Propriétaire) | ✅ (Tous) | `TournamentPolicy::settle` vérifie `$user->isAdmin() \|\| $tournament->organizer_id === $user->id` |
| Annuler un tournoi & rembourser l'Escrow (`cancel`) | ❌ (`403`) | ✅ (Propriétaire) | ✅ (Tous) | `TournamentPolicy::cancel` vérifie `$user->isAdmin() \|\| $tournament->organizer_id === $user->id` |

### 3.2 Routes Web & API et Noms de Champs Exacts (`name="..."`)

> **Important :** Toutes les routes d'action doivent être accessibles à la fois en préfixe `/api/...` et en route web directe (ou unifiées via les mêmes contrôleurs) afin que les appels `fetch('/api/...')` du Front-End et les tests HTTP fonctionnent sans aucune friction.

1. **`GET /` (Route nommée `home`) :**
   - Affiche la plateforme complète **VALKYR // ARENA** (Header, Hero 8K, Live Match Replay & HUD 60 FPS + Modale Plein Écran, Scrollytelling Pinned Sticky 3 Chapitres, Sélecteur d'Arsenal 4 Classes, Catalogue Filtrable des Tournois, Split-Screen Auth 50/50, et Cockpit Joueur/Organisateur avec Arbre de Tournoi et Grand Livre).
   - Injecte les données initiales hydratées depuis la base de données (`tournaments`, `currentUser`, `wallet`, `ledgerEntries`, `leaderboard`, `platformStats`).

2. **`POST /api/auth/register` & `POST /register` :**
   - Champs exacts : `name`, `gamertag`, `email`, `password`, `password_confirmation`, `preferred_class` (optionnel : `VANGUARD`, `SPECTRE`, `CHRONO`, `VALKYRIE`).
   - Règle de sécurité : Le champ `role` envoyé par un client est **toujours ignoré** (forcé à `'PLAYER'`) pour empêcher toute élévation de privilèges par Mass-Assignment. Crée automatiquement le `Wallet` associé avec un bonus de bienvenue initial optionnel ou `0` centimes.

3. **`POST /api/auth/login` & `POST /login` :**
   - Champs exacts : `email`, `password`.
   - Rate-limiting : `throttle:10,1` (retourne `429 Too Many Requests` en cas de brute-force).

4. **`POST /api/auth/logout` & `POST /logout` :**
   - Déconnecte la session courante.

5. **`POST /api/wallets/deposit` :**
   - Champs exacts : `amount_cents` (`integer`, min `100`, max `10000000`), `idempotency_key` (`string`, `required`, max `120`).
   - Retourne `200`/`201` avec `{ wallet, ledger_entry }`.

6. **`GET /api/wallets/{wallet}` :**
   - Protégé par `WalletPolicy::view` (`403 Forbidden` si `wallet.user_id !== auth()->id()` et non `ADMIN`).
   - Retourne le portefeuille et ses écritures de ledger.

7. **`GET /api/tournaments` :**
   - Paramètres de filtre optionnels : `game`, `status`, `format`, `min_Buy_in_cents`, `max_buy_in_cents`, `min_prize_cents`, `search`.
   - Retourne la liste des tournois avec leurs inscriptions et le décompte total.

8. **`POST /api/tournaments` :**
   - Autorisé pour `ORGANIZER` et `ADMIN` (`403 Forbidden` pour `PLAYER`).
   - Champs exacts : `title`, `game`, `format` (`1v1` ou `5v5`), `entry_fee_cents` (`integer > 0`), `max_participants` (`integer`, min `2`, max `128`), `starts_at` (optionnel).

9. **`POST /api/tournaments/{tournament}/register` :**
   - Champs exacts : `idempotency_key` (`string`, `required`), `loadout_class` (`string`, optionnel : `VANGUARD`, `SPECTRE`, `CHRONO`, `VALKYRIE`).
   - Exécute le verrouillage pessimiste `SELECT ... FOR UPDATE` par `ORDER BY id ASC`, vérifie `balance_cents >= entry_fee_cents` après verrou, et transfère les fonds vers l'Escrow.

10. **`POST /api/tournaments/{tournament}/settle` :**
    - Autorisé uniquement pour `ADMIN` ou l'`ORGANIZER` propriétaire du tournoi (`403 Forbidden` sinon).
    - Champs exacts : `first_place_user_id` (`integer`, `required`), `second_place_user_id` (`integer`, `required`), `third_place_user_id` (`integer`, `nullable`), `idempotency_key` (`string`, `required`).
    - Calcule et prélève les **8 % (`800 bps`)** de commission plateforme et distribue le pool net (**60 % / 30 % / 10 %**) en centimes entiers.

11. **`POST /api/tournaments/{tournament}/cancel` :**
    - Autorisé uniquement pour `ADMIN` ou l'`ORGANIZER` propriétaire du tournoi (`403 Forbidden` sinon).
    - Champs exacts : `idempotency_key` (`string`, `required`), `reason` (`string`, optionnel).
    - Rembourse atomiquement 100 % des frais d'entrée séquestrés aux joueurs inscrits.

---

## 4. Direction Artistique "AAA Gaming & Apple/Studio Restraint" (Pour `Front End`)

### 4.1 Palette OKLCH, Typographie & Pureté Optique
- Se référer à `DESIGN_SYSTEM.md` pour l'intégralité des variables CSS `:root` et des tokens OKLCH.
- **Typographie Google Fonts à charger dans `<head>` :**
  - `Syne:wght@700;800` (`--font-display`)
  - `Instrument+Serif:ital@0;1` (`--font-serif-italic` — vrai italique obligatoire sur la ligne 2 du Hero)
  - `Plus+Jakarta+Sans:wght@400;500;600;700` (`--font-sans`)
  - `JetBrains+Mono:wght@400;500;600;700` (`--font-mono` avec `tabular-nums` universel)

### 4.2 Header & Hero (Loi des 4 Éléments & Rythme 2 Lignes)
- **Header (`position: fixed`, `top: 0`, `z-index: 50`) :**
  - `background: transparent` (`rgba(0, 0, 0, 0)`) lorsque `window.scrollY === 0`, puis transition fluide vers `rgba(3, 4, 7, 0.85)` avec `backdrop-filter: blur(16px)` et `border-b border-white/[0.08]` au scroll.
  - **Marque :** `✦ VALKYR` sur une seule ligne (sans sous-titre gris en dessous).
  - **4 Liens de navigation non numérotés :** `Arènes`, `Live Replay`, `Arsenal`, `Classement`.
  - **Contrôles à droite :** Switch fonctionnel `FR / EN`, lien `Connexion`, et bouton CTA à angles vifs (`0px`) `Créer une Ligue ↗`.
- **Section Hero (`min-h-screen`, Grille Asymétrique + Artwork 8K Pur) :**
  - Image `<img src="/assets/hero-valkyr-arena.png" fetchpriority="high" decoding="sync">` avec `filter: none`, `transform: none`, `opacity: 1`, et **zéro div d'assombrissement** par-dessus.
  - **Les 4 éléments stricts du Hero :**
    1. **Kicker :** `PROTOCOLE D'ESCORTE COMPÉTITIF // SÉQUESTRE ATOMIQUE 0.08`
    2. **Titre `<h1>` sur exactement 2 lignes :**
       - Ligne 1 (Romain Droit `Syne`) : `SOUVERAINETÉ TACTIQUE.`
       - Ligne 2 (*Vrai Italique* `Instrument Serif` Or Souverain `#E5C158`) : `*L'Arène du Séquestre Absolu.*`
    3. **Sous-titre (2 lignes max, 17 mots) :**
       `Ligues privées et tournois haute fréquence. Vos enjeux verrouillés sur grand livre immuable, réglés à la milliseconde.`
    4. **2 Boutons CTA à angles vifs (`0px`/`2px`) avec flèches :**
       - Primaire (`bg-[#D8E5F8] text-[#030407]`) : `Rejoindre l'Arène ↗`
       - Secondaire (`border border-white/20 text-[#F4F7FB]`) : `Voir un Match Live ▶`

### 4.3 Les 5 Modules Interactifs AAA Gaming (Sous le Hero)
1. **Fenêtre "Live Match Replay & HUD Tactique 60 FPS" + Modale Plein Écran :**
   - Cadre `1px` (`border-white/[0.08]`) avec barre supérieure `REC ● 00:42:18 // 60 FPS // 4K CAPTURE // ANTI-CHEAT VERIFIED`.
   - Visuel `public/assets/live-combat-hud.png` combiné à un `<canvas>` temps réel 60 FPS (balayage radar, vecteurs de trajectoires d'escouades, barres HP/Bouclier en `tabular-nums`, journal de combat en direct).
   - Bouton `Ouvrir le Replay Tactique 4K ⤢` ouvrant une **Modale Replay Plein Écran** fonctionnelle avec timeline manipulable (`<input type="range">` ou barre interactive), contrôle Play/Pause, vitesse (`0.5x`, `1x`, `2x`) et sélecteur de POV entre 4 joueurs pros (`VKR_NYX`, `KESTREL_ZERO`, `AETHER_9`, `SOVEREIGN_X`).
2. **Scrollytelling "Pinned Sticky" en 3 Chapitres :**
   - Piste de scroll (`min-h-[240vh]`) avec conteneur `sticky top-24` affichant un artefact vectoriel/Canvas central (Noyau de Séquestre Cryptographique) qui évolue selon la progression du scroll natif à travers :
     - `01. Verrouillage Séquestre Atomique` (Verrou pessimiste `SELECT ... FOR UPDATE` & isolation ACID)
     - `02. Télémétrie Anti-Triche Temps Réel` (Validation 128-tick & empreinte déterministe du match)
     - `03. Distribution Instantanée du Cashprize` (Prélèvement 800 bps & règlement 60 % / 30 % / 10 % en centimes entiers)
3. **Sélecteur Interactif de Classe / Loadout (Arsenal) :**
   - 4 onglets d'archétypes cliquables (`VANGUARD // AEGIS-01`, `SPECTRE // KESTREL-V`, `CHRONO // ORION-IX`, `VALKYRIE // SOVEREIGN-PRIME`) affichant l'artwork 4K dédié (`/assets/class-*.png`), l'arme principale, la capacité tactique, et 4 barres de statistiques animées en `tabular-nums` (`DPS`, `Mobilité`, `Armure`, `Vélocité`) + bouton permettant de définir cette classe comme Loadout actif pour l'inscription aux tournois.
4. **Catalogue Filtrable des Tournois & Ligues Privées :**
   - Filtres facettés instantanés : Jeu, Buy-in, Taille du Cashprize, Statut (`OPEN`, `LOCKED`, `SETTLED`, `CANCELLED`) + champ de recherche en temps réel + compteur `X ARÈNES ACTIVES` en `tabular-nums`.
   - Cartes alignées au cordeau (hauteur égale, boutons alignés au pixel près) avec bouton d'ouverture du tiroir/modale d'inscription et de détail du tournoi.
5. **Split-Screen Auth 50/50 & Cockpit Joueur / Organisateur :**
   - **Split-Screen Auth 50/50 :** Bascule fluide `Connexion` (`Sign in`) ⇄ `Créer un compte` (`Create an account`), bouton `Afficher / Masquer` sur le mot de passe, et boutons de connexion rapide Démo en 1 clic (`ADMIN`, `ORGANIZER`, `PLAYER 1`, `PLAYER 2`, `PLAYER 3`) pour faciliter les tests et démonstrations.
   - **Cockpit Joueur / Organisateur :**
     - Sidebar avec indicateur actif vertical de `2px` (`border-l-2 border-[#E5C158]`).
     - Carte **Solde & Séquestre** en grand format (`Solde Disponible`, `Séquestre Verrouillé`, `Gains Cumulés`, `Score ELO`) + tableau du **Grand Livre Immuable (`ledger_entries`)** avec clés d'idempotence et soldes avant/après en `tabular-nums`.
     - **Arbre de Tournoi (Bracket Interactif)** illustrant les Quarts, Demi-finales, Petite Finale (3e place) et Grande Finale avec statuts lumineux OKLCH (`[LIVE]`, `[SETTLED]`, `[UPCOMING]`).
     - **Modales Transactionnelles Réelles** reliées au backend : Dépôt Portefeuille, Création de Ligue/Tournoi, Inscription Buy-in, et Validation Officielle de Score / Distribution du Cashprize (60/30/10 après Rake de 8 %).

---

## 5. Jeux de Données Initiaux Obligatoires (`DatabaseSeeder`)
Le `DatabaseSeeder` DOIT créer des données riches, réalistes et déterministes :
1. **Comptes Système :**
   - `escrow.vault@valkyr.arena` (`ADMIN`, portefeuille séquestre central)
   - `treasury@valkyr.arena` (`ADMIN`, portefeuille trésorerie plateforme)
2. **Comptes Utilisateurs & Démo (mot de passe : `Password123!`) :**
   - **Admin Souverain :** `admin@valkyr.arena` (`gamertag: VKR_COMMANDER`, `role: ADMIN`, `balance_cents: 500000` soit `5 000,00 €`, `elo_rating: 2450`)
   - **Organisateur Officiel :** `organizer@valkyr.arena` (`gamertag: VKR_ARBITER`, `role: ORGANIZER`, `balance_cents: 250000` soit `2 500,00 €`, `elo_rating: 2180`)
   - **Organisateur Secondaire (pour tests Anti-IDOR) :** `rival.org@valkyr.arena` (`gamertag: RIVAL_HOST`, `role: ORGANIZER`, `balance_cents: 100000` soit `1 000,00 €`, `elo_rating: 1920`)
   - **Joueurs Compétitifs :**
     * `nyx@valkyr.arena` (`gamertag: VKR_NYX`, `role: PLAYER`, `balance_cents: 150000` soit `1 500,00 €`, `elo_rating: 2390`, `preferred_class: VALKYRIE`)
     * `kestrel@valkyr.arena` (`gamertag: KESTREL_ZERO`, `role: PLAYER`, `balance_cents: 120000` soit `1 200,00 €`, `elo_rating: 2310`, `preferred_class: SPECTRE`)
     * `aether@valkyr.arena` (`gamertag: AETHER_9`, `role: PLAYER`, `balance_cents: 95000` soit `950,00 €`, `elo_rating: 2240`, `preferred_class: CHRONO`)
     * `sovereign@valkyr.arena` (`gamertag: SOVEREIGN_X`, `role: PLAYER`, `balance_cents: 80000` soit `800,00 €`, `elo_rating: 2195`, `preferred_class: VANGUARD`)
3. **Tournois Pré-configurés :**
   - Au moins **6 tournois** couvrant les 4 jeux (`VALORANT // PROTOCOL`, `COUNTER-STRIKE 2`, `APEX // SOVEREIGN`, `LEAGUE OF LEGENDS`), différents buy-ins (`25,00 €`, `50,00 €`, `100,00 €`, `250,00 €`), formats (`5v5`, `1v1`) et statuts (`OPEN` avec participants déjà inscrits en séquestre et prêts à être rejoints ou clôturés, `IN_PROGRESS`, et `SETTLED` avec historique complet de distribution 60/30/10 et commission 8 % dans `ledger_entries`).
