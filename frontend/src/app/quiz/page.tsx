"use client";
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { apiFetch } from '../../lib/api';
import { Category } from '../../types/category';
import {
    QuizAnswer,
    QuizAnswersResponse,
    QuizDirection,
    QuizMode,
    QuizQuestion,
    QuizQuestionsResponse,
    QuizSummary,
} from '../../types/quiz';
import { QuizSetup } from '../../components/quiz/QuizSetup';
import { QuizProgress } from '../../components/quiz/QuizProgress';
import { QuizQuestionCard } from '../../components/quiz/QuizQuestionCard';
import { QuizResultView } from '../../components/quiz/QuizResultView';

type Phase = 'setup' | 'playing' | 'result';

export default function QuizPage() {
    const router = useRouter();

    const [phase, setPhase] = useState<Phase>('setup');
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    // 「今日の復習はなし」など、エラーではない案内。
    const [notice, setNotice] = useState<string | null>(null);

    const [summary, setSummary] = useState<QuizSummary | null>(null);
    const [categories, setCategories] = useState<Category[]>([]);

    const [mode, setMode] = useState<QuizMode>('random');
    const [categoryId, setCategoryId] = useState<number | null>(null);
    const [direction, setDirection] = useState<QuizDirection>('mixed');
    const [count, setCount] = useState(10);

    const [questions, setQuestions] = useState<QuizQuestion[]>([]);
    const [index, setIndex] = useState(0);
    const [answers, setAnswers] = useState<QuizAnswer[]>([]);
    const [result, setResult] = useState<QuizAnswersResponse | null>(null);

    useEffect(() => {
        const fetchSetupData = async () => {
            try {
                setLoading(true);
                const [summaryResponse, categoriesResponse] = await Promise.all([
                    apiFetch('/api/quiz/summary'),
                    apiFetch('/api/categories'),
                ]);
                const summaryData: QuizSummary = await summaryResponse.json();
                const categoriesData: Category[] = await categoriesResponse.json();

                setSummary(summaryData);
                setCategories(categoriesData);

                // クエリパラメータではなくここで初期モードを決める。
                // useSearchParams は Next.js 16 で Suspense 境界を要求するため使わない。
                if (summaryData.due_count > 0) {
                    setMode('review');
                }
            } catch (err) {
                setError((err as Error).message);
            } finally {
                setLoading(false);
            }
        };
        fetchSetupData();
    }, []);

    const handleStart = async () => {
        try {
            setLoading(true);
            setError(null);
            setNotice(null);

            const params = new URLSearchParams({
                mode,
                direction,
                count: String(count),
            });
            if (mode === 'category' && categoryId !== null) {
                params.set('category_id', String(categoryId));
            }

            const response = await apiFetch(`/api/quiz/questions?${params.toString()}`);

            // 単語が 4 件未満だと 4 択が組めないのでサーバが 422 を返す。
            if (!response.ok) {
                setError('Could not start the quiz. You need at least 4 words registered.');
                return;
            }

            const data: QuizQuestionsResponse = await response.json();

            // 復習対象も未出題も 0 件。エラーではなく「今日は復習なし」という正常な状態。
            if (data.questions.length === 0) {
                setNotice('Nothing to review right now. Try Random mode or add more words.');
                return;
            }

            setQuestions(data.questions);
            setIndex(0);
            setAnswers([]);
            setResult(null);
            setPhase('playing');
        } catch (err) {
            setError((err as Error).message);
        } finally {
            setLoading(false);
        }
    };

    // 現在の問題に対する回答（未回答なら null）。
    const currentQuestion = questions[index];
    const currentAnswer = currentQuestion
        ? answers.find(answer => answer.word_id === currentQuestion.word_id) ?? null
        : null;

    const handleSelect = (wordId: number) => {
        if (!currentQuestion || currentAnswer) return;

        setAnswers(prev => [
            ...prev,
            { word_id: currentQuestion.word_id, selected_word_id: wordId },
        ]);
    };

    const correctCount = answers.filter(answer => {
        const question = questions.find(q => q.word_id === answer.word_id);
        return question ? question.answer_word_id === answer.selected_word_id : false;
    }).length;

    const submitAnswers = async (submitted: QuizAnswer[]) => {
        // 空配列はサーバ側で 422 になるので送らない。
        if (submitted.length === 0) return;

        try {
            setSubmitting(true);
            setError(null);

            const response = await apiFetch('/api/quiz/answers', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ answers: submitted }),
            });

            if (!response.ok) throw new Error('Failed to save answers');

            const data: QuizAnswersResponse = await response.json();
            setResult(data);
            // 送信済みの回答は捨てる。残しておくと二重送信で box が 2 段上がる。
            setAnswers([]);
            setPhase('result');
        } catch (err) {
            setError((err as Error).message);
        } finally {
            setSubmitting(false);
        }
    };

    const handleNext = () => {
        if (index + 1 < questions.length) {
            setIndex(index + 1);
            return;
        }
        submitAnswers(answers);
    };

    const handleRetry = () => {
        setQuestions([]);
        setIndex(0);
        setAnswers([]);
        setResult(null);
        setNotice(null);
        setPhase('setup');
    };

    return (
        <main className="flex-1 min-w-0 p-4">
            <h1 className="text-xl md:text-2xl font-bold">Quiz</h1>

            {phase === 'setup' && (
                <QuizSetup
                    summary={summary}
                    categories={categories}
                    mode={mode}
                    onModeChange={setMode}
                    categoryId={categoryId}
                    onCategoryChange={setCategoryId}
                    direction={direction}
                    onDirectionChange={setDirection}
                    count={count}
                    onCountChange={setCount}
                    onStart={handleStart}
                    loading={loading}
                />
            )}

            {phase === 'playing' && currentQuestion && (
                <>
                    <QuizProgress
                        current={index + 1}
                        total={questions.length}
                        correct={correctCount}
                    />
                    <QuizQuestionCard
                        question={currentQuestion}
                        selectedWordId={currentAnswer?.selected_word_id ?? null}
                        onSelect={handleSelect}
                        onNext={handleNext}
                        isLast={index + 1 === questions.length}
                        submitting={submitting}
                    />
                    {/*
                      * 中断して結果を見る導線。採点はセッション終了時の一括送信なので、
                      * これが無いと途中離脱で回答が全部消える。回答済みが 0 件なら
                      * 送る中身が無いうえサーバも 422 にするため無効化する。
                      */}
                    <button
                        type="button"
                        onClick={() => submitAnswers(answers)}
                        disabled={answers.length === 0 || submitting}
                        className="mt-4 border rounded border-line bg-surface hover:bg-surface-hover p-2 disabled:opacity-50"
                    >
                        Finish &amp; See Results
                    </button>
                </>
            )}

            {phase === 'result' && result && (
                <QuizResultView
                    result={result}
                    onRetry={handleRetry}
                    onBackToDashboard={() => router.push('/')}
                />
            )}

            {notice && <p className="text-muted mt-4">{notice}</p>}
            {error && <p className="text-danger-soft-foreground mt-4">Error: {error}</p>}
        </main>
    );
}
