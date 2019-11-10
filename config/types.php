<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default migration types
    |--------------------------------------------------------------------------
    |
    | This option defines the default migration types used for types.
    |
    */

    'configurations' => [
        'boolean' => [
            'migration_properties' => [
                'nullable', 'default', 'primary', 'index', 'unique',
            ],
        ],
        'char' => [
            'migration_properties' => [
                'length:maxLength', 'nullable', 'default', 'primary', 'index', 'unique',
            ],
        ],
        'composite' => [
            'migration_type' => null,
        ],
        'email' => [
            'migration_type' => 'char',
            'migration_properties' => [
                'length:maxLength', 'nullable', 'default', 'primary', 'index', 'unique',
            ],
        ],
        'enum' => [
            'migration_properties' => [
                'allowed:elementsValue', 'length:maxLength', 'nullable', 'default:defaultValue', 'primary', 'index', 'unique',
            ],
        ],
        'increment' => [
            'migration_type' => 'increments',
            'migration_properties' => [
                'nullable', 'default',
            ],
        ],
        'integer' => [
            'migration_properties' => [
                'nullable', 'default', 'primary', 'index', 'unique',
            ],
        ],
        'unsigned_integer' => [
            'migration_properties' => [
                'nullable', 'default', 'primary', 'index', 'unique',
            ],
        ],
        'link' => [
            'migration_type' => null,
        ],
        'password' => [
            'migration_type' => 'char',
            'migration_properties' => [
                'length:maxLength', 'nullable', 'default', 'primary', 'index', 'unique',
            ],
        ],
        'primary_id' => [
            'migration_type' => 'increments',
            'migration_properties' => [],
        ],
        'pattern' => [
            'migration_type' => 'char',
            'migration_properties' => [
                'length:maxLength', 'nullable', 'default', 'primary', 'index', 'unique',
            ],
        ],
        'text' => [
            'migration_type' => 'text',
            'migration_properties' => [
                'nullable', 'default', 'primary', 'index', 'unique',
            ],
        ],
        'timestamp' => [
            'migration_type' => 'timestamp',
            'migration_properties' => [
                'nullable', 'default', 'useCurrent', 'primary', 'index', 'unique',
            ],
        ],
    ],

];
