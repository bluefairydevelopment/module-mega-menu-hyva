<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Unit\Layout;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * BFDPROD-131: a desktop top-level item that opens a panel or a child list is ONE disclosure button
 * (name, label and caret), not a link to the category with a separate caret button (a split button),
 * and the category page is the first link of what it opens: "See all <Category>", as on mobile.
 *
 * The behaviour is proved in a browser by
 * Test/Playwright/tests/keyboard/single-toggle-and-see-all.spec.ts; this pins the template so a
 * return to the split markup cannot pass the PHP gates unnoticed.
 */
#[CoversNothing]
class DesktopItemIsOneToggleTest extends TestCase
{
    private function template(): string
    {
        $file = dirname(__DIR__, 3) . '/view/frontend/templates/html/header/menu/desktop.phtml';
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }

    public function testTheToggleIsTheLevelZeroButtonAndCarriesTheName(): void
    {
        $template = $this->template();

        self::assertMatchesRegularExpression(
            '#<button\s+type="button"\s+class="level-0 [^"]*"(?:(?!</button>).)*?:aria-expanded="open"(?:(?!</button>).)*?aria-controls="#s',
            $template,
            'the top-level toggle is not a level-0 button with aria-expanded and aria-controls'
        );
        self::assertMatchesRegularExpression(
            '#<button\s+type="button"\s+class="level-0(?:(?!</button>).)*?aria-controls="[^"]*"\s*>\s*<span><\?= \$escaper->escapeHtml\(\$menuItem\[\'name\'\]\) \?></span>#s',
            $template,
            'the item name is not inside the toggle'
        );
    }

    public function testTheSplitButtonIsGone(): void
    {
        $template = $this->template();

        self::assertStringNotContainsString('Show submenu for %1 category', $template, 'the separate caret button is back');
        self::assertStringNotContainsString('aria-haspopup', $template, 'a disclosure is not a popup menu');
        // The name link is rendered only in the branch for items that open nothing.
        self::assertMatchesRegularExpression(
            '#<\?php if \(\$isToggle\): \?>.*<button.*<\?php else: \?>\s*<a\s+href="<\?= \$escaper->escapeUrl\(\$menuItem\[\'url\'\]\) \?>"\s+class="level-0#s',
            $template
        );
    }

    public function testBothThePanelAndTheChildListStartWithSeeAll(): void
    {
        $template = $this->template();

        self::assertSame(2, substr_count($template, 'class="megamenu-see-all'), 'a "See all" link is missing');
        self::assertSame(2, substr_count($template, "__('See all %1', \$menuItem['name'])"));
    }

    public function testThePanelTheToggleControlsIsAlwaysInTheDom(): void
    {
        $template = $this->template();

        // aria-controls must name an element while closed: the panel wrapper is x-show, not x-if.
        self::assertStringNotContainsString("'open || isPanelLoaded('", $template);
        self::assertMatchesRegularExpression(
            '#<div\s+id="<\?= \$escaper->escapeHtmlAttr\(\$controlsId\) \?>"(?:(?!<a\s).)*?x-show="open"#s',
            $template
        );
    }

    public function testEscapeReturnsFocusToTheToggle(): void
    {
        self::assertStringContainsString(
            "this.\$el.querySelector('button.level-0') || this.\$el.querySelector('a.level-0')",
            $this->template()
        );
    }
}
