# Changelog — BlueFairy_MegaMenuHyva

## 2.2.0 — 2026-11-27

**This package is now free and open source under the MIT licence, and lives in its own public
repository**, `github.com/bluefairydevelopment/module-mega-menu-hyva`, published on packagist.org.
It was a package inside the paid `module-mega-menu` monorepo and declared `proprietary`.

No template, component or behaviour changes. What changes is how you get it and what you may do
with it:

- `composer require bluefairy/module-mega-menu-hyva` now works with no repository configuration and
  no credentials, because the package is on public Packagist.
- The core module it extends, `bluefairy/module-mega-menu` (`^2.0`), is still a paid extension, as
  is `bluefairy/module-mega-menu-graph-ql`, which the Hyvä desktop panels load their content
  through. Install those from wherever you bought them. Adobe's Marketplace repository carries no
  `hyva-themes/*` packages, which is why a Hyvä companion cannot be listed there and is distributed
  this way instead.
- `LICENSE.txt` is the MIT licence. It was the paid end-user licence agreement, which defined the
  Software as `BlueFairy_MegaMenu`, `BlueFairy_MegaMenuGraphQl` **and this package** together; that
  is no longer the arrangement, and no licence key or purchase covers this package.
- The README no longer links to `../MegaMenu/README.md`, which does not exist outside the monorepo.
- `Test/Playwright/` is not published. It drives our own test rig through its `rig-lib.sh` and is
  infrastructure rather than part of the package; no already-published BlueFairy Hyvä companion
  ships it. The PHPUnit unit and integration suites and the Node component tests are all here, as
  they are in the other companions.

A minor version rather than a patch: no code changes, but the licence and the distribution channel
both do. The package name is unchanged, so an existing installation resolves 2.2.0 from
packagist.org on its next `composer update` with no configuration change.

## 2.1.3 — 2026-11-25

No change in this package; released with the set so all three packages carry one version.
This package had no `*` requirement to correct. Update all three packages together.

### Changed

- The bundled `LICENSE.txt` states Version 2.1.3, the version this release licenses.

## 2.1.2 — 2026-11-10

### Fixed

- **Under Hyvä's strict CSP the browser refused both menu scripts, so neither menu opened.** There were two reasons.
  First, a comment in the desktop script mentioned the script tag itself. Hyvä's CSP helper puts its nonce on the last
  script tag it finds, so the nonce landed in that comment and the real tag had none. The comment no longer names the
  tag. Second, Hyvä caches the menu's parent block (`topmenu_generic`) for an hour, and a cached copy carries the nonce
  of the request that filled the cache, which no later page accepts. This package now turns that block's cache off;
  the full page cache still caches the page.
- The menus work under Hyvä's strict CSP (the Alpine CSP build). The desktop menu's item and panel attributes held
  JavaScript expressions, such as `canHoverOpen(); openPanel(12)` and `panel.featured.type === 'image'`, which that
  build cannot run, and the console showed CSP errors. Every Alpine attribute now names a property
  or a method; each item carries its category id in `data-panel-item-id`, and the loaded panel is its own component,
  `megaMenuDesktopPanel`. The mobile menu's scroll lock had the same problem. Stores without strict CSP run the same
  templates and components: neither template has a separate path for either mode. The block cache of
  `topmenu_generic` is off on every Hyvä store, with strict CSP or without.

### Changed

- Requires `hyva-themes/magento2-theme-module` 1.3.11 or later (was any version). Both menu templates use
  `Hyva\Theme\ViewModel\HyvaCsp`, which first shipped in 1.3.11; on an older theme module the menus could not render.

### Tests

- A template check fails if an inline script is not registered with Hyvä's CSP helper or an Alpine attribute holds an
  expression. The desktop components are tested with Node's own test runner:
  `node --test MegaMenuHyva/Test/Node/*.test.mjs`.
- Static analysis no longer loads Magento's unit-test framework. No change to the module.

### Documentation

- README, FEATURES.md and the marketing text describe the strict CSP support. FEATURES.md section 6 now describes
  the three desktop components (`megaMenuDesktop`, `megaMenuDesktopItem`, `megaMenuDesktopPanel`) and the
  five-minute panel cache as the code has them; it described an older single component.
- The docs no longer say a timed-out panel request shows the plain child links. A failed query does; a request
  abandoned after 8 seconds leaves the skeleton and is fetched again the next time the panel opens.

## 2.1.1 — 2026-10-29

### Fixed
- **Desktop: Escape now returns focus to the menu button on themes that listen for Escape on the whole page.** A
  header search that refocuses its own button on any Escape moved focus there after the panel closed. An Escape that
  closes a panel now stops at the menu; with no panel open it still reaches the page. (BFDPROD-131)

## 2.1.0 — 2026-10-29

### Changed
- **Desktop: each category that opens a panel is now one button.** Its name and its chevron used to be two controls - a
  link to the category page and a separate chevron button - which gave two targets side by side and two Tab stops. Now
  the whole item opens the panel; the chevron points down when closed and turns up when open. Enter, Space or a click
  opens it, Escape closes it and returns focus to it. (BFDPROD-131)
- **Desktop: every panel starts with "See all <category>"**, the link to the category page, as the mobile menu does.
  Categories without a panel or children, CMS Page items and Custom Link items are still plain links.
- The open panel's element is in the page (hidden) while closed, so the button's `aria-controls` always names it; the
  button no longer claims `aria-haspopup`, and its screen-reader text is the category name.

### Documentation
- FEATURES.md shows the new desktop toggle and its "See all" link (`docs/images/desktop-toggle-see-all.png`).
- The desktop panel screenshots are re-shot with the "See all" link, and the marketing copy describes the single
  category button (it still named the old "Show submenu for … category" button and `aria-haspopup`).

