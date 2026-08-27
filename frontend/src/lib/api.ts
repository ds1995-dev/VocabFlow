import { clearToken, getToken } from "./auth";

// バックエンド API のオリジン（末尾スラッシュなし）。
// apiFetch には `/api/words` のようなパスを渡す。
export const API_BASE = process.env.NEXT_PUBLIC_API_BASE ?? "http://localhost";

// 共通 API ラッパー。fetch と同じく Response をそのまま返す薄いラッパー。
// - すべてのリクエストに Accept: application/json を付与する。
// - 保管中のアクセストークンがあれば Authorization: Bearer を付ける。
// - 401（未認証）を受けたらトークンを破棄してログイン画面へ遷移する。
//
// 認証は Bearer トークン方式なので CSRF cookie の往復も credentials も不要。
export async function apiFetch(
  path: string,
  options: RequestInit = {},
): Promise<Response> {
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");

  const token = getToken();
  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
  });

  // 未認証。失効したトークンを消してログイン画面へ。
  // ログインページからの無限リダイレクトは防ぐ。
  if (response.status === 401) {
    clearToken();
    if (typeof window !== "undefined" && window.location.pathname !== "/login") {
      window.location.href = "/login";
    }
  }

  return response;
}
