"use client"
import { useState } from 'react';

type LoginFormProps = {
    onSubmit: (credentials: { email: string; password: string }) => Promise<boolean>;
    loading: boolean;
    fieldErrors: Record<string, string[]>;
    generalError: string | null;
}

export function LoginForm({ onSubmit, loading, fieldErrors, generalError }: LoginFormProps) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');

    // フィールド別のバリデーションエラーを表示する
    const renderFieldError = (field: string) =>
        fieldErrors[field]?.map((message) => (
            <p key={message} className="text-red-500 text-sm">{message}</p>
        ));

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        // 成功時はページ側で / へ遷移するため、入力のクリアは行わない
        await onSubmit({ email, password });
    };

    return (
        <form onSubmit={handleSubmit}>
            <h2>ログイン</h2>
            <div>
                <label htmlFor="email">
                    Email
                </label>
                <input
                    id="email"
                    type="email"
                    className="rounded border border-line bg-surface text-foreground p-2"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                />
                {renderFieldError('email')}
            </div>
            <div>
                <label htmlFor="password">
                    Password
                </label>
                <input
                    id="password"
                    type="password"
                    className="rounded border border-line bg-surface text-foreground p-2"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                />
                {renderFieldError('password')}
            </div>
            <button type="submit" disabled={loading}>
                {loading ? 'ログイン中...' : 'ログイン'}
            </button>
            {generalError && <p className="text-red-500">Error: {generalError}</p>}
        </form>
    )
}
