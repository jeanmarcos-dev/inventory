<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryLogging\Plugin\Inventory\Model\SourceItem\Command;

use Magento\Inventory\Model\SourceItem\Command\SourceItemsDelete;
use Magento\Logging\Model\Processor;

class SourceItemsDeletePlugin
{
    /**
     * @param Processor $processor
     */
    public function __construct(
        private readonly Processor $processor
    ) {
    }

    /**
     * @param SourceItemsDelete $subject
     * @param $result
     * @param array $sourceItems
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(SourceItemsDelete $subject, $result, array $sourceItems): void {
        if (empty($sourceItems)) {
            return;
        }

        foreach ($sourceItems as $sourceItem) {
            $this->processor->modelActionAfter($sourceItem, 'delete');
        }
    }
}
