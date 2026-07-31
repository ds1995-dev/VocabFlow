"use client";
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { LoginForm } from '../../../components/auth/LoginForm';
import { apiFetch } from '../../../lib/api';

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

            // 共通ラッパー経由で送る。POST なので apiFetch が CSRF cookie 取得・
            // X-XSRF-TOKEN 付与・credentials: 'include' を自動で行う。
            const response = await apiFetch('/api/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(credentials)
            });

            // 500 が HTML を返す場合に備え、JSON パースは保護する
            let data: { message?: string; errors?: Record<string, string[]> } | null = null;
            try {
                data = await response.json();
            } catch {
                data = null;
            }

            if (response.ok) {
                // フロント管理の認証フラグ cookie を立てる（middleware が参照する）
                document.cookie = 'logged_in=1; path=/; SameSite=Lax';
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
        </main>
    )
}
