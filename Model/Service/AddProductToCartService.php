<?php

namespace Buckaroo\Magento2\Model\Service;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Framework\DataObjectFactory;

class AddProductToCartService
{
    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var DataObjectFactory
     */
    private DataObjectFactory $dataObjectFactory;

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param ProductRepositoryInterface $productRepository
     * @param DataObjectFactory $dataObjectFactory
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        ProductRepositoryInterface $productRepository,
        DataObjectFactory $dataObjectFactory,
    ) {
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * Add Product Selected to cart
     *
     * @param DataObject $product
     * @param Quote $cart
     * @return Quote
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function addProductToCart(DataObject $product, Quote $cart): Quote
    {
        $productId = $product->getData('product');

        try {
            $productToBeAdded = $this->productRepository->getById($productId);
        } catch (NoSuchEntityException $e) {
            throw new NoSuchEntityException(
                __('Could not find a product with ID "%id"', ['id' => $product['id']])
            );
        }

        $cart->addProduct($productToBeAdded, $product);
        $this->cartRepository->save($cart);

        return $cart;
    }

    /**
     * Add product to quote
     *
     * @param DataObject $productDataObject
     * @param Quote $cart
     * @return Quote
     * @throws NoSuchEntityException
     * @throws AddProductException
     * @throws LocalizedException
     */
    protected function addProduct($productDataObject, $cart): Quote
    {
        $productId = $productDataObject->getData('product');
        if ($productId === null) {
            throw new AddProductException("A product is required", 1);
        }
        try {
            $product = $this->productRepository->getById($productId);
        } catch (NoSuchEntityException $e) {
            throw new NoSuchEntityException(__('Could not find a product with ID "%id"', ['id' => $productId]));
        }

        $cart->addProduct($product, $productDataObject);

        return $cart;
    }

    /**
     * Convert array into Data Object
     *
     * @param array $productArray
     * @return DataObject
     */
    private function convertArrayIntoDataObject(array $productArray): DataObject
    {
        $buyRequest = $this->dataObjectFactory->create(
            ['data' => [
                'product' => $productArray['id'],
                'selected_configurable_option' => '',
                'related_product' => '',
                'item' => $productArray['id'],
                'super_attribute' => $productArray['selected_options'] ?? '',
                'qty' => $productArray['qty'],
            ]]
        );

        // OR

        $data = [];
        foreach ($productArray as $productKeyValue) {
            $data[$productKeyValue->getName()] = $productKeyValue->getValue();
        }
        $dataObject = $this->dataObjectFactory->create();

        return $dataObject->setData($data);
    }
}
