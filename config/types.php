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
            'migration_property_keys' => [
                'nullable', 'default',
            ],
        ],
        'char' => [
            'migration_property_keys' => [
                'length:maxLength', 'nullable', 'default',
            ],
        ],
        'composite' => [
            'migration_type' => null,
        ],
        'email' => [
            'migration_type' => 'char',
            'migration_property_keys' => [
                'length:maxLength', 'nullable', 'default',
            ],
        ],
        'enum' => [
            'migration_property_keys' => [
                'allowed:elementsValue', 'length:maxLength', 'nullable', 'default:defaultValue',
            ],
        ],
        'increment' => [
            'migration_type' => 'increments',
            'migration_property_keys' => [
                'nullable', 'default',
            ],
        ],
        'integer' => [
            'migration_property_keys' => [
                'nullable', 'default',
            ],
        ],
        'unsigned_integer' => [
            'migration_property_keys' => [
                'nullable', 'default',
            ],
        ],
        'link' => [
            'migration_type' => null,
        ],
        'password' => [
            'migration_type' => 'char',
            'migration_property_keys' => [
                'length:maxLength', 'nullable', 'default',
            ],
        ],
        'primary_id' => [
            'migration_type' => 'increments',
            'migration_property_keys' => [],
        ],
        'pattern' => [
            'migration_type' => 'char',
            'migration_property_keys' => [
                'length:maxLength', 'nullable', 'default',
            ],
        ],
        'text' => [
            'migration_type' => 'text',
            'migration_property_keys' => [
                'nullable', 'default',
            ],
        ],
        'timestamp' => [
            'migration_type' => 'timestamp',
            'migration_property_keys' => [
                'nullable', 'default', 'useCurrent',
            ],
        ],
    ],

];
