<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Rep extends Model
{
    /** @use HasFactory<RepFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * @return HasMany<LocationPoint, $this>
     */
    public function locationPoints(): HasMany
    {
        return $this->hasMany(LocationPoint::class);
    }

    /**
     * @return HasMany<Stop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(Stop::class);
    }
}
