import { useState } from 'react';
import { Category } from '../../types/category';
import { Pencil, Trash2, Check, X } from 'lucide-react';

type CategoryRowProps = {
    category: Category;
    wordCount: number;
    onDelete: (id: number) => void;
    onUpdate: (id: number, newName: string) => Promise<void>;
};

export function CategoryRow({ category, wordCount, onDelete, onUpdate }: CategoryRowProps) {
    const [isEditing, setIsEditing] = useState(false);
    const [editingName, setEditingName] = useState(category.name);
    const [isSaving, setIsSaving] = useState(false);

    const handleSave = async () => {
        if (!editingName.trim()) {
            alert('Category name cannot be empty');
            return;
        }

        try {
            setIsSaving(true);
            await onUpdate(category.id, editingName);
            setIsEditing(false);
        } catch {
            alert('Failed to update category');
            setEditingName(category.name);
        } finally {
            setIsSaving(false);
        }
    };

        const handleCancel = () => {
            setEditingName(category.name);
            setIsEditing(false);
        };

        const handleKeyDown = (e: React.KeyboardEvent) => {
            if (e.key === 'Enter') {
                handleSave();
            } else if (e.key === 'Escape') {
                handleCancel();
            }
        };
    return (
        <tr className="border-b border-gray-100 hover:bg-gray-50">
            <td className="py-3 flex items-center gap-2 font-bold">
                <span className="w-3 h-3 rounded bg-blue-500 inline-block" />
                {isEditing ? (
                    <input
                        type="text"
                        value={editingName}
                        onChange={(e) => setEditingName(e.target.value)}
                        onKeyDown={handleKeyDown}
                        className="border rounded border-gray-300 p-1 flex-1"
                        autoFocus
                        disabled={isSaving}
                    />
                ) : (
                    <span>{category.name}</span>
                )}
            </td>
            <td className="py-3">
                <span className="rounded-full bg-blue-100 px-3 py-1 text-sm font-bold text-blue-700">
                    {wordCount} words
                </span>
            </td>
            <td className="py-3">
                <div className="flex items-center justify-end gap-2">
                    {isEditing ? (
                        <>
                        <button
                                type="button"
                                onClick={handleSave}
                                disabled={isSaving}
                                className="text-green-500 hover:bg-green-50 border border-gray-200 rounded p-2 disabled:opacity-50"
                                aria-label="Save category"
                            >
                                <Check size={16} />
                            </button>
                            <button
                                type="button"
                                onClick={handleCancel}
                                disabled={isSaving}
                                className="text-gray-500 hover:bg-gray-50 border border-gray-200 rounded p-2 disabled:opacity-50"
                                aria-label="Cancel editing"
                            >
                                <X size={16} />
                                </button>
                                </>
                    ) : (
                        <>
                        <button
                                type="button"
                                onClick={() => setIsEditing(true)}
                                className="text-blue-500 hover:bg-blue-50 border border-gray-200 rounded p-2"
                                aria-label="Edit category"
                            >
                                <Pencil size={16} />
                            </button>
                            {/* 削除ボタン */}
                            <button
                                type="button"
                                onClick={() => onDelete(category.id)}
                                className="text-red-500 hover:bg-red-50 border border-gray-200 rounded p-2"
                                aria-label="Delete category"
                            >
                                <Trash2 size={16} />
                            </button>
                        </>
                    )}
                </div>
            </td>
        </tr>
    );
}
