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
