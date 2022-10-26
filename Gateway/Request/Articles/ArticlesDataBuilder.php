<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Articles;

use Buckaroo\Magento2\Api\ArticleHandlerInterface;
use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticlesHandlerFactory;

class ArticlesDataBuilder extends AbstractDataBuilder
{
    /** @var ArticlesHandlerFactory */
    protected ArticlesHandlerFactory $articlesHandlerFactory;

    protected ArticleHandlerInterface $articleHandler;

    public function __construct(ArticlesHandlerFactory $articlesHandlerFactory) {
        $this->articlesHandlerFactory = $articlesHandlerFactory;
    }

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $this->articleHandler = $this->articlesHandlerFactory->create($this->getPayment()->getMethod());

        return $this->articleHandler->getOrderArticlesData($this->getOrder(), $this->getPayment());
    }
}