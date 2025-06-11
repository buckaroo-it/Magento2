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
declare(strict_types=1);

namespace Buckaroo\Magento2\Service\ExpressPayment;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Phrase;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;

class ProductValidationService
{
    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var StockRegistryInterface
     */
    private StockRegistryInterface $stockRegistry;

    /**
     * @var ModuleManager
     */
    private ModuleManager $moduleManager;

    /**
     * @var GetSalableQuantityDataBySku|null
     */
    private $getSalableQuantityDataBySku;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param StockRegistryInterface $stockRegistry
     * @param ModuleManager $moduleManager
     * @param GetSalableQuantityDataBySku|null $getSalableQuantityDataBySku
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistry,
        ModuleManager $moduleManager,
        $getSalableQuantityDataBySku = null
    ) {
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->moduleManager = $moduleManager;
        $this->getSalableQuantityDataBySku = $getSalableQuantityDataBySku;
    }

    /**
     * Validate product for express checkout
     *
     * @param int $productId
     * @param array $options
     * @param float $qty
     * @return array
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function validateProduct(int $productId, array $options = [], float $qty = 1): array
    {
        $result = [
            'is_valid' => true,
            'errors' => [],
            'selected_product' => null
        ];

        try {
            $product = $this->productRepository->getById($productId);

            // Check if product is enabled and available for sale
            if (!$product->getStatus() || $product->getStatus() != Status::STATUS_ENABLED) {
                $result['is_valid'] = false;
                $result['errors'][] = new Phrase('Product is not available for purchase.');
                return $result;
            }

            // Check product type and validate accordingly
            switch ($product->getTypeId()) {
                case Type::TYPE_SIMPLE:
                    $result = $this->validateSimpleProduct($product, $qty, $result);
                    break;

                case Configurable::TYPE_CODE:
                    $result = $this->validateConfigurableProduct($product, $options, $qty, $result);
                    break;

                default:
                    $result['is_valid'] = false;
                    $result['errors'][] = __('Product type "%1" is not supported for express checkout.', $product->getTypeId());
                    break;
            }

        } catch (NoSuchEntityException $e) {
            $result['is_valid'] = false;
            $result['errors'][] = __('Product not found.');
        } catch (\Exception $e) {
            $result['is_valid'] = false;
            $result['errors'][] = __('Error validating product: %1', $e->getMessage());
        }

        return $result;
    }

    /**
     * Validate simple product
     *
     * @param ProductInterface $product
     * @param float $qty
     * @param array $result
     * @return array
     */
    private function validateSimpleProduct(ProductInterface $product, float $qty, array $result): array
    {
        // Check stock
        $stockResult = $this->checkProductStock($product, $qty);
        if (!$stockResult['is_in_stock']) {
            $result['is_valid'] = false;
            $result['errors'] = array_merge($result['errors'], $stockResult['errors']);
        }

        // Check required custom options
        $optionsResult = $this->validateCustomOptions($product);
        if (!$optionsResult['is_valid']) {
            $result['is_valid'] = false;
            $result['errors'] = array_merge($result['errors'], $optionsResult['errors']);
        }

        if ($result['is_valid']) {
            $result['selected_product'] = $product;
        }

        return $result;
    }

    /**
     * Validate configurable product
     *
     * @param ProductInterface $product
     * @param array $options
     * @param float $qty
     * @param array $result
     * @return array
     */
    private function validateConfigurableProduct(ProductInterface $product, array $options, float $qty, array $result): array
    {
        /** @var Product $productModel */
        $productModel = $product;

        /** @var Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance();

        // Get required attributes
        $attributes = $typeInstance->getConfigurableAttributes($productModel);
        $requiredAttributeIds = [];

        foreach ($attributes as $attribute) {
            $requiredAttributeIds[] = $attribute->getAttributeId();
        }

        // Check if all required attributes are selected
        $missingAttributes = [];
        foreach ($requiredAttributeIds as $attributeId) {
            if (!isset($options[$attributeId]) || empty($options[$attributeId])) {
                // Find the attribute with the matching ID
                foreach ($attributes as $attribute) {
                    if ($attribute->getAttributeId() == $attributeId) {
                        $productAttribute = $attribute->getProductAttribute();
                        $missingAttributes[] = $productAttribute->getFrontendLabel() ?: $productAttribute->getAttributeCode();
                        break;
                    }
                }
            }
        }

        if (!empty($missingAttributes)) {
            $result['is_valid'] = false;
            $result['errors'][] = __('Please select: %1', implode(', ', $missingAttributes));
            return $result;
        }

        // Find the specific simple product based on selected options
        $childProduct = $typeInstance->getProductByAttributes($options, $productModel);

        if (!$childProduct) {
            $result['is_valid'] = false;
            $result['errors'][] = __('The selected product combination is not available.');
            return $result;
        }

        // Check stock for the selected child product
        $stockResult = $this->checkProductStock($childProduct, $qty);
        if (!$stockResult['is_in_stock']) {
            $result['is_valid'] = false;
            $result['errors'] = array_merge($result['errors'], $stockResult['errors']);
        }

        // Check required custom options on the child product
        $optionsResult = $this->validateCustomOptions($childProduct);
        if (!$optionsResult['is_valid']) {
            $result['is_valid'] = false;
            $result['errors'] = array_merge($result['errors'], $optionsResult['errors']);
        }

        if ($result['is_valid']) {
            $result['selected_product'] = $childProduct;
        }

        return $result;
    }

    /**
     * Check product stock availability
     *
     * @param ProductInterface $product
     * @param float $qty
     * @return array
     */
    private function checkProductStock(ProductInterface $product, float $qty): array
    {
        $result = [
            'is_in_stock' => false,
            'errors' => []
        ];

        try {
            // Check if MSI is enabled
            if ($this->moduleManager->isEnabled('Magento_Inventory') && $this->getSalableQuantityDataBySku) {
                // Use MSI for stock checking
                $salableQty = $this->getSalableQuantityDataBySku->execute($product->getSku());

                if (empty($salableQty)) {
                    $result['errors'][] = __('Product is out of stock.');
                    return $result;
                }

                $totalSalableQty = 0;
                foreach ($salableQty as $stockData) {
                    $totalSalableQty += $stockData['qty'];
                }

                if ($totalSalableQty < $qty) {
                    $result['errors'][] = __('The requested quantity is not available. Available quantity: %1', $totalSalableQty);
                    return $result;
                }

                $result['is_in_stock'] = true;
            } else {
                // Fallback to legacy stock checking
                $stockItem = $this->stockRegistry->getStockItem($product->getId());

                if (!$stockItem->getIsInStock()) {
                    $result['errors'][] = __('Product is out of stock.');
                    return $result;
                }

                if ($stockItem->getManageStock() && $stockItem->getQty() < $qty) {
                    $result['errors'][] = __('The requested quantity is not available. Available quantity: %1', $stockItem->getQty());
                    return $result;
                }

                $result['is_in_stock'] = true;
            }
        } catch (\Exception $e) {
            $result['errors'][] = __('Error checking stock: %1', $e->getMessage());
        }

        return $result;
    }

    /**
     * Validate custom options
     *
     * @param ProductInterface $product
     * @return array
     */
    private function validateCustomOptions(ProductInterface $product): array
    {
        $result = [
            'is_valid' => true,
            'errors' => []
        ];

        $options = $product->getOptions();
        if (!$options) {
            return $result;
        }

        $requiredOptions = [];
        foreach ($options as $option) {
            if ($option->getIsRequire()) {
                $requiredOptions[] = $option->getTitle();
            }
        }

        if (!empty($requiredOptions)) {
            $result['is_valid'] = false;
            $result['errors'][] = __('This product has required options. Please select: %1', implode(', ', $requiredOptions));
        }

        return $result;
    }

    /**
     * Get configurable product attributes with options
     *
     * @param int $productId
     * @return array
     * @throws NoSuchEntityException
     */
    public function getConfigurableAttributes(int $productId): array
    {
        $product = $this->productRepository->getById($productId);

        if ($product->getTypeId() !== Configurable::TYPE_CODE) {
            return [];
        }

        /** @var Product $productModel */
        $productModel = $product;

        /** @var Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance();
        $attributes = $typeInstance->getConfigurableAttributes($productModel);

        $result = [];
        foreach ($attributes as $attribute) {
            $productAttribute = $attribute->getProductAttribute();
            $options = [];

            foreach ($attribute->getOptions() as $option) {
                $options[] = [
                    'value' => $option['value_index'],
                    'label' => $option['label']
                ];
            }

            $result[] = [
                'attribute_id' => $attribute->getAttributeId(),
                'attribute_code' => $productAttribute->getAttributeCode(),
                'label' => $productAttribute->getFrontendLabel(),
                'options' => $options
            ];
        }

        return $result;
    }

    /**
     * Check if product has required options that need to be selected
     *
     * @param int $productId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function hasRequiredOptions(int $productId): bool
    {
        $product = $this->productRepository->getById($productId);

        // Check for configurable attributes
        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            return true; // Configurable products always require attribute selection
        }

        // Check for custom options
        $options = $product->getOptions();
        if ($options) {
            foreach ($options as $option) {
                if ($option->getIsRequire()) {
                    return true;
                }
            }
        }

        return false;
    }
}
