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

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface as BuckarooLog;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Pospayment as PospaymentConfig;
use Magento\Framework\HTTP\Header;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order\Payment as OrderPayment;

class PosPaymentValidator extends AbstractValidator
{
    /**
     * @var Header
     */
    private $header;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var BuckarooLog
     */
    private $buckarooLog;

    /**
     * @var PospaymentConfig
     */
    private $pospaymentConfig;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param CookieManagerInterface $cookieManager
     * @param BuckarooLog            $buckarooLog
     * @param Header                 $header
     * @param PospaymentConfig       $pospaymentConfig
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        CookieManagerInterface $cookieManager,
        BuckarooLog $buckarooLog,
        Header $header,
        PospaymentConfig $pospaymentConfig
    ) {
        $this->header = $header;
        $this->cookieManager = $cookieManager;
        $this->buckarooLog = $buckarooLog;
        $this->pospaymentConfig = $pospaymentConfig;
        parent::__construct($resultFactory);
    }

    /**
     * Validate POS payment method
     *
     * Runs both as part of the method's buckaroo_availability check (subject: quote) and as
     * Magento's availability check once a payment info instance is set (subject: payment).
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $quote = $validationSubject['quote'] ?? null;
        $paymentDO = $validationSubject['payment'] ?? null;
        if ($quote instanceof CartInterface) {
            $storeId = (int)$quote->getStoreId();
        } elseif ($paymentDO instanceof PaymentDataObjectInterface) {
            $storeId = $this->getStoreId($paymentDO);
        } else {
            return $this->createResult(false, [__('Payment method instance does not exist')]);
        }

        $errorMessages = [];

        if (!$this->getPosPaymentTerminalId()) {
            $errorMessages[] = __('POS Terminal Id it is not set.');
        }

        if (!$this->isUserAgentAllowed($storeId)) {
            $errorMessages[] = __('Wrong User Agent configuration');
        }

        return $this->createResult(empty($errorMessages), $errorMessages);
    }

    /**
     * Get the POS Payment Terminal ID
     *
     * @return string|null
     */
    private function getPosPaymentTerminalId(): ?string
    {
        $terminalId = $this->cookieManager->getCookie('Pos-Terminal-Id');
        $this->buckarooLog->addDebug(__METHOD__ . '|1|');
        $this->buckarooLog->addDebug(var_export($terminalId, true));
        return $terminalId;
    }

    /**
     * Check the request user agent against the user agent configured for the store
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    private function isUserAgentAllowed(?int $storeId): bool
    {
        $userAgentConfiguration = $this->pospaymentConfig->getUserAgent($storeId);
        if ($userAgentConfiguration === '') {
            return true;
        }

        $userAgent = (string)$this->header->getHttpUserAgent();
        $this->buckarooLog->addDebug(var_export([$userAgent, $userAgentConfiguration], true));

        return $userAgent === $userAgentConfiguration;
    }

    /**
     * Get the store id of the quote or order the payment belongs to
     *
     * @param PaymentDataObjectInterface $paymentDO
     *
     * @return int|null
     */
    private function getStoreId(PaymentDataObjectInterface $paymentDO): ?int
    {
        $payment = $paymentDO->getPayment();
        if ($payment instanceof QuotePayment && $payment->getQuote()) {
            return (int)$payment->getQuote()->getStoreId();
        }

        if ($payment instanceof OrderPayment && $payment->getOrder()) {
            return (int)$payment->getOrder()->getStoreId();
        }

        return null;
    }
}
