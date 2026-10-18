<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LocationPointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LocationPoint extends Model
{
    /** @use HasFactory<LocationPointFactory> */
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rep_id',
        'recorded_at',
        'recorded_date',
        'lat',
        'lng',
        'accuracy',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'recorded_date' => 'date',
            'lat' => 'float',
            'lng' => 'float',
            'accuracy' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Rep, $this>
     */
    public function rep(): BelongsTo
    {
        return $this->belongsTo(Rep::class);
    }
}
