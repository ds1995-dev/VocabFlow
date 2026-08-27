<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Models\Category;
use App\Models\LearningActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StreakTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 学習日を固定するため、日本時間 2026-08-06 12:00（UTC 03:00）を「今日」とする。
     */
    private function 日本時間の今日を2026年8月6日にする(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06 03:00:00', 'UTC'));
    }

    /**
     * 指定した学習日のアクティビティを 1 件作る。
     */
    private function 学習日を作る(User $user, string $date, ActivityType $type = ActivityType::WordCreated): void
    {
        LearningActivity::factory()->for($user)->create([
            'word_id' => null,
            'type' => $type,
            'studied_on' => $date,
        ]);
    }

    public function test_学習履歴がないユーザーはストリークが0になる(): void
    {
        $this->日本時間の今日を2026年8月6日にする();

        // 一度も学習していないユーザー
        $me = User::factory()->create();

        $response = $this->actingAsUser($me)->getJson('/api/streak');

        $response->assertStatus(200);
        $response->assertJson([
            'current_streak' => 0,
            'longest_streak' => 0,
            'studied_today' => false,
            'last_studied_on' => null,
            'today' => '2026-08-06',
        ]);
    }

    public function test_連続した3日間の学習でcurrent_streakは3になる(): void
    {
        $this->日本時間の今日を2026年8月6日にする();

        // 今日を含む 3 日連続で学習している
        $me = User::factory()->create();
        $this->学習日を作る($me, '2026-08-04');
        $this->学習日を作る($me, '2026-08-05');
        $this->学習日を作る($me, '2026-08-06');

        $response = $this->actingAsUser($me)->getJson('/api/streak');

        $response->assertStatus(200);
        $response->assertJsonPath('current_streak', 3);
        $response->assertJsonPath('studied_today', true);
    }

    public function test_1日空くとcurrent_streakが途切れる(): void
    {
        $this->日本時間の今日を2026年8月6日にする();

        // 8/3 は学習しておらず、8/4 以降だけが連続している
        $me = User::factory()->create();
        $this->学習日を作る($me, '2026-08-02');
        $this->学習日を作る($me, '2026-08-04');
        $this->学習日を作る($me, '2026-08-05');
        $this->学習日を作る($me, '2026-08-06');

        $response = $this->actingAsUser($me)->getJson('/api/streak');

        $response->assertStatus(200);
        // 8/2 は途切れているので数えず、8/4〜8/6 の 3 日だけ
        $response->assertJsonPath('current_streak', 3);
    }

    public function test_昨日まで学習していれば今日未学習でもストリークは維持される(): void
    {
        $this->日本時間の今日を2026年8月6日にする();

        // 今日（8/6）はまだ学習していないが、昨日まで 2 日続いている
        $me = User::factory()->create();
        $this->学習日を作る($me, '2026-08-04');
        $this->学習日を作る($me, '2026-08-05');

        $response = $this->actingAsUser($me)->getJson('/api/streak');

        $response->assertStatus(200);
        // 朝ログインした瞬間に 0 に見えないことがこの仕様の要
        $response->assertJsonPath('current_streak', 2);
        $response->assertJsonPath('studied_today', false);
        $response->assertJsonPath('last_studied_on', '2026-08-05');
    }

    public function test_最終学習が2日前ならcurrent_streakは0になる(): void
    {
        $this->日本時間の今日を2026年8月6日にする();

        // 最終学習が一昨日（8/4）なのでストリークは途切れている
        $me = User::factory()->create();
        $this->学習日を作る($me, '2026-08-03');
        $this->学習日を作る($me, '2026-08-04');

        $response = $this->actingAsUser($me)->getJson('/api/streak');

        $response->assertStatus(200);
        $response->assertJsonPath('current_streak', 0);
        // 途切れていても過去最長は残る
        $response->assertJsonPath('longest_streak', 2);
    }

    public function test_同じ日に複数回学習してもストリークは1日として数える(): void
    {
        $this->日本時間の今日を2026年8月6日にする();

        // 同じ日に 3 件記録する
        $me = User::factory()->create();
        $this->学習日を作る($me, '2026-08-06');
        $this->学習日を作る($me, '2026-08-06');
        $this->学習日を作る($me, '2026-08-06', ActivityType::WordLearned);

        $response = $this->actingAsUser($me)->getJson('/api/streak');

        $response->assertStatus(200);
        $response->assertJsonPath('current_streak', 1);
    }

    public function test_word_unlearnedだけの日はストリークに数えない(): void
    {
        $this->日本時間の今日を2026年8月6日にする();

        // 今日は学習済みの解除しかしていない
        $me = User::factory()->create();
        $this->学習日を作る($me, '2026-08-06', ActivityType::WordUnlearned);

        $response = $this->actingAsUser($me)->getJson('/api/streak');

        $response->assertStatus(200);
        // 取り消し操作でストリークを稼げてはいけない
        $response->assertJsonPath('current_streak', 0);
        $response->assertJsonPath('studied_today', false);
        $response->assertJsonPath('last_studied_on', null);
    }

    public function test_longest_streakは過去最長の連続日数を返す(): void
    {
        $this->日本時間の今日を2026年8月6日にする();

        // 過去に 4 日連続、直近は今日の 1 日だけ
        $me = User::factory()->create();
        $this->学習日を作る($me, '2026-07-20');
        $this->学習日を作る($me, '2026-07-21');
        $this->学習日を作る($me, '2026-07-22');
        $this->学習日を作る($me, '2026-07-23');
        $this->学習日を作る($me, '2026-08-06');

        $response = $this->actingAsUser($me)->getJson('/api/streak');

        $response->assertStatus(200);
        $response->assertJsonPath('current_streak', 1);
        $response->assertJsonPath('longest_streak', 4);
    }

    public function test_他人の学習アクティビティは自分のストリークに影響しない(): void
    {
        $this->日本時間の今日を2026年8月6日にする();

        // 他人は 3 日連続、自分は今日だけ
        $other = User::factory()->create();
        $this->学習日を作る($other, '2026-08-04');
        $this->学習日を作る($other, '2026-08-05');
        $this->学習日を作る($other, '2026-08-06');

        $me = User::factory()->create();
        $this->学習日を作る($me, '2026-08-06');

        $response = $this->actingAsUser($me)->getJson('/api/streak');

        $response->assertStatus(200);
        $response->assertJsonPath('current_streak', 1);
    }

    public function test_単語を削除しても学習した日の記録が残りストリークが変わらない(): void
    {
        $this->日本時間の今日を2026年8月6日にする();

        // 単語を 1 件登録してストリークを 1 にする
        $me = User::factory()->create();
        $myCategory = Category::factory()->for($me)->create();

        $created = $this->actingAsUser($me)->postJson('/api/words', [
            'word' => 'apple',
            'meaning' => 'りんご',
            'sentence' => 'I ate an apple.',
            'category_id' => $myCategory->id,
        ]);
        $created->assertStatus(201);

        $before = $this->actingAsUser($me)->getJson('/api/streak');
        $before->assertJsonPath('current_streak', 1);

        $this->actingAsUser($me)->deleteJson("/api/words/{$created->json('id')}")->assertStatus(200);

        $response = $this->actingAsUser($me)->getJson('/api/streak');

        $response->assertStatus(200);
        // word_id が nullOnDelete なので学習した事実は残る
        $response->assertJsonPath('current_streak', 1);
    }

    public function test_日本時間で同じ日に収まる2回の学習はストリーク1日と数える(): void
    {
        $me = User::factory()->create();

        // UTC 2026-08-05 15:30 は日本時間の 2026-08-06 00:30
        Carbon::setTestNow(Carbon::parse('2026-08-05 15:30:00', 'UTC'));
        LearningActivity::record($me, ActivityType::WordCreated);

        // UTC 2026-08-06 14:00 は日本時間の 2026-08-06 23:00 で同じ日
        Carbon::setTestNow(Carbon::parse('2026-08-06 14:00:00', 'UTC'));
        LearningActivity::record($me, ActivityType::WordCreated);

        $response = $this->actingAsUser($me)->getJson('/api/streak');

        $response->assertStatus(200);
        // UTC では 8/5 と 8/6 の 2 日にまたがるが、日本時間ではどちらも 8/6 なので 1 日
        $response->assertJsonPath('current_streak', 1);
        $response->assertJsonPath('last_studied_on', '2026-08-06');
    }

    public function test_studied_todayは日本時間の今日を基準に判定される(): void
    {
        // UTC ではまだ 8/5 だが、日本時間では既に 8/6 になっている時刻
        Carbon::setTestNow(Carbon::parse('2026-08-05 15:00:00', 'UTC'));

        $me = User::factory()->create();
        $this->学習日を作る($me, '2026-08-06');

        $response = $this->actingAsUser($me)->getJson('/api/streak');

        $response->assertStatus(200);
        // UTC の今日（8/5）で判定すると false になってしまう
        $response->assertJsonPath('studied_today', true);
        $response->assertJsonPath('today', '2026-08-06');
    }

    public function test_ストリーク_ap_iはlast_studied_onを日付形式で返す(): void
    {
        $this->日本時間の今日を2026年8月6日にする();

        // date:Y-m-d キャストの回帰テスト
        $me = User::factory()->create();
        $this->学習日を作る($me, '2026-08-06');

        $response = $this->actingAsUser($me)->getJson('/api/streak');

        $response->assertStatus(200);
        // 'date' キャストのままだと "2026-08-06T00:00:00.000000Z" になってしまう
        $response->assertJsonPath('last_studied_on', '2026-08-06');
    }
}
