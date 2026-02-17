<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\InventorySalesApi\Model;

class PreloadDataBySkuListPool implements PreloadDataBySkuListInterface
{
    /**
     * @param PreloadDataBySkuListInterface[] $pool
     */
    public function __construct(
        private readonly array $pool = []
    ) {
        // Ensures that all items in the pool implement the interface
        array_map(
            static fn (PreloadDataBySkuListInterface $preloadDataBySkuList) => $preloadDataBySkuList,
            $this->pool
        );
    }

    /**
     * @inheritDoc
     */
    public function execute(array $skus, int $stockId): void
    {
        foreach ($this->pool as $preloadDataBySkuList) {
            $preloadDataBySkuList->execute($skus, $stockId);
        }
    }
}
