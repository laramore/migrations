<?php

namespace Laramore\Fields;

return [

    /*
    |--------------------------------------------------------------------------
    | Default migration types
    |--------------------------------------------------------------------------
    |
    | This option defines the default migration types used for types.
    |
    */

    Binary::class => [
        'type' => 'binary',
        'property_keys' => [
            'nullable', 'default',
        ],
    ],
    Boolean::class => [
        'type' => 'boolean',
        'property_keys' => [
            'nullable', 'default',
        ],
    ],
    Char::class => [
        'type' => 'string',
        'property_keys' => [
            'length:maxLength', 'nullable', 'default',
        ],
    ],
    DateTime::class => [
        'type' => 'datetime',
        'property_keys' => [
            'nullable', 'default', 'useCurrent',
        ],
    ],
    Decimal::class => [
        'type' => 'decimal',
        'property_keys' => [
            'total:totalDigits', 'places:decimalDigits', 'nullable', 'default',
        ],
    ],
    Email::class => [
        'type' => 'string',
        'property_keys' => [
            'length:maxLength', 'nullable', 'default',
        ],
    ],
    Enum::class => [
        'type' => 'enum',
        'property_keys' => [
            'allowed:values', 'length:maxLength', 'nullable', 'default:defaultValue',
        ],
    ],
    Increment::class => [
        'type' => 'integer',
        'property_keys' => [
            'nullable', 'default',
        ],
    ],
    Integer::class => [
        'type' => 'integer',
        'property_keys' => [
            'nullable', 'default',
        ],
    ],
    Json::class => [
        'type' => 'json',
        'property_keys' => [
            'nullable',
        ],
    ],
    JsonList::class => [
        'type' => 'json',
        'property_keys' => [
            'nullable',
        ],
    ],
    JsonObject::class => [
        'type' => 'json',
        'property_keys' => [
            'nullable',
        ],
    ],
    Password::class => [
        'type' => 'string',
        'property_keys' => [
            'length:maxLength', 'nullable', 'default',
        ],
    ],
    PrimaryId::class => [
        'type' => 'increments',
        'property_keys' => [],
    ],
    Pattern::class => [
        'type' => 'string',
        'property_keys' => [
            'length:maxLength', 'nullable', 'default',
        ],
    ],
    Text::class => [
        'type' => 'text',
        'property_keys' => [
            'nullable', 'default',
        ],
    ],
    Timestamp::class => [
        'type' => 'timestamp',
        'property_keys' => [
            'nullable', 'default', 'useCurrent',
        ],
    ],
    UniqueId::class => [
        'type' => 'integer',
        'property_keys' => [
            'nullable', 'default',
        ],
    ],
    Uri::class => [
        'type' => 'text',
        'property_keys' => [
            'nullable', 'default',
        ],
    ],

];
