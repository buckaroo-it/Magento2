<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php81\Rector\Array_\FirstClassCallableRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/Api',
        __DIR__ . '/Block',
        __DIR__ . '/Controller',
        __DIR__ . '/Helper',
        __DIR__ . '/Model',
        __DIR__ . '/Observer',
        __DIR__ . '/Plugin',
        __DIR__ . '/Service',
        __DIR__ . '/Setup',
        __DIR__ . '/Gateway',
    ]);

    $rectorConfig->skip([
        __DIR__ . '/vendor',
        __DIR__ . '/Test',
        __DIR__ . '/var',
    ]);

    // Define sets of rules
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_84,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
    ]);

    // Add specific PHP 8.4 rules
    $rectorConfig->rule(ExplicitNullableParamTypeRector::class);

    // Magento specific rules to skip
    $rectorConfig->skip([
        ClassPropertyAssignToConstructorPromotionRector::class,
        FirstClassCallableRector::class, // Can break Magento DI
    ]);
};
