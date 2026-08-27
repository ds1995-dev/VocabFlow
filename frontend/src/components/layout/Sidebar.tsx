"use client";
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { apiFetch } from '../../lib/api';
import { clearToken } from '../../lib/auth';

export function Sidebar() {
    const pathname = usePathname();
    const router = useRouter();

    const handleLogout = async () => {
        try {
            // apiFetch が Authorization: Bearer を付けるので、サーバー側で
            // このトークンだけが失効する。
            await apiFetch('/api/logout', { method: 'POST' });
        } catch {
            // ネットワークエラー等でも下でローカル状態はクリアする
        } finally {
            // API の成否に関わらずフロント側のトークンを消してログイン画面へ。
            // middleware が access_token を見るため、これで保護ページへ戻れなくなる。
            clearToken();
            router.push('/login');
        }
    };

    return (
        <aside className="hidden md:block w-64 bg-surface border-r border-line-subtle p-6 mr-4">
            <h1 className="text-xl font-bold text-accent-soft-foreground">
                Daily English Log
            </h1>
            <nav className="mt-8 space-y-4">
                <Link href="/" className={pathname === '/' ? 'bg-accent-soft text-accent-soft-foreground font-bold' : 'text-muted hover:text-accent-soft-foreground'}>
                    Home</Link>
                <p className="text-muted">All Words</p>
                <p className="text-muted">Learned</p>
                <p className="text-muted">Unlearned</p>
                <Link href="/categories" className={pathname === '/categories' ? 'bg-accent-soft text-accent-soft-foreground font-bold' : 'text-muted hover:text-accent-soft-foreground'}>
                    Categories</Link>
                <Link href="/quiz" className={pathname === '/quiz' ? 'block bg-accent-soft text-accent-soft-foreground font-bold' : 'block text-muted hover:text-accent-soft-foreground'}>
                    Quiz</Link>
            </nav>
            <button
                type="button"
                onClick={handleLogout}
                className="mt-8 block text-left text-muted hover:text-accent-soft-foreground"
            >
                Logout
            </button>
        </aside>
    );
}