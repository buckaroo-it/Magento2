<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\ErrorMapper;

use Buckaroo\Magento2\Gateway\ErrorMapper\ErrorMessageMapper;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\Config\DataInterface;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class ErrorMessageMapperTest extends BaseTest
{
    /**
     * @var DataInterface|MockObject
     */
    private $messageMapping;

    /**
     * @var ErrorMessageMapper
     */
    private $mapper;

    public function setUp(): void
    {
        parent::setUp();

        $this->messageMapping = $this->createMock(DataInterface::class);
        $this->mapper = new ErrorMessageMapper($this->messageMapping);
    }

    public function testShortCodeIsResolvedThroughConfiguredMapping(): void
    {
        $this->messageMapping->expects($this->once())
            ->method('get')
            ->with('491')
            ->willReturn('Invalid parameter supplied');

        $message = $this->mapper->getMessage('491');

        $this->assertInstanceOf(Phrase::class, $message);
        $this->assertSame('Invalid parameter supplied', (string)$message);
    }

    public function testCodeLongerThanFourCharactersBypassesMappingAndIsReturnedVerbatim(): void
    {
        $this->messageMapping->expects($this->never())->method('get');

        $rawGatewayMessage = 'Card number is invalid';

        $message = $this->mapper->getMessage($rawGatewayMessage);

        $this->assertInstanceOf(Phrase::class, $message);
        $this->assertSame($rawGatewayMessage, (string)$message);
    }

    public function testExactlyFourCharacterCodeStillConsultsMapping(): void
    {
        $this->messageMapping->expects($this->once())
            ->method('get')
            ->with('F103')
            ->willReturn('Invalid bank account number');

        $this->assertSame('Invalid bank account number', (string)$this->mapper->getMessage('F103'));
    }

    public function testFiveCharacterCodeBypassesMappingBoundary(): void
    {
        $this->messageMapping->expects($this->never())->method('get');

        $this->assertSame('F1035', (string)$this->mapper->getMessage('F1035'));
    }

    /**
     * @param mixed $mappingResult
     */
    #[DataProvider('unmappedResultProvider')]
    public function testUnmappedShortCodeReturnsNull($mappingResult): void
    {
        $this->messageMapping->expects($this->once())
            ->method('get')
            ->with('490')
            ->willReturn($mappingResult);

        $this->assertNull($this->mapper->getMessage('490'));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function unmappedResultProvider(): array
    {
        return [
            'mapping returns null' => [null],
            'mapping returns empty string' => [''],
        ];
    }
}
