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

use Buckaroo\Magento2\Exception;
use Magento\Framework\ObjectManagerInterface;

class ArticlesHandlerFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @var array
     */
    protected array $articlesHandlers;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param array $articlesHandlers
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        array $articlesHandlers = []
    ) {
        $this->objectManager = $objectManager;
        $this->articlesHandlers = $articlesHandlers;
    }

    /**
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
                throw new \Buckaroo\Magento2\Exception(
                    new \Magento\Framework\Phrase(
                        'Unknown Articles Handler type requested: %1.',
                        [$paymentMethodName]
                    )
                );
            }

            return $this->objectManager->get($articleHandlerClass);
        } catch (\Exception $exception) {
            throw new \Buckaroo\Magento2\Exception(
                new \Magento\Framework\Phrase(
                    'Unknown Articles Handler type requested: %1.'
                )
            );
        }
    }
}
