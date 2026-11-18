# Skill K.E.P Design System

Vállalati tanulási portálhoz (LMS) készült design system: tanulói, vezetői és adminisztrátori felületekhez.
Élő előnézet: nyisd meg az `index.html` fájlt böngészőben (téma, sűrűség és cégszín váltóval).

## Fájlok

| Fájl | Tartalom |
| --- | --- |
| `css/tokens.css` | Alap skálák, szemantikus tokenek, sötét téma, sűrűség, csökkentett mozgás |
| `css/components.css` | Keretrendszer-független komponensosztályok (`.kep-*`), `@layer components` alatt |
| `css/tailwind-theme.css` | Tailwind v4 híd: a szemantikus tokenek utility osztályként (`bg-surface`, `text-muted`…) |
| `index.html` | Vizuális előnézet: alapok, komponensek, minták |

## Felépítés

1. **Alap skálák**: `--kep-ink-*` (semleges), `--kep-brand-*` (márka, alapból petrol), `--kep-gold-*` (eredmények), státusz skálák, `--kep-chart-1…6`.
2. **Szemantikus tokenek**: `--kep-bg`, `--kep-surface`, `--kep-text`, `--kep-primary`, `--kep-success`… A komponensek **csak ezeket** használják.
3. **Üzemmódok**:
   - Téma: `data-theme="dark" | "light"` a `<html>`-en; ha nincs megadva, az operációs rendszer beállítása dönt.
   - Sűrűség: `data-density="compact"` (admin táblázatokhoz); alapértelmezés a kényelmes.
   - Cégszín: a `--kep-brand-50…950` skála felülírása **a `<html>` elemen** (inline style vagy `:root[data-tenant]`). Mélyebb wrapperen nem működik, mert a szemantikus tokenek a `:root`-on oldódnak fel.

```blade
<html lang="hu" class="kep-root" data-density="comfortable"
      style="--kep-brand-600: #2c47bf; --kep-brand-700: #253a9b; /* … mind a 11 lépcső */">
```

Cégszín megadásakor a teljes 50–950 skála kell. Feltétel: a 600-as lépcső fehér háttéren legalább 4,5:1 kontrasztú legyen, a 400-as a sötét háttéren (`--kep-ink-950`) szintén.

## Alapelvek

- **Határidő előre**: kötelező képzés és lejárat mindig elsőként látszik; az állapot színben, ikonban és szövegben is.
- **Adat, nem dísz**: táblázatos számjegyek (`.kep-num`), összesítés a részletek előtt.
- **Cégre szabható**: egy skála felülírása márkázza az egész felületet.
- **Akadálymentes**: WCAG 2.1 AA, látható fókusz, 44 px érintési cél (`--kep-control-h-lg`), `prefers-reduced-motion`.

## Tipográfia és írásmód

- IBM Plex Sans (felület), IBM Plex Mono (azonosítók, időzítő). Teljes magyar ékezetkészlet.
- Felületi alapméret 14 px, tananyag olvasónézet 16 px (`.kep-prose`).
- Dátum: `2026. 12. 15.` (táblázat), `2026. dec. 15.` (szöveg). Százalék: `87,4 %`. Név: családnév elöl.
- Gombfelirat igével kezdődik: „Képzés indítása”, „Exportálás”.
- Az arany (`--kep-achievement`) csak eredményt jelöl (tanúsítvány, teljesítés), soha nem műveletet.

## Komponensek

Gomb (`.kep-btn` + `--primary | --subtle | --ghost | --danger`, `--sm | --lg | --icon`), gombcsoport, űrlapmező
(`.kep-field`, `.kep-input`, `.kep-select`, `.kep-textarea`, `.kep-check`, `.kep-switch`), állapotjelző (`.kep-badge`),
avatar, kártya, KPI (`.kep-stat`), haladásjelző (`.kep-progress`, `.kep-ring` a `--value` változóval), figyelmeztetés
(`.kep-alert`), értesítés (`.kep-toast`), fülek, morzsamenü, szűrőchip, adattábla (`.kep-table` + eszközsor, lapozó),
üres állapot, párbeszédablak (`<dialog class="kep-dialog">`), tooltip (`data-tooltip`), alkalmazáskeret
(`.kep-shell`, `.kep-nav`), kurzusvázlat (`.kep-outline`, `data-state="done|current|locked"`), tesztválasz
(`.kep-option`, `data-result="correct|incorrect"`), leírólista (`.kep-dl`).

Az állapotot ARIA attribútumok hordozzák: `aria-current`, `aria-selected`, `aria-pressed`, `aria-invalid`, `aria-sort`.

## Beépítés Laravel + Tailwind v4 alá

```css
/* resources/css/app.css */
@import 'tailwindcss';
@import '../../design-system/css/tokens.css';
@import '../../design-system/css/components.css';
@import '../../design-system/css/tailwind-theme.css';
```

```bash
bun install
bun run dev
```

A betűtípusokat éles környezetben érdemes saját szerverről kiszolgálni (pl. `@fontsource/ibm-plex-sans`), az előnézet Google Fontsot használ.
