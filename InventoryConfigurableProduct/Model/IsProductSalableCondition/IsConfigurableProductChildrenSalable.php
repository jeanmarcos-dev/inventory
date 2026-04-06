<?php
/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Model\IsProductSalableCondition;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;

/**
 * Service which checks whether any configurable product child is salable in a given Stock
 */
class IsConfigurableProductChildrenSalable
{
    /**
     * @var Configurable
     */
    private $configurable;

    /**
     * @var AreProductsSalableInterface
     */
    private $areProductsSalable;

    /**
     * @var GetProductIdsBySkusInterface
     */
    private $getProductIdsBySkus;

    /**
     * @var GetSkusByProductIdsInterface
     */
    private $getSkusByProductIds;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var StockIndexTableNameResolverInterface
     */
    private $stockIndexTableNameResolver;

    /**
     * @param Configurable $configurable
     * @param AreProductsSalableInterface $areProductsSalable
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param ResourceConnection|null $resource
     * @param StockIndexTableNameResolverInterface|null $stockIndexTableNameResolver
     */
    public function __construct(
        Configurable $configurable,
        AreProductsSalableInterface $areProductsSalable,
        GetProductIdsBySkusInterface $getProductIdsBySkus,
        GetSkusByProductIdsInterface $getSkusByProductIds,
        ?ResourceConnection $resource = null,
        ?StockIndexTableNameResolverInterface $stockIndexTableNameResolver = null
    ) {
        $this->configurable = $configurable;
        $this->areProductsSalable = $areProductsSalable;
        $this->getProductIdsBySkus = $getProductIdsBySkus;
        $this->getSkusByProductIds = $getSkusByProductIds;
        $this->resource = $resource
            ?: ObjectManager::getInstance()->get(ResourceConnection::class);
        $this->stockIndexTableNameResolver = $stockIndexTableNameResolver
            ?: ObjectManager::getInstance()->get(StockIndexTableNameResolverInterface::class);
    }

    /**
     * Get configurable product salable status based on children products salable status
     *
     * Returns TRUE if:
     *
     *  - at least one child is salable
     *
     * @param string $sku
     * @param int $stockId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(string $sku, int $stockId): bool
    {
        $productId = $this->getProductIdsBySkus->execute([$sku])[$sku];
        $ids = $this->configurable->getChildrenIds($productId);
        $childrenIds = $ids[0] ?? [];

        if (empty($childrenIds)) {
            return false;
        }

        $childrenSkus = $this->getSkusByProductIds->execute($childrenIds);

        if (empty($childrenSkus)) {
            return false;
        }

        $candidateSkus = $this->getSalableCandidatesFromIndex($childrenSkus, $stockId);

        if (empty($candidateSkus)) {
            return false;
        }

        foreach ($candidateSkus as $candidateSku) {
            $results = $this->areProductsSalable->execute([$candidateSku], $stockId);
            $result = reset($results);
            if ($result && $result->isSalable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pre-filter children using the stock index to find candidates marked as salable.
     *
     * The stock index table (inventory_stock_{id}) aggregates source item data per stock,
     * accounting for multi-source inventory assignments. This single query eliminates
     * iterating through all children individually. The returned candidates are then
     * validated through the full salability condition chain via areProductsSalable.
     *
     * @param string[] $skus
     * @param int $stockId
     * @return string[]
     */
    private function getSalableCandidatesFromIndex(array $skus, int $stockId): array
    {
        try {
            $connection = $this->resource->getConnection();
            $select = $connection->select()
                ->from(
                    $this->stockIndexTableNameResolver->execute($stockId),
                    [IndexStructure::SKU]
                )
                ->where(IndexStructure::SKU . ' IN (?)', array_values($skus))
                ->where(IndexStructure::IS_SALABLE . ' = ?', 1);

            return $connection->fetchCol($select);
        } catch (\Exception $e) {
            return [];
        }
    }
}
