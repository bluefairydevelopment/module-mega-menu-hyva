<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\MegaMenuHyva\Model;

use BlueFairy\MegaMenu\Api\NavigationSourceInterface;
use Hyva\Theme\ViewModel\Navigation as HyvaNavigation;

/**
 * M2MAP-688. Hyvä's navigation view model as the mega menu's tree source.
 *
 * Until M2MAP-688 the base module's Navigation view model took Hyvä's view model
 * directly, so the base could not compile without Hyvä. That dependency now lives
 * here, behind the base's {@see NavigationSourceInterface}, and is bound by this
 * module's etc/di.xml only. The shared Hyvä instance is wrapped as-is: the tree,
 * its request-level memo and its cache identities are Hyvä's own, so a Hyvä store
 * renders and tags exactly what it did before.
 */
class HyvaNavigationSource implements NavigationSourceInterface
{
    /**
     * @param HyvaNavigation $navigation
     */
    public function __construct(
        private readonly HyvaNavigation $navigation
    ) {
    }

    /**
     * Hyvä's tree; Hyvä answers false for "nothing", which this contract spells [].
     *
     * @param int $maxLevel
     * @return array<int|string, array<string, mixed>>
     */
    public function getNavigation(int $maxLevel): array
    {
        $tree = $this->navigation->getNavigation($maxLevel);

        return is_array($tree) ? $tree : [];
    }

    /**
     * Hyvä's identities, untouched.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        return (array) $this->navigation->getIdentities();
    }
}
