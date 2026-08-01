// バックエンド API のオリジン（末尾スラッシュなし）。
// apiFetch には `/api/words` のようなパスを渡す。
export const API_BASE = process.env.NEXT_PUBLIC_API_BASE ?? "http://localhost";

// document.cookie から指定名の cookie 値を取得する（URL デコード済み）。
// SSR 中は document が存在しないため null を返す。
function getCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(
    new RegExp("(?:^|;\\s*)" + name + "=([^;]*)"),
  );
  return match ? decodeURIComponent(match[1]) : null;
}

// 指定名の cookie を失効させる。
export function deleteCookie(name: string): void {
  if (typeof document === "undefined") return;
  document.cookie = `${name}=; path=/; max-age=0`;
}

// CSRF 用の XSRF-TOKEN cookie を確保する。
// 既に取得済みなら無駄な往復を避けるため何もしない。
async function ensureCsrfCookie(): Promise<void> {
  if (getCookie("XSRF-TOKEN")) return;
  await fetch(`${API_BASE}/sanctum/csrf-cookie`, {
    method: "GET",
    credentials: "include",
  });
}

// 共通 API ラッパー。fetch と同じく Response をそのまま返す薄いラッパー。
// - すべてのリクエストに credentials: "include" と Accept: application/json を付与する。
// - GET/HEAD 以外は事前に CSRF cookie を取得し、X-XSRF-TOKEN ヘッダーを付ける。
// - 401（未認証）を受けたら logged_in cookie を消してログイン画面へ遷移する。
export async function apiFetch(
  path: string,
  options: RequestInit = {},
): Promise<Response> {
  const method = (options.method ?? "GET").toUpperCase();
  const isMutating = method !== "GET" && method !== "HEAD";

  // 状態変更リクエストの前に CSRF cookie を用意する。
  if (isMutating) {
    await ensureCsrfCookie();
  }

  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");

  // 状態変更リクエストには CSRF トークンをヘッダーで送る。
  if (isMutating) {
    const token = getCookie("XSRF-TOKEN");
    if (token) {
      headers.set("X-XSRF-TOKEN", token);
    }
  }

  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
    credentials: "include",
  });

  // 未認証。フロント管理の logged_in を消してログイン画面へ。
  // ログインページからの無限リダイレクトは防ぐ。
  if (response.status === 401) {
    deleteCookie("logged_in");
    if (typeof window !== "undefined" && window.location.pathname !== "/login") {
      window.location.href = "/login";
    }
  }

  return response;
}
