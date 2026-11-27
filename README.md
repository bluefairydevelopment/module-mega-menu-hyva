# BlueFairy_MegaMenuHyva

**Free and open source (MIT).** The Hyvä theme companion for the paid `bluefairy/module-mega-menu` extension. Published on packagist.org; the core module is sold separately. The Hyvä desktop panels also want `bluefairy/module-mega-menu-graph-ql`, which is part of the paid extension.

Hyva theme companion for BlueFairy_MegaMenu. Provides Alpine.js-powered mega menu components with desktop hover-activated panels and mobile accordion navigation, featuring GraphQL lazy-loading, skeleton loading states, and full-page cache invalidation support.

**Install it on Hyvä stores only.** The core module has no Hyvä dependency and renders the mega menu on Luma by
itself; this module is where the Hyvä dependency lives. It binds the core's navigation tree source
(`BlueFairy\MegaMenu\Api\NavigationSourceInterface`) to Hyvä's own `Navigation` view model
(`etc/di.xml` → `Model/HyvaNavigationSource.php`), injects the core `Navigation` view model into Hyvä's
`topmenu_desktop`/`topmenu_mobile` blocks (`view/frontend/layout/hyva_default.xml`), and keeps the core's Luma
renderer off Hyvä pages. A Hyvä store upgrading from an earlier release keeps the same templates, markup and cache
identities.

---

## Requirements

- Magento 2.4.8 or 2.4.9 (`magento/framework ~103.0.8`), measured on 2.4.8-p5 and 2.4.9 (unit, static-analysis, integration and browser suites). The constraint also admits 2.4.8 before -p5, any later 2.4 release on framework 103.0.10 or above (2.4.10 onwards, none released yet), and Adobe Commerce on the same releases; none of these has been run. It refuses 2.4.7 and earlier and any 103.1 framework.
- PHP 8.3, 8.4 or 8.5 (the unit suites are run on all three; the integration and browser checks on 8.4 - Magento 2.4.8 - and 8.5 - 2.4.9)
- Hyva Theme (required), with `hyva-themes/magento2-theme-module` 1.3.11 or later: the menu templates use its `HyvaCsp` view model, which first shipped in 1.3.11
- BlueFairy_MegaMenu 2.0 or later (required — released together with this module; update both)
- BlueFairy_MegaMenuGraphQl (required for GraphQL lazy-loading of panel content; strongly recommended)

---

## Installation

### 1. Install the core and Hyva companion modules

```bash
# Core module (required first)
composer require bluefairy/module-mega-menu

# Hyva companion
composer require bluefairy/module-mega-menu-hyva

# GraphQL companion (strongly recommended for dynamic panel loading)
composer require bluefairy/module-mega-menu-graph-ql
```

### 2. Enable the modules

```bash
php bin/magento module:enable BlueFairy_MegaMenu BlueFairy_MegaMenuHyva BlueFairy_MegaMenuGraphQl
```

### 3. Run setup and compile

