import { QuizQuestion } from '../../types/quiz';

type QuizQuestionCardProps = {
    question: QuizQuestion;
    // まだ回答していなければ null。
    selectedWordId: number | null;
    onSelect: (wordId: number) => void;
    onNext: () => void;
    isLast: boolean;
    submitting: boolean;
}

export function QuizQuestionCard({
    question, selectedWordId, onSelect, onNext, isLast, submitting,
}: QuizQuestionCardProps) {
    const answered = selectedWordId !== null;

    // 回答後の色分け。正解は必ず緑、選んだ誤答だけ赤にし、残りは据え置く。
    const choiceClass = (wordId: number) => {
        if (!answered) return 'bg-surface hover:bg-surface-hover';
        if (wordId === question.answer_word_id) return 'bg-success-soft text-success-soft-foreground';
        if (wordId === selectedWordId) return 'bg-danger-soft text-danger-soft-foreground';
        return 'bg-surface';
    };

    return (
        <div className="bg-surface rounded shadow-md border border-line-subtle p-4 mt-4">
            <p className="text-xl md:text-2xl font-bold">{question.prompt}</p>

            {/* ja_to_en では答えの英単語が含まれるためサーバが null にして返す */}
            {question.sentence && (
                <p className="text-sm text-muted mt-2">{question.sentence}</p>
            )}

            <div className="grid gap-2 mt-4">
                {question.choices.map(choice => (
                    <button
                        key={choice.word_id}
                        type="button"
                        onClick={() => onSelect(choice.word_id)}
                        disabled={answered}
                        className={`border rounded border-line text-left p-2 md:p-3 ${choiceClass(choice.word_id)}`}
                    >
                        {choice.text}
                    </button>
                ))}
            </div>

            {answered && (
                <button
                    type="button"
                    onClick={onNext}
                    disabled={submitting}
                    className="mt-4 bg-blue-500 hover:bg-blue-700 text-white rounded p-2 disabled:opacity-50"
                >
                    {isLast ? (submitting ? 'Submitting...' : 'See Results') : 'Next'}
                </button>
            )}
        </div>
    );
}
