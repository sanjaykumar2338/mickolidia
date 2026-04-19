<?php

namespace Database\Factories;

use App\Models\Mt5AccountPoolEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mt5AccountPoolEntry>
 */
class Mt5AccountPoolEntryFactory extends Factory
{
    protected $model = Mt5AccountPoolEntry::class;

    public function definition(): array
    {
        return [
            'login' => (string) fake()->unique()->numerify('52######'),
            'password' => fake()->password(14, 18),
            'server' => 'ICMarketsEU-Demo',
            'account_size' => fake()->randomElement([5000, 10000, 25000, 50000, 100000]),
            'currency_code' => 'USD',
            'source_status' => 'available',
            'source_file' => 'Accounts List 2 Wolforix.ods',
            'source_batch' => 'test-batch',
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_CLIENT,
            'source_created_at' => now()->subDay(),
            'is_available' => true,
            'meta' => [
                'row_number' => 2,
                'import_notes' => [],
            ],
        ];
    }

    public function internalOnly(): static
    {
        return $this->state(fn (): array => [
            'source_pool' => Mt5AccountPoolEntry::SOURCE_POOL_INTERNAL,
        ]);
    }

    public function allocated(): static
    {
        return $this->state(fn (): array => [
            'allocated_at' => now(),
            'is_available' => false,
        ]);
    }
}
