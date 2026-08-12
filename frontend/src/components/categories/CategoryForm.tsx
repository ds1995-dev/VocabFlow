"use client";
import { useState } from 'react';

type CategoryFormProps = {
    onSubmit: (name: string) => void;
}

export function CategoryForm({ onSubmit }: CategoryFormProps) {
    const [name, setName] = useState('');
    const [categoryMessage, setCategoryMessage] = useState('');
    const [categoryError, setCategoryError] = useState('');

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!name.trim()) {
            setCategoryError('Please enter a category name');
            return;
        }

        try {
            await onSubmit(name);
            setCategoryMessage("Category added successfully!");
            setCategoryError("");
            setName("");
        } catch {
            setCategoryError("Failed to add category.");
            setCategoryMessage("");
        }
    };

    return (
        <form className="bg-surface rounded shadow-md border-line p-4 space-y-4 mt-4" onSubmit={handleSubmit}>
            <h2 className="text-md md:text-lg font-bold">Add a new Category</h2>
            <div className="md:flex w-full min-w-0">
                <input className="border rounded border-line bg-surface text-foreground md:p-2" type="text" value={name} onChange={(e) => setName(e.target.value)} placeholder="Category Name" required />
                <button className="bg-blue-500 text-white text-sm md:text-lg rounded p-1 md:p-2" type="submit">Add Category</button>
                {categoryMessage && <p className="text-green-500">{categoryMessage}</p>}
                {categoryError && <p className="text-red-500">{categoryError}</p>}
            </div>
        </form>
    )
}