<?php

declare(strict_types=1);

/**
 * PHP-CS-Fixer configuration for AG-VOTE project
 *
 * Run: vendor/bin/php-cs-fixer fix --dry-run --diff
 * Fix: vendor/bin/php-cs-fixer fix
 */

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('vendor_bak')
    ->exclude('var')
    ->exclude('tests/fixtures')
    ->notPath('bootstrap.php')
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@PHP82Migration' => true,

        // Arrays
        'array_syntax' => ['syntax' => 'short'],
        'no_whitespace_before_comma_in_array' => true,
        'trim_array_spaces' => true,
        'whitespace_after_comma_in_array' => true,

        // Braces and spacing
        'braces_position' => [
            'functions_opening_brace' => 'same_line',
            'classes_opening_brace' => 'same_line',
        ],
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'throw',
                'use',
            ],
        ],
        'no_spaces_around_offset' => true,
        'single_blank_line_at_eof' => true,

        // Imports
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],

        // Comments
        'single_line_comment_style' => ['comment_types' => ['hash']],
        'no_empty_comment' => true,

        // Functions
        'function_declaration' => ['closure_function_spacing' => 'one'],
        'method_chaining_indentation' => true,
        'return_type_declaration' => ['space_before' => 'none'],

        // Casting
        'cast_spaces' => ['space' => 'single'],
        'lowercase_cast' => true,
        'short_scalar_cast' => true,

        // Operators
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'concat_space' => ['spacing' => 'one'],
        'not_operator_with_successor_space' => false,
        'object_operator_without_whitespace' => true,
        'ternary_operator_spaces' => true,

        // Strings
        'single_quote' => true,
        'explicit_string_variable' => true,

        // Control structures
        'no_useless_else' => true,
        'no_useless_return' => true,
        'simplified_if_return' => true,

        // PHPDoc
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_indent' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_order' => true,
        'phpdoc_scalar' => true,
        'phpdoc_separation' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'phpdoc_var_without_name' => true,

        // Misc
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setCacheFile('.php-cs-fixer.cache');
