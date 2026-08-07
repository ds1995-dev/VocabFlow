<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\LearningActivity;
use App\Models\User;
use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningActivity>
 */
class LearningActivityFactory extends Factory
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
            'word_id' => Word::factory(),
            'type' => ActivityType::WordCreated,
            // アプリの timezone（UTC）ではなく学習日の今日を既定にする。
            // UTC の今日を使うと JST 早朝のテストで日付が1日ずれる。
            'studied_on' => LearningActivity::currentStudyDate(),
        ];
    }
}
