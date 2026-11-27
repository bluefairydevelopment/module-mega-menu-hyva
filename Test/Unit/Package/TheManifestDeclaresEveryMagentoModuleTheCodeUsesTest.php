<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Unit\Package;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Every Magento module this package's shipped code refers to must be required in
 * composer.json, and every required magento/module-* must appear in etc/module.xml's
 * <sequence>. A missing requirement installs fine on a store that happens to have the
 * module, then fails on one that does not; a missing <sequence> entry lets Magento
 * load this module before the one it depends on.
 *
 * Test/ and docs/ are not shipped code. The namespace maps to the package as Magento
 * names them: Magento\Framework -> magento/framework, Magento\MediaStorage ->
 * magento/module-media-storage, Magento\GraphQl -> magento/module-graph-ql.
 *
 * PORTED from module-mega-menu, where this ran as a data set named "MegaMenuHyva" over
 * the monorepo's packages. This package left that repository on 2026-11-27, which took
 * its two cases with it. The check reads only this package's own composer.json,
 * etc/module.xml and source, so it ports with no dependency on the core.
 */
#[CoversNothing]
class TheManifestDeclaresEveryMagentoModuleTheCodeUsesTest extends TestCase
{
    public function testEveryMagentoModuleTheCodeUsesIsRequired(): void
    {
        $dir = self::packageDir();
        $used = self::usedMagentoPackages($dir);
        self::assertNotEmpty(
            $used,
            'control: no Magento namespace found in this package at all - the scan examined nothing'
        );

        $missing = array_values(array_diff($used, array_keys(self::requiredPackages($dir))));

        self::assertSame(
            [],
            $missing,
            "this package's code uses these Magento modules but its composer.json does not require them"
        );
    }

    public function testEveryRequiredMagentoModuleIsInTheSequence(): void
    {
        $dir = self::packageDir();
        $required = [];
        foreach (array_keys(self::requiredPackages($dir)) as $name) {
            if (str_starts_with($name, 'magento/module-')) {
                $required[] = self::moduleNameOf($name);
            }
        }
        sort($required);

        $xml = simplexml_load_file($dir . '/etc/module.xml');
        self::assertNotFalse($xml, 'etc/module.xml does not parse');
        $sequence = [];
        foreach ($xml->xpath('/config/module/sequence/module') ?: [] as $module) {
            $name = (string) $module['name'];
            if (str_starts_with($name, 'Magento_')) {
                $sequence[] = $name;
            }
        }
        sort($sequence);

        self::assertSame($required, $sequence, 'required Magento modules and <sequence> differ');
    }

    /**
     * The scan finds what this package is known to use, so a scan that examined the wrong
     * directory, or a pattern that matches nothing, cannot pass for "nothing missing".
     *
     * In the monorepo the equivalent control asserted exactly ['magento/framework'] for
     * this package. It is asserted as a floor here instead: an added requirement should
     * not make the control fail, but an EMPTY scan still must.
     */
    public function testTheScanSeesWhatThisPackageIsKnownToUse(): void
    {
        $used = self::usedMagentoPackages(self::packageDir());

        self::assertContains('magento/framework', $used, 'the scan does not see magento/framework');
        self::assertGreaterThanOrEqual(
            1,
            count($used),
            'the scan found no Magento package at all - a pass over an empty search space reads exactly like a clean one'
        );
    }

    /** The namespace-to-package rule, pinned on the names that are easy to get wrong. */
    public function testTheNamespaceMapsToTheComposerName(): void
    {
        self::assertSame('magento/framework', self::packageOf('Framework'));
        self::assertSame('magento/module-media-storage', self::packageOf('MediaStorage'));
        self::assertSame('magento/module-graph-ql', self::packageOf('GraphQl'));
        self::assertSame('magento/module-page-cache', self::packageOf('PageCache'));
        self::assertSame('Magento_MediaStorage', self::moduleNameOf('magento/module-media-storage'));
        self::assertSame('Magento_GraphQl', self::moduleNameOf('magento/module-graph-ql'));
    }

    /** This repository IS the package. */
    private static function packageDir(): string
    {
        $dir = dirname(__DIR__, 3);
        self::assertFileExists($dir . '/composer.json');

        return $dir;
    }

    /** @return array<string, string> */
    private static function requiredPackages(string $dir): array
    {
        $manifest = json_decode((string) file_get_contents($dir . '/composer.json'), true);
        self::assertIsArray($manifest);

        return $manifest['require'] ?? [];
    }

    /**
     * Composer names of every Magento module the package's shipped code refers to.
     *
     * @return string[]
     */
    private static function usedMagentoPackages(string $dir): array
    {
        $namespaces = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            $relative = substr($file->getPathname(), strlen($dir) + 1);
            if (preg_match('#^(Test|docs|docs-build|node_modules|\.git)/#', $relative)) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if ($file->getExtension() === 'php') {
                preg_match_all('/^use\s+\\\\?Magento\\\\([A-Za-z0-9]+)\\\\/m', $source, $m);
            } elseif ($file->getExtension() === 'phtml'
                || ($file->getExtension() === 'xml' && preg_match('#^(etc|view)/#', $relative))
            ) {
                preg_match_all('/(?<![A-Za-z0-9_\\\\])\\\\?Magento\\\\([A-Za-z0-9]+)\\\\/', $source, $m);
            } else {
                continue;
            }
            foreach ($m[1] as $namespace) {
                $namespaces[$namespace] = true;
            }
        }

        $packages = array_map(self::packageOf(...), array_keys($namespaces));
        sort($packages);

        return array_values(array_unique($packages));
    }

    /** @param string $namespace e.g. "MediaStorage" */
    private static function packageOf(string $namespace): string
    {
        if ($namespace === 'Framework') {
            return 'magento/framework';
        }

        return 'magento/module-' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $namespace));
    }

    /** @param string $package e.g. "magento/module-media-storage" */
    private static function moduleNameOf(string $package): string
    {
        $words = explode('-', substr($package, strlen('magento/module-')));

        return 'Magento_' . implode('', array_map(ucfirst(...), $words));
    }
}