```bash
php bin/magento setup:upgrade
php -d memory_limit=2G bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

### 4. Verify installation

```bash
php bin/magento module:status BlueFairy_MegaMenuHyva
# Expected: Module is enabled
```

---

## How It Works

### Desktop Behavior

On hover over a category with mega menu enabled:

1. **Panel Fetch**: Fires the `megaMenuPanel` GraphQL query to load dynamic panel content.
2. **Loading State**: Shows a skeleton animation while GraphQL resolves (8-second timeout with AbortController).
3. **Panel Render**: Displays the panel with its columns of child links and the featured image, CMS block or product list.
4. **Panel Alignment**: Automatically switches to right-aligned positioning if the panel would overflow the viewport.
5. **Hover Intent**: Respects `(hover: hover) and (pointer: fine)` media query — hover is only activated on true hover devices (not touch).
6. **Panel Cache**: Caches loaded panels in memory for 5 minutes (per-item TTL) to avoid redundant queries.
7. **One toggle per item, with its chevron**: An item with a panel or child links is a single button - its name and a chevron
   (pointing down when closed, turned up when open) - that opens the panel with Enter, Space or a click, and fetches its content
   if hovering has not. The panel's first link is **See all <Category>**, to the category page, as on mobile.
   Items without a panel (CMS pages, custom links, childless categories) stay plain links.

### Mobile Behavior

On mobile (screens smaller than `lg` breakpoint):

1. **Accordion Toggle**: Tap to open/close the submenu for a category with children.
2. **Chevron Animation**: Chevron icon rotates to indicate open/closed state.
3. **Featured Banner**: If the category has a featured image, it displays at the top of the submenu.
4. **Keyboard Support**: Pressing Escape closes the submenu; focus management maintains accessibility.
5. **Inert Management**: Non-active menu items become inert while a submenu is open.
6. **Back Navigation**: A back button with chevron returns to the main menu.

### CMS Page and Custom Link Items

A menu row of type CMS Page or Custom Link (Content → Mega Menu → Menu Items) is a plain top-level link after the
categories, on the desktop bar and in the mobile menu, with its label badge: a CMS page by its *Link Text* or the
page's title, linking to the page; a custom link by its *Link Text*, linking to its *Custom URL*. No panel, no
sub-menu. The core module builds these nodes (`BlueFairy\MegaMenu\Model\Navigation\LinkItems`) into the same
tree these templates already print, so the templates needed no change for them.

### Alpine.js Components

The module registers two Alpine data objects:

- **`megaMenuDesktop`**: Parent component managing panel cache, GraphQL fetches, error handling, and panel alignment.
- **`megaMenuDesktopItem`**: Child component (per menu item) handling individual hover state, open/close toggle, and page navigation cleanup.
- **`megaMenuMobile`**: Mobile menu component managing drawer state and submenu coordination.
- **`megaMenuMobileSubMenu`**: Mobile submenu component handling accordion open/close and inert attribute toggling.

### Page Navigation Cleanup

When a user navigates back to a page (via browser back button), the `@pageshow.window` event with `event.persisted` check triggers panel cache reset and menu state cleanup. This ensures a fresh menu state on page return.

### Strict CSP

The menus support Hyvä's strict CSP mode (`Hyva/default-csp`, no `unsafe-inline` or `unsafe-eval` scripts). Both
menu templates register their inline scripts with Hyvä's CSP helper (`HyvaCsp::registerInlineScript()`), and every
Alpine.js attribute names a property or a method, as the Alpine CSP build requires. This module turns off the block
cache of the menus' parent block, `topmenu_generic` (`view/frontend/layout/hyva_default.xml`), because a cached copy
would carry the nonce of an earlier request.

### Cache Invalidation

Per-item cache tags are set automatically by the core `Navigation` ViewModel. The Hyva templates read these identities and apply them to the block, ensuring fine-grained full-page cache (FPC) invalidation when a mega menu item is updated in the admin. Since this release the identities include the `bluefairy_megamenu` sentinel, which creating or deleting a menu row or child override, enabling a row, moving it to another store view and any CMS Page / Custom Link row save purge, so a new menu row (a CMS Page or Custom Link item, a newly enriched category) shows at once (before, it waited for the header blocks' hour); an edit of a shown row or of a child override purges the row's own tag, which the header carries; a linked CMS page's save purges the header too (`cms_p_<id>`). Label colours, font sizes, border radii and admin-entered URLs are printed through the core module's rules (`LabelStyle`, `SafeUrl`): the same values are accepted on Hyvä and Luma.

---

## Configuration

Configuration is managed in the core **BlueFairy_MegaMenu** module. Navigate to:

**Stores → Configuration → BlueFairy → Mega Menu**

### General

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| Enable Mega Menu | Yes/No | No | Master switch — if disabled, mega menu enrichment is not applied. |
| Enable Debug Logging | Yes/No | No | Logs GraphQL queries, panel fetches, cache hits/misses, and errors to `var/log/debug.log`. |

For per-category configuration and child overrides, see the core extension's own README, shipped in `bluefairy/module-mega-menu`.

---

## Template Locations

The Hyva companion provides two templates:

| Template | Location | Purpose |
|----------|----------|---------|
| `desktop.phtml` | `view/frontend/templates/html/header/menu/` | Desktop mega menu with hover-activated panels |
| `mobile.phtml` | `view/frontend/templates/html/header/menu/` | Mobile accordion menu with featured banner support |

Both templates use the core `Navigation` ViewModel (scoped only to the menu block, injected by this module's `hyva_default.xml`) to enrich navigation data with mega menu configuration.

---

## GraphQL Query

The desktop component uses the `megaMenuPanel` GraphQL query (provided by `BlueFairy_MegaMenuGraphQl`):

```graphql
query MegaMenuPanel($categoryId: Int!) {
  megaMenuPanel(category_id: $categoryId) {
    category_id
    column_count
    columns {
      items {
        id
        name
        url
        level
        sort_order
        is_enabled
        label { text color icon }
        has_panel
        children {
          id
          name
          url
          level
          sort_order
          is_enabled
          label { text color icon }
          has_panel
        }
      }
    }
    featured {
      type
      image_url
      image_link
      cms_block_content
      products { id name url image price }
    }
  }
}
```

**Query Behavior:**

- **Timeout**: 8 seconds with AbortController. A request still running then is abandoned, the skeleton stays, and the panel is fetched again the next time it opens. A failed query (a GraphQL error or a network error) shows the fallback (plain child links).
- **Store Header**: The `Store` header is automatically set from the Hyva Store ViewModel if available, ensuring correct store-scoped data.
- **URL Sanitization**: All URLs are validated to begin with `http://` or `https://` to block `javascript:` injection via malicious GraphQL responses.
- **CMS Block HTML Sanitization**: Inline event handlers (`onerror`, `onclick`, etc.) are stripped from CMS block content before rendering via `x-html`. Note: Alpine does not execute `<script>` tags injected via `x-html`, but `on*` attributes will fire.

