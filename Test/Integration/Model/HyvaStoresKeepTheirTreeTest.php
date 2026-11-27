<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Integration\Model;

use BlueFairy\MegaMenu\Api\NavigationSourceInterface;
use BlueFairy\MegaMenu\Model\CacheTag;
use BlueFairy\MegaMenu\Model\Config;
use BlueFairy\MegaMenu\Test\Integration\Helper\StorefrontMenu;
use BlueFairy\MegaMenu\ViewModel\Navigation;
use BlueFairy\MegaMenuHyva\Model\HyvaNavigationSource;
use Hyva\Theme\ViewModel\Navigation as HyvaNavigation;
use Magento\Framework\App\CacheInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * M2MAP-688: an existing Hyvä store that upgrades keeps exactly its menu.
 *
 * The base module no longer references Hyvä; this module binds the base's tree
 * source to Hyvä's navigation view model. Built by the real object manager, the
 * decorator must hand back Hyvä's own tree and Hyvä's own cache identities - the
 * same instance Hyvä's templates use - exactly as when it took Hyvä's view model
 * directly.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
#[CoversClass(\BlueFairy\MegaMenuHyva\Model\HyvaNavigationSource::class)]
class HyvaStoresKeepTheirTreeTest extends TestCase
{
    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $om->removeSharedInstance(Config::class);
        $om->get(CacheInterface::class)->clean([CacheTag::SENTINEL]);
    }

    public function testTheTreeSourceIsHyvasOnAHyvaInstall(): void
    {
        $source = Bootstrap::getObjectManager()->get(NavigationSourceInterface::class);

        self::assertInstanceOf(HyvaNavigationSource::class, $source);
    }

    /**
     * @magentoConfigFixture current_store bluefairy_megamenu/general/enabled 0
     */
    public function testDisabledTheDecoratorReturnsHyvasTreeAndIdentitiesVerbatim(): void
    {
        $om = Bootstrap::getObjectManager();
        $categoryId = StorefrontMenu::create()['parent_id'];
        $hyvaTree = $om->get(HyvaNavigation::class)->getNavigation(4);
        $hyvaIdentities = $om->get(HyvaNavigation::class)->getIdentities();

        self::assertArrayHasKey('category-node-' . $categoryId, $hyvaTree, 'the fixture category is not in Hyvä\'s tree');

        $navigation = $om->create(Navigation::class);
        self::assertSame($hyvaTree, $navigation->getNavigation(4));
        self::assertSame($hyvaIdentities, $navigation->getIdentities());
    }

    /**
     * @magentoConfigFixture current_store bluefairy_megamenu/general/enabled 1
     */
    public function testEnabledTheEnrichmentLandsOnHyvasNodes(): void
    {
        $om = Bootstrap::getObjectManager();
        $menu = StorefrontMenu::create();
        $categoryId = $menu['parent_id'];
        $itemId = (int) $menu['item']->getEntityId();
        $hyvaTree = $om->get(HyvaNavigation::class)->getNavigation(4);
        $hyvaIdentities = $om->get(HyvaNavigation::class)->getIdentities();
        $key = 'category-node-' . $categoryId;

        $navigation = $om->create(Navigation::class);
        $tree = $navigation->getNavigation(4);

        self::assertArrayHasKey($key, $hyvaTree, 'the menu category is not in Hyvä\'s tree');
        foreach ($hyvaTree[$key] as $field => $value) {
            if ($field === 'childData') {
                // Children gain the enrichment's category_id marker (Navigation::mergeTree);
                // their own Hyvä fields must survive.
                self::assertSame(array_keys($value), array_keys($tree[$key]['childData']));
                foreach ($value as $childKey => $child) {
                    foreach ($child as $childField => $childValue) {
                        self::assertSame($childValue, $tree[$key]['childData'][$childKey][$childField] ?? null);
                    }
                }
                continue;
            }
            self::assertSame($value, $tree[$key][$field] ?? null, 'Hyvä\'s ' . $field . ' changed');
        }
        self::assertSame('New', $tree[$key]['label_text']);
        self::assertSame($categoryId, $tree[$key]['category_id']);
        // Hyvä's identities, the row's own tag and - since build round 6 (M2MAP-690) - the
        // sentinel every menu row save purges, so a NEW row (a CMS Page or Custom Link item,
        // or a newly enriched category) refreshes the Hyvä header at once instead of after the
        // block cache's hour. Rewritten from an exact list without the sentinel.
        self::assertSame(
            array_values(array_unique(array_merge($hyvaIdentities, [CacheTag::ITEM_PREFIX . $itemId, CacheTag::SENTINEL]))),
            $navigation->getIdentities()
        );
    }
}
