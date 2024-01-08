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

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\Data;
use Magento\Framework\Exception\LocalizedException;

class BillinkDataBuilder extends AbstractRecipientDataBuilder
{
    /**
     * @var Data
     */
    public Data $helper;

    /**
     * @param Data $helper
     * @param string $addressType
     */
    public function __construct(Data $helper, string $addressType = 'billing')
    {
        parent::__construct($addressType);
        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    protected function buildData(): array
    {
        $category = $this->getCategory();

        $data = [
            'category'  => $category,
            'careOf'    => $this->getCareOf(),
            'title'     => $this->getGender(),
            'initials'  => $this->getInitials(),
            'firstName' => $this->getFirstname(),
            'lastName'  => $this->getLastName(),
            'birthDate' => $this->getBirthDate()
        ];

        if ($category == 'B2B') {
            $data['chamberOfCommerce'] = $this->getChamberOfCommerce();
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    protected function getCategory(): string
    {
        try {
            return $this->helper->checkCustomerGroup('buckaroo_magento2_billink') ? 'B2B' : 'B2C';
        } catch (Exception $e) {
            return 'B2C';
        }
    }

    /**
     * @inheritdoc
     */
    protected function getCareOf(): string
    {
        return $this->getFirstname() . ' ' . $this->getLastName();
    }

    /**
     * @inheritdoc
     */
    protected function getGender(): string
    {
        if ($this->payment->getAdditionalInformation('customer_gender') === '1') {
            return 'Male';
        } elseif ($this->payment->getAdditionalInformation('customer_gender') === '2') {
            return 'Female';
        } else {
            return 'Unknown';
        }
    }
}
