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

namespace Buckaroo\Magento2\Model\Voucher;

use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ApplyVoucherRequest implements ApplyVoucherRequestInterface
{
    /**
     * @var StoreInterface
     */
    protected $store;

    /**
     * @var CartInterface
     */
    protected $quote;

    /**
     * @var string
     */
    protected $voucherCode;

    /**
     * @var CommandPoolInterface
     */
    private CommandPoolInterface $commandPool;

    /**
     * @var PaymentDataObjectFactory
     */
    private PaymentDataObjectFactory $paymentDataObjectFactory;

    /**
     * @param StoreManagerInterface $storeManager
     * @param CommandPoolInterface $commandPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @throws NoSuchEntityException
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CommandPoolInterface $commandPool,
        PaymentDataObjectFactory $paymentDataObjectFactory
    ) {
        $this->store = $storeManager->getStore();
        $this->commandPool = $commandPool;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
    }

    /**
     * Send voucher request
     *
     * @return mixed
     * @throws GiftcardException
     * @throws \Exception
     */
    public function send()
    {
        if ($this->voucherCode === null) {
            throw new GiftcardException("Field `voucherCode` is required");
        }

        if ($this->quote === null) {
            throw new GiftcardException("Quote is required");
        }

        try {
            // Create a mock payment object for the data object factory
            $payment = $this->createMockPayment();

            $command = $this->commandPool->get('voucher_apply');
            $command->execute([
                'payment' => $this->paymentDataObjectFactory->create($payment),
                'amount' => $this->quote->getGrandTotal()
            ]);

            // The command execution will handle the response through response handlers
            // For now, return a success indicator
            return ['status' => 'success'];
        } catch (CommandException $e) {
            throw new GiftcardException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a mock payment object with voucher data
     *
     * @return InfoInterface
     */
    private function createMockPayment(): InfoInterface
    {
        /** @var Payment $payment */
        $payment = $this->quote->getPayment();

        // Set voucher code in additional information
        $payment->setAdditionalInformation('voucher_code', $this->voucherCode);

        return $payment;
    }

    /**
     * Set voucherCode
     *
     * @param string $voucherCode
     * @return ApplyVoucherRequestInterface
     */
    public function setVoucherCode(string $voucherCode): ApplyVoucherRequestInterface
    {
        $this->voucherCode = trim($voucherCode);
        return $this;
    }

    /**
     * Set quote
     *
     * @param CartInterface $quote
     * @return ApplyVoucherRequestInterface
     */
    public function setQuote(CartInterface $quote): ApplyVoucherRequestInterface
    {
        $this->quote = $quote;
        return $this;
    }
}
