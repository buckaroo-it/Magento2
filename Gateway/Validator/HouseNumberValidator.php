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

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Service\Formatter\Address\StreetFormatter;
use Magento\Framework\Phrase;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order\Payment as OrderPayment;

class HouseNumberValidator extends AbstractValidator
{
    /**
     * @var StreetFormatter
     */
    private $streetFormatter;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param StreetFormatter        $streetFormatter
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        StreetFormatter $streetFormatter
    ) {
        $this->streetFormatter = $streetFormatter;
        parent::__construct($resultFactory);
    }

    /**
     * Validate country
     *
     * @param  array           $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        /** @var QuotePayment|OrderPayment $paymentInfo */
        $paymentInfo = $validationSubject['payment'];
        if ($paymentInfo instanceof QuotePayment) {
            $quote = $paymentInfo->getQuote();

            try {
                $this->validateHouseNumber($quote->getBillingAddress());
                $this->validateHouseNumber($quote->getShippingAddress());
            } catch (Exception $exception) {
                return $this->createResult(false, [$exception->getMessage()]);
            }
        }

        return $this->createResult(true);
    }

    /**
     * @param  Address   $address
     * @throws Exception
     */
    private function validateHouseNumber(Address $address): void
    {
        $streetFormat = $this->streetFormatter->format($address->getStreet());

        if ($address->getCountryId() !== "DE") {
            return;
        }

        if (empty(trim($streetFormat['house_number']))
            || !is_string($streetFormat['house_number'])
        ) {
            throw new Exception(
                new Phrase(
                    'A valid address is required, cannot find street number'
                )
            );
        }
    }
}
