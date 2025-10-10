<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryLogging\Plugin\Inventory\Model\SourceItem\Command\Handler;

use Magento\Inventory\Model\SourceItem\Command\Handler\SourceItemsSaveHandler;
use Magento\Logging\Model\Processor;

class SourceItemsSaveHandlerPlugin
{
    /**
     * @param Processor $processor
     */
    public function __construct(
        private readonly Processor $processor
    ) {
    }

    /**
     * @param SourceItemsSaveHandler $subject
     * @param $result
     * @param array $sourceItems
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        SourceItemsSaveHandler $subject,
        $result,
        array $sourceItems
    ): mixed {
        if (empty($sourceItems)) {
            return $result;
        }

        foreach ($sourceItems as $sourceItem) {
            $this->processor->modelActionAfter($sourceItem, 'save');
        }

        return $result;
    }
}
