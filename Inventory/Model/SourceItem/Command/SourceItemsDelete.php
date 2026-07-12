<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Inventory\Model\SourceItem\Command;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\InputException;
use Magento\Inventory\Model\IsProductAssignedToStock\CacheStorage;
use Magento\Inventory\Model\ResourceModel\SourceItem\DeleteMultiple;
use Magento\InventoryApi\Api\SourceItemsDeleteInterface;
use Psr\Log\LoggerInterface;

/**
 * @inheritdoc
 */
class SourceItemsDelete implements SourceItemsDeleteInterface
{
    /**
     * @var DeleteMultiple
     */
    private $deleteMultiple;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CacheStorage
     */
    private $isProductAssignedToStockCacheStorage;

    /**
     * @param DeleteMultiple $deleteMultiple
     * @param LoggerInterface $logger
     * @param CacheStorage $isProductAssignedToStockCacheStorage
     */
    public function __construct(
        DeleteMultiple $deleteMultiple,
        LoggerInterface $logger,
        CacheStorage $isProductAssignedToStockCacheStorage
    ) {
        $this->deleteMultiple = $deleteMultiple;
        $this->logger = $logger;
        $this->isProductAssignedToStockCacheStorage = $isProductAssignedToStockCacheStorage;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $sourceItems): void
    {
        if (empty($sourceItems)) {
            throw new InputException(__('Input data is empty'));
        }
        try {
            $this->deleteMultiple->execute($sourceItems);
            foreach ($sourceItems as $sourceItem) {
                $this->isProductAssignedToStockCacheStorage->delete((string) $sourceItem->getSku());
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new CouldNotDeleteException(__('Could not delete Source Items'), $e);
        }
    }
}
