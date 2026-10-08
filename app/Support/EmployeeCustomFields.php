<?php

namespace App\Support;

/**
 * Teacher/Staff/Driver `custom_field_values` helpers. The canonical shape is a list of
 * ['label' => ..., 'value' => ...] (what the Teachers "Fields" modal, the profile View and the
 * Staff Profile import/export use). Older imports stored a plain map instead
 * (['Designation' => 'ASST TCHR']); every helper here accepts both and always returns the list.
 */
final class EmployeeCustomFields
{
    /**
     * Excel profile columns kept in custom_field_values — form key => label. Same labels the
     * Staff Profile import writes, the profile View reads and the export writes back.
     */
    public const PROFILE_FIELDS = [
        'designation' => 'Designation',
        'grade' => 'Grade',
        'subject' => 'Subject',
        'section' => 'Section',
        'joining_salary' => 'Basic Salary at Joining',
        'dob' => 'Date of Birth',
        'category' => 'Category',
        'hs_year' => 'HS Year',
        'inter_year' => 'Inter Year',
        'grad_year' => 'Grad Year',
        'join_date' => 'Join Date',
        'address' => 'Address',
        'remarks' => 'Remarks',
    ];

    /** Validation rules for the optional `profile` object sent by the Teacher/Staff/Driver forms. */
    public static function profileRules(): array
    {
        $rules = ['profile' => 'nullable|array'];
        foreach (array_keys(self::PROFILE_FIELDS) as $key) {
            $rules["profile.{$key}"] = $key === 'address' || $key === 'remarks' ? 'nullable|string|max:1000' : 'nullable|string|max:255';
        }

        return $rules;
    }

    /**
     * Applies the form's profile values: filled ones are set, cleared ones removed; every other
     * custom field (added via "Fields" or an import) is kept.
     *
     * @param  mixed  $fields
     * @param  array<string, mixed>  $profile
     * @return list<array{label:string, value:string}>
     */
    public static function applyProfile($fields, array $profile): array
    {
        $list = self::normalize($fields);
        foreach (self::PROFILE_FIELDS as $key => $label) {
            if (! array_key_exists($key, $profile)) {
                continue;
            }
            $value = trim((string) ($profile[$key] ?? ''));
            if ($value === '') {
                $needle = mb_strtolower($label);
                $list = array_values(array_filter($list, fn ($f) => mb_strtolower($f['label']) !== $needle));
            } else {
                $list = self::set($list, $label, $value);
            }
        }

        return $list;
    }

    /**
     * @param  mixed  $fields
     * @return list<array{label:string, value:string}>
     */
    public static function normalize($fields): array
    {
        if (! is_array($fields)) {
            return [];
        }

        $list = [];
        foreach ($fields as $key => $field) {
            if (is_array($field) && isset($field['label'])) {
                $label = trim((string) $field['label']);
                $value = $field['value'] ?? '';
            } elseif (is_string($key) && (is_scalar($field) || $field === null)) {
                $label = trim($key);
                $value = $field;
            } else {
                continue;
            }
            if ($label === '') {
                continue;
            }
            $list[] = ['label' => $label, 'value' => trim((string) $value)];
        }

        return self::dedupe($list);
    }

    /** Case-insensitive lookup; '' when missing. @param  mixed  $fields */
    public static function get($fields, string $label): string
    {
        $needle = mb_strtolower($label);
        foreach (self::normalize($fields) as $field) {
            if (mb_strtolower($field['label']) === $needle) {
                return $field['value'];
            }
        }

        return '';
    }

    /**
     * Sets (or adds) each label => value; other fields are kept.
     *
     * @param  mixed  $fields
     * @param  list<array{label:string, value:string}>  $updates
     * @return list<array{label:string, value:string}>
     */
    public static function merge($fields, array $updates): array
    {
        $list = self::normalize($fields);
        foreach ($updates as $update) {
            $needle = mb_strtolower($update['label']);
            $found = false;
            foreach ($list as &$field) {
                if (mb_strtolower($field['label']) === $needle) {
                    $field['value'] = (string) $update['value'];
                    $found = true;
                    break;
                }
            }
            unset($field);
            if (! $found) {
                $list[] = ['label' => $update['label'], 'value' => (string) $update['value']];
            }
        }

        return $list;
    }

    /** @param  mixed  $fields */
    public static function set($fields, string $label, string $value): array
    {
        return self::merge($fields, [['label' => $label, 'value' => $value]]);
    }

    /**
     * First occurrence of a label wins (a map key and a list entry can name the same field).
     *
     * @param  list<array{label:string, value:string}>  $list
     * @return list<array{label:string, value:string}>
     */
    private static function dedupe(array $list): array
    {
        $seen = [];
        $out = [];
        foreach ($list as $field) {
            $key = mb_strtolower($field['label']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $field;
        }

        return $out;
    }
}
