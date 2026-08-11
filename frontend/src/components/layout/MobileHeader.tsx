export function MobileHeader() {
    return (
        <div className="md:hidden flex bg-surface rounded shadow-md border-line w-auto p-2">
            <nav className="flex mt-4 gap-2 space-y-2">
                <p className="rounded text-xs font-bold bg-accent-soft text-accent-soft-foreground p-2">Dashboard</p>
                <p className="text-xs text-muted p-2">All</p>
                <p className="text-xs text-muted p-2">Learned</p>
                <p className="text-xs text-muted p-2">Unlearned</p>
                <p className="text-xs text-muted p-2">Categories</p>
            </nav>
        </div>
    )
}