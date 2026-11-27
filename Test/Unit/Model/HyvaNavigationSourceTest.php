<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Test\Unit\Model;

use BlueFairy\MegaMenu\Api\NavigationSourceInterface;
use BlueFairy\MegaMenuHyva\Model\HyvaNavigationSource;
use Hyva\Theme\ViewModel\Navigation as HyvaNavigation;
use PHPUnit\Framework\Attributes\CoversClass;
use BlueFairy\MegaMenu\Test\Unit\Support\DoublesFollowExpectations;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * M2MAP-688: on a Hyvä store the mega menu's tree is Hyvä's own, untouched.
 */
#[CoversClass(HyvaNavigationSource::class)]
class HyvaNavigationSourceTest extends TestCase
{
    use DoublesFollowExpectations;

    public function testItIsTheBasesTreeSource(): void
    {
        self::assertInstanceOf(
            NavigationSourceInterface::class,
            new HyvaNavigationSource($this->createStub(HyvaNavigation::class))
        );
    }

    public function testTheTreeIsHyvasForTheSameLevel(): void
    {
        $tree = ['category-node-3' => ['id' => 'category-node-3', 'name' => 'Gear', 'childData' => []]];
        $hyva = $this->createMock(HyvaNavigation::class);
        $hyva->expects(self::once())->method('getNavigation')->with(4)->willReturn($tree);

        self::assertSame($tree, (new HyvaNavigationSource($hyva))->getNavigation(4));
    }

    public function testHyvasFalseForNothingBecomesAnEmptyTree(): void
    {
        $hyva = $this->createStub(HyvaNavigation::class);
        $hyva->method('getNavigation')->willReturn(false);

        self::assertSame([], (new HyvaNavigationSource($hyva))->getNavigation(3));
    }

    public function testTheIdentitiesAreHyvas(): void
    {
        $hyva = $this->createMock(HyvaNavigation::class);
        $hyva->expects(self::once())->method('getIdentities')->willReturn(['cat_c_3', 'hyva_nav']);

        self::assertSame(['cat_c_3', 'hyva_nav'], (new HyvaNavigationSource($hyva))->getIdentities());
    }
}
