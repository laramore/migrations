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
                'nullable', 'default',
            ],
        ],
        'char' => [
            'migration_properties' => [
                'length:maxLength', 'nullable', 'default',
            ],
        ],
        'composite' => [
            'migration_type' => null,
        ],
        'email' => [
            'migration_type' => 'char',
            'migration_properties' => [
                'length:maxLength', 'nullable', 'default',
            ],
        ],
        'enum' => [
            'migration_properties' => [
                'allowed:elementsValue', 'length:maxLength', 'nullable', 'default:defaultValue',
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
                'nullable', 'default',
            ],
        ],
        'unsigned_integer' => [
            'migration_properties' => [
                'nullable', 'default',
            ],
        ],
        'link' => [
            'migration_type' => null,
        ],
        'password' => [
            'migration_type' => 'char',
            'migration_properties' => [
                'length:maxLength', 'nullable', 'default',
            ],
        ],
        'primary_id' => [
            'migration_type' => 'increments',
            'migration_properties' => [],
        ],
        'pattern' => [
            'migration_type' => 'char',
            'migration_properties' => [
                'length:maxLength', 'nullable', 'default',
            ],
        ],
        'text' => [
            'migration_type' => 'text',
            'migration_properties' => [
                'nullable', 'default',
            ],
        ],
        'timestamp' => [
            'migration_type' => 'timestamp',
            'migration_properties' => [
                'nullable', 'default', 'useCurrent',
            ],
        ],
    ],

];
