<?php

namespace App\Support;

/**
 * ING-oranje: gewone spaarrekening heeft een spatie voor het nummer,
 * spaarpotjes plakken het nummer vast aan "spaarrekening".
 *
 * Naar Oranje spaarrekening L13628386  → rekening (koppelbaar)
 * Naar Oranje spaarrekeningC13134173   → potje (niet koppelen)
 */
class IngSavingsTransfer
{
    /** @return array{ref: string, isSpaarpot: bool}|null */
    public static function parseDestination(string $text): ?array
    {
        if (preg_match('/spaarrekening([A-Za-z]\d{5,})/iu', $text, $match)) {
            return [
                'ref' => mb_strtoupper($match[1]),
                'isSpaarpot' => true,
            ];
        }

        if (preg_match('/spaarrekening\s+([A-Za-z]\d{5,})/iu', $text, $match)) {
            return [
                'ref' => mb_strtoupper($match[1]),
                'isSpaarpot' => false,
            ];
        }

        return null;
    }

    public static function extractRef(string $text): ?string
    {
        $parsed = self::parseDestination($text);
        if ($parsed) {
            return $parsed['ref'];
        }

        if (preg_match('/[A-Za-z]\d{5,}/u', $text, $match)) {
            return mb_strtoupper($match[0]);
        }

        return null;
    }

    public static function isSpaarpotDescription(string $description): bool
    {
        $parsed = self::parseDestination($description);

        return $parsed !== null && $parsed['isSpaarpot'] === true;
    }

    /** A generic savings rule must not capture another recognized savings goal. */
    public static function ruleIdentifiesGoal(string $keyword, string $goalName, ?string $accountIban): bool
    {
        $ruleRef = self::extractRef($keyword);
        $goalRef = self::extractRef(trim($goalName.' '.($accountIban ?? '')));
        if ($ruleRef && $goalRef) {
            return $ruleRef === $goalRef;
        }

        $iban = strtoupper(preg_replace('/\s+/', '', $accountIban ?? '') ?? '');
        $compactKeyword = strtoupper(preg_replace('/\s+/', '', $keyword) ?? '');
        if (strlen($iban) >= 10 && str_contains($compactKeyword, $iban)) {
            return true;
        }

        $name = mb_strtolower(trim($goalName));

        return mb_strlen($name) >= 3 && ! self::isGenericSavingsLabel($name)
            && str_contains(mb_strtolower(trim($keyword)), $name);
    }

    public static function isGenericSavingsLabel(string $name): bool
    {
        $normalized = preg_replace('/\s+/', ' ', mb_strtolower(trim($name))) ?? '';

        return in_array($normalized, [
            'spaarrekening',
            'oranje spaarrekening',
            'naar oranje spaarrekening',
            'van oranje spaarrekening',
        ], true);
    }

    public static function matchesGoal(
        string $haystack,
        string $goalName,
        ?string $accountIban,
        string $ibanHaystack,
    ): bool {
        $txRef = self::extractRef($haystack);
        $goalRef = self::extractRef(trim($goalName.' '.($accountIban ?? '')));

        if ($txRef && $goalRef) {
            return $txRef === $goalRef;
        }

        $cleanIban = strtoupper(preg_replace('/\s+/', '', $accountIban ?? '') ?? '');
        $ibanMatch = $cleanIban !== '' && str_contains($ibanHaystack, $cleanIban);

        if ($txRef && $goalRef === null && self::isGenericSavingsLabel($goalName)) {
            return $ibanMatch;
        }

        if ($txRef) {
            return false;
        }

        $keyword = mb_strtolower(trim($goalName));
        $descMatch = mb_strlen($keyword) >= 3 && str_contains(mb_strtolower($haystack), $keyword);

        return $descMatch || $ibanMatch;
    }
}
