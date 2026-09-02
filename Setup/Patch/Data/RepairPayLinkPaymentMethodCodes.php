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

namespace Buckaroo\Magento2\Setup\Patch\Data;

use Buckaroo\Magento2\Model\PaymentMethodCodeResolver;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Psr\Log\LoggerInterface;

/**
 * Repair orders whose payment method was overwritten with a Buckaroo service code.
 *
 * A PayLink or Pay Per Email order used to have its method set to the prefix plus the service code
 * Buckaroo reported, which for several services is not a method this module registers. Magento then
 * cannot resolve a method instance, and the order can no longer be refunded online.
 *
 * The broken code carries the service code it was built from, so the correct method is recovered from
 * the code itself. Orders whose service has no method are left alone and reported, since there is
 * nothing to correct them to.
 */
class RepairPayLinkPaymentMethodCodes implements DataPatchInterface
{
    /**
     * Prefix every payment method this module registers carries.
     */
    private const METHOD_CODE_PREFIX = 'buckaroo_magento2_';

    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @var PaymentMethodCodeResolver
     */
    private PaymentMethodCodeResolver $paymentMethodCodeResolver;

    /**
     * @var PaymentHelper
     */
    private PaymentHelper $paymentHelper;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ModuleDataSetupInterface  $moduleDataSetup
     * @param PaymentMethodCodeResolver $paymentMethodCodeResolver
     * @param PaymentHelper             $paymentHelper
     * @param LoggerInterface           $logger
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        PaymentMethodCodeResolver $paymentMethodCodeResolver,
        PaymentHelper $paymentHelper,
        LoggerInterface $logger
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->paymentMethodCodeResolver = $paymentMethodCodeResolver;
        $this->paymentHelper = $paymentHelper;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $repaired = 0;
        $unresolved = [];

        foreach ($this->findBrokenMethodCodes() as $brokenCode) {
            $serviceCode = substr($brokenCode, strlen(self::METHOD_CODE_PREFIX));
            $methodCode = $this->paymentMethodCodeResolver->resolve($serviceCode);

            if ($methodCode === null || $methodCode === $brokenCode) {
                $unresolved[] = $brokenCode;
                continue;
            }

            $repaired += $this->replaceMethodCode($brokenCode, $methodCode);
        }

        if ($repaired > 0) {
            $this->logger->info(sprintf(
                '[BUCKAROO] RepairPayLinkPaymentMethodCodes: corrected the payment method on %d order(s).',
                $repaired
            ));
        }

        if ($unresolved !== []) {
            // Nothing to correct these to. Reported rather than guessed at, so support can decide.
            $this->logger->warning(sprintf(
                '[BUCKAROO] RepairPayLinkPaymentMethodCodes: no registered method for %s;'
                . ' those orders still need a manual correction.',
                implode(', ', array_unique($unresolved))
            ));
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * Distinct Buckaroo method codes on orders that Magento cannot resolve.
     *
     * @return string[]
     */
    private function findBrokenMethodCodes(): array
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('sales_order_payment');

        $select = $connection->select()
            ->distinct()
            ->from($table, ['method'])
            ->where('method LIKE ?', self::METHOD_CODE_PREFIX . '%');

        $registered = $this->paymentHelper->getPaymentMethods();

        return array_values(array_filter(
            $connection->fetchCol($select),
            static function ($method) use ($registered) {
                return is_string($method) && $method !== '' && !array_key_exists($method, $registered);
            }
        ));
    }

    /**
     * Point every order carrying the broken code at the method that handles it.
     *
     * @param string $brokenCode
     * @param string $methodCode
     *
     * @return int
     */
    private function replaceMethodCode(string $brokenCode, string $methodCode): int
    {
        $connection = $this->moduleDataSetup->getConnection();

        $affected = $connection->update(
            $this->moduleDataSetup->getTable('sales_order_payment'),
            ['method' => $methodCode],
            ['method = ?' => $brokenCode]
        );

        $this->logger->info(sprintf(
            '[BUCKAROO] RepairPayLinkPaymentMethodCodes: %s -> %s on %d order(s).',
            $brokenCode,
            $methodCode,
            $affected
        ));

        return (int)$affected;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
