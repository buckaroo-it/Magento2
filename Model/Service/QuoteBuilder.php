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

namespace Buckaroo\Magento2\Model\Service;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;

class QuoteBuilder implements QuoteBuilderInterface
{
    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var DataObjectFactory
     */
    protected $productRepository;

    /**
     * @var DataObjectFactory
     */
    protected $dataObjectFactory;

    /**
     * @var CustomerSession
     */
    protected $customer;

    /**
     * @var DataObject|null
     */
    protected $formData;

    /**
     * @var Quote
     */
    protected $quote;

    /**
     * @param QuoteFactory $quoteFactory
     * @param ProductRepositoryInterface $productRepository
     * @param DataObjectFactory $dataObjectFactory
     * @param CustomerSession $customer
     */
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

    /**
     * @inheritdoc
     */
    public function setFormData(array $formData): void
    {
        $this->formData = $this->formatFormData($formData);
    }

    /**
     * Format form data
     *
     * @param array $formData
     *
     * @return DataObject
     */
    protected function formatFormData(array $formData)
    {
        $data = [];

        foreach ($formData as $orderKeyValue) {
            $data[$orderKeyValue->getName()] = $orderKeyValue->getValue();
        }
        $dataObject = $this->dataObjectFactory->create();

        return $dataObject->setData($data);
    }

    /**
     * @inheritdoc
     */
    public function build(): Quote
    {
        $this->quote = $this->quoteFactory->create();
        $this->addProduct();
        $this->setUser();
        return $this->quote;
    }

    /**
     * Add product to quote
     *
     * @return void
     * @throws AddProductException
     * @throws LocalizedException
     */
    protected function addProduct()
    {
        $productId = $this->formData->getData('product');
        if ($productId === null) {
            throw new AddProductException("Product ID is required.", 1);
        }
        $product = $this->productRepository->getById($productId);
        $this->quote->addProduct($product, $this->formData);
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
            $this->quote->setCustomerId(0)
                ->setCustomerIsGuest(true)
                ->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
        }
    }
}
