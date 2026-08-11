"use client"
import { useState } from 'react';
import { User } from '../../types/user';

type RegisterFormProps = {
    onSubmit: (user: User) => Promise<boolean>;
    loading: boolean;
    fieldErrors: Record<string, string[]>;
    generalError: string | null;
    successMessage: string | null;
}

export function RegisterForm({ onSubmit, loading, fieldErrors, generalError, successMessage }: RegisterFormProps) {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');

    // フィールド別のバリデーションエラーを表示する
    const renderFieldError = (field: string) =>
        fieldErrors[field]?.map((message) => (
            <p key={message} className="text-red-500 text-sm">{message}</p>
        ));

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        const user: User = {
            name,
            email,
            password,
            password_confirmation: passwordConfirmation
        }
        const ok = await onSubmit(user);
        // 成功時のみ入力をクリアする（バリデーション失敗時は入力を残す）
        if (ok) {
            setName('');
            setEmail('');
            setPassword('');
            setPasswordConfirmation('');
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <h2>会員登録</h2>
            <div>
                <label htmlFor="name">
                    Name
                </label>
                <input
                    id="name"
                    type="text"
                    className="rounded border border-line bg-surface text-foreground p-2"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                />
                {renderFieldError('name')}
            </div>
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
            <div>
                <label htmlFor="password-confirmation">
                    Password Confirmation
                </label>
                <input
                    id="password-confirmation"
                    type="password"
                    className="rounded border border-line bg-surface text-foreground p-2"
                    value={passwordConfirmation}
                    onChange={(e) => setPasswordConfirmation(e.target.value)}
                />
                {renderFieldError('password_confirmation')}
            </div>
            <button type="submit" disabled={loading}>
                {loading ? '登録中...' : '登録'}
            </button>
            {generalError && <p className="text-red-500">Error: {generalError}</p>}
            {successMessage && <p className="text-green-500">{successMessage}</p>}
        </form>
    )
}
