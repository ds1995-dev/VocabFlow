"use client";
import { useState, useEffect } from 'react';
import { CategoriesHeader } from '../../components/categories/CategoriesHeader';
import { CategoriesStatCard } from '../../components/categories/CategoriesStatCard';
import { CategoryForm } from '../../components/categories/CategoryForm';
import { Word } from '../../types/word';
import { Category } from '../../types/category';
import { CategoryList } from '../../components/categories/CategoryList';
import { apiFetch } from '../../lib/api';

export default function CategoriesPage() {
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [categories, setCategories] = useState<Category[]>([]);
    const [words, setWords] = useState<Word[]>([]);
    const categoriesStats = [
        { title: 'Total Categories', value: categories.length },
        { title: 'Total Words', value: words.length },
        {
            title: 'Most used Category', value: categories.length > 0
                ? categories.reduce((prev, current) => {
                    const prevCount = words.filter(word => word.category_id === prev.id).length;
                    const currentCount = words.filter(word => word.category_id === current.id).length;
                    return (prevCount > currentCount) ? prev : current;
                }, categories[0]).name
                : 'N/A'
        }
    ];

    useEffect(() => {
        const fetchCategories = async () => {
            try {
                setLoading(true);
                const response = await apiFetch('/api/words');
                const categoriesResponse = await apiFetch('/api/categories');
                const data = await response.json();
                const categoriesData = await categoriesResponse.json();
                setWords(data);
                setCategories(categoriesData);;
            } catch (err) {
                setError((err as Error).message);
            } finally {
                setLoading(false);
            }
        }
        fetchCategories();
    }, []);

    const handleAddCategory = async (name: string) => {
        try {
            setLoading(true);
            setError(null);
            const response = await apiFetch('/api/categories', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ name })
            });
            const data = await response.json();
            setCategories(prevCategories => [...prevCategories, data]);
        } catch (err) {
            setError((err as Error).message);
        } finally {
            setLoading(false);
        }
    }
    const handleUpdateCategory = async (id: number, newName: string) => {
        try {
            setLoading(true);
            setError(null);
            const response = await apiFetch(`/api/categories/${id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({name: newName})
            });

            if (!response.ok) throw new Error('Failed to update');

            const updatedCategory = await response.json();

            setCategories(prev =>
                prev.map(cat => cat.id === id ? updatedCategory : cat)
            );
        } catch (err) {
            setError((err as Error).message);
            throw err;
        } finally {
            setLoading(false);
        }
    }
    const handleDeleteCategory = async (id: number) => {
        try {
            setLoading(true);
            await apiFetch(`/api/categories/${id}`, {
                method: 'DELETE',
            });
            setCategories(prev => prev.filter(category => category.id !== id));
        } catch (err) {
            setError((err as Error).message);
        } finally {
            setLoading(false);
        }
    };
    return (
        <main className="flex-1 min-w-0 p-4">
            <CategoriesHeader />
            <div className="flex justify-content gap-4 mt-4">
                {categoriesStats.map((stat) => (
                    <CategoriesStatCard key={stat.title} title={stat.title} value={stat.value} />
                ))}
            </div>
            <CategoryForm onSubmit={handleAddCategory} />
            <CategoryList
                categories={categories}
                words={words}
                onDelete={handleDeleteCategory}
                onUpdate={handleUpdateCategory}
            />
            {loading && <p>Loading...</p>}
            {error && <p className="text-red-500">Error: {error}</p>}
        </main>
    );
}