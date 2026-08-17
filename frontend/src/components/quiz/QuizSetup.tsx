import { Category } from '../../types/category';
import { QuizDirection, QuizMode, QuizSummary } from '../../types/quiz';

// 出題数の選択肢。既定の 10 はバックエンドの config('quiz.default_question_count') に合わせている。
const COUNT_OPTIONS = [5, 10, 20];

const MODE_LABELS: { value: QuizMode; label: string }[] = [
    { value: 'review', label: 'Review' },
    { value: 'random', label: 'Random' },
    { value: 'category', label: 'By Category' },
];

const DIRECTION_LABELS: { value: QuizDirection; label: string }[] = [
    { value: 'mixed', label: 'Mixed' },
    { value: 'en_to_ja', label: 'English → Japanese' },
    { value: 'ja_to_en', label: 'Japanese → English' },
];

type QuizSetupProps = {
    summary: QuizSummary | null;
    categories: Category[];
    mode: QuizMode;
    onModeChange: (mode: QuizMode) => void;
    categoryId: number | null;
    onCategoryChange: (categoryId: number | null) => void;
    direction: QuizDirection;
    onDirectionChange: (direction: QuizDirection) => void;
    count: number;
    onCountChange: (count: number) => void;
    onStart: () => void;
    loading: boolean;
}

export function QuizSetup({
    summary, categories, mode, onModeChange, categoryId, onCategoryChange,
    direction, onDirectionChange, count, onCountChange, onStart, loading,
}: QuizSetupProps) {
    // カテゴリー出題はカテゴリーを選ぶまで始められない。
    const missingCategory = mode === 'category' && categoryId === null;

    return (
        <div className="bg-surface rounded shadow-md border border-line-subtle p-4 mt-4">
            <h2 className="md:text-lg font-bold">Start a Quiz</h2>

            {summary && (
                <p className="text-sm text-muted mt-2">
                    Due today: <span className="font-bold">{summary.due_count}</span>
                    {' · '}
                    New: <span className="font-bold">{summary.new_count}</span>
                    {' · '}
                    {summary.today}
                </p>
            )}

            <div className="mt-4">
                <p className="text-sm font-bold">Range</p>
                <div className="flex flex-wrap gap-2 mt-2">
                    {MODE_LABELS.map(({ value, label }) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => onModeChange(value)}
                            className={`border rounded border-line text-xs md:text-base p-1 md:p-2 ${mode === value ? 'bg-blue-500 text-white' : 'bg-surface'}`}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>

            {mode === 'category' && (
                <div className="mt-4">
                    <p className="text-sm font-bold">Category</p>
                    <select
                        className="border rounded border-line bg-surface text-foreground text-xs md:text-base p-1 md:p-2 mt-2"
                        value={categoryId ?? ''}
                        onChange={(e) => {
                            const value = e.target.value;
                            onCategoryChange(value === '' ? null : Number(value));
                        }}
                    >
                        <option value="">Select a category</option>
                        {categories.map(category => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                </div>
            )}

            <div className="mt-4">
                <p className="text-sm font-bold">Direction</p>
                <select
                    className="border rounded border-line bg-surface text-foreground text-xs md:text-base p-1 md:p-2 mt-2"
                    value={direction}
                    onChange={(e) => onDirectionChange(e.target.value as QuizDirection)}
                >
                    {DIRECTION_LABELS.map(({ value, label }) => (
                        <option key={value} value={value}>{label}</option>
                    ))}
                </select>
            </div>

            <div className="mt-4">
                <p className="text-sm font-bold">Questions</p>
                <div className="flex flex-wrap gap-2 mt-2">
                    {COUNT_OPTIONS.map(option => (
                        <button
                            key={option}
                            type="button"
                            onClick={() => onCountChange(option)}
                            className={`border rounded border-line text-xs md:text-base p-1 md:p-2 ${count === option ? 'bg-blue-500 text-white' : 'bg-surface'}`}
                        >
                            {option}
                        </button>
                    ))}
                </div>
            </div>

            <button
                type="button"
                onClick={onStart}
                disabled={loading || missingCategory}
                className="mt-6 bg-blue-500 hover:bg-blue-700 text-white rounded p-2 disabled:opacity-50"
            >
                {loading ? 'Loading...' : 'Start Quiz'}
            </button>

            {missingCategory && (
                <p className="text-sm text-muted mt-2">Select a category to start.</p>
            )}
        </div>
    );
}
