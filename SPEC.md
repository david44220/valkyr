# SPEC.md — VALKYR // ARENA (v3 — Moteur de Revenus Multi-Flux, Méthodes de Dépôt/Retrait Configurables & Back-Office Admin Souverain)
**Plateforme Compétitive E-Sport de Ligues Privées, Tournois & Défis 1v1/5v5 avec Architecture 100 % Wallet, Rails de Paiement Manuels/Instantanés Configurables, 5 Flux de Revenus & Grand Livre en Partie Double**

---

## 1. Analyse de l'Espace de Travail & Inventaire SSOT Immuable ("Do No Harm")

### 1.1 Socle Certifié à Préserver Intégralement
- **Répertoire racine :** `c:\Users\David\Desktop\Antigravity\bench6agents`
- **Stack Active :** **PHP 8.5 + Laravel 13 (`v13.39.0`) + SQLite (`database/database.sqlite`) + Tailwind CSS v4 + Vite**.
- **Inventaire SSOT Immuable :**
  1. [`DESIGN_SYSTEM.md`](file:///c:/Users/David/Desktop/Antigravity/bench6agents/DESIGN_SYSTEM.md) : Palette OKLCH (`#030407` Obsidienne Abyssal, `#D8E5F8` Titane Glacé, `#E5C158` Or Souverain, `#10B981` Émeraude Télémétrie), Typographie à 3 niveaux (`Syne` + `Instrument Serif` *Vrai Italique* + `Plus Jakarta Sans` + `JetBrains Mono` `tabular-nums`), grilles `1px` (`rgba(255,255,255,0.08)`), espaces insécables `\u00A0` et `whitespace-nowrap tabular-nums` sur 100 % des cellules de tableaux.
  2. **Les 6 Visuels 4K/8K Sur-Mesure dans `public/assets/` :**
     - `public/assets/hero-valkyr-arena.png`
     - `public/assets/live-combat-hud.png`
     - `public/assets/class-vanguard.png`, `public/assets/class-spectre.png`, `public/assets/class-chrono.png`, `public/assets/class-valkyrie.png`
  3. **Les 4 Pages Existantes (v2) :**
     - Page 1 : `GET /` (`resources/views/welcome.blade.php`) — Vitrine publique 8K.
     - Page 2 : `GET /login` & `GET /register` (`resources/views/auth/login.blade.php`) — Auth Split-Screen 50/50.
     - Page 3 : `GET /dashboard` (`resources/views/dashboard/index.blade.php`) — Cockpit Opérateur protégé `auth`.
     - Page 4 : `GET /tournaments/{tournament}` (`resources/views/tournaments/show.blade.php`) — Page dédiée d'une Arène.

---

## 2. Architecture v3 : Tout Passe par le Wallet, Moyens de Dépôt/Retrait Configurables en Admin & 5 Flux de Revenus

### 2.1 Loi Fondamentale : Architecture 100 % Centrée sur le Portefeuille (`Wallet`)
Toutes les opérations financières des joueurs et organisateurs transitent **exclusivement par leur `Wallet`** (`wallets` + `ledger_entries` en centimes entiers `BIGINT`) :
1. L'utilisateur **crédite son Wallet** (ou retire depuis son Wallet) via des **Méthodes de Dépôt / Retrait définies, configurées et réglées par l'Admin** (`payment_rails` & `wallet_funding_requests`).
2. Une fois son Wallet crédité, l'utilisateur utilise le solde disponible (`balance_cents`) de son Wallet pour :
   - Payer ses **Buy-ins de tournois** (verrouillés en séquestre dans `SYS_ESCROW_VAULT`).
   - Souscrire au **Pass Membre `★ VALKYR PRIME`** (`1 900` centimes = `19,00 €`).
   - Payer un **Boost de Visibilité d'Arène (`Featured Arena Boost`)** (`2 500` centimes = `25,00 €` pour les Organisateurs).
   - Injecter une **Dotation Garantie Sponsorisée (`Sponsored Pool Injection`)** dans une arène.

---

### 2.2 Nouvelles Tables & Colonnes SQL (Migrations v3 Réversibles)

#### A. Enrichissement de la table `users`
- `is_prime` (`BOOLEAN NOT NULL DEFAULT 0`) : Statut d'abonné `★ VALKYR PRIME` (exonère à 100 % des frais de dépôt et de retrait).
- `prime_expires_at` (`TIMESTAMP NULL`) : Date d'expiration de l'abonnement Prime.
- `is_frozen` (`BOOLEAN NOT NULL DEFAULT 0`) : Gel administratif du portefeuille (bloque tout retrait, buy-in ou transfert si `true`).
- `kyc_status` (`VARCHAR(24) NOT NULL DEFAULT 'VERIFIED'`) : `'PENDING'`, `'VERIFIED'`, `'RESTRICTED'`.

#### B. Enrichissement de la table `tournaments`
- `organizer_share_bps` (`INTEGER NOT NULL DEFAULT 200`) : Part du Rake reversée au Wallet de l'`ORGANIZER` propriétaire lors de la clôture (ex: sur un Rake total de `800 bps` [8 %], `600 bps` [6 %] vont à `SYS_TREASURY` et `200 bps` [2 %] vont au Wallet de l'`ORGANIZER` ! Si l'organisateur est un `ADMIN`, les `800 bps` vont intégralement à `SYS_TREASURY` ou selon `organizer_share_bps`).
- `organizer_rake_cents` (`BIGINT NOT NULL DEFAULT 0`) : Montant exact en centimes reversé à l'organisateur lors du `settle`.
- `is_featured` (`BOOLEAN NOT NULL DEFAULT 0`) : Indique si l'arène est mise en avant (`★ FEATURED // SPONSORED`) en tête du catalogue.
- `boost_paid_cents` (`BIGINT NOT NULL DEFAULT 0`) : Montant payé par l'organisateur pour le boost de visibilité.

#### C. Nouvelle Table `payment_rails` (Méthodes de Dépôt & Retrait Définies et Réglées par l'Admin)
| Colonne | Type SQL | Description |
| :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Primary Key |
| `code` | `VARCHAR(50) UNIQUE` | Ex: `SEPA_MANUAL`, `SEPA_INSTANT`, `CRYPTO_USDT_TRC20`, `STRIPE_CARD`, `APPLE_PAY`, `VOUCHER_CODE` |
| `name` | `VARCHAR(120)` | Nom affiché (ex: `"Virement Bancaire SEPA (Validation Admin)"`, `"Crypto USDT / USDC (ERC-20 / TRC-20)"`, `"Carte Bancaire Instantanée"`, `"SEPA Instantané Express"`) |
| `provider` | `VARCHAR(80)` | Ex: `"Banque Souveraine SEPA"`, `"Tether / Circle On-Chain"`, `"Stripe Treasury"`, `"Trésorerie Manuelle VALKYR"` |
| `method_type` | `VARCHAR(30)` | `'BANK_TRANSFER'`, `'CRYPTO'`, `'CARD'`, `'MANUAL_VOUCHER'` |
| `supports_deposit` | `BOOLEAN DEFAULT 1` | Utilisable pour créditer le Wallet |
| `supports_withdrawal` | `BOOLEAN DEFAULT 1` | Utilisable pour retirer depuis le Wallet |
| `requires_admin_approval` | `BOOLEAN DEFAULT 0` | Si `true` (`1`), crée une demande `PENDING` dans `wallet_funding_requests` que l'Admin règle/valide manuellement dans `/admin`. Si `false` (`0`), règle immédiatement la transaction sur le Wallet. |
| `is_active` | `BOOLEAN DEFAULT 1` | Activé ou coupé par l'Admin en 1 clic |
| `deposit_fee_bps` | `INTEGER DEFAULT 0` | Frais variables sur dépôt en points de base (ex: `150` = 1,50 %) |
| `deposit_fixed_fee_cents` | `BIGINT DEFAULT 0` | Frais fixes sur dépôt en centimes (ex: `25` = 0,25 €) |
| `withdrawal_fee_bps` | `INTEGER DEFAULT 0` | Frais variables sur retrait en points de base (ex: `100` = 1,00 %) |
| `withdrawal_fixed_fee_cents` | `BIGINT DEFAULT 0` | Frais fixes sur retrait en centimes (ex: `50` = 0,50 €) |
| `min_amount_cents` | `BIGINT DEFAULT 500` | Plafond minimum en centimes (ex: `500` = 5,00 €) |
| `max_amount_cents` | `BIGINT DEFAULT 10000000` | Plafond maximum en centimes |
| `account_instructions` | `TEXT NULL` | Coordonnées & instructions définies par l'Admin (IBAN plateforme, adresse Crypto, libellé de virement obligatoire) |
| `settlement_delay_label` | `VARCHAR(60)` | Ex: `"VALIDATION ADMIN (< 1H)"`, `"INSTANTANÉ (< 5S)"` |
| `timestamps` | `TIMESTAMP` | `created_at`, `updated_at` |

#### D. Nouvelle Table `user_payment_methods` (Comptes & Moyens de Paiement Enregistrés par l'Utilisateur)
| Colonne | Type SQL | Description |
| :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Primary Key |
| `user_id` | `BIGINT UNSIGNED` | FK `users(id) ON DELETE CASCADE` |
| `payment_rail_id` | `BIGINT UNSIGNED` | FK `payment_rails(id) ON DELETE CASCADE` |
| `label` | `VARCHAR(100)` | Ex: `"Mon Compte BNP Paribas"`, `"Ledger USDC Principal"`, `"Visa Premier •••• 4242"` |
| `account_identifier` | `VARCHAR(160)` | IBAN, Adresse Crypto `0x...`, ou identifiant masqué |
| `is_default` | `BOOLEAN DEFAULT 0` | Méthode sélectionnée par défaut |
| `is_verified` | `BOOLEAN DEFAULT 1` | Statut vérifié |
| `timestamps` | `TIMESTAMP` | Index sur `(user_id, is_default)` |

#### E. Nouvelle Table `wallet_funding_requests` (Journal & File d'Approbation des Dépôts / Retraits Manuels & Instantanés)
| Colonne | Type SQL | Description |
| :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | Primary Key |
| `user_id` | `BIGINT UNSIGNED` | FK `users(id) ON DELETE CASCADE` |
| `wallet_id` | `BIGINT UNSIGNED` | FK `wallets(id) ON DELETE CASCADE` |
| `payment_rail_id` | `BIGINT UNSIGNED` | Nullable, FK `payment_rails(id) ON DELETE SET NULL` |
| `user_payment_method_id` | `BIGINT UNSIGNED` | Nullable, FK `user_payment_methods(id) ON DELETE SET NULL` |
| `direction` | `VARCHAR(20)` | `'DEPOSIT'` ou `'WITHDRAWAL'` |
| `gross_amount_cents` | `BIGINT` | Montant brut demandé par l'utilisateur (`> 0`) |
| `fee_cents` | `BIGINT` | Frais calculés selon le rail (ou `0` si `user.is_prime`) |
| `net_amount_cents` | `BIGINT` | Montant net crédité (pour un dépôt : `gross - fee`) ou versé (pour un retrait : `gross - fee`) |
| `reference_code` | `VARCHAR(120)` | Référence de virement bancaire, Hash TX Crypto, ou IBAN de destination |
| `status` | `VARCHAR(24)` | `'PENDING_ADMIN'`, `'SETTLED'`, `'REJECTED'` |
| `idempotency_key` | `VARCHAR(140) UNIQUE` | Clé d'idempotence unique |
| `processed_by_admin_id` | `BIGINT UNSIGNED NULL` | FK `users(id)` |
| `admin_notes` | ` VARCHAR(255) NULL` | Note de validation ou motif de rejet par l'Admin |
| `processed_at` | `TIMESTAMP NULL` | Date de règlement sur le Grand Livre |
| `timestamps` | `TIMESTAMP` | Index sur `(status, direction)` et `(user_id)` |

---

### 2.3 Mathématiques Exactes & Conservation Monétaire des 5 Flux de Revenus (`ledger_entries`)

Tous les nouveaux types d'écritures dans `LedgerEntry` respectent **l'invariant absolu** :
1. Pour chaque `Wallet` :
   $$\text{wallet.balance\_cents} = \sum \text{credit\_cents} - \sum \text{debit\_cents}$$
2. Sur l'ensemble de la plateforme (tous les portefeuilles joueurs, organisateurs, `SYS_ESCROW_VAULT` et `SYS_TREASURY` confondus) :
   $$\sum_{\text{all wallets}} \text{balance\_cents} = \sum \text{DEPOSIT.credit\_cents} - \sum \text{WITHDRAWAL.debit\_cents}$$

Comment cet invariant global est-il maintenu au centime près sur chaque opération ?
- **Dépôt avec Frais de Rail configurables (`deposit` ou `approveFundingRequest` de type `DEPOSIT`) :**
  - Soit $G$ le montant brut déposé (`gross_amount_cents`) et $F$ les frais du rail (`fee_cents`, forcés à `0` si `$user->is_prime` est vrai).
  - Étape 1 : Le Wallet de l'utilisateur reçoit le crédit externe brut $+G$ (`entry_type = DEPOSIT`, `credit_cents = G`).
  - Étape 2 (si $F > 0$) : Le Wallet de l'utilisateur est débité de $-F$ (`entry_type = DEPOSIT_FEE_DEBIT`, `debit_cents = F`) et le Wallet `SYS_TREASURY` est crédité simultanément de $+F$ (`entry_type = PLATFORM_PAYMENT_FEE_CREDIT`, `credit_cents = F`).
  - Résultat : L'utilisateur voit son solde augmenter de $G - F$ (`net_amount_cents`), `SYS_TREASURY` encaisse $+F$, et la somme globale des soldes a augmenté exactement de $+G$ (`DEPOSIT`) !
- **Retrait avec Frais de Rail configurables (`withdraw` ou `approveFundingRequest` de type `WITHDRAWAL`) :**
  - Soit $W$ le montant brut débité du Wallet de l'utilisateur (`gross_amount_cents`) et $F$ les frais de retrait du rail (`fee_cents`, forcés à `0` si `$user->is_prime` est vrai, avec $F < W$). Le montant net envoyé vers le compte bancaire/crypto externe est $N = W - F$.
  - Étape 1 (si $F > 0$) : Le Wallet de l'utilisateur est débité de $-F$ (`entry_type = WITHDRAWAL_FEE_DEBIT`, `debit_cents = F`) et `SYS_TREASURY` est crédité de $+F$ (`entry_type = PLATFORM_PAYMENT_FEE_CREDIT`, `credit_cents = F`).
  - Étape 2 : Le Wallet de l'utilisateur est débité de $-N$ (`entry_type = WITHDRAWAL`, `debit_cents = N`).
  - Résultat : Le Wallet de l'utilisateur a été débité au total de $F + N = W$, `SYS_TREASURY` a encaissé $+F$ de commission de retrait, et le flux sortant externe (`WITHDRAWAL`) est exactement $N$ !
- **Souscription au Pass `★ VALKYR PRIME` (`POST /api/wallets/subscribe-prime` — `1 900` centimes = `19,00 €`) :**
  - Transfert interne en partie double : Débit de `1900` centimes sur le Wallet du joueur (`PRIME_SUBSCRIPTION_DEBIT`) $\rightarrow$ Crédit de `1900` centimes sur `SYS_TREASURY` (`PRIME_SUBSCRIPTION_CREDIT`). Active `is_prime = true`, `prime_expires_at = now()->addDays(30)` et `+50` ELO.
- **Boost de Visibilité d'Arène (`POST /api/tournaments/{tournament}/boost` — `2 500` centimes = `25,00 €`) :**
  - Transfert interne en partie double : Débit de `2500` centimes sur le Wallet de l'Organisateur/Admin (`ARENA_BOOST_DEBIT`) $\rightarrow$ Crédit de `2500` centimes sur `SYS_TREASURY` (`ARENA_BOOST_CREDIT`). Active `tournament.is_featured = true`.
- **Injection de Dotation Garantie Sponsorisée (`POST /api/tournaments/{tournament}/sponsor`) :**
  - Transfert interne en partie double : Débit de `amount_cents` sur le Wallet du sponsor (`SPONSOR_POOL_DEBIT`) $\rightarrow$ Crédit de `amount_cents` sur `SYS_ESCROW_VAULT` (`SPONSOR_POOL_CREDIT`). Incrémente `tournament.escrow_pool_cents += amount_cents` et `tournament.sponsored_guarantee_cents += amount_cents`.
- **Clôture de Tournoi avec Partage de Commission Organisateur (`settleTournament`) :**
  - Soit $P$ le `escrow_pool_cents` total du tournoi, `rake_bps` la commission totale (défaut `800` bps = 8 %), et `organizer_share_bps` la part organisateur (défaut `200` bps = 2 %, plafonnée à `rake_bps`).
  - `$totalRakeCents = intdiv($P * $rakeBps, 10000)`
  - Si l'organisateur du tournoi a le rôle `ORGANIZER` (et n'est pas `SYS_TREASURY`) :
    * `$organizerRakeCents = intdiv($P * $organizerShareBps, 10000)` crédité au Wallet de l'Organisateur (`ORGANIZER_RAKE_SHARE_CREDIT`).
    * `$platformRakeCents = $totalRakeCents - $organizerRakeCents` crédité au Wallet `SYS_TREASURY` (`PLATFORM_RAKE_CREDIT`).
  - Sinon (`organizer` est `ADMIN`), `$organizerRakeCents = 0` et `$platformRakeCents = $totalRakeCents`.
  - Le Pool Net `$netPoolCents = $P - $totalRakeCents` est réparti `60 % / 30 % / 10 %` entre les 1er, 2e et 3e places.
  - Invariant vérifié : `$platformRakeCents + $organizerRakeCents + $firstPrize + $secondPrize + $thirdPrize === $P`.

