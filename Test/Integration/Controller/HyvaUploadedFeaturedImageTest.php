<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Integration\Controller;

use BlueFairy\MegaMenu\Api\MegaMenuRepositoryInterface;
use BlueFairy\MegaMenu\Model\CacheTag;
use BlueFairy\MegaMenu\Model\Config;
use BlueFairy\MegaMenu\Model\FeaturedImage;
use BlueFairy\MegaMenu\Test\Integration\Helper\StorefrontMenu;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\TestCase\AbstractController;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * M2MAP-691 on Hyvä: the phone menu's featured banner shows an uploaded image (no Featured
 * Image URL) from its media path, and links where the desktop picture does - the row's
 * Custom URL, else the category. The template read featured_image_url alone, so an upload
 * never showed. (The Hyvä desktop panel's picture comes over GraphQL - BlueFairy_MegaMenuGraphQl's
 * AnUploadedImagePanelTest.)
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
#[CoversClass(FeaturedImage::class)]
#[CoversClass(\BlueFairy\MegaMenu\ViewModel\Navigation::class)]
class HyvaUploadedFeaturedImageTest extends AbstractController
{
    private const HYVA_THEME = 'frontend/Hyva/default-csp';

    private const FILE = 'm691-hyva-banner.png';

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
        $this->_objectManager->get(DesignInterface::class)->setDesignTheme($theme, 'frontend');
    }

    /**
     * @magentoConfigFixture current_store bluefairy_megamenu/general/enabled 1
     */
    public function testThePhoneBannerShowsTheUploadAndLinksToTheCustomUrl(): void
    {
        $menu = StorefrontMenu::create();
        $item = $menu['item'];
        $item->setFeaturedImageUrl(null)->setFeaturedImage(self::FILE)->setShowBannerOnMobile(true)
            ->setCustomUrl('/m691-offer.html');
        $this->_objectManager->get(MegaMenuRepositoryInterface::class)->save($item);

        $this->dispatch('/');
        $body = html_entity_decode((string) $this->getResponse()->getBody(), ENT_QUOTES | ENT_HTML5);
        self::assertStringContainsString('x-data="megaMenuMobile"', $body, 'not the Hyvä mobile mega menu');

        $store = $this->_objectManager->get(StoreManagerInterface::class)->getStore();
        self::assertInstanceOf(Store::class, $store);
        $src = rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/' . FeaturedImage::MEDIA_PATH . self::FILE;

        self::assertMatchesRegularExpression(
            '#<a\s+href="/m691-offer\.html"\s+class="block"\s+aria-label="[^"]*featured banner"\s*>\s*<img\s+src="'
            . preg_quote($src, '#') . '"#',
            $body,
            'the Hyvä phone banner does not show the uploaded image from ' . $src . ', linked to the Custom URL'
        );
    }
}
