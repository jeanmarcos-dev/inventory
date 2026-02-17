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
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryConfiguration\Model\LegacyStockItem\CacheStorage;
use Magento\InventorySalesApi\Model\PreloadDataBySkuListInterface;

/**
 * Load legacy stock item data for given SKUs and stock id and save into cache storage.
 */
class PreloadLegacyStockItemDataBySkuList implements PreloadDataBySkuListInterface
{
    /**
     * @param StockConfigurationInterface $stockConfiguration
     * @param StockRegistryPreloader $stockRegistryPreloader
     * @param StockRegistryStorage $stockRegistryStorage
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param CacheStorage $cacheStorage
     */
    public function __construct(
        private readonly StockConfigurationInterface $stockConfiguration,
        private readonly StockRegistryPreloader $stockRegistryPreloader,
        private readonly StockRegistryStorage $stockRegistryStorage,
        private readonly GetProductIdsBySkusInterface $getProductIdsBySkus,
        private readonly CacheStorage $cacheStorage
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(array $skus, int $stockId): void
    {
        try {
            $idsBySku = $this->getProductIdsBySkus->execute($skus);
        } catch (NoSuchEntityException $skuNotFoundInCatalog) {
            return;
        }
        $skusById = array_flip($idsBySku);
        $scopeId = (int) $this->stockConfiguration->getDefaultScopeId();
        $items = array_filter(
            array_map(
                fn ($id) => $this->stockRegistryStorage->getStockItem((int) $id, $scopeId),
                $idsBySku
            )
        );
        $idsToLoad = array_diff_key($idsBySku, $items);
        if (!empty($idsToLoad)) {
            $items = array_merge(
                array_values($items),
                array_values($this->stockRegistryPreloader->preloadStockItems($idsToLoad, $scopeId))
            );
        }
        foreach ($items as $item) {
            $productId = (int) $item->getProductId();
            if (isset($skusById[$productId])) {
                $this->cacheStorage->set((string) $skusById[$productId], $item);
            }
        }
    }
}
