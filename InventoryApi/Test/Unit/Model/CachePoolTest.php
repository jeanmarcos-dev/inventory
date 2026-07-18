<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryApi\Test\Unit\Model;

use Magento\InventoryApi\Model\CacheInterface;
use Magento\InventoryApi\Model\CachePool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CachePoolTest extends TestCase
{
    /**
     * @var CacheInterface|MockObject
     */
    private $memberA;

    /**
     * @var CacheInterface|MockObject
     */
    private $memberB;

    /**
     * @var CachePool
     */
    private $cachePool;

    protected function setUp(): void
    {
        $this->memberA = $this->createMock(CacheInterface::class);
        $this->memberB = $this->createMock(CacheInterface::class);
        $this->cachePool = new CachePool([$this->memberA, $this->memberB]);
    }

    public function testWarmupFansOutToEveryPoolMember(): void
    {
        $skus = ['sku-1', 'sku-2'];
        $stockId = 7;

        $this->memberA->expects(self::once())->method('warmup')->with($skus, $stockId);
        $this->memberB->expects(self::once())->method('warmup')->with($skus, $stockId);

        $this->cachePool->warmup($skus, $stockId);
    }

    public function testCleanFansOutToEveryPoolMember(): void
    {
        $skus = ['sku-1'];
        $stockId = 3;

        $this->memberA->expects(self::once())->method('clean')->with($skus, $stockId);
        $this->memberB->expects(self::once())->method('clean')->with($skus, $stockId);

        $this->cachePool->clean($skus, $stockId);
    }

    public function testCleanForwardsNullStockId(): void
    {
        $this->memberA->expects(self::once())->method('clean')->with(['sku-1'], null);

        (new CachePool([$this->memberA]))->clean(['sku-1'], null);
    }

    public function testEmptyPoolIsNoop(): void
    {
        $this->expectNotToPerformAssertions();

        (new CachePool())->warmup(['sku-1'], 1);
    }
}
