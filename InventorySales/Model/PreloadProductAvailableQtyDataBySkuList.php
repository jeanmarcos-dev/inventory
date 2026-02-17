<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\InventorySales\Model;

use Magento\InventorySalesApi\Model\PreloadDataBySkuListInterface;

class PreloadProductAvailableQtyDataBySkuList implements PreloadDataBySkuListInterface
{
    /**
     * @param GetProductAvailableQtyBySkuListCache $getProductAvailableQtyBySkuListCache
     */
    public function __construct(
        private readonly GetProductAvailableQtyBySkuListCache $getProductAvailableQtyBySkuListCache
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(array $skus, int $stockId): void
    {
        $this->getProductAvailableQtyBySkuListCache->execute($skus, $stockId);
    }
}
