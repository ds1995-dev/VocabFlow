"use client";
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { apiFetch, deleteCookie } from '../../lib/api';

export function Sidebar() {
    const pathname = usePathname();
    const router = useRouter();

    const handleLogout = async () => {
        try {
            // POST なので apiFetch が CSRF cookie 取得・X-XSRF-TOKEN 付与・
            // credentials: 'include' を自動で行う。
            await apiFetch('/api/logout', { method: 'POST' });
        } catch {
            // ネットワークエラー等でも下でローカル状態はクリアする
        } finally {
            // API の成否に関わらずフロント側の認証フラグを消してログイン画面へ。
            // middleware が logged_in を見るため、これで保護ページへ戻れなくなる。
            deleteCookie('logged_in');
            router.push('/login');
        }
    };

    return (
        <aside className="hidden md:block w-64 bg-white border-r border-gray-200 p-6 mr-4">
            <h1 className="text-xl font-bold text-blue-600">
                Daily English Log
            </h1>
            <nav className="mt-8 space-y-4">
                <Link href="/" className={pathname === '/' ? 'bg-blue-100 text-blue-600 font-bold' : 'text-gray-600 hover:text-blue-600'}>
                    Home</Link>
                <p className="text-gray-600">All Words</p>
                <p className="text-gray-600">Learned</p>
                <p className="text-gray-600">Unlearned</p>
                <Link href="/categories" className={pathname === '/categories' ? 'bg-blue-100 text-blue-600 font-bold' : 'text-gray-600 hover:text-blue-600'}>
                    Categories</Link>
            </nav>
            <button
                type="button"
                onClick={handleLogout}
                className="mt-8 block text-left text-gray-600 hover:text-blue-600"
            >
                Logout
            </button>
        </aside>
    );
}