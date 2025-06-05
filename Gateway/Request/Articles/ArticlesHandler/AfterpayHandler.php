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

namespace Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface as BuckarooLog;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderMethodFactory;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Catalog\Helper\Image;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;

class AfterpayHandler extends AbstractArticlesHandler
{
    /**
     * @param \Magento\Catalog\Helper\Image $imageHelper
     */
    protected $imageHelper;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param BuckarooLog $buckarooLog
     * @param QuoteFactory $quoteFactory
     * @param Calculation $taxCalculation
     * @param Config $taxConfig
     * @param BuckarooFee $configProviderBuckarooFee
     * @param SoftwareData $softwareData
     * @param ConfigProviderMethodFactory $configProviderMethodFactory
     * @param PayReminderService $payReminderService
     * @param Image $imageHelper
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        BuckarooLog $buckarooLog,
        QuoteFactory $quoteFactory,
        Calculation $taxCalculation,
        Config $taxConfig,
        BuckarooFee $configProviderBuckarooFee,
        SoftwareData $softwareData,
        ConfigProviderMethodFactory $configProviderMethodFactory,
        PayReminderService $payReminderService,
        \Magento\Catalog\Helper\Image $imageHelper
    ) {
        parent::__construct(
            $scopeConfig,
            $buckarooLog,
            $quoteFactory,
            $taxCalculation,
            $taxConfig,
            $configProviderBuckarooFee,
            $softwareData,
            $configProviderMethodFactory,
            $payReminderService
        );

        $this->imageHelper = $imageHelper;
    }

    /**
     * Get the structure of the array returned to request for refunded items
     *
     * @param string|null $articleDescription
     * @param int|string|null $articleId
     * @param int|float $articleQuantity
     * @param string|float $articleUnitPrice
     * @param string|float $articleVat
     * @return array
     */
    public function getArticleRefundArrayLine(
        ?string $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    ): array {
        return [
            'refundType'    => 'Return',
            'identifier'    => $articleId,
            'description'   => $articleDescription,
            'vatPercentage' => $articleVat,
            'quantity'      => $articleQuantity,
            'price'         => $articleUnitPrice
        ];
    }

    /**
     * Get items lines
     *
     * @return array
     */
    protected function getItemsLines(): array
    {
        $articles = [];
        $count = 1;
        $bundleProductQty = 0;

        $quote = $this->getQuote();
        $cartData = $quote->getAllItems();

        /**
         * @var Item $item
         */
        foreach ($cartData as $item) {
            if ($this->skipItem($item)) {
                continue;
            }

            if ($this->skipBundleProducts($item, $bundleProductQty)) {
                continue;
            }

            $article = $this->getArticleArrayLine(
                $item->getName(),
                $this->getIdentifier($item),
                $bundleProductQty ?: $item->getQty(),
                $this->calculateProductPrice($item),
                $this->getItemTax($item),
                $this->getProductImageUrl($item)
            );

            $articles[] = $article;

            if ($count >= self::MAX_ARTICLE_COUNT) {
                break;
            }

            $count++;
        }

        return $articles;
    }

    /**
     * Get product image URL
     *
     * @param Item $item
     * @return string
     */
    protected function getProductImageUrl($item)
    {
        $product = $item->getProduct();
        return $this->imageHelper->init($product, 'thumbnail')
            ->setImageFile($product->getImage())
            ->getUrl();
    }

    /**
     * @inheritdoc
     */
    public function getArticleArrayLine(
        ?string $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = '',
        $imageUrl = ''
    ): array {
        return [
            'identifier'    => $articleId,
            'description'   => $articleDescription,
            'vatPercentage' => $articleVat,
            'quantity'      => $articleQuantity,
            'price'         => $articleUnitPrice,
            'imageUrl'      => $imageUrl
        ];
    }
}
