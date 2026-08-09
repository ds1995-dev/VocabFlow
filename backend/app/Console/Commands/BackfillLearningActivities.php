<?php

namespace App\Console\Commands;

use App\Enums\ActivityType;
use App\Models\LearningActivity;
use App\Models\Word;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class BackfillLearningActivities extends Command
{
    protected $signature = 'study:backfill-activities';

    protected $description = '既存の単語から word_created の学習アクティビティを生成する（再実行しても重複しない）';

    public function handle(): int
    {
        $created = 0;
        $skipped = 0;

        Word::query()->chunkById(100, function (Collection $words) use (&$created, &$skipped) {
            // 生成済みの単語を 1 クエリでまとめて引き、冪等性を保つ
            $backfilled = LearningActivity::query()
                ->where('type', ActivityType::WordCreated)
                ->whereIn('word_id', $words->pluck('id'))
                ->pluck('word_id')
                ->all();

            foreach ($words as $word) {
                if (in_array($word->id, $backfilled)) {
                    $skipped++;

                    continue;
                }

                $activity = new LearningActivity([
                    'word_id' => $word->id,
                    'type' => ActivityType::WordCreated,
                    // 単語を登録した日時を学習日に読み替える
                    'studied_on' => LearningActivity::studyDateFor($word->created_at),
                ]);

                // user_id は $fillable 外なので直接代入する。
                // 記録日時も単語の作成時刻に揃えたいので timestamps を明示的に上書きする。
                $activity->user_id = $word->user_id;
                $activity->created_at = $word->created_at;
                $activity->updated_at = $word->created_at;
                $activity->save();

                $created++;
            }
        });

        $this->info("学習アクティビティを {$created} 件生成しました（生成済みのためスキップ: {$skipped} 件）。");

        return self::SUCCESS;
    }
}
