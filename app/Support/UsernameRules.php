<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/** قواعد اسم المستخدم — عربي و/أو إنجليزي مع أرقام و _ و - */
final class UsernameRules
{
    public const PATTERN = '/^[\p{Arabic}A-Za-z0-9_\-]{3,50}$/u';

    public static function normalize(string $username): string
    {
        $username = trim($username);

        if ($username === '') {
            return '';
        }

        // أسماء ASCII فقط — lowercase للتوافق مع الحسابات القديمة
        if (preg_match('/^[\x00-\x7F]+$/', $username)) {
            return strtolower($username);
        }

        return $username;
    }

    /** @return list<\Illuminate\Contracts\Validation\ValidationRule|string> */
    public static function rules(?int $ignoreUserId = null): array
    {
        $unique = $ignoreUserId
            ? Rule::unique('users', 'username')->ignore($ignoreUserId)
            : Rule::unique('users', 'username');

        return [
            'required',
            'string',
            'min:3',
            'max:50',
            'regex:'.self::PATTERN,
            $unique,
        ];
    }

    /** @return list<\Illuminate\Contracts\Validation\ValidationRule|string> */
    public static function optionalRules(?int $ignoreUserId = null): array
    {
        $rules = self::rules($ignoreUserId);
        $rules[0] = 'sometimes';

        return $rules;
    }

    public static function messageAttributes(): array
    {
        return [
            'username.regex' => 'اسم المستخدم: حروف عربية أو إنجليزية وأرقام و _ و - فقط (3-50 حرف).',
            'username.unique' => 'اسم المستخدم مستخدم مسبقاً.',
        ];
    }
}
