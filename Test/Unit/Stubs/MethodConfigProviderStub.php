<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * PHPUnit 12 replacement for MockBuilder::getMockForAbstractClass() on the method config provider.
 *
 * AbstractConfigProvider declares no abstract methods - it is abstract by keyword only - so a
 * concrete subclass is enough. Mock it with onlyMethods() so every method not named stays real,
 * which is what getMockForAbstractClass() used to give us.
 *
 * The real constructor wants five collaborators only so it can pass `static::CODE` down to the
 * base. This one takes the base's arguments directly, because a test that is about config path
 * resolution should not have to build an asset repository to get there. `CODE` is a constant and
 * cannot be injected, so it is pinned to a real method code - the path pattern is
 * `payment/%s/%s`, which makes reads resolve to `payment/buckaroo_magento2_ideal/<field>`.
 */
class MethodConfigProviderStub extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_ideal';

    /**
     * @param ScopeConfigInterface|null $scopeConfig
     * @param string|null               $methodCode
     * @param string                    $pathPattern
     */
    public function __construct(
        ?ScopeConfigInterface $scopeConfig = null,
        ?string $methodCode = self::CODE,
        string $pathPattern = 'payment/%s/%s'
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->methodCode = $methodCode;
        $this->pathPattern = $pathPattern;
    }

    public function getConfig(): array
    {
        return [];
    }
}
