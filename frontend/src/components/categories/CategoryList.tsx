import { Word } from '../../types/word';
import { Category } from '../../types/category';
import { CategoryRow } from './CategoryRow';

type CategoryListProps = {
    categories: Category[];
    words: Word[];
    onDelete: (id: number) => void;
    onUpdate: (id: number, newName: string) => Promise<void>;
};

export function CategoryList({ categories, words, onDelete, onUpdate }: CategoryListProps) {
    return (
        <div className="bg-surface rounded shadow p-4 mt-4">
            <h2 className="text-lg font-bold mb-4">Category List</h2>

            {categories.length === 0 ? (
                <p className="text-muted">No categories yet.</p>
            ) : (
                <table className="w-full text-left">
                    <thead>
                        <tr className="text-sm text-muted border-b border-line-subtle">
                            <th className="py-2 font-medium">Category Name</th>
                            <th className="py-2 font-medium">Words</th>
                            <th className="py-2 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {categories.map((category) => {
                            const wordCount = words.filter(
                                (word) => word.category_id === category.id
                            ).length;

                            return (
                                <CategoryRow
                                    key={category.id}
                                    category={category}
                                    wordCount={wordCount}
                                    onDelete={onDelete}
                                    onUpdate={onUpdate}
                                />
                            );
                        })}
                    </tbody>
                </table>
            )}
        </div>
    );
}
