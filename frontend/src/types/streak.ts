// GET /api/streak のレスポンス。
// 日付はすべてサーバ側で学習日タイムゾーンに換算済みの "YYYY-MM-DD" 文字列。
export type Streak = {
    current_streak: number;
    longest_streak: number;
    studied_today: boolean;
    last_studied_on: string | null;
    today: string;
}
