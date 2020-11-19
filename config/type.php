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
        'binary' => [
            'migration_property_keys' => [
                'nullable', 'default',
            ],
        ],
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
        'date_time' => [
            'migration_property_keys' => [
                'nullable', 'default',
            ],
        ],
        'email' => [
            'migration_name' => 'char',
            'migration_property_keys' => [
                'length:maxLength', 'nullable', 'default',
            ],
        ],
        'enum' => [
            'migration_property_keys' => [
                'allowed:values', 'length:maxLength', 'nullable', 'default:defaultValue',
            ],
        ],
        'increment' => [
            'migration_name' => 'increments',
            'migration_property_keys' => [
                'nullable', 'default',
            ],
        ],
        'integer' => [
            'migration_property_keys' => [
                'nullable', 'default',
            ],
        ],
        'json' => [
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
            'migration_name' => null,
        ],
        'password' => [
            'migration_name' => 'char',
            'migration_property_keys' => [
                'length:maxLength', 'nullable', 'default',
            ],
        ],
        'primary_id' => [
            'migration_name' => 'increments',
            'migration_property_keys' => [],
        ],
        'pattern' => [
            'migration_name' => 'char',
            'migration_property_keys' => [
                'length:maxLength', 'nullable', 'default',
            ],
        ],
        'text' => [
            'migration_name' => 'text',
            'migration_property_keys' => [
                'nullable', 'default',
            ],
        ],
        'timestamp' => [
            'migration_name' => 'timestamp',
            'migration_property_keys' => [
                'nullable', 'default', 'useCurrent',
            ],
        ],
    ],

];
