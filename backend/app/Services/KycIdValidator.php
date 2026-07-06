<?php

namespace App\Services;

/**
 * Per-country identity-number format validation for assisted KYC (Tahap A).
 * Returns null when the number is acceptable, or a human-readable error string.
 *
 * Strong (checksum/structure): MY MyKad, SG NRIC, TH, IN Aadhaar, ID NIK.
 * Structural: PK, VN, BD, PH. Passport: universal ICAO-ish. Others: generic.
 * NOTE: format validity ≠ a genuine document — the staff face-check + selfie
 * are what confirm the real person.
 */
class KycIdValidator
{
    public static function validate(string $country, string $idType, string $number): ?string
    {
        $n = strtoupper(preg_replace('/[\s\-]/', '', $number));
        if ($n === '') return 'ID number is required.';
        if (preg_match('/^(.)\1+$/', $n)) return 'ID number looks invalid.';

        if ($idType === 'passport') {
            return preg_match('/^[A-Z0-9]{5,12}$/', $n) ? null : 'Passport number must be 5–12 letters/digits.';
        }
        if ($idType === 'license') {
            return preg_match('/^[A-Z0-9]{4,20}$/', $n) ? null : 'Driving licence number format is invalid.';
        }

        // National ID card (id_type = ic)
        return match (strtoupper($country)) {
            'MY' => self::malaysia($n),
            'ID' => self::indonesia($n),
            'SG' => self::singapore($n),
            'TH' => self::thai($n),
            'IN' => self::aadhaar($n),
            'PK' => preg_match('/^\d{13}$/', $n) ? null : 'Pakistan CNIC must be 13 digits.',
            'VN' => preg_match('/^\d{9,12}$/', $n) ? null : 'Vietnam ID must be 9–12 digits.',
            'BD' => preg_match('/^(\d{10}|\d{13}|\d{17})$/', $n) ? null : 'Bangladesh NID must be 10, 13, or 17 digits.',
            'PH' => preg_match('/^\d{9,16}$/', $n) ? null : 'Philippine ID must be 9–16 digits.',
            default => preg_match('/^[A-Z0-9]{5,20}$/', $n) ? null : 'National ID must be 5–20 letters/digits.',
        };
    }

    private static function malaysia(string $n): ?string
    {
        if (!preg_match('/^\d{12}$/', $n)) return 'Malaysian IC (MyKad) must be 12 digits.';
        $mm = (int) substr($n, 2, 2); $dd = (int) substr($n, 4, 2);
        if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) return 'Malaysian IC birth date is invalid.';
        $state = (int) substr($n, 6, 2);
        $bad = [0, 17, 18, 19, 20, 69, 70, 73, 74, 80, 81, 94, 95, 96, 97];
        if (in_array($state, $bad, true)) return 'Malaysian IC place-of-birth code is invalid.';
        return null;
    }

    private static function indonesia(string $n): ?string
    {
        if (!preg_match('/^\d{16}$/', $n)) return 'Indonesian NIK must be 16 digits.';
        $dd = (int) substr($n, 6, 2); $mm = (int) substr($n, 8, 2);
        $day = $dd > 40 ? $dd - 40 : $dd; // +40 for females
        if ($mm < 1 || $mm > 12 || $day < 1 || $day > 31) return 'Indonesian NIK date is invalid.';
        return null;
    }

    private static function singapore(string $n): ?string
    {
        if (!preg_match('/^[STFGM]\d{7}[A-Z]$/', $n)) return 'Singapore NRIC/FIN format is invalid.';
        $w = [2, 7, 6, 5, 4, 3, 2]; $sum = 0;
        for ($i = 0; $i < 7; $i++) $sum += ((int) $n[$i + 1]) * $w[$i];
        $first = $n[0];
        if ($first === 'T' || $first === 'G') $sum += 4;
        if ($first === 'M') $sum += 3;
        $r = $sum % 11;
        $stfg = ['J', 'Z', 'I', 'H', 'G', 'F', 'E', 'D', 'C', 'B', 'A'];
        $mfin = ['K', 'L', 'J', 'N', 'P', 'Q', 'R', 'T', 'U', 'W', 'X'];
        $expected = $first === 'M' ? $mfin[$r] : $stfg[$r];
        return $n[8] === $expected ? null : 'Singapore NRIC/FIN checksum failed.';
    }

    private static function thai(string $n): ?string
    {
        if (!preg_match('/^\d{13}$/', $n)) return 'Thai ID must be 13 digits.';
        $sum = 0;
        for ($i = 0; $i < 12; $i++) $sum += ((int) $n[$i]) * (13 - $i);
        $check = (11 - ($sum % 11)) % 10;
        return ((int) $n[12]) === $check ? null : 'Thai ID checksum failed.';
    }

    private static function aadhaar(string $n): ?string
    {
        if (!preg_match('/^\d{12}$/', $n)) return 'Aadhaar must be 12 digits.';
        $d = [
            [0,1,2,3,4,5,6,7,8,9],[1,2,3,4,0,6,7,8,9,5],[2,3,4,0,1,7,8,9,5,6],[3,4,0,1,2,8,9,5,6,7],
            [4,0,1,2,3,9,5,6,7,8],[5,9,8,7,6,0,4,3,2,1],[6,5,9,8,7,1,0,4,3,2],[7,6,5,9,8,2,1,0,4,3],
            [8,7,6,5,9,3,2,1,0,4],[9,8,7,6,5,4,3,2,1,0],
        ];
        $p = [
            [0,1,2,3,4,5,6,7,8,9],[1,5,7,6,2,8,3,0,9,4],[5,8,0,3,7,9,6,1,4,2],[8,9,1,6,0,4,3,5,2,7],
            [9,4,5,3,1,2,6,8,7,0],[4,2,8,6,5,7,3,9,0,1],[2,7,9,3,8,0,6,4,1,5],[7,0,4,6,9,1,3,2,5,8],
        ];
        $c = 0; $rev = strrev($n);
        for ($i = 0; $i < strlen($rev); $i++) $c = $d[$c][$p[$i % 8][(int) $rev[$i]]];
        return $c === 0 ? null : 'Aadhaar checksum failed.';
    }
}
