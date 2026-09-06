<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SystemHealthStatus;
use Database\Factories\SystemHealthCheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single point-in-time probe result for one internal component, recorded
 * on a schedule by `health:record`.
 *
 * @property int                        $id
 * @property string                     $component
 * @property SystemHealthStatus         $status
 * @property int|null                   $response_time_ms
 * @property string|null                $message
 * @property \Illuminate\Support\Carbon $checked_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class SystemHealthCheck extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<SystemHealthCheckFactory> */
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var list<string> */
    protected $fillable = [
        'component',
        'status',
        'response_time_ms',
        'message',
        'checked_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | `casts()`
    |--------------------------------------------------------------------------
    */

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string> a map of attribute name to cast type
     */
    protected function casts(): array
    {
        return [
            'status' => SystemHealthStatus::class,
            'response_time_ms' => 'integer',
            'checked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new factory instance for the model.
     *
     * @return SystemHealthCheckFactory the SystemHealthCheck factory
     */
    protected static function newFactory(): SystemHealthCheckFactory
    {
        return SystemHealthCheckFactory::new();
    }
}
