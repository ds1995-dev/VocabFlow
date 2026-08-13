<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Word extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'word',
        'meaning',
        'sentence',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 忘却曲線の復習状態。まだ一度も出題していない単語では null になる。
     */
    public function review()
    {
        return $this->hasOne(WordReview::class);
    }
}
