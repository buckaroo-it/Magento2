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
use Buckaroo\Magento2\Gateway\Request\BasicParameter\AmountCreditDataBuilder;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Service\DataBuilderService;

/**
 * Article lines for a Klarna (KP and MoR) Refund request.
 *
 * Klarna takes the refunded lines by ArticleNumber and ArticleQuantity, the numbers the reserve
 * sent, so it can register which order lines were refunded. The lines are only sent when, at
 * their reserved prices, they add up to AmountCredit. A refund they do not explain, such as an
 * adjustment refund, part of the shipping or an amount capped for a partial payment, stays a
 * refund by amount alone.
 *
 * Must run after the builder that sets AmountCredit.
 */
class KlarnaRefundArticlesDataBuilder extends AbstractDataBuilder
{
    /**
     * @var ArticlesHandlerFactory
     */
    private ArticlesHandlerFactory $articlesHandlerFactory;

    /**
     * @var DataBuilderService
     */
    private DataBuilderService $dataBuilderService;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param ArticlesHandlerFactory  $articlesHandlerFactory
     * @param DataBuilderService      $dataBuilderService
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        ArticlesHandlerFactory $articlesHandlerFactory,
        DataBuilderService $dataBuilderService,
        BuckarooLoggerInterface $logger
    ) {
        $this->articlesHandlerFactory = $articlesHandlerFactory;
        $this->dataBuilderService = $dataBuilderService;
        $this->logger = $logger;
    }

    /**
     * Build the refunded article lines, or nothing for a refund by amount.
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

        // Amounts are compared in whole cents, so float noise in the line sum cannot tip the match.
        $amountCents = $this->toCents((float)$this->dataBuilderService->getElement(AmountCreditDataBuilder::AMOUNT_CREDIT));
        if ($amountCents < 1) {
            return [];
        }

        $articleHandler = $this->articlesHandlerFactory->create($this->getPayment()->getMethod());
        $lines = $articleHandler->getCreditmemoRefundLines($this->getOrder(), $this->getPayment());
        if (empty($lines)) {
            return [];
        }

        $linesTotal = 0.0;
        $articles = [];
        foreach ($lines as $line) {
            $linesTotal += $line['price'] * $line['quantity'];
            $articles[] = [
                'identifier' => $line['identifier'],
                'quantity' => $line['quantity'],
            ];
        }
        $linesCents = $this->toCents($linesTotal);

        if ($linesCents !== $amountCents) {
            $this->logger->addDebug(sprintf('[REFUND] | [Klarna] | [%s] - Refund by amount only for order %s: lines total %d cents at reserved prices, AmountCredit %d cents', __METHOD__, $this->getOrder()->getIncrementId(), $linesCents, $amountCents));

            return [];
        }

        $this->logger->addDebug(sprintf('[REFUND] | [Klarna] | [%s] - Refund for order %s nominates %s', __METHOD__, $this->getOrder()->getIncrementId(), implode(', ', array_map(fn($a) => $a['identifier'] . ' x' . $a['quantity'], $articles))));

        return ['articles' => $articles];
    }

    /**
     * An amount in whole cents.
     *
     * @param float $amount
     *
     * @return int
     */
    private function toCents(float $amount): int
    {
        return (int)round($amount * 100);
    }
}
