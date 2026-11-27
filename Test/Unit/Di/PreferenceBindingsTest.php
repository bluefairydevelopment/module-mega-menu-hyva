<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Unit\Di;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Every <preference> this package declares must bind an interface that exists to a
 * class that exists and really is an instance of it. Magento does not check this: a
 * preference naming a class that cannot stand in for the interface fails at the first
 * consumer, and where the consumer has a native return type it is a TypeError on every
 * call - in production, on the storefront, not here.
 *
 * PORTED from module-mega-menu, where this ran as a data set named
 * "MegaMenuHyva/etc/di.xml: BlueFairy\MegaMenu\Api\NavigationSourceInterface" over
 * every package's di.xml. This package left that repository on 2026-11-27, which took
 * that case with it.
 *
 * NO DEV DEPENDENCY WAS ADDED FOR THIS, AND NONE IS NEEDED. The interface belongs to
 * bluefairy/module-mega-menu, which this package declares as a hard `require`, not a
 * dev one - so in any installation where this package exists at all, the core is in the
 * Magento root's vendor/ and the interface is autoloadable. Adding it to require-dev
 * would have been worse than useless: the core is not on public Packagist, so
 * `composer install` in this public repository would fail outright for anyone without
 * our Private Packagist credentials.
 *
 * The monorepo's version of this test had to prepend an autoloader for the sibling
 * packages, because there it was reading UNINSTALLED sibling trees and a copy in
 * vendor/ would have tested the installed package rather than the tree under test (the
 * M2MAP-233 trap). That does not apply here and the hack is deliberately not ported:
 * the core genuinely is an installed dependency now, and the installed copy - at the
 * version this package's constraint admits - is exactly what should be checked.
 */
#[CoversNothing]
class PreferenceBindingsTest extends TestCase
{
    private const DI = 'etc/di.xml';

    public function testThereIsAPreferenceToExamineAtAll(): void
    {
        // FLOOR ON THE SEARCH SPACE. Every assertion below is about the preferences
        // found; if the file moved or the xpath stopped matching, they would all pass
        // over an empty list and read exactly like a clean run.
        self::assertNotSame(
            [],
            self::preferences(),
            self::DI . ' declares no <preference> at all. This package exists to bind '
            . 'HyvaNavigationSource over the core NavigationSourceInterface, so finding none '
            . 'means the file or the xpath is wrong, not that there is nothing to check'
        );
    }

    public function testTheCoreInterfaceIsReachable(): void
    {
        // Not a skip. A skipped check whose reason never expires is how a suite goes
        // quiet: this package hard-requires the core, so if the interface is missing the
        // installation is broken and that is worth failing over.
        self::assertTrue(
            interface_exists(\BlueFairy\MegaMenu\Api\NavigationSourceInterface::class),
            'BlueFairy\MegaMenu\Api\NavigationSourceInterface is not autoloadable. This package '
            . 'declares bluefairy/module-mega-menu ^2.0 as a hard require, so it should be in the '
            . "Magento root's vendor/. Run the suite against an installation that has it: "
            . 'MAGENTO_ROOT=/path/to/magento /path/to/magento/vendor/bin/phpunit -c phpunit.xml.dist'
        );
    }

    public function testEveryPreferredTypeCanActuallyStandInForTheInterface(): void
    {
        $checked = 0;
        foreach (self::preferences() as $for => $concrete) {
            self::assertTrue(
                interface_exists($for) || class_exists($for),
                self::DI . " declares a preference for {$for}, which does not exist"
            );
            self::assertTrue(
                class_exists($concrete),
                self::DI . " binds {$for} to {$concrete}, which does not exist"
            );
            self::assertTrue(
                is_a($concrete, $for, true),
                self::DI . " binds {$for} to {$concrete}, but {$concrete} is not a {$for}. "
                . 'Every consumer that type-hints the interface fails - and where the consumer is a '
                . 'native return type, that is a TypeError on every call.'
            );
            $checked++;
        }

        self::assertGreaterThanOrEqual(1, $checked, 'no preference was checked');
    }

    /**
     * The <preference> entries of this package, interface => concrete class.
     *
     * @return array<string, string>
     */
    private static function preferences(): array
    {
        $path = dirname(__DIR__, 3) . '/' . self::DI;
        self::assertFileExists($path);
        $xml = simplexml_load_file($path);
        self::assertNotFalse($xml, self::DI . ' does not parse');

        $out = [];
        foreach ($xml->xpath('/config/preference') ?: [] as $preference) {
            $out[(string) $preference['for']] = (string) $preference['type'];
        }

        return $out;
    }
}
