/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 *
 * The desktop menu's Alpine components, run from the inline script in desktop.phtml.
 * Under Hyvä's strict CSP the template may only name properties and methods, so the logic the
 * attributes used to hold lives in these methods. Run: node --test MegaMenuHyva/Test/Node/*.test.mjs
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const TEMPLATE = new URL('../../view/frontend/templates/html/header/menu/desktop.phtml', import.meta.url);
const html = readFileSync(TEMPLATE, 'utf8');

function loadComponents() {
    const match = html.match(/\n<script>\n([\s\S]*?)\n<\/script>\n<\?php \$hyvaCsp->registerInlineScript\(\) \?>/);
    assert.ok(match, 'the inline script was not found');
    const registered = {};
    let onInit = null;
    const sandbox = {
        window: { matchMedia: () => ({ matches: true }), innerWidth: 1280 },
        document: {
            addEventListener: (name, fn) => {
                if (name === 'alpine:init') {
                    onInit = fn;
                }
            }
        },
        Alpine: {
            data: (name, fn) => {
                registered[name] = fn;
            }
        },
        console
    };
    vm.runInNewContext(match[1], sandbox);
    assert.equal(typeof onInit, 'function', 'nothing listens for alpine:init');
    onInit();
    return registered;
}

// Alpine merges a nested component's scope with its parents'; a prototype chain does the same
// for reads, and writes land on the object that owns the property, as here.
function item(root, panelItemId) {
    const dispatched = [];
    const it = Object.assign(Object.create(root), components.megaMenuDesktopItem());
    it.$root = { dataset: { panelItemId: String(panelItemId) } };
    it.$dispatch = (name, detail) => {
        dispatched.push([name, { ...detail }]);
        root.onOpenPanel({ detail });
    };
    it.init();
    return { it, dispatched };
}

const components = loadComponents();

function root() {
    const r = components.megaMenuDesktop();
    r.fetched = [];
    r.fetchPanel = function (id) {
        this.fetched.push(id);
    };
    return r;
}

test('registers the three components with Alpine.data', () => {
    assert.deepEqual(Object.keys(components).sort(), [
        'megaMenuDesktop',
        'megaMenuDesktopItem',
        'megaMenuDesktopPanel'
    ]);
});

test('the item reads its panel id from data-panel-item-id', () => {
    assert.equal(item(root(), 42).it.panelItemId, 42);
    assert.equal(item(root(), 0).it.panelItemId, 0);
});

test('hover opens the panel and asks the root for its content', () => {
    const r = root();
    const { it, dispatched } = item(r, 7);
    it.hoverOpen();
    assert.equal(it.open, true);
    assert.equal(it.hoverOpened, true);
    assert.deepEqual(dispatched, [['megamenu-open-panel', { itemId: 7 }]]);
    assert.deepEqual(r.fetched, [7]);
});

test('an item without a panel asks for nothing', () => {
    const r = root();
    const { it, dispatched } = item(r, 0);
    it.hoverOpen();
    it.toggle();
    assert.deepEqual(dispatched, []);
    assert.equal(it.panelLoaded(), false);
    assert.equal(it.panelFailed(), false);
});

test('toggle opens and asks for the panel; a click after hover keeps it open', () => {
    const r = root();
    const { it, dispatched } = item(r, 3);
    it.toggle();
    assert.equal(it.open, true);
    assert.deepEqual(dispatched, [['megamenu-open-panel', { itemId: 3 }]]);
    it.toggle();
    assert.equal(it.open, false);

    const hovered = item(root(), 3).it;
    hovered.hoverOpen();
    hovered.toggle();
    assert.equal(hovered.open, true);
});

test('skeleton shows while open and neither loaded nor failed', () => {
    const r = root();
    const { it } = item(r, 5);
    assert.equal(it.showSkeleton(), false);
    it.open = true;
    assert.equal(it.showSkeleton(), true);
    r.panelCache[5] = { columns: [] };
    r.panelCacheTimes[5] = Date.now();
    assert.equal(it.panelLoaded(), true);
    assert.equal(it.showSkeleton(), false);
    delete r.panelCache[5];
    r.panelErrors[5] = true;
    assert.equal(it.panelFailed(), true);
    assert.equal(it.showSkeleton(), false);
});

test('alignPanel measures only an open panel', () => {
    const { it } = item(root(), 5);
    const seen = [];
    it.checkPanelAlign = (el) => seen.push(el);
    it.$el = 'panel-div';
    it.alignPanel();
    it.open = true;
    it.alignPanel();
    assert.deepEqual(seen, ['panel-div']);
});

function panelFor(data) {
    const r = root();
    r.panelCache[9] = data;
    r.panelCacheTimes[9] = Date.now();
    const { it } = item(r, 9);
    const p = Object.assign(Object.create(it), components.megaMenuDesktopPanel());
    p.init();
    return p;
}

test("the panel component takes its item's cached panel", () => {
    const data = { columns: [{ items: [] }], featured: null };
    assert.equal(panelFor(data).panel, data);
});

test('featured kinds and the grid', () => {
    const cms = panelFor({
        columns: [],
        featured: { type: 'cms_block', cms_block_content: '<p onclick="x()">Hi</p>' }
    });
    assert.equal(cms.isCmsBlock(), true);
    assert.equal(cms.hasSideFeatured(), false);
    assert.equal(cms.showGrid(), false);
    assert.equal(cms.gridStyle(), '');

    const image = panelFor({
        columns: [{ items: [] }],
        featured: { type: 'image', image_link: '', image_url: '/a.png' }
    });
    assert.equal(image.isFeaturedImage(), true);
    assert.equal(image.isFeaturedProducts(), false);
    assert.equal(image.hasSideFeatured(), true);
    assert.equal(image.showGrid(), true);
    assert.equal(image.featuredImageLink(), '#');
    assert.equal(image.gridStyle(), 'grid-template-columns: repeat(1, minmax(0, 1fr)) 200px');

    const none = panelFor({ columns: [], featured: { type: 'none' } });
    assert.equal(none.showGrid(), false);
    assert.equal(none.cmsBlockHtml(), '');

    const products = panelFor({ columns: [], featured: { type: 'products', products: [] } });
    assert.equal(products.isFeaturedProducts(), true);
    assert.equal(products.showGrid(), true);

    const noFeatured = panelFor({ columns: [{ items: [] }, { items: [] }] });
    assert.equal(noFeatured.featuredType(), 'none');
    assert.equal(noFeatured.showGrid(), true);
});

test('loop methods read the x-for item and keep the URL and colour rules', () => {
    const p = panelFor({ columns: [], featured: null });
    const scope = (vars) => Object.assign(Object.create(p), vars);
    const child = scope({ child: { url: '//evil.example', label: { text: 'New', color: 'red' }, children: [{}] } });
    assert.equal(p.childUrl.call(child), '#');
    assert.equal(p.hasChildLabel.call(child), true);
    assert.equal(p.childLabelStyle.call(child), 'background-color: #2563eb');
    assert.equal(p.hasGrandchildren.call(child), true);

    const plain = scope({ child: { url: '/shoes.html', label: null, children: [] } });
    assert.equal(p.childUrl.call(plain), '/shoes.html');
    assert.equal(p.hasChildLabel.call(plain), false);
    assert.equal(p.hasGrandchildren.call(plain), false);
    assert.equal(
        p.childLabelStyle.call(scope({ child: { label: { text: 'x', color: '#abc' } } })),
        'background-color: #abc'
    );

    assert.equal(p.grandUrl.call(scope({ grand: { url: 'javascript:alert(1)' } })), '#');
    assert.equal(p.grandUrl.call(scope({ grand: { url: 'https://shop.example/a' } })), 'https://shop.example/a');
});

test('every name an Alpine attribute uses exists on its component or in scope', () => {
    const r = root();
    const { it } = item(r, 1);
    const p = Object.assign(Object.create(it), components.megaMenuDesktopPanel());
    const loopVars = ['panel', 'column', 'child', 'grand', 'product'];
    const markup = html.replace(/<!--[\s\S]*?-->/g, '').split('\n<script>')[0];
    const names = [];
    markup.replace(
        /\s(?:x-(?!data|for|transition|ref|cloak)[\w:.-]+|@[\w.:-]+|:[\w.-]+)="([A-Za-z_$][\w$.]*)"/g,
        (m, n) => {
            names.push(n);
            return m;
        }
    );
    assert.ok(names.length >= 25, `only ${names.length} names found`);
    const missing = names.filter((n) => {
        const head = n.split('.')[0];
        return !(head in p) && !loopVars.includes(head);
    });
    assert.deepEqual(missing, []);
});
