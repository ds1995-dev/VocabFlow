import Link from 'next/link';

type ReviewBannerProps = {
    // summary 取得前・取得失敗時は null。件数が確定するまで描画しない。
    dueCount: number | null;
}

// 今日の復習件数を出し、クイズ画面への導線にするバナー。
// stats グリッドは grid-cols-2 lg:grid-cols-4 なので5枚目のカードにはせず、独立したバナーにしている。
export function ReviewBanner({ dueCount }: ReviewBannerProps) {
    // 取得前に「0件」と出してしまうのを避ける。
    if (dueCount === null) return null;

    const hasDue = dueCount > 0;

    return (
        <Link
            href="/quiz"
            className={
                hasDue
                    ? 'flex items-center justify-between gap-2 mt-4 p-4 rounded shadow-md bg-accent-soft text-accent-soft-foreground hover:opacity-90'
                    : 'flex items-center justify-between gap-2 mt-4 p-4 rounded shadow-md bg-surface border border-line-subtle hover:bg-surface-hover'
            }
        >
            {hasDue ? (
                <>
                    <span>
                        <span className="font-bold">Review today</span>
                        <span className="block text-sm">
                            {dueCount} {dueCount === 1 ? 'word' : 'words'} due
                        </span>
                    </span>
                    <span className="text-sm font-bold whitespace-nowrap">Start quiz →</span>
                </>
            ) : (
                <>
                    <span>
                        <span className="font-bold">Nothing due today</span>
                        <span className="block text-sm text-muted">You are all caught up</span>
                    </span>
                    <span className="text-sm font-bold text-muted whitespace-nowrap">Practice in Random mode →</span>
                </>
            )}
        </Link>
    );
}
