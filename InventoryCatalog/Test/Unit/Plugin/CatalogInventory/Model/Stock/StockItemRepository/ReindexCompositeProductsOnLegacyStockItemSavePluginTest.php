<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Plugin\CatalogInventory\Model\Stock\StockItemRepository;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Inventory\Model\SourceItem;
use Magento\Inventory\Model\SourceItem\Command\GetSourceItemsBySku;
use Magento\InventoryCatalog\Plugin\CatalogInventory\Model\Stock\StockItemRepository\ReindexCompositeProductsOnLegacyStockItemSavePlugin; // phpcs:ignore Generic.Files.LineLength.TooLong
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryIndexer\Indexer\CompositeProductsIndexer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReindexCompositeProductsOnLegacyStockItemSavePluginTest extends TestCase
{
    /** @var GetSkusByProductIdsInterface|MockObject */
    private $getSkusByProductIds;

    /** @var GetSourceItemsBySku|MockObject */
    private $getSourceItemsBySku;

    /** @var CompositeProductsIndexer|MockObject */
    private $compositeProductsIndexer;

    /** @var ReindexCompositeProductsOnLegacyStockItemSavePlugin */
    private $plugin;

    protected function setUp(): void
    {
        $this->getSkusByProductIds = $this->createMock(GetSkusByProductIdsInterface::class);
        $this->getSourceItemsBySku = $this->createMock(GetSourceItemsBySku::class);
        $this->compositeProductsIndexer = $this->createMock(CompositeProductsIndexer::class);

        $this->plugin = new ReindexCompositeProductsOnLegacyStockItemSavePlugin(
            $this->getSkusByProductIds,
            $this->getSourceItemsBySku,
            $this->compositeProductsIndexer
        );
    }

    public function testReindexIsTriggeredWhenProductHasNoSourceItems(): void
    {
        $productId = 123;
        $sku = 'bundle-sku';

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getProductId')->willReturn($productId);

        $this->getSkusByProductIds->expects($this->once())
            ->method('execute')
            ->with([$productId])
            ->willReturn([$productId => $sku]);

        $this->getSourceItemsBySku->expects($this->once())
            ->method('execute')
            ->with($sku)
            ->willReturn([]);

        $this->compositeProductsIndexer->expects($this->once())
            ->method('reindexList')
            ->with([$sku]);

        $result = $this->plugin->afterSave(
            $this->createMock(StockItemRepository::class),
            $stockItem
        );

        $this->assertSame($stockItem, $result);
    }

    public function testReindexIsSkippedWhenProductHasSourceItems(): void
    {
        $productId = 123;
        $sku = 'simple-sku';

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getProductId')->willReturn($productId);

        $this->getSkusByProductIds->expects($this->once())
            ->method('execute')
            ->with([$productId])
            ->willReturn([$productId => $sku]);

        $sourceItem = $this->createMock(SourceItem::class);
        $this->getSourceItemsBySku->expects($this->once())
            ->method('execute')
            ->with($sku)
            ->willReturn([$sourceItem]);

        $this->compositeProductsIndexer->expects($this->never())
            ->method('reindexList');

        $result = $this->plugin->afterSave(
            $this->createMock(StockItemRepository::class),
            $stockItem
        );

        $this->assertSame($stockItem, $result);
    }

    public function testReindexIsSkippedWhenProductSkuIsNotFound(): void
    {
        $productId = 123;

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getProductId')->willReturn($productId);

        $this->getSkusByProductIds->expects($this->once())
            ->method('execute')
            ->with([$productId])
            ->willReturn([]);

        $this->getSourceItemsBySku->expects($this->never())
            ->method('execute');
        $this->compositeProductsIndexer->expects($this->never())
            ->method('reindexList');

        $result = $this->plugin->afterSave(
            $this->createMock(StockItemRepository::class),
            $stockItem
        );

        $this->assertSame($stockItem, $result);
    }
}
