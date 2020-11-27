<?php

namespace Laramore\Fields\Constraint;

return [

    /*
    |--------------------------------------------------------------------------
    | Default migrable constraints
    |--------------------------------------------------------------------------
    |
    | This option defines the default constraints use to generate migrations.
    |
    */

    'configurations' => [
        'primary' => [
            'migrable' => true,
        ],
        'index' => [
            'migrable' => true,
        ],
        'unique' => [
            'migrable' => true,
        ],
        'foreign' => [
            'migrable' => true,
        ],
    ],
];
