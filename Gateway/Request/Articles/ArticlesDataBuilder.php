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

class ArticlesDataBuilder extends AbstractDataBuilder
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
     * Build order articles request data for the payment method.
     *
     * @param array $buildSubject
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $articleHandler = $this->articlesHandlerFactory->create($this->getPayment()->getMethod());

        $articles = $articleHandler->getOrderArticlesData($this->getOrder(), $this->getPayment());

        $this->articleTotalRegistry->set(
            ArticleTotalRegistry::CONTEXT_ORDER,
            (string)$this->getOrder()->getIncrementId(),
            $this->articleTotalRegistry->sumArticles($articles)
        );

        return $articles;
    }
}
