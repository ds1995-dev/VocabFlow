<?php

namespace App\Http\Requests;

class UpdateWordRequest extends StoreWordRequest
{
    // store と同一のバリデーションルールを再利用する。
    // 挙動を変えたくなった場合は rules() を override する。
}
