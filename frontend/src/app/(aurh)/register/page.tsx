"use client";
import { useState } from 'react';
import { RegisterForm } from '../../../components/auth/RegisterForm';
import { User } from '../../../types/user';


export default function RegisterPage() {
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

            const response = await fetch('http://localhost/api/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
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
                // API は 201 で { message: "User created successfully" } を返す
                setSuccessMessage(data?.message ?? '登録が完了しました');
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
