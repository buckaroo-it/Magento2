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

namespace Buckaroo\Magento2\Gateway\Request\Articles;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticlesHandlerFactory;

class InvoicedArticlesDataBuilder extends AbstractDataBuilder
{
    /**
     * @var ArticlesHandlerFactory
     */
    protected $articlesHandlerFactory;

    /**
     * @var ArticleTotalRegistry
     */
    private ArticleTotalRegistry $articleTotalRegistry;

    /**
     * Constructor
     *
     * @param ArticlesHandlerFactory $articlesHandlerFactory
     * @param ArticleTotalRegistry   $articleTotalRegistry
     */
    public function __construct(
        ArticlesHandlerFactory $articlesHandlerFactory,
        ArticleTotalRegistry $articleTotalRegistry
    ) {
        $this->articlesHandlerFactory = $articlesHandlerFactory;
        $this->articleTotalRegistry = $articleTotalRegistry;
    }

    /**
     * Build invoiced articles request data for the payment method.
     *
     * @param array $buildSubject
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $articleHandler = $this->articlesHandlerFactory->create($this->getPayment()->getMethod());

        $articles = $articleHandler->getInvoiceArticlesData($this->getOrder(), $this->getPayment());

        $this->articleTotalRegistry->set(
            ArticleTotalRegistry::CONTEXT_INVOICE,
            (string)$this->getOrder()->getIncrementId(),
            $this->articleTotalRegistry->sumArticles($articles)
        );

        return $articles;
    }
}