---

## 3. Nouvelle Page 5 — Surface d'Administration Souveraine (`GET /admin` — `resources/views/admin/index.blade.php`)

### 3.1 Protection & Navigation
- **Route :** `GET /admin` (route nommée `admin.index`), protégée par le middleware `auth` et une vérification stricte `user->isAdmin()` (`403 Forbidden` pour tout `PLAYER` ou `ORGANIZER`).
- **Accès rapide :**
  - Dans le Header de `GET /` et la Topbar/Sidebar de `GET /dashboard`, si `auth()->user()->isAdmin()`, affiche un bouton Or Souverain **`Commandement Admin (/admin) ↗`**.
  - Barre de bascule rapide de rôle Démo présente également sur `/admin`.

### 3.2 Les 5 Panneaux de Commandement de `/admin` (Navigation par Onglets Exclusifs sans `scrollIntoView`)
1. **Panneau 1 (`data-admin-panel="treasury"`) — `Trésorerie Souveraine, Statistiques de Revenus & Audit d'Intégrité` :**
   - **8 Cartes KPI en `tabular-nums` :**
     1. `Solde Trésorerie Plateforme (SYS_TREASURY)`
     2. `Fonds Verrouillés en Séquestre (SYS_ESCROW_VAULT)`
     3. `Volume Brut Global (GMV Dépôts)`
     4. `Revenus Rake Tournois (Net Plateforme)`
     5. `Revenus Frais de Dépôt / Retrait`
     6. `Revenus Abonnements ★ VALKYR PRIME`
     7. `Revenus Boosts d'Arènes Sponsorisées`
     8. `Commissions Reversées aux Organisateurs (RevShare)`
   - **Audit Mathématique en Direct (`Ledger Health Check`) :** Tableau vérifiant que **100 % des portefeuilles** ont `balance_cents === SUM(credits) - SUM(debits)` et que l'invariant global `SUM(wallets) === SUM(DEPOSIT) - SUM(WITHDRAWAL)` est à `0,00 €` d'écart.
   - **Action Trésorerie :** Permet à l'Admin d'injecter des fonds de dotation ou d'ajuster un portefeuille avec motif d'audit.

