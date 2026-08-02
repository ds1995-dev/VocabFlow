"use client";
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { RegisterForm } from '../../../components/auth/RegisterForm';
import { User } from '../../../types/user';
import { apiFetch } from '../../../lib/api';


export default function RegisterPage() {
    const router = useRouter();
    const [loading, setLoading] = useState(false);
    // バリデーションエラー（422）: フィールドごとのメッセージ配列
    const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
    // 一般エラー（例外/500/ネットワーク等）: 単一メッセージ
    const [generalError, setGeneralError] = useState<string | null>(null);
    // 登録成功メッセージ
    const [successMessage, setSuccessMessage] = useState<string | null>(null);

    // 成功したかどうかを返す（フォーム側で成功時のみ入力をクリアする）
    const handleRegister = async (user: User): Promise<boolean> => {
        try {
            setLoading(true);
            setFieldErrors({});
            setGeneralError(null);
            setSuccessMessage(null);

            // 共通ラッパー経由で送る。POST なので apiFetch が CSRF cookie 取得・
            // X-XSRF-TOKEN 付与・credentials: 'include' を自動で行う。
            const response = await apiFetch('/api/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(user)
            });

            // 500 が HTML を返す場合に備え、JSON パースは保護する
            let data: { message?: string; errors?: Record<string, string[]> } | null = null;
            try {
                data = await response.json();
            } catch {
                data = null;
            }

            if (response.ok) {
                // 登録時に backend で自動ログイン済み。フロント管理の認証フラグ cookie を
                // 立ててから（middleware が参照する）ダッシュボードへ遷移する。
                document.cookie = 'logged_in=1; path=/; SameSite=Lax';
                router.push('/');
                return true;
            }

            if (response.status === 422) {
                // バリデーションエラー
                setFieldErrors(data?.errors ?? {});
            } else {
                // それ以外の例外エラー
                setGeneralError(data?.message ?? '登録に失敗しました');
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
            <RegisterForm
                onSubmit={handleRegister}
                loading={loading}
                fieldErrors={fieldErrors}
                generalError={generalError}
                successMessage={successMessage}
            />
        </main>
    )
}
