import { QuizAnswersResponse } from '../../types/quiz';

type QuizResultViewProps = {
    result: QuizAnswersResponse;
    onRetry: () => void;
    onBackToDashboard: () => void;
}

export function QuizResultView({ result, onRetry, onBackToDashboard }: QuizResultViewProps) {
    return (
        <div className="bg-surface rounded shadow-md border border-line-subtle p-4 mt-4">
            <h2 className="md:text-lg font-bold">Results</h2>

            <p className="text-2xl font-bold mt-2">
                {result.correct} / {result.results.length}
            </p>

            {/* 削除済みの単語などで適用されなかった分がある場合だけ説明を出す */}
            {result.skipped > 0 && (
                <p className="text-sm text-muted mt-1">
                    {result.skipped} answer(s) were skipped because the word no longer exists.
                </p>
            )}

            <ul className="mt-4 divide-y divide-line-subtle">
                {result.results.map(row => (
                    <li key={row.word_id} className="py-2 flex flex-wrap items-center gap-2">
                        <span
                            className={`rounded text-xs font-bold px-2 py-1 ${row.correct ? 'bg-success-soft text-success-soft-foreground' : 'bg-danger-soft text-danger-soft-foreground'}`}
                        >
                            {row.correct ? 'Correct' : 'Wrong'}
                        </span>
                        <span className="font-bold">{row.word}</span>
                        <span className="text-muted">{row.meaning}</span>
                        <span className="text-xs text-muted ml-auto">
                            Box {row.box} · Next: {row.due_on}
                        </span>
                        {row.is_learned && (
                            <span className="rounded text-xs font-bold px-2 py-1 bg-success-soft text-success-soft-foreground">
                                Learned
                            </span>
                        )}
                    </li>
                ))}
            </ul>

            <div className="flex gap-2 mt-6">
                <button
                    type="button"
                    onClick={onRetry}
                    className="bg-blue-500 hover:bg-blue-700 text-white rounded p-2"
                >
                    Try Again
                </button>
                <button
                    type="button"
                    onClick={onBackToDashboard}
                    className="border rounded border-line bg-surface hover:bg-surface-hover p-2"
                >
                    Back to Dashboard
                </button>
            </div>
        </div>
    );
}