2. **Panneau 2 (`data-admin-panel="rails"`) — `Gestion des Moyens de Dépôt/Retrait, Frais & Demandes Manuelles` :**
   - **Section A — File d'Approbation des Dépôts & Retraits Manuels (`wallet_funding_requests` en statut `PENDING_ADMIN` et historique récent) :**
     * Affiche l'opérateur, la méthode choisie (`Virement SEPA Manuel`, `Crypto USDT Manuel`, etc.), le montant brut, les frais calculés, le montant net, la référence (`reference_code`), et 2 boutons d'action atomiques :
       - **`Approuver & Régler sur le Wallet ✓`** (`POST /api/admin/funding-requests/{id}/approve`) : exécute immédiatement l'écriture en partie double sur le Wallet de l'utilisateur et crédite les frais à `SYS_TREASURY`.
       - **`Rejeter ✕`** (`POST /api/admin/funding-requests/{id}/reject`).
   - **Section B — Configuration des Rails de Paiement existants (`payment_rails`) :**
     * Tableau/Cartes permettant de modifier en direct pour chaque méthode :
       - `deposit_fee_bps` & `deposit_fixed_fee_cents`
       - `withdrawal_fee_bps` & `withdrawal_fixed_fee_cents`
       - `min_amount_cents` & `max_amount_cents`
       - `requires_admin_approval` (Validation Manuelle Admin vs Crédit/Débit Instantané)
       - `is_active` (Actif / Désactivé)
       - `account_instructions` (IBAN, adresse Crypto, consignes de référence)
   - **Section C — Créer une Nouvelle Méthode de Dépôt / Retrait (`POST /api/admin/payment-rails`) :**
     * Formulaire permettant à l'Admin d'ajouter un nouveau moyen de paiement personnalisé avec ses frais, limites et instructions.

