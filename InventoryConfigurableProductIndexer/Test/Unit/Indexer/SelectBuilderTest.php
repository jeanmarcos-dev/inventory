<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProductIndexer\Test\Unit\Indexer;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryConfigurableProductIndexer\Indexer\SelectBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexName;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameResolverInterface;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SelectBuilderTest extends TestCase
{
    public function testExecuteOrdersBySkuAscending(): void
    {
        $connection = $this->createMock(AdapterInterface::class);

        $select = $this->createMock(Select::class);
        foreach (['from', 'joinInner', 'joinLeft', 'where', 'group'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $connection->method('select')->willReturn($select);

        $select->expects(self::once())
            ->method('order')
            ->with('parent_product_entity.sku ASC')
            ->willReturnSelf();

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $indexNameBuilder = $this->createMock(IndexNameBuilder::class);
        $indexNameBuilder->method('setIndexId')->willReturnSelf();
        $indexNameBuilder->method('addDimension')->willReturnSelf();
        $indexNameBuilder->method('setAlias')->willReturnSelf();
        $indexNameBuilder->method('build')->willReturn($this->createMock(IndexName::class));

        $indexNameResolver = $this->createMock(IndexNameResolverInterface::class);
        $indexNameResolver->method('resolveName')->willReturn('inventory_stock_2');

        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('row_id');
        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->willReturn($metadata);

        $defaultStockProvider = $this->createMock(DefaultStockProviderInterface::class);
        $defaultStockProvider->method('getId')->willReturn(1);

        $statusAttribute = $this->createMock(Attribute::class);
        $statusAttribute->method('getId')->willReturn(97);
        $eavConfig = $this->createMock(Config::class);
        $eavConfig->method('getAttribute')->willReturn($statusAttribute);

        $selectBuilder = (new ObjectManager($this))->getObject(
            SelectBuilder::class,
            [
                'resourceConnection' => $resourceConnection,
                'indexNameBuilder' => $indexNameBuilder,
                'indexNameResolver' => $indexNameResolver,
                'metadataPool' => $metadataPool,
                'defaultStockProvider' => $defaultStockProvider,
                'eavConfig' => $eavConfig,
            ]
        );

        $selectBuilder->execute(2);
    }
}