If your theme overrides `BlueFairy_MegaMenuHyva::html/header/menu/desktop.phtml`, compare it with this release.

## 2.0.2 — 2026-10-14

Documentation and licence wording corrected; no code change.

## 2.0.1 — 2026-10-13

Documentation only. The 2.0.0 release notes are corrected; no code change. 2.0.1 is identical to 2.0.0 in behaviour.

## 2.0.0 — 2026-10-13

Released with the core 2.0.0 (a major release: its `@api` `MegaMenuItemInterface` gained `getTitle()` /
`setTitle()`); requires the core `^2.0`. Update all three packages together. (M2MAP-688)

The Hyvä dependency of the mega menu now lives here, not in the core module. No change to what a Hyvä store
renders apart from the items below.

### Added
- **CMS Page and Custom Link items show on Hyvä** - desktop bar and mobile menu - as plain top-level links after the
  categories, with their label. A new menu row or a child override shows at once. (M2MAP-690)
- **The phone banner shows an uploaded featured image** and links to the row's Custom URL, else the category;
  the desktop panel's picture comes over GraphQL. (M2MAP-691)
- **Keyboard: the chevron alone opens the loaded panel.** Tab to the chevron and press Enter or Space, or click it
  with no hover first, and the panel's content loads; before, only hovering loaded it and the keyboard got an endless
  loading skeleton.
- **Escape closes the open desktop panel and returns focus to the item's chevron** (or its link).

### Changed
- Label colours, font sizes, border radii and URLs follow the same rule as the Luma menu. Only `#rgb` and `#rrggbb`
  colours and px, rem, em or % sizes are accepted, and an unsafe URL (`//host`, a backslash, a control character) is
  ignored in the mobile menu and in the panel's script.
- Featured CMS block HTML is sanitised in an inert document, so the browser no longer fetches the block's images or
  runs handlers before they are stripped; `noscript`, `noembed` and `noframes` are removed too.
- `magento/framework` is `~103.0.8`: Magento 2.4.8 or 2.4.9. Requires PHP 8.3, 8.4 or 8.5.
- Licence: the independent-contractor clause is reworded; it now reads "Nothing in this Agreement creates a joint
  venture, agency, franchise or employment relationship". The meaning is unchanged (each party remains an
  independent contractor).

### Fixed
- **The label icons New, Sale and Hot broke the Hyvä storefront menu.** They now map to the same icons the Luma menu
  draws (New: sparkles, Sale: badge-percent, Hot: flame-kindling); an unknown icon name shows no icon instead of
  taking the menu down.
- Hovering an item and then clicking its chevron closed the panel; the click now keeps it open and the next click
  closes it.
- The README describes the chevron under Desktop Behavior, where it applies; the mobile menu is an accordion.

## 1.1.1 — 2026-09-17

Release-gate tooling + one template hardening.

### Added
- Package-level `phpstan.neon.dist` and `phpcs.xml.dist` Gate 1 configs. (M2MAP-601)
- Covered by the repository's pre-push release gate. (M2MAP-591)

### Changed
- Ported the canonical Tailwind `@source` gate test — globstar-aware coverage over
  this package's four-level-deep templates, plus comment-stripper and
  glob-exclusion controls — replacing the earlier single-substring check.
  (M2MAP-612)
- `desktop.phtml` derives its unique element id with SHA-256 instead of `md5()`
  (a cosmetic id; the Magento2 standard forbids `md5`).
- `phpunit.xml` renamed to `phpunit.xml.dist`.

## 1.1.0 — 2026-09-17

Pre-sale hardening + Hyvä build registration.

### Added
- **Registration with Hyvä's Tailwind build** (`view/frontend/tailwind/module.css`,
  a `hyva_config_generate_before` observer, `events.xml`, and the PSR-4 autoload
  the package was missing), so this module's utility classes survive the theme
  build instead of being silently purged. (M2MAP-230)
- A unit-test runner and a guard test that the Tailwind sources reach the build.

### Fixed
- The per-category Label Font Size / Border Radius / Show-on-Mobile controls now
  render (CSS-validated) in the desktop and mobile menus.
- Hardened the client-side CMS-block sanitizer (control-char scheme bypass and
  `template`/`svg` mXSS vectors) and validated admin-sourced label colours before
  they reach a style attribute.

### Security
- Ships the proprietary Blue Fairy EULA (was OSL-3.0). (M2MAP-496)

## 1.0.2 — 2026-08-12

No functional changes. Version bump for docs image release in core module.

## 1.0.1 — 2026-08-12

### Fixed
- Added copyright headers to all PHP files missing them
- Layout XML: replaced `default.xml` with `hyva_default.xml` (correct Hyva layout handle)
- Alpine.js component: closed-over panel state reset on Escape key; focus trap removed (accessibility regression)
- Playwright test suite: updated selectors to match revised layout handle

### Changed
- Composer package name corrected from `blue-fairy/module-mega-menu-hyva` to `bluefairy/module-mega-menu-hyva` across all docs

## 1.0.0 — 2026-08-11

### Added
- Desktop mega menu panel with hover open/close and configurable column grid layout
- Featured image, featured CMS block, and featured products carousel slots in desktop panel
- Label badge rendering per navigation item
- Skeleton loading state while panel content loads
- GraphQL lazy-loading of panel content on hover (avoids server-side rendering cost)
- Per-item Full Page Cache integration
- Mobile accordion navigation with expand/collapse per item and chevron rotation animation
- Mobile featured image and subcategory stack rendering
- Keyboard navigation support: Escape key closes open panel, focus management on open/close
- Alpine.js component architecture with reactive state, methods, and GraphQL integration
- Playwright end-to-end test suite covering desktop panel, mobile accordion, and keyboard navigation
