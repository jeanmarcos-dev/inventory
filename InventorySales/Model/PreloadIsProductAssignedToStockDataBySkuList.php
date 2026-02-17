<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\InventorySales\Model;

use Magento\Inventory\Model\IsProductAssignedToStockBySkuListCache;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventorySalesApi\Model\PreloadDataBySkuListInterface;

class PreloadIsProductAssignedToStockDataBySkuList implements PreloadDataBySkuListInterface
{
    /**
     * @param IsProductAssignedToStockBySkuListCache $isProductAssignedToStockBySkuListCache
     * @param DefaultStockProviderInterface $defaultStockProvider
     */
    public function __construct(
        private readonly IsProductAssignedToStockBySkuListCache $isProductAssignedToStockBySkuListCache,
        private readonly DefaultStockProviderInterface $defaultStockProvider
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(array $skus, int $stockId): void
    {
        /**
         * This data is loaded only for non-default stock
         * @see \Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface::execute
         */
        if ($stockId !== $this->defaultStockProvider->getId()) {
            $this->isProductAssignedToStockBySkuListCache->execute($skus, $stockId);
        }
    }
}
