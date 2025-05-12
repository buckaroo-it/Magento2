<?php
/**
 * Monolog 2 ↔ 3 compatibility shim for the Buckaroo logger.
 * Loaded automatically from registration.php.
 */

declare(strict_types=1);

namespace {
    /* ---------------------------------------------------------
     * JsonSerializableDateTimeImmutable – added in Monolog 3.
     * ------------------------------------------------------- */
    if (!class_exists(\Monolog\JsonSerializableDateTimeImmutable::class)
        &&  class_exists(\Monolog\DateTimeImmutable::class)
    ) {
        class_alias(
            \Monolog\DateTimeImmutable::class,
            \Monolog\JsonSerializableDateTimeImmutable::class
        );
    }

    /* ---------------------------------------------------------
     * Level enum – added in Monolog 3.
     * ------------------------------------------------------- */
    if (!class_exists(\Monolog\Level::class)) {
        /**
         * Minimal stand-in used only to satisfy type hints.
         */
        final class _Buckaroo_Monolog_Level_Shim
        {
            public function __construct(public int $value) {}

            public static function Debug()     { return new self(100); }
            public static function Info()      { return new self(200); }
            public static function Notice()    { return new self(250); }
            public static function Warning()   { return new self(300); }
            public static function Error()     { return new self(400); }
            public static function Critical()  { return new self(500); }
            public static function Alert()     { return new self(550); }
            public static function Emergency() { return new self(600); }
        }

        // Use plain strings → no early class-constant evaluation
        class_alias(
            '_Buckaroo_Monolog_Level_Shim',
            'Monolog\\Level'
        );
    }
}
