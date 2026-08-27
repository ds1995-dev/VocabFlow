// アクセストークンの保管層。
// localStorage ではなく cookie に置くのは、src/middleware.ts（サーバー実行）から
// トークンの存在を確認してルート保護を行うため。localStorage はサーバーから読めない。
// React Native から使う場合はこのファイルを expo-secure-store 実装に差し替える。

const TOKEN_COOKIE = "access_token";

// トークンを保持する期間（秒）。backend の SANCTUM_EXPIRATION（既定 30 日）と揃える。
const MAX_AGE_SECONDS = 60 * 60 * 24 * 30;

// document.cookie から指定名の cookie 値を取得する（URL デコード済み）。
// SSR 中は document が存在しないため null を返す。
function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(
    new RegExp("(?:^|;\\s*)" + name + "=([^;]*)"),
  );
  return match ? decodeURIComponent(match[1]) : null;
}

// 保管中のアクセストークンを返す。未ログインなら null。
export function getToken(): string | null {
  return readCookie(TOKEN_COOKIE);
}

// アクセストークンを保存する。
export function setToken(token: string): void {
  if (typeof document === "undefined") return;
  // http の開発環境では Secure を付けると cookie が保存されないため https のときだけ付ける
  const secure = window.location.protocol === "https:" ? "; Secure" : "";
  document.cookie =
    `${TOKEN_COOKIE}=${encodeURIComponent(token)}` +
    `; path=/; max-age=${MAX_AGE_SECONDS}; SameSite=Lax${secure}`;
}

// アクセストークンを破棄する（ログアウト・401 時）。
export function clearToken(): void {
  if (typeof document === "undefined") return;
  document.cookie = `${TOKEN_COOKIE}=; path=/; max-age=0`;
}
