<?php

namespace Buckaroo\Magento2\Model\Service;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

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
        DataObjectFactory $dataObjectFactory
    ) {
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * Add Product Selected to cart.
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
}
