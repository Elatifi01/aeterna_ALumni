# Aeterna — Design System (`design.md`)

> **Direction:** taken from the two reference images.
> **Image 2** (SaaS dashboard) defines the application shell, tables and data density.
> **Image 1** (checkout "good UI/UX") defines the form language.
> *Neither reference image is kept in the repository — their rules are fully transcribed into
> §3–§7 below, which are the binding source. The only image in the project is the logo.*
> **Hard rule:** no shadows on cards. See §3.4.
> **Colors:** derived from the Aeterna logo — navy `#16375A` for primary actions, steel blue
> `#2E6F9E` as the single accent. See §2.2. **Light and dark themes** — §2.7.

---

## 1. Principles taken from the references

1. **Borders, not shadows.** Surfaces are separated by a 1px border and a background tone shift.
2. **One accent, used sparingly.** In image 2 the accent appears only on: active nav indicator,
   selected row indicator, current pagination page, workspace dots. Everything else is neutral.
3. **The darkest brand tone carries primary actions.** In image 2 that button is black; here it is
   the logo navy, so the accent stays free for indicators.
4. **Grouping before density.** Image 1 wins by chunking a long form into named sections with
   breathing room, not by removing fields.
5. **Labels inside the field.** Placeholder-style labels with a leading icon, rather than a label
   stacked above every input.
6. **Status is a pill, never plain text.** Tinted background + colored text + no border.
7. **Numbers are tabular.** All figures in tables and stat cards use tabular-nums.

---

## 2. Tokens

Declared once in `resources/css/app.css` as CSS variables, consumed through Tailwind v4 `@theme`.
Nothing in the codebase hardcodes a hex value.

### 2.1 Neutrals — light mode

Neutral grays, matching the reference dashboard: a light gray canvas behind the shell, pure white
for every content surface. No blue tint in the neutral ramp — the blue stays in the brand tokens.

| Token | Value | Use |
|---|---|---|
| `--color-canvas` | `#F1F1F3` | Page background behind the app shell |
| `--color-surface` | `#FFFFFF` | Cards, table, sidebar, header, inputs |
| `--color-surface-muted` | `#FAFAFA` | Table header, hover rows, section strips |
| `--color-border` | `#E7E7EA` | All 1px borders and dividers |
| `--color-border-strong` | `#D4D4D9` | Input borders, focused dividers |
| `--color-text` | `#15171C` | Titles, table values |
| `--color-text-secondary` | `#5A5F6B` | Labels, secondary text, nav items |
| `--color-text-muted` | `#8B909B` | Placeholders, captions, section eyebrows |

### 2.2 Brand — derived from the logo

The logo holds two blues: the deep navy of the **A** and the wordmark, and the lighter steel blue
of the infinity loop. The navy becomes the primary action color (it replaces the near-black of
image 2), the mid blue becomes the accent, and the pale blue stays decorative.

| Token | Value | Source | Use |
|---|---|---|---|
| `--color-primary` | `#16375A` | navy of the A / wordmark | Primary buttons, bulk-action bar, sidebar org tile |
| `--color-primary-hover` | `#0F2740` | navy, darkened | Hover / active state of primary |
| `--color-accent` | `#2E6F9E` | infinity loop, darkened to 5.4:1 on white | Active nav bar, selected row bar, current pagination page, focus ring, links, numbered section circles |
| `--color-accent-hover` | `#245A81` | | |
| `--color-accent-soft` | `#E9F1F7` | accent at ~8% | Selected row tint, selected choice tile, active nav background |
| `--color-accent-contrast` | `#FFFFFF` | | Text/icon on accent or primary |
| `--color-brand-light` | `#7FAAC6` | infinity loop as drawn | Decorative only: avatar backgrounds, empty-state icons, chart secondary series. **Never text, never a border on its own.** |

Contrast: `--color-accent` on white is 5.4:1 and `--color-primary` on white is 12.3:1 — both pass
AA for text and UI. `--color-brand-light` fails AA and is therefore barred from any text usage.

### 2.2.1 Logo usage

- **Sidebar** — the ORGANIZATION's own logo when it has uploaded one, on a neutral
  `surface` tile with a 1px border, `--radius-lg`, `object-contain` so any aspect ratio
  fits. The people using the product every day work for their establishment, not for
  Aeterna. With no logo the tile falls back to the Aeterna mark only (no wordmark), white
  on a `--color-primary` 36px tile. Same tile on the collapsed sidebar.
  Uploads are PNG, JPG or WebP, at most 2 MB and between 64 and 2000 pixels square.
  SVG is refused: it is a document format that can carry script, and the file is served
  from our own origin.
