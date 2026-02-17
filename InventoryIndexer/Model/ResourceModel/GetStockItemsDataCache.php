<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Model\ResourceModel;

use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;
use Magento\InventoryIndexer\Model\GetStockItemData\CacheStorage;

/**
 * @inheritdoc
 */
class GetStockItemsDataCache implements GetStockItemsDataInterface
{
    /**
     * @param GetStockItemsData $getStockItemsData
     * @param CacheStorage $cacheStorage
     * @param bool $isReadonly
     */
    public function __construct(
        private readonly GetStockItemsData $getStockItemsData,
        private readonly CacheStorage $cacheStorage,
        private readonly bool $isReadonly = false
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus, int $stockId): array
    {
        $stockItemsData = [];

        // Get data from the cache and identify which SKUs need to be fetched
        $skusToFetch = [];
        foreach ($skus as $sku) {
            $cachedData = $this->cacheStorage->get($stockId, (string)$sku);
            if ($cachedData !== null) {
                $stockItemsData[$sku] = $cachedData;
            } else {
                $skusToFetch[] = $sku;
            }
        }

        // Fetch the data for the remaining SKUs and cache it
        if (!empty($skusToFetch)) {
            $fetchedItemsData = $this->getStockItemsData->execute($skusToFetch, $stockId);

            foreach ($fetchedItemsData as $sku => $stockItemData) {
                $stockItemsData[$sku] = $stockItemData;

                if ($stockItemData !== null && !$this->isReadonly) {
                    $this->cacheStorage->set($stockId, (string)$sku, $stockItemData);
                }
            }
        }

        return $stockItemsData;
    }
}
