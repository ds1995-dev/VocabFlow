<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use App\Models\Word;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Test User に紐づくカテゴリと単語のサンプルを投入する。
        // 各カテゴリに単語を 3 件ずつ、いずれも所有者を Test User に揃える。
        Category::factory()
            ->count(3)
            ->for($user)
            ->create()
            ->each(function (Category $category) use ($user) {
                Word::factory()
                    ->count(3)
                    ->for($user)
                    ->for($category)
                    ->create();
            });
    }
}
