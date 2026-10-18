<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Stop Detection Thresholds
    |--------------------------------------------------------------------------
    |
    | Defines the distance radius in meters from the cluster anchor and the
    | minimum stationary duration in seconds required to declare a stop.
    |
    */
    'radius_meters' => (float) env('TRACKING_STOP_RADIUS_METERS', 50.0),
    'min_stop_duration_seconds' => (int) env('TRACKING_MIN_STOP_DURATION_SECONDS', 900),

    /*
    |--------------------------------------------------------------------------
    | Gap Handling
    |--------------------------------------------------------------------------
    |
    | Maximum acceptable gap between consecutive points before a trajectory
    | is considered disconnected.
    |
    */
    'max_gap_seconds' => (int) env('TRACKING_MAX_GAP_SECONDS', 1800),

    /*
    |--------------------------------------------------------------------------
    | Zone Analysis Gap Cap
    |--------------------------------------------------------------------------
    |
    | Maximum duration (seconds) credited to a zone stay for any single
    | transition between two points inside the zone. Prevents mobile device
    | disconnects/outages from artificially inflating time inside a zone.
    |
    */
    'zone_gap_cap_seconds' => (int) env('TRACKING_ZONE_GAP_CAP_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Ingestion Configuration
    |--------------------------------------------------------------------------
    |
    | Maximum chunk size for bulk database inserts.
    |
    */
    'batch_chunk_size' => (int) env('TRACKING_BATCH_CHUNK_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Track Simplification (Ramer-Douglas-Peucker)
    |--------------------------------------------------------------------------
    |
    | Tolerance (perpendicular distance threshold in meters or approx degrees)
    | used by the Ramer-Douglas-Peucker algorithm to simplify web map tracks.
    |
    */
    'rdp_tolerance' => (float) env('TRACKING_RDP_TOLERANCE', 0.00008),
];
