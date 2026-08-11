type CategoriesStatCardProps = {
    title: string;
    value: string | number;
};

export function CategoriesStatCard({ title, value }: CategoriesStatCardProps) {
    return (
        <div className="bg-surface rounded shadow p-2 md:p-4 w-full md:w-1/3">
            <h2 className="md:text-lg font-bold mb-2">{title}</h2>
            <p className="md:text-2xl font-bold">{value}</p>
        </div>
    )
}