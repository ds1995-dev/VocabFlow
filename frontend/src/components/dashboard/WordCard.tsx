import React from 'react';
import { Word } from '../../types/word';
import { Category } from '../../types/category';
import { Trash2 } from 'lucide-react';
import { Circle } from 'lucide-react';
import { CircleCheck } from 'lucide-react';

type WordCardProps = {
    word: Word;
    category: Category[];
    onDelete: (id: number) => void;
    onToggleLearned: (id: number) => void;
}

export function WordCard({ word, onDelete, onToggleLearned }: WordCardProps) {
    return (
        <div className="flex w-full w-min-0 justify-between border rounded border-line bg-surface p-4">
            <div className="mr-10">
                <h2 className="md:text-xl font-bold">{word.word}</h2>
                <p className="text-xs md:text-lg">{word.meaning}</p>
                {word.sentence && <p className='text-xs md:text-sm'>{word.sentence}</p>}
            </div>
            <div className="flex items-center gap-8">
                <div className="flex items-center gap-4">
                    {word.is_learned && <span className="inline-flex items-center rounded-full bg-success-soft px-3 py-1 text-sm font-bold text-success-soft-foreground">Learned</span>}
                    <span className="rounded-full bg-chip px-3 py-1 text-sm font-bold text-chip-foreground">{word.category.name}</span>
                </div>
                <div className="flex items-center gap-2">
                    <button className="bg-blue-500 hover:bg-blue-700 text-white md:font-bold md:py-1 md:px-2 rounded ml-2" onClick={() => onToggleLearned(word.id)}>
                        {word.is_learned ? <CircleCheck size={16} /> : <Circle size={16} />}
                    </button>
                    <button className="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded" onClick={() => onDelete(word.id)}>
                        <Trash2 size={16} />
                    </button>
                </div>
            </div>
        </div>
    );
}