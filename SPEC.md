# SPEC.md — VALKYR // ARENA (v4 — Économie Compétitive Complète : Duels 1v1 Instantanés, Boutique Armory, Pronostics Spectateurs, Franchises de Clans, Codes Vouchers, Litiges avec Caution & Export CSV Admin)
**Plateforme Compétitive E-Sport de Ligues Privées, Tournois & Défis 1v1/5v5 avec Architecture 100 % Wallet, 8 Flux de Revenus, Rails de Paiement Manuels/Instantanés Configurables & Grand Livre en Partie Double**

---

## 1. Analyse de l'Espace de Travail & Inventaire SSOT Immuable ("Do No Harm")

### 1.1 Socle v3 Certifié à Préserver Intégralement
- **Répertoire racine :** `c:\Users\David\Desktop\Antigravity\bench6agents`
- **Stack Active :** **PHP 8.5 + Laravel 13 (`v13.39.0`) + SQLite (`database/database.sqlite`) + Tailwind CSS v4 + Vite**.
- **Inventaire SSOT Immuable :**
  1. [`DESIGN_SYSTEM.md`](file:///c:/Users/David/Desktop/Antigravity/bench6agents/DESIGN_SYSTEM.md) : Palette OKLCH (`#030407` Obsidienne Abyssal, `#D8E5F8` Titane Glacé, `#E5C158` Or Souverain, `#10B981` Émeraude Télémétrie), Typographie à 3 niveaux (`Syne` + `Instrument Serif` *Vrai Italique* + `Plus Jakarta Sans` + `JetBrains Mono` `tabular-nums`), grilles `1px`, espaces insécables `\u00A0` et `whitespace-nowrap tabular-nums` sur 100 % des cellules de tableaux.
  2. **Les 6 Visuels 4K/8K Sur-Mesure dans `public/assets/` :** `hero-valkyr-arena.png`, `live-combat-hud.png`, `class-vanguard.png`, `class-spectre.png`, `class-chrono.png`, `class-valkyrie.png`.
  3. **Les 5 Pages Existantes (v3) :**
     - Page 1 : `GET /` (`resources/views/welcome.blade.php`) — Vitrine publique 8K.
     - Page 2 : `GET /login` & `GET /register` (`resources/views/auth/login.blade.php`) — Auth Split-Screen 50/50.
     - Page 3 : `GET /dashboard` (`resources/views/dashboard/index.blade.php`) — Cockpit Opérateur protégé `auth`.
     - Page 4 : `GET /tournaments/{tournament}` (`resources/views/tournaments/show.blade.php`) — Page dédiée d'une Arène.
     - Page 5 : `GET /admin` (`resources/views/admin/index.blade.php`) — Back-Office Admin Souverain protégé `ADMIN`.

---

## 2. Les 7 Nouvelles Briques Fonctionnelles & de Monétisation de la v4 (100 % via Wallet)

### 2.1 Invariant Mathématique Absolu (Conservation Monétaire sur les 8 Flux de Revenus)
Toutes les nouvelles fonctionnalités opèrent **exclusivement via le `Wallet` de l'utilisateur** en centimes entiers (`BIGINT`) :
- Pour chaque `Wallet` $w$ :
  $$w.\text{balance\_cents} = \sum_{e \in w} e.\text{credit\_cents} - \sum_{e \in w} e.\text{debit\_cents}$$
- Sur l'ensemble de la plateforme (tous les portefeuilles joueurs, organisateurs, `SYS_ESCROW_VAULT` et `SYS_TREASURY` confondus) :
  $$\sum_{w} w.\text{balance\_cents} = \sum \text{DEPOSIT.credit\_cents} - \sum \text{WITHDRAWAL.debit\_cents}$$

---

### 2.2 Brique 1 — Défis Instantanés 1v1 « Cash-Match / Wager Duels » (`wager_duels`)
- **Table `wager_duels` (`App\Models\WagerDuel`) :**
  - `id`, `code` (`VARCHAR(40) UNIQUE`, ex: `DUEL-901`), `challenger_id` (FK `users.id`), `opponent_id` (nullable FK `users.id`), `winner_id` (nullable FK `users.id`), `game` (`VARCHAR(80)`), `loadout_class` (`VARCHAR(40)`), `stake_cents` (`BIGINT >= 500` — mise par joueur), `escrow_pot_cents` (`BIGINT`), `rake_bps` (`INTEGER DEFAULT 800`), `platform_rake_cents` (`BIGINT DEFAULT 0`), `winner_payout_cents` (`BIGINT DEFAULT 0`), `status` (`OPEN`, `IN_PROGRESS`, `SETTLED`, `CANCELLED`), `idempotency_key` (`VARCHAR(140) UNIQUE`), `settled_at` (`TIMESTAMP NULL`), `timestamps`.
- **Cycle de vie 100 % via Wallet (`EscrowLedgerService`) :**
  1. **Créer un Duel 1v1 (`POST /api/duels` / `POST /duels`) :** Verrouille `stake_cents` du Wallet du `challenger` (`DUEL_ESCROW_DEBIT`) vers `SYS_ESCROW_VAULT` (`DUEL_ESCROW_CREDIT`), incrémente `locked_escrow_cents += stake_cents`, et place le duel en `OPEN` (`escrow_pot_cents = stake_cents`).
  2. **Accepter un Duel 1v1 (`POST /api/duels/{duel}/accept`) :** Un adversaire (`opponent_id !== challenger_id`) verrouille `stake_cents` depuis son Wallet (`DUEL_ESCROW_DEBIT`) vers `SYS_ESCROW_VAULT` (`DUEL_ESCROW_CREDIT`). Le pot passe à `2 * stake_cents` et le statut à `IN_PROGRESS`.
  3. **Clôturer un Duel (`POST /api/duels/{duel}/settle`) :** Autorisé pour l'`ADMIN`, un `ORGANIZER` ou l'un des participants du duel. Libère `escrow_pot_cents` depuis `SYS_ESCROW_VAULT` (`ESCROW_RELEASE_DEBIT`), prélève **8 % (`800 bps`)** vers `SYS_TREASURY` (`PLATFORM_RAKE_CREDIT`), et verse **92 %** sur le Wallet du `winner_id` (`DUEL_PAYOUT_CREDIT`), libère `locked_escrow_cents` des deux duellistes et met à jour l'ELO (`+15` pour le gagnant).

---

### 2.3 Brique 2 — Boutique Souveraine & Licences Tactiques (`armory_items` & `user_armory_purchases`)
- **Table `armory_items` (`App\Models\ArmoryItem`) :**
  - `id`, `sku` (`VARCHAR(60) UNIQUE`), `name` (`VARCHAR(120)`), `category` (`VARCHAR(40)` : `'BADGE_TITLE'`, `'ELO_SHIELD'`, `'AVATAR_FRAME'`), `description` (`TEXT`), `price_cents` (`BIGINT > 0`), `elo_bonus` (`INTEGER DEFAULT 0`), `badge_label` (`VARCHAR(80)`), `is_active` (`BOOLEAN DEFAULT 1`), `timestamps`.
- **Table `user_armory_purchases` (`App\Models\UserArmoryPurchase`) :**
  - `id`, `user_id` (FK `users.id`), `armory_item_id` (FK `armory_items.id`), `paid_cents` (`BIGINT`), `idempotency_key` (`VARCHAR(140) UNIQUE`), `timestamps`.
  - Nouvelle colonne sur `users` : `equipped_badge` (`VARCHAR(80) NULL`), `clan_tag` (`VARCHAR(12) NULL`).
- **Achat en 1 clic (`POST /api/armory/{armoryItem}/buy` / `POST /armory/{armoryItem}/buy`) :**
  - Débite `price_cents` du Wallet de l'acheteur (`ARMORY_PURCHASE_DEBIT`) et crédite **100 %** à `SYS_TREASURY` (`ARMORY_REVENUE_CREDIT`).
  - Équipe automatiquement `user->equipped_badge = $item->badge_label` et ajoute `$item->elo_bonus` à `user->elo_rating`.

---

### 2.4 Brique 3 — Pool de Pronostics Spectateurs sur les Arènes (`tournament_predictions` — Rake 10 %)
- **Table `tournament_predictions` (`App\Models\TournamentPrediction`) :**
  - `id`, `tournament_id` (FK `tournaments.id`), `user_id` (FK `users.id`), `predicted_winner_user_id` (FK `users.id`), `stake_cents` (`BIGINT >= 200`), `payout_cents` (`BIGINT DEFAULT 0`), `status` (`LOCKED`, `WON`, `LOST`, `REFUNDED`), `idempotency_key` (`VARCHAR(140) UNIQUE`), `timestamps`.
- **Fonctionnement :**
  - **Engager un Pronostic (`POST /api/tournaments/{tournament}/predict` / `POST /tournaments/{tournament}/predict`) :** Sur une arène `OPEN`, `LOCKED` ou `IN_PROGRESS`, débite `stake_cents` du Wallet de l'utilisateur (`PREDICTION_STAKE_DEBIT`) et crédite `SYS_ESCROW_VAULT` (`PREDICTION_STAKE_CREDIT`).
  - **Règlement automatique lors de `settleTournament` :**
    * S'il existe des pronostics `LOCKED` sur ce tournoi (somme totale `$predictionPoolCents`), `SYS_ESCROW_VAULT` est débité de `$predictionPoolCents` (`ESCROW_RELEASE_DEBIT`).
    * La plateforme prélève **10 % (`1000 bps`)** vers `SYS_TREASURY` (`PLATFORM_RAKE_CREDIT`).
    * Les **90 %** restants sont répartis au prorata de `stake_cents` entre les pronostiqueurs ayant prédit `$firstPlaceUserId` (`PREDICTION_PAYOUT_CREDIT`, avec reliquat d'arrondi entier attribué au premier pronostiqueur gagnant ; si aucun pronostiqueur n'a trouvé le gagnant, les 90 % sont remboursés aux pronostiqueurs ou crédités à la trésorerie pour conserver 100 % des centimes).

---

### 2.5 Brique 4 — Économie B2B : Clans, Équipes & Franchises (`clans` — Licence `29,00 €`)
- **Table `clans` (`App\Models\Clan`) :**
  - `id`, `tag` (`VARCHAR(10) UNIQUE`, ex: `VKR`, `NYX`, `SOV`), `name` (`VARCHAR(100)`), `owner_id` (FK `users.id`), `revenue_share_bps` (`INTEGER DEFAULT 1500`), `member_count` (`INTEGER DEFAULT 1`), `license_paid_cents` (`BIGINT DEFAULT 2900`), `timestamps`.
- **Créer une Franchise / Clan (`POST /api/clans` / `POST /clans`) :**
  - Débite `2900` centimes (`29,00 €`) du Wallet du fondateur (`CLAN_LICENSE_DEBIT`) et crédite `2900` centimes à `SYS_TREASURY` (`CLAN_LICENSE_CREDIT`).
  - Assigne `user->clan_tag = $clan->tag`.
- **Rejoindre un Clan (`POST /api/clans/{clan}/join` / `POST /clans/{clan}/join`) :**
  - Assigne `user->clan_tag = $clan->tag` et incrémente `member_count`.

---

### 2.6 Brique 5 — Générateur de Codes Vouchers / Cartes Cadeaux en Admin (`voucher_codes`)
- **Table `voucher_codes` (`App\Models\VoucherCode`) :**
  - `id`, `code` (`VARCHAR(40) UNIQUE`, ex: `VALKYR-VIP-50`, `SOVEREIGN-100`), `credit_amount_cents` (`BIGINT >= 100`), `max_uses` (`INTEGER DEFAULT 10`), `used_count` (`INTEGER DEFAULT 0`), `is_active` (`BOOLEAN DEFAULT 1`), `created_by_admin_id` (nullable FK `users.id`), `timestamps`.
- **Table `voucher_redemptions` (`App\Models\VoucherRedemption`) :**
  - `id`, `voucher_code_id` (FK `voucher_codes.id`), `user_id` (FK `users.id`), `credited_cents` (`BIGINT`), `idempotency_key` (`VARCHAR(140) UNIQUE`), `timestamps`. Contrainte `UNIQUE(voucher_code_id, user_id)` (1 utilisation par joueur par code).
- **Fonctionnement :**
  - **Création par l'Admin dans `/admin` (`POST /api/admin/vouchers` / `POST /admin/vouchers`)**.
  - **Activation par le Joueur dans `/dashboard` (`POST /api/wallets/redeem-voucher` / `POST /wallets/redeem-voucher`) :** Crédite instantanément `credit_amount_cents` sur le Wallet de l'utilisateur avec une écriture `LedgerEntry::TYPE_DEPOSIT` (maintenant l'invariant global `SUM(wallets) === SUM(DEPOSIT) - SUM(WITHDRAWAL)`).

