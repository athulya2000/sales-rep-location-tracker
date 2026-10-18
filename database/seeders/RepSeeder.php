<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Rep;
use Illuminate\Database\Seeder;

final class RepSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reps = [
            ['name' => 'Rahul Nair'],
            ['name' => 'Aisha Rahman'],
            ['name' => 'Mohammed Shafi'],
        ];

        foreach ($reps as $data) {
            Rep::query()->firstOrCreate(['name' => $data['name']], $data);
        }
    }
}
