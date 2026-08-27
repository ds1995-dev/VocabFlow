"use client";
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { LoginForm } from '../../../components/auth/LoginForm';
import { apiFetch } from '../../../lib/api';
import { setToken } from '../../../lib/auth';
import { AuthResponse } from '../../../types/user';

export default function LoginPage() {
    const router = useRouter();
    const [loading, setLoading] = useState(false);
    // バリデーションエラー（422）: フィールドごとのメッセージ配列
    const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
    // 一般エラー（例外/500/ネットワーク等）: 単一メッセージ
    const [generalError, setGeneralError] = useState<string | null>(null);

    // 成功したかどうかを返す
    const handleLogin = async (credentials: { email: string; password: string }): Promise<boolean> => {
        try {
            setLoading(true);
            setFieldErrors({});
            setGeneralError(null);

            // 共通ラッパー経由で送る。ログイン前なのでトークンはまだ付かない。
            const response = await apiFetch('/api/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(credentials)
            });

            // 500 が HTML を返す場合に備え、JSON パースは保護する
            let data:
                | (Partial<AuthResponse> & { message?: string; errors?: Record<string, string[]> })
                | null = null;
            try {
                data = await response.json();
            } catch {
                data = null;
            }

            if (response.ok) {
                // 想定外のレスポンスでトークン無しのまま遷移しないようにする
                if (!data?.token) {
                    setGeneralError('ログインに失敗しました');
                    return false;
                }
                // アクセストークンを保存する（middleware もこの cookie を参照する）
                setToken(data.token);
                router.push('/');
                return true;
            }

            if (response.status === 422) {
                // 認証失敗は errors.email 形式の 422 で返る
                setFieldErrors(data?.errors ?? {});
            } else {
                // それ以外の例外エラー
                setGeneralError(data?.message ?? 'ログインに失敗しました');
            }
            return false;
        } catch (err) {
            // ネットワークエラー等
            setGeneralError((err as Error).message);
            return false;
        } finally {
            setLoading(false);
        }
    };

    return (
        <main>
            <LoginForm
                onSubmit={handleLogin}
                loading={loading}
                fieldErrors={fieldErrors}
                generalError={generalError}
            />
            {/* 未登録ユーザー向けに新規登録ページへの導線 */}
            <p className="mt-4 text-sm text-muted">
                アカウントをお持ちでない方は{' '}
                <Link href="/register" className="text-accent-soft-foreground hover:underline">
                    新規登録
                </Link>
            </p>
        </main>
    )
}