---

## Panel Cache

The desktop component maintains an in-memory panel cache (scoped to the Alpine component):

```javascript
panelCache: {
  [categoryId]: { /* panel data */ }
},
panelCacheTimes: {
  [categoryId]: Date.now()
}
```

**TTL**: 5 minutes per item. Stale entries are evicted on next access rather than via a timer.

**Page Navigation Reset**: When `@pageshow.window` fires with `event.persisted = true` (user navigates back), the panel cache is cleared and the menu is closed.

---

## Styling & Tailwind CSS

The templates use Hyva's Tailwind CSS classes. Key class groups:

- **Grid Layout**: `grid`, `gap-6`, `grid-template-columns` (inline style computed by `megaMenuPanelGridStyle()`)
- **Responsive**: `hidden lg:flex` (desktop), `lg:hidden` (mobile)
- **States**: `x-show`, `x-transition`, `x-cloak`, `:aria-expanded`
- **Accessibility**: `aria-label`, `aria-controls`, `aria-current`, `sr-only` (screen-reader-only text)

The skeleton loading state uses Tailwind's `animate-pulse` and grayscale placeholders.

---

## Troubleshooting

**Panel not appearing on hover (desktop)**

- Confirm **Stores → Configuration → BlueFairy → Mega Menu → Enable Mega Menu** is set to Yes.
- Verify **BlueFairy_MegaMenuGraphQl** is enabled and compiled: `php bin/magento module:status BlueFairy_MegaMenuGraphQl`.
- Check browser console (F12 → Console) for GraphQL errors: `[MegaMenu] panel fetch failed...`.
- Enable debug logging: **Stores → Configuration → BlueFairy → Mega Menu → Enable Debug Logging** and check `var/log/debug.log` for GraphQL endpoint failures.
- Confirm the category is configured with mega menu enabled: **Catalog → Categories → [category] → Mega Menu → Is Enabled = Yes**.
- Hard-refresh the browser (Ctrl+Shift+R / Cmd+Shift+R) and check the Network tab to see if the `/graphql` request is being sent and what status it returns.

**Mobile accordion not expanding**

- Confirm you are on a mobile screen (< `lg` breakpoint, typically < 1024px width).
- Check the browser console for JavaScript errors — the Alpine.js component may have failed to initialize.
- Verify Hyva theme Alpine.js is loaded (check Network tab for `alpine.js` file).
- Try clearing the browser cache and reloading.

**Skeleton shows but panel never loads**

