<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Observer;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\InventoryApi\Model\CacheInterface;
use Magento\InventorySales\Observer\PreloadData;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PreloadDataTest extends TestCase
{
    private const FLAG = 'cataloginventory/options/enable_inventory_check';

    /**
     * @var CacheInterface|MockObject
     */
    private $cache;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var StockByWebsiteIdResolverInterface|MockObject
     */
    private $stockByWebsiteIdResolver;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfig;

    /**
     * @var PreloadData
     */
    private $observer;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->stockByWebsiteIdResolver = $this->createMock(StockByWebsiteIdResolverInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->observer = new PreloadData(
            $this->cache,
            $this->storeManager,
            $this->stockByWebsiteIdResolver,
            $this->scopeConfig
        );
    }

    public function testWarmsUpCacheWhenInventoryCheckEnabled(): void
    {
        $skus = ['sku-1', 'sku-2'];
        $storeId = 4;
        $websiteId = 2;
        $stockId = 9;

        $collection = $this->createMock(Collection::class);
        $collection->method('getStoreId')->willReturn($storeId);
        $collection->method('getColumnValues')->with('sku')->willReturn($skus);

        $this->scopeConfig->method('isSetFlag')
            ->with(self::FLAG, ScopeInterface::SCOPE_STORE, $storeId)
            ->willReturn(true);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn($websiteId);
        $this->storeManager->method('getStore')->with($storeId)->willReturn($store);

        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn($stockId);
        $this->stockByWebsiteIdResolver->method('execute')->with($websiteId)->willReturn($stock);

        $this->cache->expects(self::once())->method('warmup')->with($skus, $stockId);

        $this->observer->execute($this->buildObserver($collection));
    }

    public function testDoesNotWarmUpWhenInventoryCheckDisabled(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getStoreId')->willReturn(4);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $this->cache->expects(self::never())->method('warmup');

        $this->observer->execute($this->buildObserver($collection));
    }

    public function testDoesNotWarmUpWhenStoreIdMissing(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getStoreId')->willReturn(0);

        $this->cache->expects(self::never())->method('warmup');

        $this->observer->execute($this->buildObserver($collection));
    }

    private function buildObserver(Collection $collection): Observer
    {
        $observer = $this->createMock(Observer::class);
        $observer->method('getData')->with('collection')->willReturn($collection);

        return $observer;
    }
}
