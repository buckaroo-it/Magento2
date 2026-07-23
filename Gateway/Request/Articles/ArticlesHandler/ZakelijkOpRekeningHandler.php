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
            'identifier' => $articleId,
            'description' => $articleDescription,
            'quantity' => $articleQuantity,
            'price' => $price,
            'vatPercentage' => $vatPercentage,
            'vatAmount' => $vatAmount,
        ];
    }
}