- **Login / password pages** — full lockup (mark + wordmark) centered above the card, on `canvas`.
- **Campaign emails** — full lockup at the top of the email; the sending organization's own name
  appears as text below it, since campaigns go out on behalf of the tenant.
- **Favicon / app icon** — mark only, navy on white.
- The logo is never recolored, never placed on `--color-accent`, and keeps clear space equal to
  the height of the mark's crossbar on all sides. The internal gradient of the mark is not
  reproduced anywhere else in the interface — flat colors only in the UI.

### 2.3 Semantic

| Token | Value | Use |
|---|---|---|
| `--color-success` / `-soft` | `#12855C` / `#E6F4EE` | "Actif", "Envoyée", "Présent", positive delta |
| `--color-warning` / `-soft` | `#A9721A` / `#FBF2E3` | "Brouillon", "Expire bientôt", "En attente" |
| `--color-danger` / `-soft` | `#C43D3D` / `#FBEBEB` | "Échec", "Annulé", destructive actions |
| `--color-info` / `-soft` | = `--color-accent` / `--color-accent-soft` | "En cours d'envoi", informational |

Info reuses the accent rather than introducing a second blue — two blues in the same table would
be unreadable as status.

### 2.4 Typography

- **UI + body:** `Inter` (variable), fallback `system-ui, sans-serif`.
- **Data:** same family with `font-variant-numeric: tabular-nums` on every numeric cell,
  stat value, price, date and counter.
- No second display face — the references use a single family; personality comes from weight
  and spacing, not from a font pairing.

| Role | Size / line | Weight | Spacing |
|---|---|---|---|
| Page title | 24 / 32 | 600 | −0.01em |
| Page subtitle | 14 / 20 | 400 | — |
| Section title | 16 / 24 | 600 | — |
| Sidebar section eyebrow | 11 / 16 | 600, uppercase | 0.06em |
| Nav item | 14 / 20 | 500 | — |
| Body / table cell | 14 / 20 | 400 | — |
| Table header | 13 / 18 | 500 | — |
| Stat label | 13 / 18 | 500 | — |
| Stat value | 28 / 34 | 600 | −0.02em |
| Badge / caption | 12 / 16 | 500 | — |
| Input text | 14 / 20 | 400 | — |
| Button | 14 / 20 | 500 | — |

### 2.5 Spacing, radius, borders

- Spacing scale: `4 · 8 · 12 · 16 · 20 · 24 · 32 · 40 · 48`.
- Radius: `--radius-sm 6px` (badges, checkboxes) · `--radius-md 8px` (buttons, inputs, nav items)
  · `--radius-lg 12px` (cards, table container, sidebar cards).
- Border: always `1px solid var(--color-border)`.
- Focus: `2px` accent ring with `2px` offset, on every interactive element.

### 2.6 Elevation

**Cards, stat cards, tables, the sidebar, inputs and the header carry no shadow — ever.**
Separation comes from `--color-border` and the `canvas / surface` tone difference.

One exception, only for elements that genuinely float above the page and have no border context:
dropdown menus, the bulk-action bar, modals and toasts.

```
--shadow-overlay: 0 8px 24px rgba(18, 33, 47, .10);
```

If you prefer zero shadows anywhere, say so and these four elements switch to
`border + --color-surface-muted` instead.

In dark mode the overlay shadow becomes `0 8px 24px rgba(0, 0, 0, .45)`.

---

### 2.7 Dark mode

Dark mode is a **token override only**. Not a single component, layout rule, spacing value or
class outside `app.css` is aware of the theme — every component already reads tokens, so the two
themes stay identical in structure. The no-shadow rule holds: surfaces are still separated by
borders and by the canvas/surface tone difference, which is why `--color-surface` is *lighter*
than `--color-canvas` in dark mode, exactly as it is darker than the canvas in light mode.

Backgrounds carry a slight navy cast taken from the logo, so dark mode reads as the same product
rather than as a generic gray theme.

