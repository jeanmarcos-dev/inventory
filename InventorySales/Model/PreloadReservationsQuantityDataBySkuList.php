<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\InventorySales\Model;

use Magento\InventoryReservationsApi\Model\GetReservationsQuantityBySkuListCacheableInterface;
use Magento\InventorySalesApi\Model\PreloadDataBySkuListInterface;

class PreloadReservationsQuantityDataBySkuList implements PreloadDataBySkuListInterface
{
    /**
     * @param GetReservationsQuantityBySkuListCacheableInterface $getReservationsQuantityBySkuListCache
     */
    public function __construct(
        private readonly GetReservationsQuantityBySkuListCacheableInterface $getReservationsQuantityBySkuListCache
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(array $skus, int $stockId): void
    {
        $this->getReservationsQuantityBySkuListCache->execute($skus, $stockId);
    }
}
