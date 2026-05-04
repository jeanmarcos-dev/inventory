<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Plugin\CatalogInventory\Model\Stock\StockItemRepository;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Inventory\Model\SourceItem\Command\GetSourceItemsBySku;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryIndexer\Indexer\CompositeProductsIndexer;

/**
 * Reindexes non-default stocks for composite products that have no source items
 * (e.g. bundles) when the legacy stock item is saved.
 *
 * Without this plugin, admin-side toggles of bundle Stock Status only update
 * inventory_stock_1 (a VIEW over cataloginventory_stock_status) but leave
 * inventory_stock_N out of sync because no source-item indexer is triggered.
 */
class ReindexCompositeProductsOnLegacyStockItemSavePlugin
{
    /**
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param GetSourceItemsBySku $getSourceItemsBySku
     * @param CompositeProductsIndexer $compositeProductsIndexer
     */
    public function __construct(
        private readonly GetSkusByProductIdsInterface $getSkusByProductIds,
        private readonly GetSourceItemsBySku $getSourceItemsBySku,
        private readonly CompositeProductsIndexer $compositeProductsIndexer
    ) {
    }

    /**
     * Trigger composite reindex on non-default stocks for products without source items.
     *
     * @param StockItemRepository $subject
     * @param StockItemInterface $stockItem
     * @return StockItemInterface
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSave(StockItemRepository $subject, StockItemInterface $stockItem): StockItemInterface
    {
        $productId = (int) $stockItem->getProductId();
        $skusByIds = $this->getSkusByProductIds->execute([$productId]);
        if (!isset($skusByIds[$productId])) {
            return $stockItem;
        }
        $productSku = $skusByIds[$productId];

        if ($this->getSourceItemsBySku->execute($productSku) === []) {
            $this->compositeProductsIndexer->reindexList([$productSku]);
        }

        return $stockItem;
    }
}
