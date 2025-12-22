<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryBundleProductIndexer\Indexer;

use Magento\Bundle\Model\ResourceModel\Indexer\StockStatusQueryProcessorInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Model\Stock;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Zend_Db_Expr;

class InventoryStockStatusQueryProcessor implements StockStatusQueryProcessorInterface
{
    /**
     * @param ResourceConnection $resource
     * @param StockIndexTableNameResolverInterface $stockTableResolver
     * @param Config $eavConfig
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StockIndexTableNameResolverInterface $stockTableResolver,
        private readonly Config $eavConfig,
        private readonly MetadataPool $metadataPool
    ) {
    }

    /**
     * Apply stock status filter to the Select
     *
     * @param Select $select
     * @return Select
     * @throws \Zend_Db_Select_Exception
     */
    public function execute(Select $select): Select
    {
        $select->joinInner(
            ['stock' => $this->buildInventorySelect()],
            'stock.product_id = bs.product_id',
            []
        );
        $select->where('stock_status = ?', Stock::STOCK_IN_STOCK);

        return $select;
    }

    /**
     * Build a Select that combines:
     * - cataloginventory_stock_status (legacy/default stock)
     * - MSI inventory stock indexes (inventory_stock_<stock_id>) for all non-default stocks
     *
     * Output columns:
     *  product_id, website_id, stock_id, qty, stock_status
     *
     * @return Select
     * @throws \Zend_Db_Select_Exception
     * @throws LocalizedException
     */
    private function buildInventorySelect(): Select
    {
        $conn = $this->resource->getConnection();
        $statusAttributeId = (int)$this->eavConfig->getAttribute(
            Product::ENTITY,
            ProductInterface::STATUS
        )->getId();
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $linkField = $metadata->getLinkField();

        $legacyTable = $this->resource->getTableName('cataloginventory_stock_status');
        $cpeTable = $this->resource->getTableName('catalog_product_entity');
        $cpeiTable = $this->resource->getTableName('catalog_product_entity_int');
        $cisiTable = $this->resource->getTableName('cataloginventory_stock_item');

        $bundleSelTable = $this->resource->getTableName('catalog_product_bundle_selection');
        $bundleOptTable = $this->resource->getTableName('catalog_product_bundle_option');

        /**
         * - bundle parents (enabled)
         * - selection children (enabled) that belong to enabled bundle parents
         *
         */
        $bundleParents = $conn->select()
            ->from(['p' => $cpeTable], ['product_id' => 'p.entity_id'])
            ->joinInner(
                ['p_status' => $cpeiTable],
                'p_status.' . $linkField . ' = p.' . $linkField
                . ' AND p_status.attribute_id = ' . $statusAttributeId
                . ' AND p_status.value = 1',
                []
            )
            ->where('p.type_id = ?', 'bundle');

        $selectionChildren = $conn->select()
            ->from(['p' => $cpeTable], ['product_id' => 'c.entity_id'])
            ->joinInner(
                ['p_status' => $cpeiTable],
                'p_status.' . $linkField . ' = p.' . $linkField
                . ' AND p_status.attribute_id = ' . $statusAttributeId
                . ' AND p_status.value = 1',
                []
            )
            ->joinInner(
                ['bo' => $bundleOptTable],
                'bo.parent_id = p.' . $linkField,
                []
            )
            ->joinInner(
                ['bs' => $bundleSelTable],
                'bs.option_id = bo.option_id AND bs.parent_product_id = p.' . $linkField,
                []
            )
            ->joinInner(['c' => $cpeTable], 'c.entity_id = bs.product_id', [])
            ->joinInner(
                ['c_status' => $cpeiTable],
                'c_status.' . $linkField . ' = c.' . $linkField
                . ' AND c_status.attribute_id = ' . $statusAttributeId
                . ' AND c_status.value = 1',
                []
            )
            ->where('p.type_id = ?', 'bundle');

        $interestingProductIds = $conn->select()->union([$bundleParents, $selectionChildren], Select::SQL_UNION_ALL);

        $legacySelect = $conn->select()
            ->from(['l' => $legacyTable], [
                'product_id',
                'website_id',
                'stock_id',
                'qty',
                'stock_status',
            ])
            ->joinInner(
                ['ip' => $interestingProductIds],
                'ip.product_id = l.product_id',
                []
            );

        $stockWebsiteRows = $conn->fetchAll(
            $conn->select()
                ->from(['s' => $this->resource->getTableName('inventory_stock')], ['stock_id' => 's.stock_id'])
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

        $msiParts = [];

        foreach ($stockWebsiteRows as $row) {
            $stockId = (int)$row['stock_id'];
            $websiteId = (int)$row['website_id'];

            if ($stockId === 1) {
                continue;
            }

            $msiIndexTable = $this->resource->getTableName(
                $this->stockTableResolver->execute($stockId)
            );

            $cols = $conn->describeTable($msiIndexTable);
            if (!isset($cols['is_salable'])) {
                continue;
            }

            $qtyExpr = new Zend_Db_Expr('msi.quantity');
            $statusExpr = new Zend_Db_Expr(
                "IF(cisi.use_config_manage_stock = 0 AND cisi.manage_stock = 0, 1, IFNULL(msi.is_salable, 0))"
            );

            if (isset($cols['product_id'])) {
                $sel = $conn->select()
                    ->from(['msi' => $msiIndexTable], [])
                    ->joinInner(
                        ['ip' => $interestingProductIds],
                        'ip.product_id = msi.product_id',
                        []
                    )
                    ->joinLeft(
                        ['cisi' => $cisiTable],
                        'cisi.product_id = msi.product_id AND cisi.stock_id = 1',
                        []
                    )
                    ->columns([
                        'product_id' => 'msi.product_id',
                        'website_id' => new Zend_Db_Expr((string)$websiteId),
                        'stock_id' => new Zend_Db_Expr((string)$stockId),
                        'qty' => $qtyExpr,
                        'stock_status' => $statusExpr,
                    ]);

                $msiParts[] = $sel;
            } elseif (isset($cols['sku'])) {
                $sel = $conn->select()
                    ->from(['msi' => $msiIndexTable], [])
                    ->joinInner(['e' => $cpeTable], 'e.sku = msi.sku', [])
                    ->joinInner(
                        ['ip' => $interestingProductIds],
                        'ip.product_id = e.entity_id',
                        []
                    )
                    ->joinLeft(
                        ['cisi' => $cisiTable],
                        'cisi.product_id = e.entity_id AND cisi.stock_id = 1',
                        []
                    )
                    ->columns([
                        'product_id' => 'e.entity_id',
                        'website_id' => new Zend_Db_Expr((string)$websiteId),
                        'stock_id' => new Zend_Db_Expr((string)$stockId),
                        'qty' => $qtyExpr,
                        'stock_status' => $statusExpr,
                    ]);

                $msiParts[] = $sel;
            }
        }

        if (!$msiParts) {
            return $legacySelect;
        }

        $msiUnion = $conn->select()->union($msiParts, Select::SQL_UNION_ALL);
        $combined = $conn->select()->union([$legacySelect, $msiUnion], Select::SQL_UNION_ALL);

        return $conn->select()
            ->from(['stock_all' => $combined], [
                'product_id',
                'website_id',
                'stock_id',
                'qty' => new Zend_Db_Expr('MAX(stock_all.qty)'),
                'stock_status' => new Zend_Db_Expr('MAX(stock_all.stock_status)'),
            ])
            ->group(['product_id', 'website_id', 'stock_id']);
    }
}