| Token | Dark value | Note |
|---|---|---|
| `--color-canvas` | `#0C1219` | Page background |
| `--color-surface` | `#131B24` | Cards, table, sidebar, header, inputs |
| `--color-surface-muted` | `#1A232E` | Table header, hover rows |
| `--color-border` | `#232E3A` | Dividers, card borders |
| `--color-border-strong` | `#33404E` | Input borders |
| `--color-text` | `#E8EDF2` | |
| `--color-text-secondary` | `#A2AEBD` | |
| `--color-text-muted` | `#788594` | |
| `--color-primary` | `#24618F` | Navy lifted so it stays visible on a dark canvas; white label = 6.4:1 |
| `--color-primary-hover` | `#2E77AB` | |
| `--color-accent` | `#6FAFDA` | The loop blue at full brightness — on dark it needs no darkening |
| `--color-accent-hover` | `#8AC0E6` | |
| `--color-accent-soft` | `#16303F` | Selected row tint, active nav background |
| `--color-accent-contrast` | `#0C1219` | Text on an accent fill |
| `--color-brand-light` | `#7FAAC6` | Unchanged, still decorative only |
| `--color-success` / `-soft` | `#34B27E` / `#102A21` | |
| `--color-warning` / `-soft` | `#D9A441` / `#2C2214` | |
| `--color-danger` / `-soft` | `#E4696A` / `#2E1719` | |
| `--color-info` / `-soft` | = accent / accent-soft | |

**Rules**

- Badges keep the same shape; only their `-soft` background and text color swap. Text on a
  `-soft` background is the full-strength semantic color, which passes AA on these tints.
- The active nav bar, selected row bar and current pagination page use `--color-accent` in both
  themes.
- Inputs stay `--color-surface`, one step lighter than the canvas — never darker than the page.
- Images and avatars are not dimmed. The only asset that changes is the logo (below).
- Charts and the stat-card deltas read the same semantic tokens, so nothing else needs a variant.

**Logo in dark mode**

- Sidebar tile: `--color-surface-muted` background with the mark in `#FFFFFF` — the navy tile
  from light mode disappears against a dark sidebar.
- Auth pages and campaign emails: `logo-full-light.svg`, the lockup with a white wordmark and the
  mark drawn in `--color-accent`. The navy lockup is never placed on a dark background.

**Implementation**

- Class strategy: `<html class="dark">`, tokens redeclared under `.dark` in `app.css`.
- Default follows the operating system (`prefers-color-scheme`); the user can override with a
  sun/moon toggle placed in the sidebar bottom block, next to Paramètres.
- The choice is stored in `localStorage` and applied by a tiny inline script in the Blade layout
  before the app renders, so there is no white flash on load. **No database column and no
  migration** — the cahier des charges defines no user preference storage, so the theme stays
  client-side.
- `<meta name="color-scheme" content="light dark">` so native controls, scrollbars and the
  date picker follow the theme.

---

## 3. Application shell (from image 2)

```
┌──────────────┬────────────────────────────────────────────────────────┐
│ ORG CARD     │  Page title                    ⚙ 🔔 avatars  [Primary] │  header 72px
│ Search  ⌘K   ├────────────────────────────────────────────────────────┤
│              │  [View ▾][Filter][Sort]  Toggle    [Ghost][Ghost][+ Add]│  toolbar 56px
│ MAIN MENU    │ ┌────────┬────────┬────────┬────────┐                   │
│ ▸ Dashboard  │ │ stat   │ stat   │ stat   │ stat   │                   │
│ ▸ Alumni     │ └────────┴────────┴────────┴────────┘                   │
│ ▸ Événements │ ┌────────────────────────────────────────────────────┐  │
│ ▸ Campagnes  │ │ table                                              │  │
│              │ │                                                    │  │
│ ADMINISTRATION│ │                                                   │  │
│ ▸ Utilisateurs│ │                                                   │  │
│ ▸ Rôles      │ ├────────────────────────────────────────────────────┤  │
│ ▸ Organisation│ │ 10 ▾ per page          ‹ 1 2 3 … ›     Go to page  │  │
│              │ └────────────────────────────────────────────────────┘  │
│ Aide         │                                                          │
│ Paramètres   │                                                          │
│ [Upgrade ⤴]  │                                                          │
└──────────────┴────────────────────────────────────────────────────────┘
```

### 3.1 Sidebar — 264px, `surface`, right border, full height, no shadow

- **Organization card** (top): 36px rounded-lg logo tile, organization name (14/600), plan name
  below (12, muted) — the plan comes from the active subscription. Collapse chevron on the right;
  collapsed width 72px, icons only, tooltips on hover.
