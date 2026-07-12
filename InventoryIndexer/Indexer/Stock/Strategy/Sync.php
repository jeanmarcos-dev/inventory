<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\InventoryIndexer\Indexer\Stock\Strategy;

use Magento\Framework\App\ResourceConnection;
use Magento\Indexer\Model\ProcessManager;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Indexer\Stock\GetAllStockIds;
use Magento\InventoryIndexer\Indexer\Stock\IndexDataProviderByStockId;
use Magento\InventoryIndexer\Indexer\Stock\PrepareReservationsIndexData;
use Magento\InventoryIndexer\Indexer\Stock\ReservationsIndexTable;
use Magento\InventoryMultiDimensionalIndexerApi\Model\Alias;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexHandlerInterface;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexStructureInterface;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexTableSwitcherInterface;

/**
 * Reindex stocks synchronously.
 */
class Sync
{
    /**
     * @var GetAllStockIds
     */
    private $getAllStockIds;

    /**
     * @var IndexStructureInterface
     */
    private $indexStructure;

    /**
     * @var IndexHandlerInterface
     */
    private $indexHandler;

    /**
     * @var IndexNameBuilder
     */
    private $indexNameBuilder;

    /**
     * @var IndexDataProviderByStockId
     */
    private $indexDataProviderByStockId;

    /**
     * @var IndexTableSwitcherInterface
     */
    private $indexTableSwitcher;

    /**
     * @var DefaultStockProviderInterface
     */
    private $defaultStockProvider;

    /**
     * @var ReservationsIndexTable
     */
    private $reservationsIndexTable;

    /**
     * @var PrepareReservationsIndexData
     */
    private $prepareReservationsIndexData;

    /**
     * @var ProcessManager
     */
    private $processManager;

    /**
     * $indexStructure is reserved name for construct variable in index internal mechanism
     *
     * @param GetAllStockIds $getAllStockIds
     * @param IndexStructureInterface $indexStructureHandler
     * @param IndexHandlerInterface $indexHandler
     * @param IndexNameBuilder $indexNameBuilder
     * @param IndexDataProviderByStockId $indexDataProviderByStockId
     * @param IndexTableSwitcherInterface $indexTableSwitcher
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param ReservationsIndexTable $reservationsIndexTable
     * @param PrepareReservationsIndexData $prepareReservationsIndexData
     * @param ProcessManager $processManager
     */
    public function __construct(
        GetAllStockIds $getAllStockIds,
        IndexStructureInterface $indexStructureHandler,
        IndexHandlerInterface $indexHandler,
        IndexNameBuilder $indexNameBuilder,
        IndexDataProviderByStockId $indexDataProviderByStockId,
        IndexTableSwitcherInterface $indexTableSwitcher,
        DefaultStockProviderInterface $defaultStockProvider,
        ReservationsIndexTable $reservationsIndexTable,
        PrepareReservationsIndexData $prepareReservationsIndexData,
        ProcessManager $processManager
    ) {
        $this->getAllStockIds = $getAllStockIds;
        $this->indexStructure = $indexStructureHandler;
        $this->indexHandler = $indexHandler;
        $this->indexNameBuilder = $indexNameBuilder;
        $this->indexDataProviderByStockId = $indexDataProviderByStockId;
        $this->indexTableSwitcher = $indexTableSwitcher;
        $this->defaultStockProvider = $defaultStockProvider;
        $this->reservationsIndexTable = $reservationsIndexTable;
        $this->prepareReservationsIndexData = $prepareReservationsIndexData;
        $this->processManager = $processManager;
    }

    /**
     * Reindex all stocks.
     *
     * @return void
     */
    public function executeFull(): void
    {
        $stockIds = $this->getAllStockIds->execute();
        $this->executeList($stockIds);
    }

    /**
     * Reindex single stock by id.
     *
     * @param int $stockId
     * @return void
     */
    public function executeRow(int $stockId): void
    {
        $this->executeList([$stockId]);
    }

    /**
     * Reindex list of stock by provided ids.
     *
     * @param int[] $stockIds
     * @return void
     */
    public function executeList(array $stockIds): void
    {
        $stocksToReindex = [];
        foreach ($stockIds as $stockId) {
            if ($this->defaultStockProvider->getId() !== (int)$stockId) {
                $stocksToReindex[] = (int)$stockId;
            }
        }

        if (count($stocksToReindex) > 1) {
            $userFunctions = [];
            foreach ($stocksToReindex as $stockId) {
                $userFunctions[] = function () use ($stockId) {
                    $this->reindexStock($stockId);
                };
            }
            $this->processManager->execute($userFunctions);
        } elseif ($stocksToReindex) {
            $this->reindexStock($stocksToReindex[0]);
        }
    }

    /**
     * Rebuild the index of a single stock through the replica table.
     *
     * @param int $stockId
     * @return void
     */
    private function reindexStock(int $stockId): void
    {
        $replicaIndexName = $this->indexNameBuilder
            ->setIndexId(InventoryIndexer::INDEXER_ID)
            ->addDimension('stock_', (string)$stockId)
            ->setAlias(Alias::ALIAS_REPLICA)
            ->build();

        $mainIndexName = $this->indexNameBuilder
            ->setIndexId(InventoryIndexer::INDEXER_ID)
            ->addDimension('stock_', (string)$stockId)
            ->setAlias(Alias::ALIAS_MAIN)
            ->build();

        $this->indexStructure->delete($replicaIndexName, ResourceConnection::DEFAULT_CONNECTION);
        $this->indexStructure->create($replicaIndexName, ResourceConnection::DEFAULT_CONNECTION);

        if (!$this->indexStructure->isExist($mainIndexName, ResourceConnection::DEFAULT_CONNECTION)) {
            $this->indexStructure->create($mainIndexName, ResourceConnection::DEFAULT_CONNECTION);
        }

        $this->reservationsIndexTable->createTable($stockId);
        $this->prepareReservationsIndexData->execute($stockId);

        $this->indexHandler->saveIndex(
            $replicaIndexName,
            $this->indexDataProviderByStockId->execute($stockId),
            ResourceConnection::DEFAULT_CONNECTION
        );
        $this->indexTableSwitcher->switch($mainIndexName, ResourceConnection::DEFAULT_CONNECTION);
        $this->indexStructure->delete($replicaIndexName, ResourceConnection::DEFAULT_CONNECTION);

        $this->reservationsIndexTable->dropTable($stockId);
    }
}
