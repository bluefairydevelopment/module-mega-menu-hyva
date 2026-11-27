<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Unit\Package;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Review round 9, undirected-mega LOW-1: the README listed the chevron's panel fetch under "Mobile
 * Behavior". The fetch exists only in desktop.phtml, so the sentence belongs under "Desktop Behavior".
 */
#[CoversNothing]
class ReadmeKeepsTheChevronUnderDesktopTest extends TestCase
{
    private function section(string $heading): string
    {
        $file = dirname(__DIR__, 3) . '/README.md';
        self::assertFileExists($file);
        $readme = (string) file_get_contents($file);
        $start = strpos($readme, "### $heading");
        self::assertNotFalse($start, "README has no '### $heading' section");
        $end = strpos($readme, "\n### ", $start + 1);

        return substr($readme, $start, $end === false ? null : $end - $start);
    }

    public function testTheDesktopSectionDescribesTheChevronRoute(): void
    {
        $desktop = $this->section('Desktop Behavior');

        self::assertStringContainsString('chevron', $desktop);
        self::assertStringContainsString('Enter, Space or a click', $desktop);
    }

    public function testTheMobileSectionDoesNotClaimAPanelFetch(): void
    {
        $mobile = $this->section('Mobile Behavior');

        self::assertStringNotContainsString('loads the panel', $mobile);
        self::assertStringNotContainsString('fetches', $mobile);
    }
}
