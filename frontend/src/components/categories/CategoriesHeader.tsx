export function CategoriesHeader() {
    return (
        <header className="flex justify-between bg-surface rounded shadow text-sm md:text-lg p-2 md:p-4 mb-4">
            <h1 className="text-lg md:text-2xl font-bold">Categories</h1>
            <form className="md:mt-4 flex gap-2">
                <input
                    type="text"
                    placeholder="Search categories..."
                    className="flex-1 md:p-2 border border-line bg-surface text-foreground rounded"
                />
                <button
                    type="submit"
                    className="bg-blue-600 text-white p\md:px-4 md:py-2 rounded hover:bg-blue-700"
                >
                    Search
                </button>
            </form>
        </header>
    );
}