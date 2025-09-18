<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryBundleProductIndexer\Indexer;

use ArrayIterator;
use Magento\Framework\App\ResourceConnection;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Indexer\SiblingProductsProviderInterface;
use Magento\InventoryIndexer\Indexer\Stock\GetAllStockIds;
use Magento\InventoryIndexer\Indexer\Stock\PrepareReservationsIndexData;
use Magento\InventoryIndexer\Indexer\Stock\ReservationsIndexTable;
use Magento\InventoryMultiDimensionalIndexerApi\Model\Alias;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexHandlerInterface;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexStructureInterface;

/**
 * Index bundle products for given stocks.
 */
class StockIndexer
{
    /**
     * @param GetAllStockIds $getAllStockIds
     * @param IndexStructureInterface $indexStructure
     * @param IndexHandlerInterface $indexHandler
     * @param IndexNameBuilder $indexNameBuilder
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param SiblingProductsProviderInterface $productsProvider
     * @param ReservationsIndexTable $reservationsIndexTable
     * @param PrepareReservationsIndexData $prepareReservationsIndexData
     */
    public function __construct(
        private readonly GetAllStockIds $getAllStockIds,
        private readonly IndexStructureInterface $indexStructure,
        private readonly IndexHandlerInterface $indexHandler,
        private readonly IndexNameBuilder $indexNameBuilder,
        private readonly DefaultStockProviderInterface $defaultStockProvider,
        private readonly SiblingProductsProviderInterface $productsProvider,
        private readonly ReservationsIndexTable $reservationsIndexTable,
        private readonly PrepareReservationsIndexData $prepareReservationsIndexData,
    ) {
    }

    /**
     * Index bundle products for all stocks.
     *
     * @return void
     */
    public function executeFull()
    {
        $stockIds = $this->getAllStockIds->execute();
        $this->executeList($stockIds);
    }

    /**
     * Index bundle products for given stock.
     *
     * @param int $stockId
     * @param array $skuList
     * @return void
     */
    public function executeRow(int $stockId, array $skuList = [])
    {
        $this->executeList([$stockId], $skuList);
    }

    /**
     * Index bundle products for given stocks.
     *
     * @param array $stockIds
     * @param array $skuList
     * @return void
     */
    public function executeList(array $stockIds, array $skuList = [])
    {
        foreach ($stockIds as $stockId) {
            if ($this->defaultStockProvider->getId() === $stockId) {
                continue;
            }

            $mainIndexName = $this->indexNameBuilder
                ->setIndexId(InventoryIndexer::INDEXER_ID)
                ->addDimension('stock_', (string)$stockId)
                ->setAlias(Alias::ALIAS_MAIN)
                ->build();

            if (!$this->indexStructure->isExist($mainIndexName, ResourceConnection::DEFAULT_CONNECTION)) {
                $this->indexStructure->create($mainIndexName, ResourceConnection::DEFAULT_CONNECTION);
            }

            $this->reservationsIndexTable->createTable($stockId);
            $this->prepareReservationsIndexData->execute($stockId);

            $data = $this->productsProvider->getData($mainIndexName, $skuList);
            $this->indexHandler->cleanIndex(
                $mainIndexName,
                new ArrayIterator($skuList),
                ResourceConnection::DEFAULT_CONNECTION
            );
            $this->indexHandler->saveIndex(
                $mainIndexName,
                new ArrayIterator($data),
                ResourceConnection::DEFAULT_CONNECTION
            );

            $this->reservationsIndexTable->dropTable($stockId);
        }
    }
}
