<?php

namespace App\Http\Requests;

class UpdateCategoryRequest extends StoreCategoryRequest
{
    // store と同一のバリデーションルールを再利用する。
    // 挙動を変えたくなった場合は rules() を override する。
}
