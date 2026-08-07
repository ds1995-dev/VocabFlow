<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWordRequest;
use App\Http\Requests\UpdateWordRequest;
use App\Models\LearningActivity;
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

        // 単語の登録はこのアプリの中心的な学習行為なのでストリークに数える
        LearningActivity::record($request->user(), ActivityType::WordCreated, $word);

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

    public function toggleLearned(Request $request, Word $word)
    {
        $this->authorize('update', $word);

        $word->is_learned = ! $word->is_learned;
        $word->save();

        // 解除も記録は残すが、ストリークには数えない（ActivityType 側で出し分ける）
        LearningActivity::record(
            $request->user(),
            $word->is_learned ? ActivityType::WordLearned : ActivityType::WordUnlearned,
            $word,
        );

        $word->load('category');

        return response()->json($word);
    }
}
