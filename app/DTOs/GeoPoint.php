<?php

declare(strict_types=1);

namespace App\DTOs;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final readonly class GeoPoint
{
    public function __construct(
        public float $lat,
        public float $lng,
        public CarbonImmutable $recordedAt,
        public ?int $accuracy = null,
    ) {}

    /**
     * @param  array{lat: float|int|string, lng: float|int|string, recorded_at: string|DateTimeInterface, accuracy?: int|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $recordedAt = $data['recorded_at'] instanceof DateTimeInterface
            ? CarbonImmutable::instance($data['recorded_at'])
            : CarbonImmutable::parse((string) $data['recorded_at']);

        return new self(
            lat: (float) $data['lat'],
            lng: (float) $data['lng'],
            recordedAt: $recordedAt,
            accuracy: isset($data['accuracy']) ? (int) $data['accuracy'] : null,
        );
    }

    /**
     * @return array{lat: float, lng: float, recorded_at: string, accuracy: int|null}
     */
    public function toArray(): array
    {
        return [
            'lat' => $this->lat,
            'lng' => $this->lng,
            'recorded_at' => $this->recordedAt->toIso8601String(),
            'accuracy' => $this->accuracy,
        ];
    }
}