- The GraphQL query took longer than 8 seconds and was abandoned (it is tried again when the panel next opens). Check server-side PHP/MySQL performance.
- Confirm the GraphQL endpoint is responding: use Postman or the browser Network tab to test `/graphql` directly.
- Check `var/log/system.log` and `var/log/debug.log` for database or GraphQL resolver errors.
- If using a firewall or CDN, ensure the `/graphql` endpoint is not being blocked or rate-limited.

**Featured image not displaying**

- Confirm the image was uploaded and the category (Catalog → Categories → [category] → Mega Menu → Featured Image) or menu item SAVED: an upload waits in `pub/media/bluefairy/megamenu/tmp/` and moves to `pub/media/bluefairy/megamenu/` only with the save.
- Verify the directory exists and is readable: `ls -la pub/media/bluefairy/megamenu/`.
- Ensure the Hyva companion module (**BlueFairy_MegaMenuHyva**) is enabled and compiled.
- Hard-refresh the browser to bypass cached HTML.
- On mobile, the featured image is only shown in the submenu if Featured Type is set to 'Image' in the category configuration.

**A panel left open after going back**

- Each desktop menu item listens for `pageshow`; when the browser restores the page from its back-forward cache (`event.persisted = true`), it closes the item's panel. The panel cache is not cleared by this: it lives in the page, and an entry is fetched again after 5 minutes.

**Fallback links showing instead of mega menu panel**

- The fallback (plain child links) is displayed if the GraphQL query fails (a GraphQL error or a network error). A timed-out request shows the skeleton instead.
- Check the browser console for the `[MegaMenu] panel fetch failed` error message and any logged exception details.
- Verify the GraphQL endpoint is responsive and returning valid data.
- Inspect the GraphQL request/response in the Network tab: look for `data.errors` or missing `data.data.megaMenuPanel`.

**CMS block content not rendering**

- Confirm the CMS block ID is valid and the block is enabled: **Content → Blocks → [block] → Enable Block = Yes**.
- CMS block HTML is sanitized to remove `on*` event handlers before rendering. If your block contains event handlers, they will be stripped.
- Alpine's `x-html` directive does not execute `<script>` tags; only server-side PHP or Magento block rendering will execute scripts.
- If the block contains dynamic Magento directives (e.g. `{{block type="Magento\Cms\Block\Widget\Block"}}` in the content), they will NOT be parsed — the CMS block content is returned as-is from the GraphQL resolver.

**Menu not respecting category sort order**

- The menu respects both the category's native `position` attribute and the mega menu item's custom `sort_order` field.
- If a mega menu item has a custom `sort_order` set, it overrides the category position. Set `sort_order` to NULL in the admin to use the category position.
- Clear the full-page cache after changing sort order: `php bin/magento cache:flush`.

---

## Disabling or Uninstalling

### Disable (keeps database tables and all configuration data)

```bash
php bin/magento module:disable BlueFairy_MegaMenuHyva BlueFairy_MegaMenuGraphQl
php bin/magento cache:flush
```

When disabled, the Hyva templates still render but fall back to plain child links (no mega menu panels or styling).

### Uninstall (removes module code; this companion has no tables)

```bash
php bin/magento module:uninstall BlueFairy_MegaMenuHyva
php bin/magento cache:flush
```

This Hyva companion ships no database tables of its own, so uninstalling it removes only its code. The mega menu enrichment data lives in the core module's tables; to drop those, uninstall the core module with the `--remove-data` flag:

```bash
php bin/magento module:uninstall --remove-data BlueFairy_MegaMenu
php bin/magento cache:flush
```

---

## Related Documentation

- **Core Module**: See `BlueFairy_MegaMenu/README.md` for core configuration, database schema, admin UI, and Service Contracts.
- **GraphQL Module**: See `BlueFairy_MegaMenuGraphQl/README.md` for GraphQL query documentation and cache invalidation strategy.
- **Hyva Theme**: https://docs.hyva.io/ — for Tailwind CSS, Alpine.js, and Hyva component patterns.
- **Magento Coding Standards**: https://developer.adobe.com/commerce/php/coding-standards/ — for PSR-12 and Magento 2 best practices.
