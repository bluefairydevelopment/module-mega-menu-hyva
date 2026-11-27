<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 *
 * M2MAP-365 / M2MAP-233. Package-local PSR-4 prepend so this suite tests THIS
 * repository, not the copy installed in a rig's vendor/. Magento's unit
 * bootstrap prepends its own autoloader, so without this `BlueFairy\MegaMenuHyva\`
 * would resolve to the deployed package.
 */

declare(strict_types=1);

$magentoRoot = getenv('MAGENTO_ROOT') ?: '';
if ($magentoRoot === '' || !is_file($magentoRoot . '/vendor/autoload.php')) {
    fwrite(
        STDERR,
        "MAGENTO_ROOT must point at a Magento installation whose vendor/ carries the framework:\n"
        . "    MAGENTO_ROOT=/path/to/magento /path/to/magento/vendor/bin/phpunit -c phpunit.xml\n"
    );
    exit(1);
}

require $magentoRoot . '/vendor/autoload.php';

if (!defined('BP')) {
    define('BP', $magentoRoot);
}

// Magento's unit-test framework (its ObjectManager helper), for the unit run only.
//
// NEVER under static analysis. That framework's FactoryGenerator writes a stand-in
// for any class whose name ends in "Factory" - a create() with an empty body - and
// phpstan then analyses the stand-in, not the real class: it shadowed the real
// Magento\Framework\Controller\ResultFactory (TYPE_JSON "undefined") and stops
// checking anything a stand-in's create() returns. Under analysis the compiled
// generated/code is autoloaded instead, and the run refuses if there is none.
$underAnalyser = str_contains(strtolower((string) ($_SERVER['argv'][0] ?? '')), 'phpstan')
    || class_exists('PHPStan\Analyser\Analyser', false);

if ($underAnalyser) {
    $generated = $magentoRoot . '/generated/code/';
    if (!is_file($generated . 'Magento/Catalog/Api/Data/ProductExtensionInterface.php')) {
        fwrite(STDERR, sprintf(
            "\nREFUSING to analyse: %s has no compiled DI, so every generated Factory would be unknown.\n"
            . "Run:  cd %s && php -d memory_limit=-1 bin/magento setup:di:compile\n\n",
            $magentoRoot,
            $magentoRoot
        ));
        exit(1);
    }
    spl_autoload_register(static function (string $class) use ($generated): void {
        $file = $generated . str_replace('\\', '/', $class) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
} elseif (\Phar::running() === '' && is_file($magentoRoot . '/dev/tests/unit/framework/bootstrap.php')) {
    require $magentoRoot . '/dev/tests/unit/framework/bootstrap.php';
}

$packageRoot = dirname(__DIR__);
$repoRoot = dirname($packageRoot);

// This monorepo package depends on the sibling BlueFairy_MegaMenu package. Both
// must resolve from THIS working tree, not the copy installed in a rig's vendor/
// (the M2MAP-233 trap): a cross-package mock of a BlueFairy\MegaMenu\... class
// would otherwise reflect the deployed class, missing methods added here.
$psr4 = [
    'BlueFairy\\MegaMenuHyva\\' => $packageRoot,
    'BlueFairy\\MegaMenu\\' => $repoRoot . '/MegaMenu',
];
spl_autoload_register(
    static function (string $class) use ($psr4): void {
        foreach ($psr4 as $prefix => $root) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            $file = $root . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    },
    true,
    true
);
