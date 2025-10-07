<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Model\Queue;

use Magento\InventoryIndexer\Model\Queue\ReservationData;
use Magento\InventoryIndexer\Model\Queue\ReservationDataFactory;
use Magento\InventoryIndexer\Model\Queue\UpdateIndexSalabilityStatus;
use Magento\InventoryIndexer\Model\Queue\UpdateIndexSalabilityStatus\IndexProcessor;
use Magento\InventoryCatalogApi\Model\GetParentSkusOfChildrenSkusInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(UpdateIndexSalabilityStatus::class),
]
class UpdateIndexSalabilityStatusTest extends TestCase
{
    /**
     * @var IndexProcessor|MockObject
     */
    private $indexProcessor;

    /**
     * @var GetParentSkusOfChildrenSkusInterface|MockObject
     */
    private $getParentSkusOfChildrenSkus;

    /**
     * @var ReservationDataFactory|MockObject
     */
    private $reservationDataFactory;

    /**
     * @var UpdateIndexSalabilityStatus
     */
    private $model;

    /**
     * @inheridoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->indexProcessor = $this->createMock(IndexProcessor::class);
        $this->getParentSkusOfChildrenSkus = $this->createMock(GetParentSkusOfChildrenSkusInterface::class);
        $this->reservationDataFactory = $this->createMock(ReservationDataFactory::class);
        $this->model = new UpdateIndexSalabilityStatus(
            $this->indexProcessor,
            $this->getParentSkusOfChildrenSkus,
            $this->reservationDataFactory
        );
    }

    /**
     * Test that legacy stock indexer is executed if the stock is default otherwise custom stock indexer is executed
     *
     * @param int $stockId
     * @param int $indexProcessorInvokeCount
     * @param array $parentSkusOfChildrenSkus
     * @param array $affectedParentSkus
     * @dataProvider executeDataProvider
     */
    public function testExecute(
        int $stockId,
        int $indexProcessorInvokeCount,
        array $parentSkusOfChildrenSkus,
        array $affectedParentSkus
    ): void {
        $skus = ['P1', 'P2'];
        $changes = ['P1' => true];
        $parentChanges = array_fill_keys($affectedParentSkus, true);
        $changes = array_merge($changes, $parentChanges);

        $reservation = new ReservationData($skus, $stockId);
        $this->indexProcessor->expects($this->exactly($indexProcessorInvokeCount))
            ->method('execute')
            ->willReturn($changes);
        $this->getParentSkusOfChildrenSkus->method('execute')
            ->willReturn($parentSkusOfChildrenSkus);
        $reservationData = $this->createMock(ReservationData::class);
        $this->reservationDataFactory->method('create')
            ->willReturn($reservationData);

        $this->assertEquals($changes, $this->model->execute($reservation));
    }

    /**
     * @return array
     */
    public static function executeDataProvider(): array
    {
        return [
            [
                'stockId' => 1,
                'indexProcessorInvokeCount' => 1,
                'parentSkusOfChildrenSkus' => [],
                'affectedParentSkus' => [],
            ],
            [
                'stockId' => 2,
                'indexProcessorInvokeCount' => 2,
                'parentSkusOfChildrenSkus' => [
                    'P1' => ['PConf1', 'PConf2']
                ],
                'affectedParentSkus' => ['PConf1', 'PConf2'],
            ],
            [
                'stockId' => 3,
                'indexProcessorInvokeCount' => 2,
                'parentSkusOfChildrenSkus' => [
                    'P1' => ['PConf3']
                ],
                'affectedParentSkus' => ['PConf3'],
            ],
        ];
    }
}
