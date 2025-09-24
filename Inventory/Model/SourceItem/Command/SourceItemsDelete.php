<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Inventory\Model\SourceItem\Command;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\InputException;
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
     * @var ManagerInterface
     */
    private ManagerInterface $eventManager;

    /**
     * @param DeleteMultiple $deleteMultiple
     * @param LoggerInterface $logger
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        DeleteMultiple $deleteMultiple,
        LoggerInterface $logger,
        ManagerInterface $eventManager
    ) {
        $this->deleteMultiple = $deleteMultiple;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
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
                $this->eventManager->dispatch('model_delete_after', ['object' => $sourceItem]);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new CouldNotDeleteException(__('Could not delete Source Items'), $e);
        }
    }
}
