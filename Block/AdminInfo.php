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

namespace Buckaroo\Magento2\Block;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Buckaroo\Magento2\Service\LogoService;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\ConfigurableInfo;
use Magento\Payment\Gateway\ConfigInterface;

class AdminInfo extends ConfigurableInfo
{
    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var GiftcardCollection
     */
    protected $giftcardCollection;

    /**
     * @var LogoService
     */
    protected $logoService;

    /**
     * @param Context                 $context
     * @param ConfigInterface         $config
     * @param PaymentGroupTransaction $groupTransaction
     * @param GiftcardCollection      $giftcardCollection
     * @param LogoService             $logoService
     * @param array                   $data
     */
    public function __construct(
        Context $context,
        ConfigInterface $config,
        PaymentGroupTransaction $groupTransaction,
        GiftcardCollection $giftcardCollection,
        LogoService $logoService,
        array $data = []
    ) {
        parent::__construct($context, $config, $data);
        $this->groupTransaction = $groupTransaction;
        $this->giftcardCollection = $giftcardCollection;
        $this->logoService = $logoService;
    }

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Buckaroo_Magento2::info/payment_method.phtml');
    }

    /**
     * Get giftcards
     *
     * @throws LocalizedException
     *
     * @return array
     */
    public function getGiftCards()
    {
        $result = [];

        if ($this->getInfo()->getOrder() && $this->getInfo()->getOrder()->getIncrementId()) {
            $items = $this->groupTransaction->getGroupTransactionItems($this->getInfo()->getOrder()->getIncrementId());
            foreach ($items as $giftcard) {
                if ($foundGiftcard = $this->giftcardCollection
                    ->getItemByColumnValue('servicecode', $giftcard['servicecode'])
                ) {
                    $result[] = [
                        'code'  => $giftcard['servicecode'],
                        'label' => $foundGiftcard['label'],
                    ];
                }

                if ($giftcard['servicecode'] == 'buckaroovoucher') {
                    $result[] = [
                        'code'  => $giftcard['servicecode'],
                        'label' => 'Buckaroo Voucher',
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Get PayPerEmail label payment method
     *
     * @throws LocalizedException
     *
     * @return array|false
     */
    public function getPayPerEmailMethod()
    {
        $payment = $this->getInfo()->getOrder()->getPayment();
        if ($payment->getAdditionalInformation('isPayPerEmail')) {
            return [
                'label' => __('Buckaroo PayPerEmail'),
            ];
        }
        return false;
    }

    /**
     * Get payment method logo
     *
     * @param string $method
     *
     * @return string
     */
    public function getPaymentLogo(string $method): string
    {
        return $this->logoService->getPayment($method);
    }

    /**
     * Get giftcard logo url by code
     *
     * @param string $code
     *
     * @return string
     */
    public function getGiftcardLogo(string $code): string
    {
        return $this->logoService->getGiftcardLogo($code);
    }

    /**
     * Get creditcard logo by code
     *
     * @param string $code
     *
     * @return string
     */
    public function getCreditcardLogo(string $code): string
    {
        return $this->logoService->getCreditcard($code);
    }

    /**
     * Get Specific Payment Details set on Success Push to display on Payment Order Information
     *
     * @throws LocalizedException
     *
     * @return array
     */
    public function getSpecificPaymentDetails(): array
    {
        $details = $this->getInfo()->getAdditionalInformation('specific_payment_details');

        if (!$details || !is_array($details)) {
            return [];
        }

        $transformedKeys = array_map([$this, 'getLabel'], array_keys($details));
        $transformedValues = array_map(function ($value) {
            return $this->getValueView('', (string)$value);
        }, $details);

        return array_combine($transformedKeys, $transformedValues);
    }

    /**
     * Prepare information specific to current payment method
     *
     * @param null|DataObject|array $transport
     *
     * @throws LocalizedException
     *
     * @return DataObject
     */
    protected function _prepareSpecificInformation($transport = null): DataObject
    {
        $transport = parent::_prepareSpecificInformation($transport);
        if ($transferDetails = $this->getInfo()->getAdditionalInformation('transfer_details')) {
            foreach ($transferDetails as $key => $transferDetail) {
                $transport->setData(
                    (string)$this->getLabel($key),
                    $this->getValueView($key, $transferDetail)
                );
            }
        }
        return $transport;
    }

    /**
     * Returns label
     *
     * @param string $field
     *
     * @return Phrase
     */
    protected function getLabel($field)
    {
        $words = explode('_', $field);
        $transformedWords = array_map('ucfirst', $words);
        return __(implode(' ', $transformedWords));
    }

    /**
     * Sets data to transport
     *
     * @param DataObject   $transport
     * @param string       $field
     * @param string|array $value
     */
    protected function setDataToTransfer(
        DataObject $transport,
        $field,
        $value
    ) {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $transport->setData(
                    (string)$this->getLabel($key),
                    (string)$this->getValueView(
                        $key,
                        $val
                    )
                );
            }
        } else {
            $transport->setData(
                (string)$this->getLabel($field),
                (string)$this->getValueView(
                    $field,
                    $value
                )
            );
        }
    }
}
