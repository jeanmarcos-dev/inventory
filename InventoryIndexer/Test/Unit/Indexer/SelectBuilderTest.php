<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Indexer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\InventoryIndexer\Indexer\SelectBuilder;
use Magento\InventoryIndexer\Indexer\Stock\ReservationsIndexTable;
use Magento\InventorySales\Model\ResourceModel\IsStockItemSalableCondition\GetIsStockItemSalableConditionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SelectBuilderTest extends TestCase
{
    public function testExecuteOrdersBySkuAscending(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('getCheckSql')->willReturn('quantity_expression');
        $connection->method('fetchCol')->willReturn(['default']);

        $sourceCodesSelect = $this->createSelfReturningSelect();
        $indexSelect = $this->createSelfReturningSelect();
        $connection->method('select')->willReturnOnConsecutiveCalls($sourceCodesSelect, $indexSelect);

        $indexSelect->expects(self::once())
            ->method('order')
            ->with('source_item.sku ASC')
            ->willReturnSelf();

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $salableCondition = $this->createMock(GetIsStockItemSalableConditionInterface::class);
        $salableCondition->method('execute')->willReturn('is_salable_expression');

        $reservationsIndexTable = $this->createMock(ReservationsIndexTable::class);
        $reservationsIndexTable->method('getTableName')->willReturn('reservations_temp');

        $selectBuilder = (new ObjectManager($this))->getObject(
            SelectBuilder::class,
            [
                'resourceConnection' => $resourceConnection,
                'getIsStockItemSalableCondition' => $salableCondition,
                'productTableName' => 'catalog_product_entity',
                'reservationsIndexTable' => $reservationsIndexTable,
            ]
        );

        $selectBuilder->execute(2);
    }

    /**
     * @return Select|MockObject
     */
    private function createSelfReturningSelect()
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'joinLeft', 'joinInner', 'where', 'group', 'columns'] as $method) {
            $select->method($method)->willReturnSelf();
        }

        return $select;
    }
}
