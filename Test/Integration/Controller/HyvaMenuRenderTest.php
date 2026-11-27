<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Integration\Controller;

use BlueFairy\MegaMenu\Model\CacheTag;
use BlueFairy\MegaMenu\Model\Config;
use BlueFairy\MegaMenu\Test\Integration\Helper\StorefrontMenu;
use BlueFairy\MegaMenu\ViewModel\Navigation;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\TestCase\AbstractController;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * M2MAP-688: a Hyvä storefront still renders this module's Alpine menu, fed by the
 * base Navigation view model - now wired from this module's hyva_default.xml
 * instead of the base's default.xml - and the base's Luma renderer stays off it.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
#[CoversClass(\BlueFairy\MegaMenuHyva\Model\HyvaNavigationSource::class)]
#[CoversClass(\BlueFairy\MegaMenu\ViewModel\Navigation::class)]
class HyvaMenuRenderTest extends AbstractController
{
    private const HYVA_THEME = 'frontend/Hyva/default-csp';

    protected function setUp(): void
    {
        parent::setUp();
        $this->_objectManager->removeSharedInstance(Config::class);
        $this->_objectManager->get(CacheInterface::class)->clean([CacheTag::SENTINEL]);
        $types = $this->_objectManager->get(TypeListInterface::class);
        foreach (['block_html', 'full_page', 'layout'] as $type) {
            $types->cleanType($type);
        }

        $theme = $this->_objectManager->get(ThemeCollectionFactory::class)->create()
            ->getThemeByFullPath(self::HYVA_THEME);
        self::assertNotNull($theme->getId(), self::HYVA_THEME . ' is not registered on this install');
        $this->_objectManager->get(MutableScopeConfigInterface::class)->setValue(
            'design/theme/theme_id',
            (string) $theme->getId(),
            ScopeInterface::SCOPE_STORE,
            'default'
        );
        // The frontend area (and its design) is already loaded when the test starts, so the
        // request would keep the theme it loaded with; set it on the design as well.
        $this->_objectManager->get(DesignInterface::class)->setDesignTheme($theme, 'frontend');
    }

    /**
     * @magentoConfigFixture current_store bluefairy_megamenu/general/enabled 1
     */
    public function testTheHyvaMenuIsRenderedFromTheBaseNavigationViewModel(): void
    {
        StorefrontMenu::create();
        $this->dispatch('/');
        $body = (string) $this->getResponse()->getBody();

        self::assertStringContainsString('x-data="megaMenuDesktop"', $body, 'not the Hyvä desktop mega menu');
        self::assertStringContainsString('x-data="megaMenuMobile"', $body, 'not the Hyvä mobile mega menu');
        self::assertStringContainsString(StorefrontMenu::PARENT_NAME, $body);
        self::assertStringContainsString('static/version', $body);
        self::assertStringContainsString('/frontend/Hyva/default-csp/', $body, 'the page was not rendered with the Hyvä theme');
        self::assertStringNotContainsString('bf-megamenu', $body, 'the base\'s Luma renderer reached a Hyvä page');

        $layout = $this->_objectManager->get(LayoutInterface::class);
        foreach (['topmenu_desktop' => 'desktop.phtml', 'topmenu_mobile' => 'mobile.phtml'] as $name => $file) {
            $block = $layout->getBlock($name);
            self::assertNotFalse($block, $name . ' is not on the page');
            self::assertSame('BlueFairy_MegaMenuHyva::html/header/menu/' . $file, $block->getTemplate());
            self::assertInstanceOf(Navigation::class, $block->getData('viewModel'), $name . ' has no Navigation view model');
        }
        self::assertFalse($layout->hasElement('bluefairy.megamenu.luma'), 'the Luma renderer block was built on a Hyvä page');
    }
}
