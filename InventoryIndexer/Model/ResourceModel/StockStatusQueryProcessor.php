<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Model\ResourceModel;

use Magento\CatalogInventory\Model\ResourceModel\Indexer\Stock\QueryProcessorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;

class StockStatusQueryProcessor implements QueryProcessorInterface
{
    /**
     * @param ResourceConnection $resource
     * @param StockIndexTableNameResolverInterface $stockTableResolver
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StockIndexTableNameResolverInterface $stockTableResolver
    ) {
    }

    /**
     * Processes stock status query to include stock status from all stocks
     *
     * @param Select $select
     * @param int[]|null $entityIds
     * @param bool $usePrimaryTable
     * @return Select
     * @throws \Zend_Db_Select_Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function processQuery(Select $select, $entityIds = null, $usePrimaryTable = false)
    {
        if (empty($entityIds)) {
            return $select;
        }

        $connection = $this->resource->getConnection();
        $stockIds = $connection->fetchCol(
            $connection->select()
                ->from($this->resource->getTableName('inventory_stock'), ['stock_id'])
        );

        if (!$stockIds) {
            return $select;
        }

        $productEntityTable = $this->resource->getTableName('catalog_product_entity');

        $unionParts = [];
        foreach ($stockIds as $stockId) {
            $stockIndexTable = $this->resource->getTableName(
                $this->stockTableResolver->execute((int)$stockId)
            );
            $cols = $connection->describeTable($stockIndexTable);

            if (!isset($cols['is_salable'])) {
                continue;
            }

            if (isset($cols['product_id'])) {
                $unionParts[] = $connection->select()
                    ->from(['s' => $stockIndexTable], [
                        'product_id'   => 's.product_id',
                        'stock_status' => 's.is_salable',
                    ])
                    ->where('s.product_id IN (?)', $entityIds);
            } elseif (isset($cols['sku'])) {
                $unionParts[] = $connection->select()
                    ->from(['s' => $stockIndexTable], [
                        'product_id'   => 'e.entity_id',
                        'stock_status' => 's.is_salable',
                    ])
                    ->joinInner(['e' => $productEntityTable], 'e.sku = s.sku', [])
                    ->where('e.entity_id IN (?)', $entityIds);
            }
        }

        if (!$unionParts) {
            return $select;
        }

        $unionAllStocks = $connection->select()->union($unionParts, Select::SQL_UNION_ALL);
        $anyStock = $connection->select()
            ->from(['u' => $unionAllStocks], [
                'entity_id'  => 'u.product_id',
                'website_id' => new \Zend_Db_Expr('0'),
                'stock_id'   => new \Zend_Db_Expr('1'),
                'qty'        => new \Zend_Db_Expr('0'),
                'status'     => new \Zend_Db_Expr('MAX(u.stock_status)'),
            ])
            ->group('u.product_id');

        $combinedUnion = $connection->select()->union([$select, $anyStock], Select::SQL_UNION_ALL);

        return $connection->select()->from(['t' => $combinedUnion]);
    }
}
