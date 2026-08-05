import { Category } from '../../types/category';
type Filter = 'all' | 'learned' | 'unlearned';

type WordFilterProps = {
    search: string;
    setSearch: (search: string) => void;
    filter: Filter;
    onChange: (filter: Filter) => void;
    categories: Category[];
    categoryFilter: number | 'all';
    onCategoryChange: (value: number | 'all') => void;

}

export function WordFilter({
    search, setSearch, filter, onChange, categories, categoryFilter, onCategoryChange }: WordFilterProps) {
    return (
        <div className="bg-white rounded shadow-md border-gray-300 w-auto p-4">
            <h2 className="md:text-lg font-bold mr-4">Search & Filter</h2>
            <input
                className="border rounded border-gray-300 md:p-2 w-1/3"
                type="text"
                placeholder="Search..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
            />
            <div className="flex min-w-0 w-full md:gap-4 mt-2">
                <button onClick={() => onChange('all')} className={`border rounded border-gray-300 text-xs md:text-lg p-1 md:p-2 ${filter === 'all' ? 'bg-blue-500 text-white' : 'bg-white'}`}>All</button>
                <button onClick={() => onChange('learned')} className={`border rounded border-gray-300 text-xs md:text-lg p-1 md:p-2 ${filter === 'learned' ? 'bg-blue-500 text-white' : 'bg-white'}`}>Learned</button>
                <button onClick={() => onChange('unlearned')} className={`border rounded border-gray-300 text-xs md:text-lg p-1 md:p-2 ${filter === 'unlearned' ? 'bg-blue-500 text-white' : 'bg-white'}`}>Unlearned</button>
                <select
                    className="border rounded border-gray-300 text-xs md:text-lg p-1 md:p-2"
                    value={categoryFilter}
                    onChange={(e) => {
                        const value = e.target.value;

                        onCategoryChange(
                            value === 'all'
                                ? 'all'
                                : Number(value)
                        );
                    }}
                >
                    <option value='all'>All Categories</option>
                    {categories.map(category => (
                        <option
                            key={category.id}
                            value={category.id}
                        >
                            {category.name}
                        </option>
                    ))}
                </select>
            </div>
        </div >
    )
}