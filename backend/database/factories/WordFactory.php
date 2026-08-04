<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Word>
 */
class WordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'word' => fake()->word(),
            'meaning' => fake()->sentence(),
            'sentence' => fake()->sentence(),
            'is_learned' => false,
        ];
    }
}
