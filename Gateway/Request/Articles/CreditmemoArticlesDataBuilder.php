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
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticleHandlerInterface;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticlesHandlerFactory;

class CreditmemoArticlesDataBuilder extends AbstractDataBuilder
{
    /**
     * @var ArticlesHandlerFactory
     */
    protected $articlesHandlerFactory;

    /**
     * Constructor
     *
     * @param ArticlesHandlerFactory $articlesHandlerFactory
     */
    public function __construct(ArticlesHandlerFactory $articlesHandlerFactory)
    {
        $this->articlesHandlerFactory = $articlesHandlerFactory;
    }

    /**
     * Build credit memo articles request data for the payment method.
     *
     * @param array $buildSubject
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        /** @var \Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticleHandlerInterface $articleHandler */
        $articleHandler = $this->articlesHandlerFactory->create($this->getPayment()->getMethod());

        return $articleHandler->getCreditMemoArticlesData($this->getOrder(), $this->getPayment());
    }
}
