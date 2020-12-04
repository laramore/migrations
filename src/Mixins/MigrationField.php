<?php
/**
 * Migration mixin for fields.
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2020
 * @license MIT
 */

namespace Laramore\Mixins;

use Illuminate\Support\Str;
use Laramore\Contracts\Field\{
    IncrementField, NumericField
};
use Laramore\Facades\Option;


class MigrationField {
    public function getMigrationConfig() 
    {
        return function (string $path = '', $default = null) {
            if (!config()->has('field.migrations.'.static::class)) {
                throw new \Exception('Missing migration configs for '.static::class);
            }

            return config('field.migrations.'.static::class.($path ? '.'.$path : ''), $default);
        };
    }

    public function getMigrationType()
    {
        return function () {
            /** @var \Laramore\Fields\BaseField $this */
            $type = $this->getMigrationConfig('type');

            if ($this instanceof NumericField && !($this instanceof IncrementField)) {
                if ($this->hasOption(Option::bigNumber())) {
                    $type = 'big'.Str::studly($type);
                } else if ($this->hasOption(Option::smallNumber())) {
                    $type = 'small'.Str::studly($type);
                }

                if ($this->hasOption(Option::unsigned())) {
                    $type = 'unsigned'.Str::studly($type);
                }
            }

            return $type;
        };
    }

    public function getMigrationProperties()
    {
        return function () {
            /** @var \Laramore\Fields\BaseField $this */
            $keys = $this->getMigrationConfig('property_keys', []);
            $properties = [];

            foreach ($keys as $property) {
                $nameKey = explode(':', $property);
                $name = $nameKey[0];
                $key = ($nameKey[1] ?? $name);

                // Do not accept default values when they are dynamic.
                if ($key === 'default' && \is_callable($this->default) && !\is_string($this->default)) {
                    continue;
                }

                if (Option::has($snakeKey = Str::snake($key))) {
                    if ($this->hasOption($snakeKey)) {
                        $properties[$name] = true;
                    }
                } else {
                    $value = null;

                    if (\method_exists($this, $method = 'get'.\ucfirst($key))) {
                        $value = \call_user_func([$this, $method]);
                    } else {
                        $value = $this->getProperty($key);
                    }

                    if ($key === 'default') {
                        $value = $this->dry($value);
                    }

                    if (!\is_null($value)) {
                        $properties[$name] = $value;
                    }
                }
            }

            return $properties;
        };
    }
}