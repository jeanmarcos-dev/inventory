<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model;

use Magento\InventorySales\Model\GetProductAvailableQty\CacheStorage;
use Magento\InventorySales\Model\ResourceModel\GetProductAvailableQtyBySkuList;
use Magento\InventorySalesApi\Model\GetProductAvailableQtyBySkuListInterface;

class GetProductAvailableQtyBySkuListCache implements GetProductAvailableQtyBySkuListInterface
{
    /**
     * @param GetProductAvailableQtyBySkuList $getProductAvailableQtyBySkuList
     * @param CacheStorage $getProductAvailableQtyCacheStorage
     */
    public function __construct(
        private readonly GetProductAvailableQtyBySkuList $getProductAvailableQtyBySkuList,
        private readonly CacheStorage $getProductAvailableQtyCacheStorage
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
            if ($this->getProductAvailableQtyCacheStorage->has((string) $sku, $stockId)) {
                $result[$sku] = $this->getProductAvailableQtyCacheStorage->get((string) $sku, $stockId);
            } else {
                $skusToLoad[] = $sku;
            }
        }
        if (!empty($skusToLoad)) {
            foreach ($this->getProductAvailableQtyBySkuList->execute($skusToLoad, $stockId) as $sku => $value) {
                $result[$sku] = $value;
                $this->getProductAvailableQtyCacheStorage->set((string) $sku, $stockId, $value);
            }
        }
        return $result;
    }
}
