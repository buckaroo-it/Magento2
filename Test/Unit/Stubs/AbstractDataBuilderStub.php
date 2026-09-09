<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;

/**
 * PHPUnit 12 replacement for MockBuilder::getMockForAbstractClass() on AbstractDataBuilder.
 *
 * Only `build()` is abstract there, and the behaviour under test - getStoreId() - is concrete.
 * createMock() would stub it out and the assertion would pass against a null it invented, so the
 * concrete method has to stay real. AbstractDataBuilder has no constructor.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class AbstractDataBuilderStub extends AbstractDataBuilder
{
    public function build(array $buildSubject)
    {
        return [];
    }
}
