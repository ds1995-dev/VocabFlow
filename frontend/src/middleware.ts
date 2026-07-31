import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

// 認証フラグ cookie 名（ログイン成功時に立てる logged_in=1）。
// 非 HttpOnly のフロント管理フラグで、middleware から存在チェックのみ行う。
const AUTH_COOKIE = 'logged_in';

// 未ログインで弾く保護ルート。
const PROTECTED_PATHS = ['/', '/categories'];
// ログイン済みなら遠ざける認証ルート。
const AUTH_PATHS = ['/login', '/register'];

export function middleware(request: NextRequest) {
  const isLoggedIn = Boolean(request.cookies.get(AUTH_COOKIE));
  const { pathname } = request.nextUrl;

  // 未ログイン → 保護ルートは /login へリダイレクト。
  if (!isLoggedIn && PROTECTED_PATHS.includes(pathname)) {
    return NextResponse.redirect(new URL('/login', request.url));
  }

  // ログイン済み → 認証ルートは / へリダイレクト。
  if (isLoggedIn && AUTH_PATHS.includes(pathname)) {
    return NextResponse.redirect(new URL('/', request.url));
  }

  return NextResponse.next();
}

// 対象パスのみに絞る。ルートグループ (auth) は URL に出ないため実パスで指定する。
export const config = {
  matcher: ['/', '/categories', '/login', '/register'],
};
