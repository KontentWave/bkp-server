<?php

namespace App\Services;

class PhoneNumberNormalizer
{
    public function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $trimmed = trim($input);

        if ($trimmed === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d+]/', '', $trimmed) ?? '';

        if (str_starts_with($normalized, '00')) {
            $normalized = '+'.substr($normalized, 2);
        }

        if (str_starts_with($normalized, '421') && ! str_starts_with($normalized, '+')) {
            $normalized = '+'.$normalized;
        }

        if (preg_match('/^0\d{9}$/', $normalized) === 1) {
            $normalized = '+421'.substr($normalized, 1);
        }

        if (preg_match('/^\+\d{10,15}$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }
}