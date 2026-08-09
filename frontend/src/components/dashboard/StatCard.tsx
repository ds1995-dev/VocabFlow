type StatCardProps = {
    title: string;
    // 取得前のストリークを '—' で表示するため文字列も受け付ける
    value: string | number;
    subtext: string;
}

export function StatCard({ title, value, subtext }: StatCardProps) {
    return (
            <div className="bg-white md:p-4 lg:p-6 rounded shadow-md w-full mr-4">
                <h2 className="md:text-lg font-bold mb-2">{title}</h2>
                <p className="md:text-2xl font-bold">{value}</p>
                <p className="text-gray-600">{subtext}</p>
            </div>
    )
}