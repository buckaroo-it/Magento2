<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'var', 'Test'])
    ->notPath('registration.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PHP84Migration' => true,
        '@PSR12' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'modernize_types_casting' => true,
        'visibility_required' => true,
        'array_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => true,
        'no_unused_imports' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);