<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Service\Push;

use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Service\Push\KlarnaMorDataRequestPushDetector;
use Buckaroo\Magento2\Test\Unit\Model\Refund\PushRequestStub;
use PHPUnit\Framework\TestCase;

class KlarnaMorDataRequestPushDetectorTest extends TestCase
{
    /**
     * @var KlarnaMorDataRequestPushDetector
     */
    private KlarnaMorDataRequestPushDetector $detector;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->detector = new KlarnaMorDataRequestPushDetector();
    }

    public function testAcknowledgesPlazaKlarnaDataRequestWithoutOrderReference(): void
    {
        $pushRequest = $this->createPlazaPushRequest();

        $this->assertTrue($this->detector->shouldAcknowledgeWithoutOrder($pushRequest));
    }

    public function testDoesNotAcknowledgeMagentoInitiatedPush(): void
    {
        $pushRequest = $this->createPlazaPushRequest();
        $pushRequest->setAdditionalInformation('initiated_by_magento', '1');

        $this->assertFalse($this->detector->shouldAcknowledgeWithoutOrder($pushRequest));
    }

    public function testDoesNotAcknowledgeWhenOrderNumberIsPresent(): void
    {
        $pushRequest = $this->createPlazaPushRequest();
        $pushRequest->setOrderNumber('000000123');

        $this->assertFalse($this->detector->shouldAcknowledgeWithoutOrder($pushRequest));
    }

    private function createPlazaPushRequest(): KlarnaMorPushRequestStub
    {
        $pushRequest = new KlarnaMorPushRequestStub();
        $pushRequest->setDatarequest('DD0A97CDF9F84ADD88CA923278A16C5D');
        $pushRequest->setStatusCode((string)BuckarooStatusCode::SUCCESS);
        $pushRequest->setPrimaryService('klarna');

        return $pushRequest;
    }
}

/**
 * Configurable push request stub for Klarna MOR detector tests.
 */
class KlarnaMorPushRequestStub extends PushRequestStub
{
    /**
     * @var string|null
     */
    private ?string $datarequest = null;

    /**
     * @var string|null
     */
    private ?string $orderNumber = null;

    /**
     * @var string|null
     */
    private ?string $invoiceNumber = null;

    /**
     * @var string|null
     */
    private ?string $statusCode = null;

    /**
     * @var string|null
     */
    private ?string $transactionMethod = null;

    /**
     * @var string|null
     */
    private ?string $primaryService = null;

    /**
     * @var array<string, string|null>
     */
    private array $additionalInformation = [];

    /**
     * @param string|null $datarequest
     *
     * @return $this
     */
    public function setDatarequest(?string $datarequest): self
    {
        $this->datarequest = $datarequest;

        return $this;
    }

    /**
     * @param string|null $orderNumber
     *
     * @return $this
     */
    public function setOrderNumber(?string $orderNumber): self
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    /**
     * @param string|null $invoiceNumber
     *
     * @return $this
     */
    public function setInvoiceNumber(?string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }

    /**
     * @param string|null $statusCode
     *
     * @return $this
     */
    public function setStatusCode(?string $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * @param string|null $transactionMethod
     *
     * @return $this
     */
    public function setTransactionMethod(?string $transactionMethod): self
    {
        $this->transactionMethod = $transactionMethod;

        return $this;
    }

    /**
     * @param string|null $primaryService
     *
     * @return $this
     */
    public function setPrimaryService(?string $primaryService): self
    {
        $this->primaryService = $primaryService;

        return $this;
    }

    /**
     * @param string      $key
     * @param string|null $value
     *
     * @return $this
     */
    public function setAdditionalInformation(string $key, ?string $value): self
    {
        $this->additionalInformation[$key] = $value;

        return $this;
    }

    /**
     * @param string $property
     *
     * @return string|null
     */
    public function get(string $property): ?string
    {
        if ($property === 'PrimaryService') {
            return $this->primaryService;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getDatarequest(): ?string
    {
        return $this->datarequest;
    }

    /**
     * @inheritdoc
     */
    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    /**
     * @inheritdoc
     */
    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    /**
     * @inheritdoc
     */
    public function getStatusCode(): ?string
    {
        return $this->statusCode;
    }

    /**
     * @inheritdoc
     */
    public function getTransactionMethod(): ?string
    {
        return $this->transactionMethod;
    }

    /**
     * @inheritdoc
     */
    public function getAdditionalInformation(string $propertyName): ?string
    {
        return $this->additionalInformation[$propertyName] ?? null;
    }
}
