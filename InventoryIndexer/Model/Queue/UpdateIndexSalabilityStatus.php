<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Model\Queue;

use Magento\Framework\Exception\StateException;
use Magento\InventoryIndexer\Model\Queue\UpdateIndexSalabilityStatus\IndexProcessor;
use Magento\InventoryCatalogApi\Model\GetParentSkusOfChildrenSkusInterface;

/**
 * Recalculates index items salability status.
 */
class UpdateIndexSalabilityStatus
{
    /**
     * @param IndexProcessor $indexProcessor
     * @param GetParentSkusOfChildrenSkusInterface $getParentSkusOfChildrenSkus
     * @param ReservationDataFactory $reservationDataFactory
     */
    public function __construct(
        private readonly IndexProcessor $indexProcessor,
        private readonly GetParentSkusOfChildrenSkusInterface $getParentSkusOfChildrenSkus,
        private readonly ReservationDataFactory $reservationDataFactory
    ) {
    }

    /**
     * Reindex items salability statuses.
     *
     * @param ReservationData $reservationData
     * @return array<string, bool> - ['sku' => bool]: list of SKUs with salability status changed.
     * @throws StateException
     */
    public function execute(ReservationData $reservationData): array
    {
        $dataForUpdate = [];
        if ($reservationData->getSkus()) {
            $dataForUpdate = $this->indexProcessor->execute($reservationData);
            if ($dataForUpdate) {
                $parentSkusOfChildrenSkus = $this->getParentSkusOfChildrenSkus->execute(array_keys($dataForUpdate));
                if ($parentSkusOfChildrenSkus) {
                    $parentSkus = array_values($parentSkusOfChildrenSkus);
                    $parentSkus = array_merge(...$parentSkus);
                    $parentSkus = array_unique($parentSkus);
                    $parentReservationData = $this->reservationDataFactory->create([
                        'skus' => $parentSkus,
                        'stock' => $reservationData->getStock(),
                    ]);
                    $parentDataForUpdate = $this->indexProcessor->execute($parentReservationData);
                    $dataForUpdate += $parentDataForUpdate + array_fill_keys($parentSkus, true);
                }
            }
        }

        return $dataForUpdate;
    }
}
