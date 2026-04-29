<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Integration;

use Magento\Bundle\Test\Fixture\Link as BundleSelectionFixture;
use Magento\Bundle\Test\Fixture\Option as BundleOptionFixture;
use Magento\Bundle\Test\Fixture\Product as BundleProductFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Test\Fixture\Source as SourceFixture;
use Magento\InventoryApi\Test\Fixture\SourceItems as SourceItemsFixture;
use Magento\InventoryApi\Test\Fixture\Stock as StockFixture;
use Magento\InventoryApi\Test\Fixture\StockSourceLinks as StockSourceLinksFixture;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockItemData;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\InventorySalesApi\Test\Fixture\StockSalesChannels as StockSalesChannelsFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that bundle product salability on non-default stocks is
 * updated when stock status is changed via StockItemRepository.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ReindexBundleOnNonDefaultStockAfterLegacyStockItemSaveTest extends TestCase
{
    /**
     * @var StockRegistryInterface
     */
    private StockRegistryInterface $stockRegistry;

    /**
     * @var GetStockItemData
     */
    private GetStockItemData $getStockItemData;

    protected function setUp(): void
    {
        parent::setUp();
        $objectManager = Bootstrap::getObjectManager();
        $this->stockRegistry = $objectManager->get(StockRegistryInterface::class);
        $this->getStockItemData = $objectManager->get(GetStockItemData::class);
    }

    #[
        DbIsolation(false),
        DataFixture(SourceFixture::class, ['source_code' => 'eu-1'], 'source'),
        DataFixture(StockFixture::class, as: 'stock'),
        DataFixture(
            StockSourceLinksFixture::class,
            [['stock_id' => '$stock.stock_id$', 'source_code' => '$source.source_code$']]
        ),
        DataFixture(
            StockSalesChannelsFixture::class,
            ['stock_id' => '$stock.stock_id$', 'sales_channels' => ['base']]
        ),
        DataFixture(ProductFixture::class, ['sku' => 'bundle-child-simple'], 'child'),
        DataFixture(
            SourceItemsFixture::class,
            [['sku' => '$child.sku$', 'source_code' => '$source.source_code$', 'quantity' => 10, 'status' => 1]]
        ),
        DataFixture(BundleSelectionFixture::class, ['sku' => '$child.sku$', 'qty' => 1], 'link'),
        DataFixture(BundleOptionFixture::class, ['product_links' => ['$link$'], 'required' => true], 'opt'),
        DataFixture(
            BundleProductFixture::class,
            ['sku' => 'bundle-parent', '_options' => ['$opt$'], 'shipment_type' => 1],
            'bundle'
        ),
    ]
    /**
     * Asserts that setting is_in_stock=false marks the bundle as not salable on a non-default stock.
     *
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testBundleIsMarkedNotSalableOnNonDefaultStockAfterLegacyStockItemSave(): void
    {
        $fixtures = DataFixtureStorageManager::getStorage();
        $stock = $fixtures->get('stock');
        $this->assertNotNull($stock, 'Non-default stock fixture must be created');

        $stockId = (int) $stock->getStockId();

        $stockItemDataBefore = $this->getStockItemData->execute('bundle-parent', $stockId);
        $this->assertNotNull(
            $stockItemDataBefore,
            'Bundle must be indexed on the non-default stock before the legacy stock item save'
        );
        $this->assertEquals(
            1,
            (int) $stockItemDataBefore[GetStockItemDataInterface::IS_SALABLE],
            'Bundle must be salable on non-default stock before setting is_in_stock = false'
        );

        $stockItem = $this->stockRegistry->getStockItemBySku('bundle-parent');
        $stockItem->setIsInStock(false);
        $this->stockRegistry->updateStockItemBySku('bundle-parent', $stockItem);

        $stockItemDataAfter = $this->getStockItemData->execute('bundle-parent', $stockId);
        $this->assertNotNull(
            $stockItemDataAfter,
            'Bundle stock data on the non-default stock must exist after the legacy stock item save'
        );
        $this->assertEquals(
            0,
            (int) $stockItemDataAfter[GetStockItemDataInterface::IS_SALABLE],
            'Bundle must be marked not salable on non-default stock after admin sets is_in_stock = false'
        );
    }

    #[
        DbIsolation(false),
        DataFixture(SourceFixture::class, ['source_code' => 'eu-2'], 'source'),
        DataFixture(StockFixture::class, as: 'stock'),
        DataFixture(
            StockSourceLinksFixture::class,
            [['stock_id' => '$stock.stock_id$', 'source_code' => '$source.source_code$']]
        ),
        DataFixture(
            StockSalesChannelsFixture::class,
            ['stock_id' => '$stock.stock_id$', 'sales_channels' => ['base']]
        ),
        DataFixture(ProductFixture::class, ['sku' => 'bundle-child-simple-2'], 'child'),
        DataFixture(
            SourceItemsFixture::class,
            [['sku' => '$child.sku$', 'source_code' => '$source.source_code$', 'quantity' => 10, 'status' => 1]]
        ),
        DataFixture(BundleSelectionFixture::class, ['sku' => '$child.sku$', 'qty' => 1], 'link'),
        DataFixture(BundleOptionFixture::class, ['product_links' => ['$link$'], 'required' => true], 'opt'),
        DataFixture(
            BundleProductFixture::class,
            ['sku' => 'bundle-parent-2', '_options' => ['$opt$'], 'shipment_type' => 1],
            'bundle'
        ),
    ]
    /**
     * Asserts that restoring is_in_stock=true marks the bundle as salable again on a non-default stock.
     *
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testBundleIsMarkedSalableOnNonDefaultStockAfterLegacyStockItemRestored(): void
    {
        $fixtures = DataFixtureStorageManager::getStorage();
        $stock = $fixtures->get('stock');
        $this->assertNotNull($stock, 'Non-default stock fixture must be created');

        $stockId = (int) $stock->getStockId();

        $stockItem = $this->stockRegistry->getStockItemBySku('bundle-parent-2');
        $stockItem->setIsInStock(false);
        $this->stockRegistry->updateStockItemBySku('bundle-parent-2', $stockItem);

        $stockItemDataOos = $this->getStockItemData->execute('bundle-parent-2', $stockId);
        $this->assertEquals(
            0,
            (int) ($stockItemDataOos[GetStockItemDataInterface::IS_SALABLE] ?? 1),
            'Bundle must be not salable after setting is_in_stock = false'
        );

        $stockItem = $this->stockRegistry->getStockItemBySku('bundle-parent-2');
        $stockItem->setIsInStock(true);
        $this->stockRegistry->updateStockItemBySku('bundle-parent-2', $stockItem);

        $stockItemDataBack = $this->getStockItemData->execute('bundle-parent-2', $stockId);
        $this->assertNotNull(
            $stockItemDataBack,
            'Bundle stock data on the non-default stock must exist after restoring is_in_stock = true'
        );
        $this->assertEquals(
            1,
            (int) $stockItemDataBack[GetStockItemDataInterface::IS_SALABLE],
            'Bundle must be salable again on non-default stock after admin sets is_in_stock = true'
        );
    }
}
