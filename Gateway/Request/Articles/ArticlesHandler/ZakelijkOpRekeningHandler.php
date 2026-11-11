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

namespace Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler;

/**
 * ABN AMRO "Zakelijk op rekening" article lines.
 *
 * In3 AbnB2b requires VatPercentage + VatAmount on every article (use 0 when
 * there is no tax). Omitting them makes BPE send vatTotal/vatAmount as null,
 * and In3 rejects with: "Order net amount is not equal to total line item net amounts".
 *
 * Requires buckaroo/sdk In3 Article to declare `vatAmount`.
 */
class ZakelijkOpRekeningHandler extends CapayableIn3Handler
{
    /**
     * @inheritdoc
     */
    public function getArticleArrayLine(
        ?string $articleDescription,
        $articleId,
        $articleQuantity,
        $articleUnitPrice,
        $articleVat = ''
    ): array {
        $price = floor((float)$articleUnitPrice * 100) / 100;
        $vatPercentage = is_numeric($articleVat) ? (float)$articleVat : 0.0;
        $vatAmount = 0.0;

        if ($vatPercentage > 0 && $price > 0) {
            $vatAmount = floor(($price * $vatPercentage / (100 + $vatPercentage)) * 100) / 100;
        }

        return [
            'identifier' => (string)$articleId,
            'description' => $articleDescription,
            'quantity' => (int)$articleQuantity,
            'price' => $price,
            'vatPercentage' => $vatPercentage,
            'vatAmount' => $vatAmount,
        ];
    }
}
