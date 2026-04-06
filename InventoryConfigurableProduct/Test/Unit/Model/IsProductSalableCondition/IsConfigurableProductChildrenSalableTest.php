<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Test\Unit\Model\IsProductSalableCondition;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryConfigurableProduct\Model\IsProductSalableCondition\IsConfigurableProductChildrenSalable;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IsConfigurableProductChildrenSalableTest extends TestCase
{
    /**
     * @var Configurable|MockObject
     */
    private $configurableMock;

    /**
     * @var AreProductsSalableInterface|MockObject
     */
    private $areProductsSalableMock;

    /**
     * @var GetProductIdsBySkusInterface|MockObject
     */
    private $getProductIdsBySkusMock;

    /**
     * @var GetSkusByProductIdsInterface|MockObject
     */
    private $getSkusByProductIdsMock;

    /**
     * @var ResourceConnection|MockObject
     */
    private $resourceMock;

    /**
     * @var StockIndexTableNameResolverInterface|MockObject
     */
    private $stockIndexTableNameResolverMock;

    /**
     * @var AdapterInterface|MockObject
     */
    private $connectionMock;

    /**
     * @var Select|MockObject
     */
    private $selectMock;

    /**
     * @var IsConfigurableProductChildrenSalable
     */
    private $model;

    protected function setUp(): void
    {
        $this->configurableMock = $this->createMock(Configurable::class);
        $this->areProductsSalableMock = $this->createMock(AreProductsSalableInterface::class);
        $this->getProductIdsBySkusMock = $this->createMock(GetProductIdsBySkusInterface::class);
        $this->getSkusByProductIdsMock = $this->createMock(GetSkusByProductIdsInterface::class);
        $this->resourceMock = $this->createMock(ResourceConnection::class);
        $this->stockIndexTableNameResolverMock = $this->createMock(StockIndexTableNameResolverInterface::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->selectMock = $this->createMock(Select::class);

        $this->resourceMock->method('getConnection')->willReturn($this->connectionMock);
        $this->connectionMock->method('select')->willReturn($this->selectMock);
        $this->selectMock->method('from')->willReturnSelf();
        $this->selectMock->method('where')->willReturnSelf();

        $this->model = new IsConfigurableProductChildrenSalable(
            $this->configurableMock,
            $this->areProductsSalableMock,
            $this->getProductIdsBySkusMock,
            $this->getSkusByProductIdsMock,
            $this->resourceMock,
            $this->stockIndexTableNameResolverMock
        );
    }

    public function testReturnsFalseWhenNoChildren(): void
    {
        $this->setupConfigurableProduct('configurable-sku', 100, []);

        $result = $this->model->execute('configurable-sku', 1);

        self::assertFalse($result);
    }

    public function testReturnsFalseWhenNoChildrenSkus(): void
    {
        $this->setupConfigurableProduct('configurable-sku', 100, [10 => 10, 20 => 20]);
        $this->getSkusByProductIdsMock->method('execute')
            ->with([10 => 10, 20 => 20])
            ->willReturn([]);

        $result = $this->model->execute('configurable-sku', 1);

        self::assertFalse($result);
    }

    public function testReturnsFalseWhenNoSalableCandidatesInIndex(): void
    {
        $this->setupConfigurableProduct('configurable-sku', 100, [10 => 10, 20 => 20]);
        $this->getSkusByProductIdsMock->method('execute')
            ->willReturn([10 => 'simple_10', 20 => 'simple_20']);

        $this->stockIndexTableNameResolverMock->method('execute')
            ->with(1)
            ->willReturn('inventory_stock_1');
        $this->connectionMock->method('fetchCol')
            ->willReturn([]);

        $this->areProductsSalableMock->expects($this->never())->method('execute');

        $result = $this->model->execute('configurable-sku', 1);

        self::assertFalse($result);
    }

    public function testReturnsTrueWhenCandidateConfirmedSalable(): void
    {
        $this->setupConfigurableProduct('configurable-sku', 100, [10 => 10, 20 => 20]);
        $this->getSkusByProductIdsMock->method('execute')
            ->willReturn([10 => 'simple_10', 20 => 'simple_20']);

        $this->stockIndexTableNameResolverMock->method('execute')
            ->with(5)
            ->willReturn('inventory_stock_5');
        $this->connectionMock->method('fetchCol')
            ->willReturn(['simple_20']);

        $salableResult = $this->createSalableResult('simple_20', true);
        $this->areProductsSalableMock->expects($this->once())
            ->method('execute')
            ->with(['simple_20'], 5)
            ->willReturn([$salableResult]);

        $result = $this->model->execute('configurable-sku', 5);

        self::assertTrue($result);
    }

    public function testReturnsFalseWhenCandidateNotConfirmedSalable(): void
    {
        $this->setupConfigurableProduct('configurable-sku', 100, [10 => 10, 20 => 20]);
        $this->getSkusByProductIdsMock->method('execute')
            ->willReturn([10 => 'simple_10', 20 => 'simple_20']);

        $this->stockIndexTableNameResolverMock->method('execute')
            ->willReturn('inventory_stock_1');
        $this->connectionMock->method('fetchCol')
            ->willReturn(['simple_10', 'simple_20']);

        $result1 = $this->createSalableResult('simple_10', false);
        $result2 = $this->createSalableResult('simple_20', false);
        $this->areProductsSalableMock->expects($this->exactly(2))
            ->method('execute')
            ->willReturnMap([
                [['simple_10'], 1, [$result1]],
                [['simple_20'], 1, [$result2]],
            ]);

        $result = $this->model->execute('configurable-sku', 1);

        self::assertFalse($result);
    }

    public function testEarlyExitOnFirstConfirmedSalable(): void
    {
        $this->setupConfigurableProduct('configurable-sku', 100, [10 => 10, 20 => 20]);
        $this->getSkusByProductIdsMock->method('execute')
            ->willReturn([10 => 'simple_10', 20 => 'simple_20']);

        $this->stockIndexTableNameResolverMock->method('execute')
            ->willReturn('inventory_stock_1');
        $this->connectionMock->method('fetchCol')
            ->willReturn(['simple_10', 'simple_20']);

        $salableResult = $this->createSalableResult('simple_10', true);
        // Only called once — early exit after first salable candidate confirmed
        $this->areProductsSalableMock->expects($this->once())
            ->method('execute')
            ->with(['simple_10'], 1)
            ->willReturn([$salableResult]);

        $result = $this->model->execute('configurable-sku', 1);

        self::assertTrue($result);
    }

    public function testOnlySalableCandidatesPassedToAreProductsSalable(): void
    {
        $childIds = [];
        $childSkus = [];
        for ($i = 1; $i <= 100; $i++) {
            $childIds[$i] = $i;
            $childSkus[$i] = 'child_' . $i;
        }

        $this->setupConfigurableProduct('configurable-sku', 500, $childIds);
        $this->getSkusByProductIdsMock->method('execute')
            ->willReturn($childSkus);

        $this->stockIndexTableNameResolverMock->method('execute')
            ->willReturn('inventory_stock_1');
        // Only 2 out of 100 are salable in the index
        $this->connectionMock->method('fetchCol')
            ->willReturn(['child_50', 'child_75']);

        $salableResult = $this->createSalableResult('child_50', true);
        // Early exit: only first candidate checked, second never reached
        $this->areProductsSalableMock->expects($this->once())
            ->method('execute')
            ->with(['child_50'], 1)
            ->willReturn([$salableResult]);

        $result = $this->model->execute('configurable-sku', 1);

        self::assertTrue($result);
    }

    public function testWorksWithNonDefaultStock(): void
    {
        $this->setupConfigurableProduct('configurable-sku', 100, [10 => 10]);
        $this->getSkusByProductIdsMock->method('execute')
            ->willReturn([10 => 'simple_10']);

        $this->stockIndexTableNameResolverMock->expects($this->once())
            ->method('execute')
            ->with(20)
            ->willReturn('inventory_stock_20');
        $this->connectionMock->method('fetchCol')
            ->willReturn(['simple_10']);

        $salableResult = $this->createSalableResult('simple_10', true);
        $this->areProductsSalableMock->expects($this->once())
            ->method('execute')
            ->with(['simple_10'], 20)
            ->willReturn([$salableResult]);

        $result = $this->model->execute('configurable-sku', 20);

        self::assertTrue($result);
    }

    public function testGracefulDegradationOnIndexQueryFailure(): void
    {
        $this->setupConfigurableProduct('configurable-sku', 100, [10 => 10]);
        $this->getSkusByProductIdsMock->method('execute')
            ->willReturn([10 => 'simple_10']);

        $this->stockIndexTableNameResolverMock->method('execute')
            ->willReturn('inventory_stock_1');
        $this->connectionMock->method('fetchCol')
            ->willThrowException(new \Exception('Table does not exist'));

        $this->areProductsSalableMock->expects($this->never())->method('execute');

        $result = $this->model->execute('configurable-sku', 1);

        self::assertFalse($result);
    }

    /**
     * Set up common mocks for configurable product resolution
     */
    private function setupConfigurableProduct(string $sku, int $productId, array $childrenIds): void
    {
        $this->getProductIdsBySkusMock->method('execute')
            ->with([$sku])
            ->willReturn([$sku => $productId]);
        $this->configurableMock->method('getChildrenIds')
            ->with($productId)
            ->willReturn([0 => $childrenIds]);
    }

    /**
     * Create a mock salable result
     */
    private function createSalableResult(string $sku, bool $isSalable): IsProductSalableResultInterface
    {
        $mock = $this->createMock(IsProductSalableResultInterface::class);
        $mock->method('getSku')->willReturn($sku);
        $mock->method('isSalable')->willReturn($isSalable);
        return $mock;
    }
}
