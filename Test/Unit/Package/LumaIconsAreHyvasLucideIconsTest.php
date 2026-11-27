<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Unit\Package;

use BlueFairy\MegaMenu\Model\LabelIcon;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M2MAP-688: a label icon looks the same on Luma and Hyvä.
 *
 * Hyvä draws a label icon from its own Lucide set (the name {@see LabelIcon::lucideName()}
 * gives); Luma draws the inline copy the base module ships ({@see LabelIcon::body()}).
 * This compares the two, glyph and stroke attributes, against the Lucide SVG files of
 * the Hyvä installed under MAGENTO_ROOT - so a Hyvä upgrade that redraws an icon, or
 * an edit to the base's copy, turns this red instead of the two themes drifting apart.
 * It also proves each Lucide name exists in that Hyvä, the cause of the original
 * "Unable to find the SVG icon" for new/sale/hot.
 */
#[CoversNothing]
class LumaIconsAreHyvasLucideIconsTest extends TestCase
{
    private static function lucideDir(): string
    {
        $root = (string) getenv('MAGENTO_ROOT');
        $dir = $root . '/vendor/hyva-themes/magento2-theme-module/src/view/base/web/svg/lucide';
        self::assertDirectoryExists($dir, 'no Hyvä Lucide icon set under MAGENTO_ROOT=' . $root);
        self::assertGreaterThan(100, count(glob($dir . '/*.svg') ?: []), 'the Lucide directory holds almost no icons');

        return $dir;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function values(): array
    {
        $cases = [];
        foreach (array_keys(LabelIcon::LUCIDE_NAMES) as $value) {
            $cases[$value] = [$value];
        }

        return $cases;
    }

    #[DataProvider('values')]
    public function testTheBasesGlyphIsHyvasLucideIcon(string $value): void
    {
        $icons = new LabelIcon();
        $name = (string) $icons->lucideName($value);
        $file = self::lucideDir() . '/' . $name . '.svg';
        self::assertFileExists($file, 'Hyvä has no Lucide icon "' . $name . '" for label icon "' . $value . '"');

        $svg = (string) file_get_contents($file);
        self::assertSame(1, preg_match('#^\s*<svg([^>]*)>(.*)</svg>\s*$#s', $svg, $m), $file . ' is not one <svg>');
        self::assertSame($m[2], $icons->body($value), 'the Luma glyph for "' . $value . '" is not Hyvä\'s ' . $name);

        $hyvaAttributes = $this->attributes($m[1]);
        unset($hyvaAttributes['xmlns'], $hyvaAttributes['width'], $hyvaAttributes['height']);
        self::assertSame(
            $hyvaAttributes,
            $this->attributes(LabelIcon::SVG_ATTRIBUTES),
            'the Luma icon is not drawn with Hyvä\'s stroke attributes'
        );
    }

    /**
     * @return array<string, string>
     */
    private function attributes(string $markup): array
    {
        preg_match_all('#([a-z:-]+)="([^"]*)"#', $markup, $all, PREG_SET_ORDER);
        $attributes = [];
        foreach ($all as $a) {
            $attributes[$a[1]] = $a[2];
        }
        ksort($attributes);
        self::assertNotSame([], $attributes, 'no attributes parsed from: ' . $markup);

        return $attributes;
    }
}
