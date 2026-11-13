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

namespace Buckaroo\Magento2\Model\PartialPayment\Instrument;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Voucher as VoucherConfig;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentRejectedException;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote;

/**
 * A Buckaroo voucher code
 */
class Voucher implements InstrumentInterface
{
    /**
     * The gateway puts this in front of the reason a voucher action failed, e.g.
     * "Voucher action failed:Insufficient voucher balance". Only this prefix is removed, so a
     * reason that contains a colon of its own is shown in full.
     */
    private const GATEWAY_MESSAGE_PREFIX = '/^\s*Voucher action failed\s*:\s*/i';

    /**
     * @var string
     */
    private $code;

    /**
     * @param string $code
     */
    public function __construct(string $code)
    {
        $this->code = trim($code);
    }

    /**
     * @inheritdoc
     */
    public function getMethodCode(): string
    {
        return VoucherConfig::CODE;
    }

    /**
     * @inheritdoc
     */
    public function getServiceCode(): string
    {
        return VoucherConfig::SERVICE_CODE;
    }

    /**
     * @inheritdoc
     */
    public function validate(Quote $quote): void
    {
        if ($this->code === '') {
            throw new PartialPaymentRejectedException(__('Please enter a voucher code.'));
        }
    }

    /**
     * @inheritdoc
     */
    public function getRequestFields(Quote $quote): array
    {
        return ['vouchercode' => $this->code];
    }

    /**
     * @inheritdoc
     */
    public function getFallbackRejectionMessage(): Phrase
    {
        return __('The voucher could not be redeemed. Please check the code and try again.');
    }

    /**
     * @inheritdoc
     */
    public function formatRejectionMessage(string $message): string
    {
        return trim((string)preg_replace(self::GATEWAY_MESSAGE_PREFIX, '', $message, 1));
    }
}
