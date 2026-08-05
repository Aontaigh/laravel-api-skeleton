<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use Closure;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialises only attributes that were selected on the underlying Model.
 *
 * @mixin JsonResource
 */
trait SerialisesSparseAttributes
{
    /*
    |--------------------------------------------------------------------------
    | Abstract
    |--------------------------------------------------------------------------
    */

    /**
     * Conditionally include a value in the serialised output.
     *
     * Declared abstractly so the dependency on the host JsonResource is a
     * contract PHP enforces. `protected` matches Laravel exactly — an
     * abstract `public` method cannot be satisfied by a protected
     * implementation.
     *
     * @param  bool  $condition whether the value should be included
     * @param  mixed $value     the value, or a Closure resolving it
     * @param  mixed $default   included when the condition fails
     * @return mixed the value, or a MissingValue that is stripped from output
     */
    abstract protected function when($condition, $value, $default = null);

    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Include a scalar attribute only when it was loaded on the Model.
     *
     * The value is a Closure, not a plain expression, because reading an
     * attribute that a sparse `select()` left out throws
     * `MissingAttributeException` under
     * `Model::preventAccessingMissingAttributes()`. Passing
     * `$this->resource->email` directly would evaluate it before the guard
     * could skip it.
     *
     * @param  string           $column the model attribute name
     * @param  Closure(): mixed $value  resolves the serialised value when selected
     * @return mixed            the resolved value, or omitted when the attribute was not selected
     */
    protected function whenAttributeSelected(string $column, Closure $value): mixed
    {
        return $this->when(
            array_key_exists($column, $this->resource->getAttributes()),
            $value,
        );
    }
}