- **Search**: 36px input, muted background, magnifier icon, `⌘K` hint chip on the right.
- **Section eyebrows**: `MENU PRINCIPAL`, `ADMINISTRATION`, and for the super admin `PLATEFORME`.
- **Nav item**: 40px tall, 8px radius, 20px outline icon + label, count badge right-aligned
  (muted pill; e.g. campaigns being sent). Hover = `surface-muted`.
  **Active** = `surface-muted` background + **3px accent bar flush to the left edge of the sidebar**
  + `text` color + 500 weight.
- **Bottom block**: Aide, Paramètres, the light/dark toggle (sun/moon icon button, §2.7), then the
  **plan card** — border, `surface-muted`,
  accent-tinted square icon, "Passer au plan Pro" + one line on what it unlocks, chevron.
  Hidden when the organization is already on the highest plan.

Super admin sidebar uses the same shell with a platform badge in place of the organization card.

### 3.2 Header — 72px, `surface`, bottom border

Page title + optional one-line subtitle on the left. On the right: icon buttons (36px, ghost,
border on hover), the user avatar, then at most **one** primary button.

### 3.3 Toolbar — 56px, sits above the content, no border box of its own

Left group: view/scope dropdown, `Filtrer` (opens a popover with the module's filters), `Trier`.
Right group: ghost secondary actions, then the primary `+ Ajouter …` button in `--color-primary` (navy).
Buttons: 36px tall, 8px radius, 1px border, `surface` background, icon 16px.

### 3.4 Content surfaces

Cards, stat cards and tables: `surface`, 1px border, `--radius-lg`, **no shadow**.

---

## 4. Data display

### 4.1 Stat cards
Row of 4 (2 on tablet, 1 on mobile), equal width, 20px padding.
Label (13, secondary) + info icon → tooltip explaining the metric. Value (28/600, tabular).
Under it: `vs mois dernier` in muted, followed by a delta chip — `success-soft` background with
an up arrow, `danger-soft` with a down arrow, muted when flat.

### 4.2 Table
- Container: bordered, `--radius-lg`, `overflow-hidden`, horizontal scroll on small screens.
- Header row: `surface-muted`, 13/500 secondary text, 44px, bottom border, sortable columns show
  a chevron on hover.
- Rows: 52px, bottom border, hover `surface-muted`.
- **Column 1 is a checkbox** (header checkbox = select all on page).
- **Selected row**: `accent-soft` background + 3px accent bar on the left edge of the row.
- Status column: badge — 12/500, 6px radius, 2px/8px padding, `*-soft` background, `*` text,
  no border.
- Actions column: right-aligned row of 32px ghost icon buttons — Voir (eye), Modifier (pencil),
  Supprimer (trash, turning `danger` on hover). One click rather than two. Each is icon-only,
  so each carries an `aria-label` naming the record plus a tooltip; an action the user has no
  permission for is not rendered at all, never disabled. Extra per-module actions (activate /
  deactivate) sit between Modifier and Supprimer. Deleting from a row opens the destructive
  confirmation of §5.5, since there is no detail page to explain the consequence first.
- **Selection bar** (amended 2026-09-17, was a floating pill at the bottom): appears ABOVE the
  table once ≥1 row is ticked, in the flow rather than floating — `accent-soft` background, thin
  `accent` border, 8px radius. Left: `N sélectionné(s)` + `Annuler la sélection`. Right: the allowed
  bulk actions, the first of which everywhere is `Supprimer la sélection` (danger button, opens the
  §5.5 confirmation naming the count). It never covers a row and never competes with a toast for a
  corner of the screen. The selection clears once the action has gone through.
- **Footer**: `Afficher par page [10 ▾]` on the left, numbered pagination in the center (current
  page = accent background, white text, 8px radius), `Aller à la page [ ] Go` on the right.
- **Empty state**: centered inside the table container — icon in a muted circle, one sentence
  describing what belongs here, and the primary create button. Never an empty grid.

### 4.3 Column set per module
- **Alumni**: checkbox · Nom (avatar initials + name over email) · Diplôme · Promotion ·
  Entreprise · Poste · Ville · actions.
- **Événements**: checkbox · Titre · Date · Lieu · Inscrits (`24/50`) · Participation (%) ·
  Statut · actions.
- **Campagnes**: checkbox · Objet · Destinataires · Envoyées · Statut · Date d'envoi · actions.
- **Utilisateurs**: checkbox · Nom · Email · Rôle · Statut · actions.
- **Organisations** (super admin): checkbox · Organisation · Plan · Alumni · Statut · Fin
  d'abonnement · actions.

---

## 5. Forms (from image 1)

### 5.1 Structure
- Single column, max width 640px, centered in the content area — or in a right-side panel for
  quick creates.
- Title + one-line subtitle at the top, exactly like the checkout header.
- **Numbered sections** (`①` 28px circle in accent, section title 16/600) **only when the form is
  genuinely sequential or multi-part**: campaign creation (contenu → destinataires → envoi),
  event creation (informations → date & lieu → inscriptions), organization creation
  (organisation → administrateur → abonnement). For a simple 6-field form, use a plain section
  title and no numbers.
- Two fields share a row only when both are short (Ville / Code postal, Date début / Date fin,
  Promotion / Diplôme). Everything else is full width.

### 5.2 Input
48px tall, 1px `border-strong`, 8px radius, `surface` background, 20px leading icon in muted,
label as placeholder. Focus: accent border + focus ring. Filled state keeps the icon.
Two-line inputs (like the address field in image 1) show the label on line 1 and the hint on
line 2 in muted.
Error: `danger` border + one line of `danger` text below saying what to fix, never just "invalide".

### 5.3 Choice tiles
For a small set of exclusive options (billing cycle monthly/yearly, campaign recipients
all/filtered, event status): a row of tiles — icon on top, label below, 1px border, 8px radius.
Selected = accent border + `accent-soft` background + accent icon. Not radio buttons.

### 5.4 Checkbox
20px, 6px radius, accent when checked with a white check, label 14 on the right.

### 5.5 Actions
Primary submit at the bottom: 48px, full width on mobile, auto width right-aligned on desktop,
`--color-primary` (navy), label + trailing chevron or leading icon. `Annuler` as a ghost button on
its left.
A one-line reassurance under a consequential action — under "Envoyer la campagne":
`La campagne sera envoyée à 248 alumni. Cette action est définitive.`
Destructive confirmations use a modal with the danger button and the count of affected records.

---

## 6. Buttons

| Variant | Background | Border | Text | Use |
|---|---|---|---|---|
| Primary | `primary` (navy) | none | white | One per view: the main create/save action |
| Secondary | `surface` | `border` | `text` | Toolbar actions, Cancel |
| Ghost | transparent | none | `text-secondary` | Icon buttons, tertiary links |
| Danger | `danger` | none | white | Confirmed deletions only |
| Accent | `accent` | none | `accent-contrast` | Reserved for plan upgrade / subscribe — the only place accent fills a button |

Heights: 36px (toolbar, table), 40px (default), 48px (form submit). Radius 8px. Icon 16px with
8px gap. Disabled: 45% opacity, no pointer.

---

## 7. Responsive

| Breakpoint | Behavior |
|---|---|
| `< 768px` | Sidebar becomes an off-canvas drawer, hamburger in the header. Stat cards stack. Tables become stacked cards: primary field bold at the top, other fields as label/value rows, status badge top-right, `⋯` menu. Toolbar collapses into a search field + a single `Filtres` button opening a sheet. Primary button becomes full width. |
| `768–1279px` | Sidebar collapsed to icons. Stat cards 2×2. Table keeps 4–5 priority columns; the rest hidden. |
| `≥ 1280px` | Full layout as in image 2. |

Touch targets ≥ 44px on mobile.

---

## 8. Implementation notes

- All tokens in `resources/css/app.css` under `@theme`; every color in the codebase reads a token,
  so a palette change touches that file only.
- Logo files: `public/logo-full.svg` (mark + wordmark), `public/logo-full-light.svg` (dark-mode
  lockup), `public/logo-mark.svg` (mark, single-color so it can be filled white in the sidebar
  tile), `public/favicon.svg`.
- Add `ThemeToggle` to the Phase 2.1 component list; it is the only theme-aware component.
- Icons: `lucide-react`, 16px in dense UI, 20px in nav and inputs, `stroke-width: 1.75`.
- Components to build first (Phase 2.1 of the roadmap): `AppShell`, `Sidebar`, `NavItem`,
  `PageHeader`, `Toolbar`, `StatCard`, `DataTable` (+ `BulkBar`, `Pagination`, `EmptyState`),
  `Badge`, `Button`, `Input`, `Select`, `ChoiceTile`, `Checkbox`, `Modal`, `Toast`, `Avatar`.
- Motion: 150ms ease-out on hover/focus, 200ms on drawer and popover. Nothing else animates.
  `prefers-reduced-motion` disables all transitions.
- Interface language: French, sentence case, active voice. A button labelled `Enregistrer`
  produces the toast `Enregistré`.
