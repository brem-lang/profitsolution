<?php
declare(strict_types=1);

/*
 * Mobile phone check for the lead form (IT / ES / GB / DE / SA numbers only).
 * PHP copy of assets/phone-check.js — keep the rules identical.
 *
 * check_phone($raw, $defaultCountry) → ['ok' => bool, 'e164' => string, 'country' => string, 'reason' => string]
 *   reason when not ok: letters | country | short | long | landline | invalid
 */

const PHONE_DIAL = ['IT' => '39', 'ES' => '34', 'GB' => '44', 'DE' => '49', 'SA' => '966'];

const PHONE_MESSAGES = [
    'letters'  => 'Il numero di telefono può contenere solo cifre.',
    'country'  => 'Sono accettati solo numeri italiani (+39), spagnoli (+34), britannici (+44), tedeschi (+49) o sauditi (+966).',
    'short'    => 'Il numero di telefono è troppo corto.',
    'long'     => 'Il numero di telefono è troppo lungo.',
    'landline' => 'Sembra un numero fisso: inserisci un numero di cellulare.',
    'invalid'  => 'Inserisci un numero di cellulare valido.',
];

function phone_looks_fake(string $n): bool
{
    if (preg_match('/(\d)\1{6,}/', $n)) {
        return true; // 3333333
    }
    for ($i = 0; $i + 7 <= strlen($n); $i++) {
        $run = substr($n, $i, 7);
        if (str_contains('0123456789012', $run) || str_contains('9876543210987', $run)) {
            return true; // 1234567
        }
    }
    return false;
}

/** $n = national significant number (digits after the country code). */
function phone_check_national(string $country, string $n): string
{
    $len = strlen($n);
    if ($country === 'IT') {
        if (preg_match('/^0/', $n)) return 'landline';
        if (!preg_match('/^3/', $n)) return 'invalid';
        if ($len < 9) return 'short';
        if ($len > 10) return 'long';
    } elseif ($country === 'ES') {
        if (preg_match('/^[89]/', $n)) return 'landline';
        if (!preg_match('/^(6|7[1-9])/', $n)) return 'invalid';
        if ($len < 9) return 'short';
        if ($len > 9) return 'long';
    } elseif ($country === 'GB') {
        if (preg_match('/^[123]/', $n)) return 'landline';
        if (!preg_match('/^7/', $n)) return 'invalid';
        if ($len < 10) return 'short';
        if ($len > 10) return 'long';
        if (preg_match('/^76/', $n) && !preg_match('/^7624/', $n)) return 'invalid'; // pagers
        if (preg_match('/^7700900/', $n)) return 'invalid'; // Ofcom TV/film range
    } elseif ($country === 'DE') {
        // Mobiles are 15x / 16x / 17x; everything else starting 1–9 is a landline.
        if (!preg_match('/^1[5-7]/', $n)) return preg_match('/^[1-9]/', $n) ? 'landline' : 'invalid';
        if ($len < 10) return 'short';
        if ($len > 11) return 'long';
    } elseif ($country === 'SA') {
        // Mobiles are 5x xxx xxxx; area codes 1x, 4x, 6x, 7x are landlines.
        if (!preg_match('/^5/', $n)) return preg_match('/^[1467]/', $n) ? 'landline' : 'invalid';
        if ($len < 9) return 'short';
        if ($len > 9) return 'long';
    }
    return phone_looks_fake($n) ? 'invalid' : '';
}

function check_phone(string $raw, string $defaultCountry): array
{
    $fail = static fn(string $reason, string $country = ''): array =>
        ['ok' => false, 'e164' => '', 'country' => $country, 'reason' => $reason];

    $s = trim($raw);
    if (preg_match('/[A-Za-z]/', $s)) return $fail('letters');
    $plus = str_starts_with($s, '+');
    $digits = preg_replace('/\D/', '', $s) ?? '';
    if ($digits === '') return $fail('short');
    if (!$plus && str_starts_with($digits, '00')) {
        $plus = true;
        $digits = substr($digits, 2);
    }

    if ($plus) {
        // Dial codes are 2 or 3 digits long.
        $country = false;
        foreach (PHONE_DIAL as $code => $dial) {
            if (str_starts_with($digits, $dial)) {
                $country = $code;
                break;
            }
        }
        if ($country === false) return $fail('country');
        $n = substr($digits, strlen(PHONE_DIAL[$country]));
    } else {
        $country = isset(PHONE_DIAL[$defaultCountry]) ? $defaultCountry : 'IT';
        $n = $digits;
        // "39 312 345 6789" typed without the +
        $cc = PHONE_DIAL[$country];
        if (str_starts_with($n, $cc) && phone_check_national($country, substr($n, strlen($cc))) === ''
            && phone_check_national($country, $n) !== '') {
            $n = substr($n, strlen($cc));
        }
    }
    // Trunk prefix: 07700 → 7700 (GB), 0151 → 151 (DE), 050 → 50 (SA).
    if (in_array($country, ['GB', 'DE', 'SA'], true) && str_starts_with($n, '0')) $n = substr($n, 1);

    $reason = phone_check_national($country, $n);
    if ($reason !== '') return $fail($reason, $country);
    return ['ok' => true, 'e164' => '+' . PHONE_DIAL[$country] . $n, 'country' => $country, 'reason' => ''];
}
