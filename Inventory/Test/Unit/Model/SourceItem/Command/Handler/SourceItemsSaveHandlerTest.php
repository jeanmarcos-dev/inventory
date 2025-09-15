<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Inventory\Test\Unit\Model\SourceItem\Command\Handler;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Validation\ValidationResult;
use Magento\Inventory\Model\ResourceModel\SourceItem\SaveMultiple;
use Magento\Inventory\Model\SourceItem\Command\Handler\SourceItemsSaveHandler;
use Magento\Inventory\Model\SourceItem\Validator\SourceItemsValidator;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SourceItemsSaveHandlerTest extends TestCase
{
    /**
     * @var SourceItemsValidator|MockObject
     */
    private SourceItemsValidator $sourceItemsValidator;

    /**
     * @var SaveMultiple|MockObject
     */
    private SaveMultiple $saveMultiple;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface $logger;

    /**
     * @var ManagerInterface|MockObject
     */
    private ManagerInterface $eventManager;

    /**
     * @var SourceItemsSaveHandler
     */
    private SourceItemsSaveHandler $sourceItemsSaveHandler;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->sourceItemsValidator = $this->createMock(SourceItemsValidator::class);
        $this->saveMultiple = $this->createMock(SaveMultiple::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventManager = $this->createMock(ManagerInterface::class);
        $this->sourceItemsSaveHandler = new SourceItemsSaveHandler(
            $this->sourceItemsValidator,
            $this->saveMultiple,
            $this->logger,
            $this->eventManager
        );
        parent::setUp();
    }

    /**
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Validation\ValidationException
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function testObserverDispatch(): void
    {
        $sourceItem = $this->createMock(SourceItemInterface::class);
        $validationResult = $this->createMock(ValidationResult::class);
        $this->sourceItemsValidator->expects($this->once())
            ->method('validate')
            ->with([$sourceItem])
            ->willReturn($validationResult);
        $validationResult->expects($this->once())->method('isValid')->willReturn(true);
        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with('model_save_after', ['object' => $sourceItem]);
        $this->sourceItemsSaveHandler->execute([$sourceItem]);
    }
}
