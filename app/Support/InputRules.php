<?php

namespace App\Support;

/**
 * Shared validation rules for person names, LRN, phone, etc.
 */
class InputRules
{
    /** Letters (incl. accented / Ñ), spaces, apostrophes, hyphens, periods — no digits. */
    public const PERSON_NAME = "regex:/^[\\p{L}\\s'.\\-]+$/u";

    /** Digits only (DepEd LRN). */
    public const LRN = 'regex:/^\\d+$/';

    /** Digits and common phone punctuation. */
    public const PHONE = 'regex:/^[+]?[\\d\\s()\\-]+$/';

    /** Letters, numbers, and hyphens. */
    public const EMPLOYEE_NO = 'regex:/^[A-Za-z0-9\\-]+$/';

    /**
     * @return list<string>
     */
    public static function personName(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:100',
            self::PERSON_NAME,
        ];
    }

    /**
     * @return list<string|\Illuminate\Validation\Rules\Unique>
     */
    public static function lrn(bool $required = false, mixed ...$extra): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'string',
            'max:20',
            self::LRN,
            ...$extra,
        ]));
    }

    /**
     * @return list<string>
     */
    public static function phone(): array
    {
        return ['nullable', 'string', 'max:20', self::PHONE];
    }

    /**
     * @return list<string|\Illuminate\Validation\Rules\Unique>
     */
    public static function employeeNo(mixed ...$extra): array
    {
        return array_values(array_filter([
            'nullable',
            'string',
            'max:50',
            self::EMPLOYEE_NO,
            ...$extra,
        ]));
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'first_name.regex' => 'The first name may only contain letters (no numbers).',
            'last_name.regex' => 'The last name may only contain letters (no numbers).',
            'lrn.regex' => 'The LRN may only contain numbers.',
            'phone.regex' => 'The phone may only contain numbers and + ( ) - characters.',
            'employee_no.regex' => 'The employee number may only contain letters, numbers, and hyphens.',
            'relationship.regex' => 'The relationship may only contain letters (no numbers).',
        ];
    }
}
