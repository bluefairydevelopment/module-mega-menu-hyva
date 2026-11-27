<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Unit\Layout;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Build round 9, item 8: the desktop chevron opens a panel and asks for its content with an event
 * the menu root listens for. The two halves live far apart in desktop.phtml (a button attribute and
 * the root's attribute) and the behaviour is proved in a browser by
 * Test/Playwright/tests/keyboard/chevron-opens-the-loaded-panel.spec.ts; this pins that the event's
 * name is spelled the same on both sides, so a rename of one cannot pass the PHP gates unnoticed.
 */
#[CoversNothing]
class ChevronAskForThePanelWiringTest extends TestCase
{
    private function template(): string
    {
        $file = dirname(__DIR__, 3) . '/view/frontend/templates/html/header/menu/desktop.phtml';
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }

    public function testTheRootListensForTheEventTheItemDispatches(): void
    {
        $template = $this->template();

        self::assertStringContainsString("this.\$dispatch('megamenu-open-panel'", $template, 'nothing dispatches the event');
        self::assertStringContainsString('@megamenu-open-panel="onOpenPanel"', $template, 'the root does not listen for it');
        self::assertStringContainsString('onOpenPanel(event) {', $template, 'the root has no handler');
    }

    public function testTheChevronPassesItsItemToToggle(): void
    {
        $template = $this->template();

        // Strict CSP allows no arguments in the attribute: the item carries its id in
        // data-panel-item-id, reads it in init(), and toggle() asks for that panel.
        self::assertStringContainsString('@click="toggle"', $template);
        self::assertStringContainsString('data-panel-item-id="<?= $hasPanel ? (int) $itemId : 0 ?>"', $template);
        self::assertStringContainsString('this.panelItemId = Number(this.$root.dataset.panelItemId) || 0;', $template);
        self::assertMatchesRegularExpression('#toggle\(\) \{(?:(?!\n            \w+\().)*this\.requestPanel\(\);#s', $template);
    }
}
