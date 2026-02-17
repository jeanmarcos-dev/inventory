<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Model\StockRegistryPreloader;
use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryIndexer\Model\GetStockItemData\CacheStorage;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;
use Magento\InventorySalesApi\Model\PreloadDataBySkuListInterface;

/**
 * Load stock data for given SKUs and stock id and save into cache storage.
 */
class PreloadStockItemDataBySkuList implements PreloadDataBySkuListInterface
{
    /**
     * @param StockConfigurationInterface $stockConfiguration
     * @param StockRegistryPreloader $stockRegistryPreloader
     * @param StockRegistryStorage $stockRegistryStorage
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param CacheStorage $cacheStorage
     * @param GetStockItemsDataInterface $getStockItemsData
     */
    public function __construct(
        private readonly StockConfigurationInterface $stockConfiguration,
        private readonly StockRegistryPreloader $stockRegistryPreloader,
        private readonly StockRegistryStorage $stockRegistryStorage,
        private readonly DefaultStockProviderInterface $defaultStockProvider,
        private readonly GetProductIdsBySkusInterface $getProductIdsBySkus,
        private readonly CacheStorage $cacheStorage,
        private readonly GetStockItemsDataInterface $getStockItemsData
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(array $skus, int $stockId): void
    {
        if ($stockId === $this->defaultStockProvider->getId()) {
            try {
                $idsBySku = $this->getProductIdsBySkus->execute($skus);
            } catch (NoSuchEntityException $skuNotFoundInCatalog) {
                return;
            }
            $skusById = array_flip($idsBySku);
            $scopeId = (int) $this->stockConfiguration->getDefaultScopeId();
            $items = array_filter(
                array_map(
                    fn($id) => $this->stockRegistryStorage->getStockStatus((int) $id, $scopeId),
                    $idsBySku
                )
            );
            $idsToLoad = array_diff_key($idsBySku, $items);
            if (!empty($idsToLoad)) {
                $items = array_merge(
                    array_values($items),
                    array_values($this->stockRegistryPreloader->preloadStockStatuses($idsToLoad, $scopeId)),
                );
            }
            foreach ($items as $item) {
                $productId = (int)$item->getProductId();
                if (isset($skusById[$productId])) {
                    $this->cacheStorage->set(
                        $stockId,
                        (string) $skusById[$productId],
                        [
                            GetStockItemDataInterface::QUANTITY => $item->getQty(),
                            GetStockItemDataInterface::IS_SALABLE => $item->getStockStatus(),
                        ]
                    );
                }
            }
        } else {
            $items = $this->getStockItemsData->execute($skus, $stockId);
            foreach ($items as $sku => $itemData) {
                if (!empty($itemData)) {
                    $this->cacheStorage->set($stockId, (string) $sku, $itemData);
                }
            }
        }
    }
}
