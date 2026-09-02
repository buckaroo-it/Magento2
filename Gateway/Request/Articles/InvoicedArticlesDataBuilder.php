<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
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
