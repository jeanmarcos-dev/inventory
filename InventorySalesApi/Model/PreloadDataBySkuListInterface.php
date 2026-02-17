<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\InventorySalesApi\Model;

/**
 * Proactively fetch and store data in runtime caches for salability checks for the given SKU list and stock.
 *
 * Used to avoid N+1 queries where product salability is evaluated per SKU.
 * Implementations should fetch data in bulk and store it in runtime cache to be used later during salability checks.
 */
interface PreloadDataBySkuListInterface
{
    /**
     * Prepares data by SKU list for further processing.
     *
     * @param array $skus
     * @param int $stockId
     * @return void
     */
    public function execute(array $skus, int $stockId): void;
}
