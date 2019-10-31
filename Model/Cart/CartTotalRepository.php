<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * fix for Magento 2.2.2: https://github.com/magento/magento2/issues/9646
 * Based on: https://github.com/ekuusela/magento2/commit/aa535ea5d4bf78915bddd4387a9d3c4b39943eea
 */
namespace TIG\Buckaroo\Model\Cart;

use Magento\Quote\Api;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Quote\Model\Cart\Totals\ItemConverter;
use Magento\Quote\Api\CouponManagementInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Quote\Model\Cart\TotalsConverter;
use Magento\Quote\Model\Cart\Totals;
use Magento\Quote\Model\Cart\CartTotalRepository as MagentoTotalRepository;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * Cart totals data object.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CartTotalRepository extends MagentoTotalRepository
{
    /**
     * Cart totals factory.
     *
     * @var Api\Data\TotalsInterfaceFactory
     */
    private $totalsFactory;

    /**
     * Quote repository.
     *
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var \Magento\Framework\Api\DataObjectHelper
     */
    private $dataObjectHelper;

    /**
     * @var ConfigurationPool
     */
    private $itemConverter;

    /**
     * @var CouponManagementInterface
     */
    protected $couponService;

    /**
     * @var \Magento\Quote\Model\Cart\TotalsConverter
     */
    protected $totalsConverter;

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @param Api\Data\TotalsInterfaceFactory $totalsFactory
     * @param CartRepositoryInterface $quoteRepository
     * @param DataObjectHelper $dataObjectHelper
     * @param CouponManagementInterface $couponService
     * @param TotalsConverter $totalsConverter
     * @param ItemConverter $converter
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        Api\Data\TotalsInterfaceFactory $totalsFactory,
        CartRepositoryInterface $quoteRepository,
        DataObjectHelper $dataObjectHelper,
        CouponManagementInterface $couponService,
        TotalsConverter $totalsConverter,
        ItemConverter $converter,
        ProductMetadataInterface $productMetadata
    ) {
        parent::__construct(
            $totalsFactory,
            $quoteRepository,
            $dataObjectHelper,
            $couponService,
            $totalsConverter,
            $converter
        );

        $this->totalsFactory = $totalsFactory;
        $this->quoteRepository = $quoteRepository;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->couponService = $couponService;
        $this->totalsConverter = $totalsConverter;
        $this->itemConverter = $converter;
        $this->productMetadata = $productMetadata;
    }

    /**
     * {@inheritDoc}
     *
     * @param int $cartId The cart ID.
     * @return Totals Quote totals data.
     */
    public function get($cartId)
    {
        if (!$this->isCorrectMagentoVersion()) {
            return parent::get($cartId);
        }

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->quoteRepository->getActive($cartId);
        if ($quote->isVirtual()) {
            $addressTotalsData = $quote->getBillingAddress()->getData();
            $addressTotals = $quote->getBillingAddress()->getTotals();
        } else {
            $addressTotalsData = $quote->getShippingAddress()->getData();
            $addressTotals = $quote->getShippingAddress()->getTotals();
        }

        // Rewrite start
        unset($addressTotalsData[ExtensibleDataInterface::EXTENSION_ATTRIBUTES_KEY]);
        // Rewrite end

        /** @var \Magento\Quote\Api\Data\TotalsInterface $quoteTotals */
        $quoteTotals = $this->totalsFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $quoteTotals,
            $addressTotalsData,
            \Magento\Quote\Api\Data\TotalsInterface::class
        );
        $items = [];
        foreach ($quote->getAllVisibleItems() as $index => $item) {
            $items[$index] = $this->itemConverter->modelToDataObject($item);
        }
        $calculatedTotals = $this->totalsConverter->process($addressTotals);
        $quoteTotals->setTotalSegments($calculatedTotals);

        $amount = $quoteTotals->getGrandTotal() - $quoteTotals->getTaxAmount();
        $amount = $amount > 0 ? $amount : 0;
        $quoteTotals->setCouponCode($this->couponService->get($cartId));
        $quoteTotals->setGrandTotal($amount);
        $quoteTotals->setItems($items);
        $quoteTotals->setItemsQty($quote->getItemsQty());
        $quoteTotals->setBaseCurrencyCode($quote->getBaseCurrencyCode());
        $quoteTotals->setQuoteCurrencyCode($quote->getQuoteCurrencyCode());
        return $quoteTotals;
    }

    /**
     * Overwrite only version 2.2.2
     *
     * @return bool
     */
    private function isCorrectMagentoVersion()
    {
        $currentVersion = $this->productMetadata->getVersion();

        if (in_array($currentVersion, ['2.2.2', '2.2.3'])) {
            return true;
        }

        return false;
    }
}