---

### 2.7 Brique 6 — Centre de Litiges & Caution d'Arbitrage (`tournament_disputes` — Caution `5,00 €`)
- **Table `tournament_disputes` (`App\Models\TournamentDispute`) :**
  - `id`, `tournament_id` (FK `tournaments.id`), `challenger_user_id` (FK `users.id`), `bond_cents` (`BIGINT DEFAULT 500` — `5,00 €`), `reason` (`TEXT`), `evidence_url` (`VARCHAR(255) NULL`), `status` (`OPEN`, `UPHELD_REFUNDED`, `REJECTED_FORFEITED`), `resolved_by_admin_id` (nullable FK `users.id`), `admin_resolution_notes` (`VARCHAR(255) NULL`), `idempotency_key` (`VARCHAR(140) UNIQUE`), `timestamps`.
- **Fonctionnement :**
  1. **Ouvrir un Litige (`POST /api/tournaments/{tournament}/disputes` / `POST /tournaments/{tournament}/disputes`) :** Débite la caution de `500` centimes (`5,00 €`) du Wallet du joueur (`DISPUTE_BOND_DEBIT`) vers `SYS_ESCROW_VAULT` (`DISPUTE_BOND_CREDIT`).
  2. **Trancher le Litige dans `/admin` (`POST /api/admin/disputes/{dispute}/resolve` / `POST /admin/disputes/{dispute}/resolve`) :**
     - Si `decision === 'UPHELD'` (Litige fondé) : Débite `500` centimes de `SYS_ESCROW_VAULT` (`ESCROW_RELEASE_DEBIT`) et rembourse `500` centimes sur le Wallet du joueur (`TOURNAMENT_REFUND_CREDIT`), statut `UPHELD_REFUNDED`.
     - Si `decision === 'REJECTED'` (Litige abusif) : Débite `500` centimes de `SYS_ESCROW_VAULT` (`ESCROW_RELEASE_DEBIT`) et crédite `500` centimes à `SYS_TREASURY` (`DISPUTE_FORFEIT_CREDIT` — revenu d'arbitrage acquis à la plateforme), statut `REJECTED_FORFEITED`.

---

### 2.8 Brique 7 — Export Comptable CSV Certifié & Analytique 8 Flux dans `/admin`
- **Route `GET /admin/export/ledger.csv` & `GET /api/admin/export/ledger.csv` (protégée `ADMIN`) :**
  - Génère et télécharge un fichier CSV `Content-Type: text/csv; charset=UTF-8` contenant toutes les écritures du Grand Livre avec colonnes propres.
- **Tableau de Bord Analytique `/admin` enrichi :**
  - Ventilation complète des revenus (`Rake Tournois`, `Frais de Rails`, `Pass ★ PRIME`, `Boosts d'Arène`, `Boutique ARMORY`, `Licences CLANS`, `Rake Duels 1v1` & `Pronostics`, `Cautions de Litiges confisquées`), gestionnaire de Codes Vouchers et Tribunal d'Arbitrage des Litiges.