3. **Panneau 3 (`data-admin-panel="operators"`) — `Gestion des Opérateurs, Wallets, Rôles & Abonnements PRIME` :**
   - Tableau complet des utilisateurs (`whitespace-nowrap tabular-nums`) affichant `ID`, `Gamertag`, `Email`, `Rôle`, `Statut ★ PRIME`, `Statut Wallet (ACTIF / GELÉ)`, `Solde Disponible`, `Séquestre`, `Gains Cumulés`, `Cote ELO`.
   - **Actions Admin par ligne :**
     * Changer le rôle (`PLAYER` ⇄ `ORGANIZER`) via `POST /api/admin/users/{user}/update-status`.
     * Activer/Révoquer le statut `★ VALKYR PRIME` gratuitement ou prolonger l'abonnement.
     * Geler / Dégeler le portefeuille (`is_frozen`).
     * Créditer un bonus / dotation manuelle sur le Wallet de l'opérateur (`POST /api/admin/users/{user}/credit-wallet`).

4. **Panneau 4 (`data-admin-panel="arenas"`) — `Pilotage Global des Arènes, Rake Dynamique, RevShare & Sponsoring` :**
   - Tableau et contrôles sur tous les tournois permettant à l'Admin de :
     * Ajuster le `rake_bps` (ex: `500` à `1500` bps) et l'`organizer_share_bps` (ex: `0` à `500` bps) via `POST /api/admin/tournaments/{tournament}/configure`.
     * Activer / Désactiver la mise en avant `★ FEATURED` (`is_featured`).
     * Injecter une dotation garantie sponsorisée (`POST /api/tournaments/{tournament}/sponsor`).
     * Ouvrir la console de règlement 60/30/10 ou d'annulation 100 %.

