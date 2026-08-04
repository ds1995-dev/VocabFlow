<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWordRequest;
use App\Http\Requests\UpdateWordRequest;
use App\Models\Word;
use Illuminate\Http\Request;

class WordController extends Controller
{
    public function index(Request $request)
    {
        $words = $request->user()
            ->words()
            ->with('category')
            ->get();

        return response()->json($words);
    }

    public function store(StoreWordRequest $request)
    {
        $word = $request->user()
            ->words()
            ->create($request->validated());

        $word->load('category');

        return response()->json($word, 201);
    }

    public function update(UpdateWordRequest $request, Word $word)
    {
        $this->authorize('update', $word);

        $word->update($request->validated());

        $word->load('category');

        return response()->json($word);
    }

    public function destroy(Word $word)
    {
        $this->authorize('delete', $word);

        $word->delete();

        return response()->json([
            'message' => 'Word deleted successfully',
        ]);
    }

    public function toggleLearned(Word $word)
    {
        $this->authorize('update', $word);

        $word->is_learned = ! $word->is_learned;
        $word->save();

        $word->load('category');

        return response()->json($word);
    }
}
