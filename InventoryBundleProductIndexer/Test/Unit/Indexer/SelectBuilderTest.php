<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryBundleProductIndexer\Test\Unit\Indexer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\InventoryBundleProductIndexer\Indexer\OptionsStatusSelectBuilder;
use Magento\InventoryBundleProductIndexer\Indexer\SelectBuilder;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use PHPUnit\Framework\TestCase;

class SelectBuilderTest extends TestCase
{
    public function testExecuteOrdersBySkuAscending(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('getCheckSql')->willReturn('check_expression');
        $connection->method('getIfNullSql')->willReturn('ifnull_expression');

        $select = $this->createMock(Select::class);
        foreach (['from', 'joinLeft', 'where', 'group', 'columns'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $connection->method('select')->willReturn($select);

        $select->expects(self::once())
            ->method('order')
            ->with('product_entity.sku ASC')
            ->willReturnSelf();

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $defaultStockProvider = $this->createMock(DefaultStockProviderInterface::class);
        $defaultStockProvider->method('getId')->willReturn(1);

        $optionsStatusSelectBuilder = $this->createMock(OptionsStatusSelectBuilder::class);
        $optionsStatusSelectBuilder->method('execute')->willReturn($this->createMock(Select::class));

        $selectBuilder = (new ObjectManager($this))->getObject(
            SelectBuilder::class,
            [
                'resourceConnection' => $resourceConnection,
                'defaultStockProvider' => $defaultStockProvider,
                'optionsStatusSelectBuilder' => $optionsStatusSelectBuilder,
            ]
        );

        $selectBuilder->execute(2, ['bundle_1']);
    }
}
