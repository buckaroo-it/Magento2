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

namespace Buckaroo\Magento2\Plugin;

use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartSearchResultsInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class QuoteRepositoryPlugin
{
    /**
     * @var array|string[]
     */
    private array $buckarooFieldNames = [
        'buckaroo_fee',
        'base_buckaroo_fee',
        'buckaroo_fee_tax_amount',
        'buckaroo_fee_base_tax_amount',
        'buckaroo_fee_incl_tax',
        'base_buckaroo_fee_incl_tax',
        'buckaroo_already_paid',
        'base_buckaroo_already_paid'
    ];

    /**
     * @var CartExtensionFactory
     */
    private CartExtensionFactory $extensionFactory;

    /**
     * @param CartExtensionFactory $extensionFactory
     */
    public function __construct(CartExtensionFactory $extensionFactory)
    {
        $this->extensionFactory = $extensionFactory;
    }

    /**
     * Adds custom Buckaroo fields to each quote's extension attributes.
     *
     * @param CartRepositoryInterface $subject
     * @param CartSearchResultsInterface $searchResult
     * @return CartSearchResultsInterface
     */
    public function afterGetList(
        CartRepositoryInterface $subject,
        CartSearchResultsInterface $searchResult
    ): CartSearchResultsInterface {
        $quotes = $searchResult->getItems();

        foreach ($quotes as $quote) {
            $this->afterGet($subject, $quote);
        }

        return $searchResult;
    }

    /**
     * Adds custom Buckaroo fields to the quote's extension attributes.
     *
     * @param CartRepositoryInterface $subject
     * @param CartInterface $quote
     * @return CartInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(CartRepositoryInterface $subject, CartInterface $quote): CartInterface
    {
        $extensionAttributes = $quote->getExtensionAttributes();

        if (!$extensionAttributes) {
            $extensionAttributes = $this->extensionFactory->create();
        }

        foreach ($this->buckarooFieldNames as $fieldName) {
            $fieldValue = $quote->getData($fieldName);
            $setterMethod = 'set' . str_replace('_', '', ucwords($fieldName, '_'));
            if (method_exists($extensionAttributes, $setterMethod)) {
                $extensionAttributes->$setterMethod($fieldValue);
            }
        }

        $quote->setExtensionAttributes($extensionAttributes);

        return $quote;
    }

    /**
     * Save extension attributes data to quote before saving
     *
     * @param CartRepositoryInterface $subject
     * @param CartInterface $quote
     * @return array
     */
    public function beforeSave(CartRepositoryInterface $subject, CartInterface $quote): array
    {
        $extensionAttributes = $quote->getExtensionAttributes();
        
        if ($extensionAttributes) {
            foreach ($this->buckarooFieldNames as $fieldName) {
                $getterMethod = 'get' . str_replace('_', '', ucwords($fieldName, '_'));
                if (method_exists($extensionAttributes, $getterMethod)) {
                    $fieldValue = $extensionAttributes->$getterMethod();
                    if ($fieldValue !== null) {
                        $quote->setData($fieldName, $fieldValue);
                    }
                }
            }
        }

        return [$quote];
    }
} 