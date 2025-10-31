<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Model\Ideal;

use Magento\Customer\Model\Group;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\DataObjectFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;

class QuoteBuilder implements QuoteBuilderInterface
{

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    protected $dataObjectFactory;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customer;

    /**
     * @var Magento\Framework\DataObject|null
     */
    protected $formData;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $quote;

    public function __construct(
        QuoteFactory               $quoteFactory,
        ProductRepositoryInterface $productRepository,
        DataObjectFactory          $dataObjectFactory,
        CustomerSession            $customer
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->productRepository = $productRepository;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->customer = $customer;
    }

    /** @inheritDoc */
    public function setFormData(string $formData)
    {
        $this->formData = $this->formatFormData($formData);
    }

    /**
     * Build quote from form data and session without persisting it
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function build()
    {
        $this->quote = $this->quoteFactory->create();
        $this->quote->setIsCheckoutCart(true);
        $this->addProduct();
        $this->setUser();
        return $this->quote;
    }

    /**
     * Add user to quote
     */
    protected function setUser()
    {
        if ($this->customer->isLoggedIn()) {
            $this->quote->setCustomerId(
                $this->customer->getCustomerId()
            );
            $this->quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
        } else {
            $this->quote->setCustomerId(null)
                ->setCustomerIsGuest(true)
                ->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
        }
    }

    /**
     * Add product to quote
     *
     * @throws IdealException
     */
    protected function addProduct()
    {
        $productId = $this->formData->getData('product');
        if ($productId === null) {
            throw new IdealException("A product is required", 1);
        }

        try {
            $product = $this->productRepository->getById($productId);
            $item = $this->quote->addProduct($product, $this->formData);

            if (!$item instanceof Item) {
                $exceptionMessage = "Cannot add product to cart";
                if (is_string($item)) {
                    $exceptionMessage = $item;
                }
                throw new IdealException($exceptionMessage, 1);
            }
        } catch (\Exception $e) {
            throw new IdealException("Failed to add product to quote: " . $e->getMessage(), 1);
        }
    }

    /**
     * Format form data
     *
     * @param string $form_data
     *
     * @return \Magento\Framework\DataObject
     */
    protected function formatFormData(string $form_data)
    {
        $data = [];
        parse_str($form_data, $data);
        $dataObject = $this->dataObjectFactory->create();

        return $dataObject->setData($data);
    }
}
