export type User = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

// /api/user・login/register レスポンスの user 用（パスワードは含まない）
export type AuthUser = {
    id: number;
    name: string;
    email: string;
}