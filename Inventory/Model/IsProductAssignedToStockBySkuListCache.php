<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Inventory\Model;

use Magento\Inventory\Model\IsProductAssignedToStock\CacheStorage;
use Magento\Inventory\Model\ResourceModel\IsProductAssignedToStockBySkuList;
use Magento\InventoryApi\Model\IsProductAssignedToStockBySkuListInterface;

class IsProductAssignedToStockBySkuListCache implements IsProductAssignedToStockBySkuListInterface
{
    /**
     * @param IsProductAssignedToStockBySkuList $isProductAssignedToStockBySkuList
     * @param CacheStorage $isProductAssignedToStockCacheStorage
     */
    public function __construct(
        private readonly IsProductAssignedToStockBySkuList $isProductAssignedToStockBySkuList,
        private readonly CacheStorage $isProductAssignedToStockCacheStorage
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus, int $stockId): array
    {
        $skusToLoad = [];
        $result = [];
        foreach ($skus as $sku) {
            if ($this->isProductAssignedToStockCacheStorage->has((string) $sku, $stockId)) {
                $result[$sku] = $this->isProductAssignedToStockCacheStorage->get((string) $sku, $stockId);
            } else {
                $skusToLoad[] = $sku;
            }
        }
        if (!empty($skusToLoad)) {
            foreach ($this->isProductAssignedToStockBySkuList->execute($skusToLoad, $stockId) as $sku => $value) {
                $result[$sku] = $value;
                $this->isProductAssignedToStockCacheStorage->set((string) $sku, $stockId, $value);
            }
        }
        return $result;
    }
}
