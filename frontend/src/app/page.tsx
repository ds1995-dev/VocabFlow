"use client";
import { useState, useEffect } from 'react';
import { Word } from '../types/word';
import { Category } from '../types/category';
import { Streak } from '../types/streak';
import { Header } from '../components/dashboard/Header';
import { StatCard } from '../components/dashboard/StatCard';
import { WordForm } from '../components/dashboard/WordForm';
import { WordCard } from '../components/dashboard/WordCard';
import { WordFilter } from '../components/dashboard/WordFilter';
import { apiFetch } from '../lib/api';

export default function Home() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [words, setWords] = useState<Word[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [streak, setStreak] = useState<Streak | null>(null);
  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState<number | 'all'>('all')
  const [filter, setFilter] = useState<'all' | 'learned' | 'unlearned'>('all');
  const stats = [
    { title: 'Total Words', value: words.length, subtext: 'Total words' },
    { title: 'Learned', value: words.filter(w => w.is_learned).length, subtext: 'Words' },
    { title: 'Categories', value: categories.length, subtext: 'categories' },
    { title: 'Streak', value: streak?.current_streak ?? '—', subtext: 'days' }
  ];

  // ストリークは学習日タイムゾーンを基準にサーバ側で集計するため、都度取得する。
  // 取得に失敗しても単語一覧の表示は妨げない。
  const refreshStreak = async () => {
    try {
      const response = await apiFetch('/api/streak');
      if (response.ok) {
        setStreak(await response.json());
      }
    } catch {
      // ストリークは補助的な指標なのでエラーは表示しない
    }
  };

  useEffect(() => {
    const fetchWords = async () => {
      try {
        setLoading(true);
        const response = await apiFetch('/api/words');
        const categoriesResponse = await apiFetch('/api/categories');
        const data = await response.json();
        const categoriesData = await categoriesResponse.json();
        setWords(data);
        setCategories(categoriesData);
        await refreshStreak();
      } catch (err) {
        setError((err as Error).message);
      } finally {
        setLoading(false);
      }
    }
    fetchWords();
  }, []);


  const handleAddWord = async (word: string, meaning: string, sentence: string, categoryId: number) => {
    try {
      setLoading(true);
      setError(null);
      const response = await apiFetch('/api/words', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ word, meaning, sentence, category_id: categoryId })
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.message || 'Failed to add word');
      }
      setWords((prevWords) => [...prevWords, data]);
      // その日はじめての学習ならストリークが伸びる。await しないので一覧の反映は遅れない。
      refreshStreak();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setLoading(false);
    }
  };

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
      setCategories((prevCategories) => [...prevCategories, data]);
      return data;
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setLoading(false);
    }
  }

  const handleDeleteWord = async (id: number) => {
    try {
      setLoading(true);
      await apiFetch(`/api/words/${id}`, {
        method: 'DELETE',
      });
      setWords((prevWords) => prevWords.filter(word => word.id !== id));
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setLoading(false);
    }
  };

  const handleToggleLearned = async (id: number) => {
    try {
      setLoading(true);
      const response = await apiFetch(`/api/words/${id}/toggle-learned`, {
        method: 'PATCH',
      });
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Failed to toggle learned status');
      }
      setWords((prevWords) => prevWords.map(word => word.id === id ? data : word));
      // 学習済みへの切り替えもストリークに数えるので取り直す
      refreshStreak();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setLoading(false);
    }
  }

  const filteredWords = words.filter(word => {
    const matchesSearch = word.word.toLowerCase().includes(search.toLowerCase()) || word.meaning.toLowerCase().includes(search.toLowerCase()) || (word.sentence?.toLowerCase().includes(search.toLowerCase()) ?? false);
    const matchesFilter = filter === 'all' || (filter === 'learned' && word.is_learned) || (filter === 'unlearned' && !word.is_learned);
    const matchesCategory = categoryFilter === 'all' || word.category_id === categoryFilter;
    return matchesSearch && matchesFilter && matchesCategory;
  });

  return (
      <main className="flex-1 min-w-0 p-4">
        <Header />
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-2 mt-4">
          {stats.map((stat) => (
            <StatCard title={stat.title} value={stat.value} subtext={stat.subtext} key={stat.title} />
          ))}
        </div>
        <div className="flex-1 min-w-0 justify-center mt-4">
          <WordForm onSubmit={handleAddWord} categories={categories} onCreateCategory={handleAddCategory} />
        </div>
        <div className="flex-1 min-w-0 justify-center p-4">
          <WordFilter
            search={search}
            setSearch={setSearch}
            filter={filter}
            onChange={setFilter}
            categories={categories}
            categoryFilter={categoryFilter}
            onCategoryChange={setCategoryFilter}
          />
        </div>

        {filteredWords.length === 0 ? (
          <p className="text-2xl flex justify-center">No words found</p>
        ) : (
          filteredWords.map(word => (
            <WordCard key={word.id} word={word} category={categories} onDelete={handleDeleteWord} onToggleLearned={handleToggleLearned} />
          ))
        )}
        {loading && <p>Loading...</p>}
        {error && <p className="text-red-500">Error: {error}</p>}
      </main>
  );
}