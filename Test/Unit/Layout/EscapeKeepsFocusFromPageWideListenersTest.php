<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Unit\Layout;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * BFDPROD-131 (2.1.1): a theme may listen for Escape on the whole window (a header search that
 * refocuses its button on any Escape). That listener runs after ours, as the event bubbles to the
 * window, and moved focus off the menu button the item had just returned it to. When the item
 * handles Escape (its panel was open) it stops the event there. The behaviour is proved in a
 * browser by Test/Playwright/tests/keyboard/escape-closes-panel.spec.ts; this pins the wiring.
 */
#[CoversNothing]
class EscapeKeepsFocusFromPageWideListenersTest extends TestCase
{
    private function escapeHandler(): string
    {
        $file = dirname(__DIR__, 3) . '/view/frontend/templates/html/header/menu/desktop.phtml';
        self::assertFileExists($file);
        $template = (string) file_get_contents($file);

        $start = strpos($template, 'escape(event) {');
        self::assertNotFalse($start, 'the item has no escape(event) handler');
        $end = strpos($template, 'canHoverOpen()', $start);
        self::assertNotFalse($end);

        return substr($template, $start, $end - $start);
    }

    public function testAHandledEscapeStopsBeforeTheWindow(): void
    {
        $handler = $this->escapeHandler();

        self::assertStringContainsString('event.stopPropagation()', $handler);
        // Only a handled Escape is stopped: the closed-panel early return comes first.
        self::assertLessThan(
            strpos($handler, 'event.stopPropagation()'),
            strpos($handler, 'if (!this.open) {'),
            'an Escape the item did not handle must still reach the page'
        );
    }
}
