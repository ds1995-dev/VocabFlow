<?php

namespace Database\Factories;

use App\Models\LearningActivity;
use App\Models\User;
use App\Models\Word;
use App\Models\WordReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WordReview>
 */
class WordReviewFactory extends Factory
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
            'box' => 0,
            // LearningActivityFactory と同じ理由で、アプリの timezone（UTC）ではなく
            // 学習日の今日を既定にする。UTC の今日だと JST 早朝のテストで1日ずれる。
            'due_on' => LearningActivity::currentStudyDate(),
            'last_reviewed_on' => null,
            'correct_count' => 0,
            'incorrect_count' => 0,
        ];
    }
}