5. **Panneau 5 (`data-admin-panel="ledger"`) — `Explorateur Maître du Grand Livre (`ledger_entries` Global)` :**
   - Vue intégrale sur les écritures comptables de tous les portefeuilles (y compris `SYS_TREASURY` et `SYS_ESCROW_VAULT`) avec filtre par type d'opération (`ALL`, `DEPOSIT`, `WITHDRAWAL`, `PLATFORM_RAKE_CREDIT`, `ORGANIZER_RAKE_SHARE_CREDIT`, `PLATFORM_PAYMENT_FEE_CREDIT`, `PRIME_SUBSCRIPTION_CREDIT`, `ARENA_BOOST_CREDIT`, `SPONSOR_POOL_CREDIT`).

---

## 4. Enrichissement de l'Espace Membre (`GET /dashboard`) et des Arènes (`GET /` & `GET /tournaments/{slug}`)

1. **Dans `GET /dashboard` — Onglet 1 (`Vue d'Ensemble, Portefeuille & Moyens de Paiement`) :**
   - **Sélecteur de Méthode de Dépôt & Calculateur de Frais en Temps Réel :**
     * L'utilisateur choisit parmi les `payment_rails` actifs configurés par l'Admin (ex: `SEPA Instantané (0% frais)`, `Carte Bancaire Instantanée (1,50% + 0,25 €)`, `Crypto USDT/USDC (0,50%)`, `Virement Bancaire Manuel (Validation Admin)`).
     * Affiche les **instructions/coordonnées définies par l'Admin** pour le rail sélectionné, un champ `reference_code` (référence de virement / hash TX / carte enregistrée), et le **calculateur dynamique en `tabular-nums`** : `Montant Brut`, `Frais du Rail` (`0,00 €` barré si l'opérateur est `★ VALKYR PRIME`), `Net Crédité sur Wallet`, et le mode (`CRÉDIT INSTANTANÉ` vs `SOUMIS À VALIDATION ADMIN`).
   - **Sélecteur de Méthode de Retrait & Calculateur de Frais en Temps Réel :**
     * L'utilisateur choisit un rail de retrait actif ou l'un de ses **Moyens de Paiement Enregistrés (`user_payment_methods`)** (IBAN vérifié, adresse Crypto, etc.), voit les frais exacts et le net versé, et soumet son retrait.
   - **Gestionnaire de Mes Moyens de Paiement Enregistrés (`user_payment_methods`) :**
     * Permet d'ajouter un nouveau compte/IBAN/Wallet Crypto favori (`POST /api/payment-methods`) ou d'en supprimer un (`DELETE /api/payment-methods/{id}`).
   - **Carte d'Abonnement `★ VALKYR PRIME` (`19,00 € / 30 jours` payé via le Wallet) :**
     * Affiche le statut actuel (`MEMBRE ★ PRIME ACTIF — 0% FRAIS DE DÉPÔT/RETRAIT` ou bouton `Activer ★ VALKYR PRIME avec mon Wallet (19,00 €) ↗` via `POST /api/wallets/subscribe-prime`).
   - **Historique de mes Demandes de Dépôt / Retrait (`wallet_funding_requests`) :**
     * Affiche l'état (`PENDING_ADMIN`, `SETTLED`, `REJECTED`) des opérations soumises.

2. **Dans `GET /dashboard` — Onglet 4 (`Console Organisateur`) & `GET /tournaments/{slug}` :**
   - Lors de la création ou de la gestion d'un tournoi, l'Organisateur peut :
     * Choisir le `rake_bps` et voir sa **Part Organisateur (`organizer_share_bps`, ex: 2,00 % du Pool Brut reversés sur son Wallet à la clôture)** !
     * Activer le **Boost de Visibilité `★ FEATURED` (`25,00 €` débités de son Wallet)** (`POST /api/tournaments/{id}/boost`).
     * Injecter une **Dotation Sponsorisée (`POST /api/tournaments/{id}/sponsor`)** depuis son Wallet pour gonfler le Cashprize garanti.
