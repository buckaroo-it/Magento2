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
use Magento\Sales\Model\Order;

/**
 * Article lines for a Klarna KP Pay request.
 *
 * Klarna KP takes no prices on a capture: a full capture carries only the reservation number, a
 * partial one nominates reserved lines by ArticleNumber and ArticleQuantity. AmountDebit is
 * still derived from the priced lines so it matches what Klarna computes.
 */
class KlarnaKpInvoicedArticlesDataBuilder extends AbstractDataBuilder
{
    /**
     * @var ArticlesHandlerFactory
     */
    private ArticlesHandlerFactory $articlesHandlerFactory;

    /**
     * @var ArticleTotalRegistry
     */
    private ArticleTotalRegistry $articleTotalRegistry;

    /**
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
     * Build the Pay article lines.
     *
     * @param array $buildSubject
     *
     * @return array
     *
     * @throws \Buckaroo\Magento2\Exception
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $order = $this->getOrder();

        $articleHandler = $this->articlesHandlerFactory->create($this->getPayment()->getMethod());
        $articles = $articleHandler->getInvoiceArticlesData($order, $this->getPayment());

        $this->articleTotalRegistry->set(
            ArticleTotalRegistry::CONTEXT_INVOICE,
            (string)$order->getIncrementId(),
            $this->articleTotalRegistry->sumArticles($articles)
        );

        if ($this->isFullCapture($order)) {
            return [];
        }

        return ['articles' => $this->toNominatedLines($articles)];
    }

    /**
     * Whether this capture settles the whole reservation in one go.
     *
     * @param Order $order
     *
     * @return bool
     */
    private function isFullCapture(Order $order): bool
    {
        return $order->getInvoiceCollection()->count() === 1 && !$order->canInvoice();
    }

    /**
     * Reduce priced article lines to the identifier and quantity Klarna expects.
     *
     * @param array $articles
     *
     * @return array
     */
    private function toNominatedLines(array $articles): array
    {
        $lines = [];

        foreach (($articles['articles'] ?? []) as $article) {
            if (!is_array($article) || !isset($article['identifier'])) {
                continue;
            }

            $lines[] = [
                'identifier' => $article['identifier'],
                'quantity' => (int)($article['quantity'] ?? 1),
            ];
        }

        return $lines;
    }
}
