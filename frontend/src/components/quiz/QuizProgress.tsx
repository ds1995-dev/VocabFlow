type QuizProgressProps = {
    current: number;
    total: number;
    correct: number;
}

export function QuizProgress({ current, total, correct }: QuizProgressProps) {
    const percent = total === 0 ? 0 : Math.round((current / total) * 100);

    return (
        <div className="bg-surface rounded shadow-md border border-line-subtle p-4 mt-4">
            <div className="flex justify-between text-sm">
                <p className="font-bold">Question {current} / {total}</p>
                <p className="text-muted">Correct: {correct}</p>
            </div>
            <div className="h-2 w-full bg-surface-hover rounded mt-2">
                <div
                    className="h-2 bg-blue-500 rounded"
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}
