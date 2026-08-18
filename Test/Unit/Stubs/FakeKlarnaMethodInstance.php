<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * Minimal stand-in for a Buckaroo payment method instance, mirroring the
 * public static $requestOnVoid flag on BuckarooAdapter that the processor toggles.
 */
class FakeKlarnaMethodInstance
{
    public static $requestOnVoid = true;
}
