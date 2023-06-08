<?php

namespace Buckaroo\Magento2\Model;

class BuckarooStatusCode
{
    const SUCCESS               = 190;
    const FAILED                = 490;
    const VALIDATION_FAILURE    = 491;
    const TECHNICAL_ERROR       = 492;
    const REJECTED              = 690;
    const WAITING_ON_USER_INPUT = 790;
    const PENDING_PROCESSING    = 791;
    const WAITING_ON_CONSUMER   = 792;
    const PAYMENT_ON_HOLD       = 793;
    const PENDING_APPROVAL      = 794;
    const CANCELLED_BY_USER     = 890;
    const CANCELLED_BY_MERCHANT = 891;
    const ORDER_FAILED          = 11014; // Code created by dev, not by Buckaroo.
}


