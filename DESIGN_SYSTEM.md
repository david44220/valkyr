# DESIGN_SYSTEM.md — VALKYR // ARENA (AAA Gaming & Apple/Studio Restraint)

## 1. Archétype & Philosophie Visuelle
- **Archétype Principal :** **Archétype D — AAA Gaming & Live Gameplay Hub**, fusionné avec la retenue chirurgicale **Apple / Studio Hardware**.
- **Références Directrices :** *FACEIT Pro Circuit*, *Riot Games Valorant Champions Tour (VCT) Broadcast HUD*, *Bungie Marathon Graphic Systems*, *Linear Dark Mode Precision*.
- **Verrouillage Thématique :** `color-scheme: dark` forcé sur `<html>`. Aucun mode clair dégradé, aucun dégradé néon violet/rose ("AI Slop" strictement interdit).
- **Loi de Pureté Optique 4K/8K :** Les visuels Hero et les artworks de classes sont affichés avec une clarté optique native (`filter: none`, `transform: none`, `opacity: 1`, zéro calque `<div>` d'assombrissement par-dessus l'image). Le contraste typographique est assuré par la composition de l'image elle-même (éclairage rim-light latéral droit et vide abyssal naturel `#030407` à gauche derrière le texte).

---

## 2. Architecture Chromatique OKLCH & Variables CSS (`:root`)

```css
:root {
  color-scheme: dark;

  /* Surfaces & Fonds (Obsidienne Abyssale) */
  --bg-abyss: #030407;                  /* oklch(0.11 0.015 260) - Fond principal */
  --bg-cockpit: #080B11;                /* oklch(0.14 0.020 260) - Surface secondaire */
  --bg-card: #0C1018;                   /* oklch(0.17 0.022 258) - Cartes & Modales */
  --bg-glass: rgba(12, 16, 24, 0.78);   /* Surface HUD avec backdrop-filter: blur(16px) */
  --bg-elevated: #121824;               /* oklch(0.21 0.025 255) - Hover / Active row */

  /* Grilles Structurelles & Bordures (1px Hairline) */
  --border-hairline: rgba(255, 255, 255, 0.08); /* Grille structurelle continue 1px */
  --border-subtle: rgba(255, 255, 255, 0.05);   /* Diviseurs internes */
  --border-focus: rgba(216, 229, 248, 0.38);    /* Bordure active Titane Glacé */
  --border-gold: rgba(229, 193, 88, 0.45);      /* Bordure Cashprize / Séquestre */

  /* Hiérarchie Typographique */
  --text-primary: #F4F7FB;              /* oklch(0.97 0.008 250) - Blanc Albâtre */
  --text-secondary: #94A3B8;            /* oklch(0.71 0.025 255) - Gris Acier */
  --text-muted: #64748B;                /* oklch(0.55 0.025 255) - Métadonnées techniques */

  /* Accents Chirurgicaux */
  --accent-titanium: #D8E5F8;           /* oklch(0.91 0.035 245) - Titane Glacé (CTA & Focus) */
  --accent-gold: #E5C158;               /* oklch(0.82 0.140 85)  - Or Souverain (Cashprizes & Escrow) */
  --accent-gold-dim: rgba(229, 193, 88, 0.12);
  --telemetry-emerald: #10B981;         /* oklch(0.70 0.170 160) - Télémétrie Live / Anti-Cheat OK */
  --telemetry-amber: #F59E0B;           /* oklch(0.75 0.160 75)  - Séquestre Verrouillé / Alerte */
  --telemetry-crimson: #EF4444;         /* oklch(0.63 0.220 25)  - REC ● / Élimination / Danger */

  /* Typographie à 3 Niveaux */
  --font-display: 'Syne', 'Clash Display', sans-serif;
  --font-serif-italic: 'Instrument Serif', 'Playfair Display', Georgia, serif;
  --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
  --font-mono: 'JetBrains Mono', monospace;

  /* Courbes cinétiques & Transitions */
  --ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1);
  --ease-tactical: cubic-bezier(0.22, 1, 0.36, 1);
}
```

---

## 3. Système Typographique à 3 Niveaux & Loi `tabular-nums`

1. **`--font-display` + `--font-serif-italic` (Duo Haute Tension — Titres & Hero) :**
   - **Ligne 1 (Romain Droit) :** `Syne` (`font-weight: 800`, `text-transform: uppercase`, `letter-spacing: -0.035em`, `line-height: 0.96`).
   - **Ligne 2 (*Vrai Italique*) :** `Instrument Serif` (`font-style: italic`, chargée avec `ital@1` depuis Google Fonts, `font-weight: 400`, `letter-spacing: -0.015em`, couleur `--accent-gold` `#E5C158` ou `--accent-titanium` `#D8E5F8`).
2. **`--font-sans` (Interface & Cockpit) :**
   - **Police :** `Plus Jakarta Sans` (`wght@400;500;600;700`).
   - **Usage :** Navigation, filtres de catalogue, formulaires, libellés de boutons, descriptions courtes.
3. **`--font-mono` (Télémétrie, Finance & Scores) :**
   - **Police :** `JetBrains Mono` (`wght@400;500;600;700`).
   - **Règle Absolue :** `font-variant-numeric: tabular-nums; font-feature-settings: "tnum" 1, "zero" 1;` obligatoire sur **100 %** des montants en euros/centimes, cagnottes séquestrées, commissions (800 bps), scores ELO/MMR, chronomètres (`00:42:18`), FPS, ping, statistiques d'arsenal et écritures du grand livre (`ledger_entries`).

---

## 4. Composants Structurels & Règles Géométriques
- **Angles Vifs Tactiques :** Boutons principaux, badges d'état et conteneurs HUD utilisent des angles vifs `border-radius: 0px` ou micro-chanfreinés `2px` (`rounded-none` ou `rounded-[2px]`).
- **Grille Hairline `1px` :** Toutes les sections et cartes de tournois s'articulent sur une grille visible de `1px solid rgba(255, 255, 255, 0.08)` sans ombres floues baveuses.
- **Alignement Subgrid des Cartes :** Les cartes du catalogue de tournois ont une hauteur strictement égale par rangée (`display: grid; grid-template-rows: subgrid` ou flex-col avec `mt-auto` sur le pied de carte) et des boutons d'action parfaitement alignés horizontalement.
- **Espacement Minimum des Contrôles :** Minimum `12px` (`gap-3` ou `gap-4`) entre tous les boutons, filtres et onglets adjacents (zéro chevauchement ni collision de boîtes englobantes).
