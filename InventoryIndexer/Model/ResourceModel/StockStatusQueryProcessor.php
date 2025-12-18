<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Model\ResourceModel;

use Magento\CatalogInventory\Model\ResourceModel\Indexer\Stock\QueryProcessorInterface;
use Magento\CatalogInventory\Model\ResourceModel\Indexer\Stock\StatusExpression\DefaultExpression;
use Magento\CatalogInventory\Model\ResourceModel\Indexer\Stock\StatusExpression\ExpressionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;

class StockStatusQueryProcessor implements QueryProcessorInterface
{
    /**
     * @var ExpressionInterface
     */
    private ExpressionInterface $statusExpression;

    /**
     * @param ResourceConnection $resource
     * @param StockIndexTableNameResolverInterface $stockTableResolver
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param ExpressionInterface|null $defaultExpression
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StockIndexTableNameResolverInterface $stockTableResolver,
        private readonly DefaultStockProviderInterface $defaultStockProvider,
        ?ExpressionInterface $defaultExpression = null,
    ) {
        $this->statusExpression = $defaultExpression ?? \Magento\Framework\App\ObjectManager::getInstance()
            ->get(DefaultExpression::class);
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

        $stockWebsiteMap = $this->getStockWebsiteMap();
        if (!$stockWebsiteMap) {
            return $select;
        }

        $productEntityTable = $this->resource->getTableName('catalog_product_entity');
        $catalogInventoryStockItemTable = $this->resource->getTableName('cataloginventory_stock_item');
        $unionParts = [];
        $connection = $this->resource->getConnection();
        foreach ($stockWebsiteMap as $stockId => $websiteIds) {
            $stockIndexTable = $this->resource->getTableName(
                $this->stockTableResolver->execute($stockId)
            );
            $isBundle = $this->hasBundleTypeCondition((string)$select);

            $baseInventorySelectFactory = function (int $websiteId) use (
                $connection,
                $stockIndexTable,
                $productEntityTable,
                $catalogInventoryStockItemTable,
                $entityIds,
                $stockId,
                $isBundle
            ): Select {
                $websiteExpr = new \Zend_Db_Expr((string)$websiteId);
                $stockExpr   = new \Zend_Db_Expr((string)$stockId);
                $qtyExpr     = $connection->getCheckSql('s.quantity > 0', 's.quantity', 0);

                if ($isBundle) {
                    $statusExpr = $this->statusExpression->getExpression($connection, false);
                } else {
                    $statusExpr = $connection->getCheckSql(
                        'cisi.use_config_manage_stock = 0 AND cisi.manage_stock = 0',
                        '1',
                        's.is_salable'
                    );
                }

                return $connection->select()
                    ->from(['s' => $stockIndexTable], [
                        'entity_id'  => 'e.entity_id',
                        'website_id' => $websiteExpr,
                        'stock_id'   => $stockExpr,
                        'qty'        => $qtyExpr,
                        'status'     => $statusExpr
                    ])
                    ->joinInner(['e' => $productEntityTable], 'e.sku = s.sku', [])
                    ->joinInner(
                        ['cisi' => $catalogInventoryStockItemTable],
                        'cisi.product_id = e.entity_id',
                        []
                    )
                    ->where('e.entity_id IN (?)', $entityIds);
            };

            foreach ($websiteIds as $websiteId) {
                $unionParts[] = $baseInventorySelectFactory($websiteId);
            }
        }

        if (!$unionParts) {
            return $select;
        }

        $stockInventoryUnion = $connection->select()->union($unionParts, Select::SQL_UNION_ALL);
        $combinedUnion = $connection->select()->union([$select, $stockInventoryUnion], Select::SQL_UNION_ALL);

        return $connection->select()
            ->from(
                ['t' => $combinedUnion],
                [
                    'entity_id',
                    'website_id',
                    'stock_id',
                    'qty'    => new \Zend_Db_Expr('MAX(t.qty)'),
                    'status' => new \Zend_Db_Expr('MAX(t.status)'),
                ]
            )
            ->group(['entity_id', 'website_id', 'stock_id']);
    }

    /**
     * Retrieve map of stock IDs to website IDs
     *
     * @return array
     */
    private function getStockWebsiteMap(): array
    {
        $stockWebsiteMap = [];
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['s' => $this->resource->getTableName('inventory_stock')],
                    ['stock_id' => 's.stock_id']
                )
                ->join(
                    ['sc' => $this->resource->getTableName('inventory_stock_sales_channel')],
                    'sc.stock_id = s.stock_id AND sc.type = "website"',
                    []
                )
                ->join(
                    ['w' => $this->resource->getTableName('store_website')],
                    'w.code = sc.code',
                    ['website_id' => 'w.website_id']
                )
        );
        foreach ($rows as $row) {
            $stockId = (int)$row['stock_id'];
            if ($this->defaultStockProvider->getId() === $stockId) {
                continue;
            }

            $stockWebsiteMap[$stockId][] = (int)$row['website_id'];
        }

        return $stockWebsiteMap;
    }

    /**
     * Returns true if the SQL contains a condition like: e.type_id = 'bundle'
     *
     * @param string $sql
     * @return bool
     */
    private function hasBundleTypeCondition(string $sql): bool
    {
        $pattern = '/(?:^|[\s(])`?e`?\s*\.\s*`?type_id`?\s*=\s*(["\'])\s*bundle\s*\1/i';
        return preg_match($pattern, $sql) === 1;
    }
}
