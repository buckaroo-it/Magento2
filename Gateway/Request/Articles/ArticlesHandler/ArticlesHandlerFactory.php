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

use Buckaroo\Magento2\Exception;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;

class ArticlesHandlerFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var array
     */
    protected $articlesHandlers;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param array                  $articlesHandlers
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        array $articlesHandlers = []
    ) {
        $this->objectManager = $objectManager;
        $this->articlesHandlers = $articlesHandlers;
    }

    /**
     * Create an article handler instance for the given payment method.
     *
     * @param mixed $payment
     *
     * @throws Exception
     */
    public function create($payment)
    {
        try {
            if (empty($this->articlesHandlers)) {
                throw new \LogicException('There is no articles handler.');
            }

            $paymentMethodName = str_replace('buckaroo_magento2_', '', $payment);

            $articleHandlerClass = $this->articlesHandlers[$paymentMethodName] ?? $this->articlesHandlers['default'];

            if (empty($articleHandlerClass)) {
                throw new Exception(
                    new Phrase(
                        'Unknown Articles Handler type requested: %1.',
                        [$paymentMethodName]
                    )
                );
            }

            return $this->objectManager->get($articleHandlerClass);
        } catch (Exception $exception) {
            throw new Exception(
                new Phrase(
                    'Unknown Articles Handler type requested: %1.'
                )
            );
        }
    }
}
