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

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\KlarnaKpHandler;
use Magento\Payment\Gateway\Response\HandlerInterface;

/**
 * Mark a Klarna KP reservation as made with unique article numbers.
 */
class KlarnaKpUniqueArticleNumbersHandler implements HandlerInterface
{
    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        SubjectReader::readPayment($handlingSubject)
            ->getPayment()
            ->setAdditionalInformation(KlarnaKpHandler::UNIQUE_ARTICLE_NUMBERS, true);
    }
}
