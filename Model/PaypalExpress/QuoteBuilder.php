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

namespace Buckaroo\Magento2\Model\PaypalExpress;

use Magento\Customer\Model\Group;
use Magento\Quote\Model\QuoteFactory;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\DataObjectFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Buckaroo\Magento2\Model\PaypalExpress\QuoteBuilderInterface;
use Buckaroo\Magento2\Model\PaypalExpress\PaypalExpressException;

class QuoteBuilder implements QuoteBuilderInterface
{

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var \Magento\Framework\DataObjectFactory
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
        QuoteFactory $quoteFactory,
        ProductRepositoryInterface $productRepository,
        DataObjectFactory $dataObjectFactory,
        CustomerSession $customer
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->productRepository = $productRepository;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->customer = $customer;
    }

    public function setFormData(array $formData)
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
        $this->addProduct();
        $this->setUser();
        return $this->quote;
    }

    /**
     * Add user to quote
     *
     * @return void
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
     * @return void
     * @throws \Buckaroo\Magento2\Model\PaypalExpress\PaypalExpressException
     */
    protected function addProduct()
    {
        $productId = $this->formData->getData('product');
        if ($productId === null) {
            throw new PaypalExpressException("A product is required", 1);
        }
        $product = $this->productRepository->getById($productId);
        $this->quote->addProduct($product, $this->formData);
    }
    /**
     * Format form data
     *
     * @param array $form_data
     *
     * @return \Magento\Framework\DataObject
     */
    protected function formatFormData(array $form_data)
    {
        $data = [];

        foreach ($form_data as $orderKeyValue) {
            $data[$orderKeyValue->getName()] = $orderKeyValue->getValue();
        }
        $dataObject = $this->dataObjectFactory->create();

        return $dataObject->setData($data);
    }
}
