<?php

namespace Buckaroo\Magento2\Service\Applepay;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class AddProduct
{
    private CartRepositoryInterface $cartRepository;
    private ProductRepositoryInterface $productRepository;
    private DataObjectFactory $dataObjectFactory;

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
        $this->productRepository = $productRepository;
        $this->cartRepository = $cartRepository;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * Add Product Selected to cart
     *
     * @param array $product
     * @param Quote $cart
     * @return Quote
     * @throws NoSuchEntityException|LocalizedException
     */
    public function addProductToCart($product, $cart): Quote
    {
        try {
            $productToBeAdded = $this->productRepository->getById($product['id']);
        } catch (NoSuchEntityException $e) {
            throw new NoSuchEntityException(__('Could not find a product with ID "%id"', ['id' => $product['id']]));
        }

        $buyRequest = $this->dataObjectFactory->create(
            ['data' => [
                'product' => $product['id'],
                'selected_configurable_option' => '',
                'related_product' => '',
                'item' => $product['id'],
                'super_attribute' => $product['selected_options'] ?? '',
                'qty' => $product['qty'],
            ]]
        );

        $cart->addProduct($productToBeAdded, $buyRequest);
        $this->cartRepository->save($cart);

        return $cart;
    }
}
