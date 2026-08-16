// クイズ関連 API のレスポンス。
// 日付はすべてサーバ側で学習日タイムゾーンに換算済みの "YYYY-MM-DD" 文字列。

// 出題範囲。category のときだけ category_id が要る。
export type QuizMode = 'category' | 'random' | 'review';

// セッション開始時に指定する出題方向。
export type QuizDirection = 'en_to_ja' | 'ja_to_en' | 'mixed';

// 1 問ごとの出題方向。mixed はサーバ側で解決済みなのでここには現れない。
export type QuestionDirection = 'en_to_ja' | 'ja_to_en';

export type QuizChoice = {
    word_id: number;
    text: string;
}

export type QuizQuestion = {
    word_id: number;
    direction: QuestionDirection;
    prompt: string;
    // ja_to_en では答えの英単語が露出するのでサーバが null にして返す。
    sentence: string | null;
    choices: QuizChoice[];
    answer_word_id: number;
}

// GET /api/quiz/questions のレスポンス。
export type QuizQuestionsResponse = {
    mode: QuizMode;
    direction: QuizDirection;
    questions: QuizQuestion[];
}

// POST /api/quiz/answers に送る 1 問分。未選択のまま進んだ場合は selected_word_id が null。
export type QuizAnswer = {
    word_id: number;
    selected_word_id: number | null;
}

export type QuizResultRow = {
    word_id: number;
    word: string;
    meaning: string;
    correct: boolean;
    box: number;
    due_on: string;
    is_learned: boolean;
}

// POST /api/quiz/answers のレスポンス。
// total = results.length + skipped が常に成立する（skipped は削除済み・他人の単語と重複の分）。
export type QuizAnswersResponse = {
    total: number;
    correct: number;
    skipped: number;
    results: QuizResultRow[];
}

// GET /api/quiz/summary のレスポンス。
export type QuizSummary = {
    due_count: number;
    new_count: number;
    today: string;
}
