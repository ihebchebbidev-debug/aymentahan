<?php
/**
 * Helpers de normalisation pour les colonnes geo (localisation_xy, code_postal, gouvernorat)
 * partagés entre prospects.php, opportunities.php et contracts.php.
 *
 * - localisation_xy : "lat,lng" (ex: "36.123456,10.123698").
 *   Tolère les virgules françaises "36,123456,10,123698" -> "36.123456,10.123698".
 * - code_postal : chaîne libre, max 20 caractères.
 * - gouvernorat : les 24 gouvernorats tunisiens (normalisation alias / accents).
 */

if (!function_exists('tunisia_governorate_values')) {
    /** @return string[] */
    function tunisia_governorate_values(): array {
        return [
            'ARIANA', 'BEJA', 'BEN AROUS', 'BIZERTE', 'GABES', 'GAFSA',
            'JENDOUBA', 'KAIROUAN', 'KASSERINE', 'KEBILI', 'LE KEF', 'MAHDIA',
            'MANOUBA', 'MEDENINE', 'MONASTIR', 'NABEUL', 'SFAX', 'SIDI BOUZID',
            'SILIANA', 'SOUSSE', 'TATAOUINE', 'TOZEUR', 'TUNIS', 'ZAGHOUAN',
        ];
    }
}

if (!function_exists('prospect_norm_gouvernorat')) {
    function prospect_norm_gouvernorat($v): string {
        if ($v === null) return '';
        $s = trim((string) $v);
        if ($s === '') return '';

        $fold = static function (string $raw): string {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw);
            if ($t === false) {
                $t = $raw;
            }
            $t = strtoupper($t);
            $t = preg_replace('/[^A-Z0-9 ]+/', ' ', $t) ?? $t;
            $t = preg_replace('/\s+/', ' ', $t) ?? $t;
            return trim($t);
        };

        $key = $fold($s);
        $compact = str_replace(' ', '', $key);

        static $aliases = [
            'ARIANA' => 'ARIANA',
            'BEJA' => 'BEJA',
            'BEN AROUS' => 'BEN AROUS',
            'BENAROUS' => 'BEN AROUS',
            'BIZERTE' => 'BIZERTE',
            'GABES' => 'GABES',
            'GAFSA' => 'GAFSA',
            'JENDOUBA' => 'JENDOUBA',
            'KAIROUAN' => 'KAIROUAN',
            'KASSERINE' => 'KASSERINE',
            'KEBILI' => 'KEBILI',
            'KEF' => 'LE KEF',
            'LE KEF' => 'LE KEF',
            'MAHDIA' => 'MAHDIA',
            'MANOUBA' => 'MANOUBA',
            'MEDENINE' => 'MEDENINE',
            'MONASTIR' => 'MONASTIR',
            'NABEUL' => 'NABEUL',
            'SFAX' => 'SFAX',
            'SIDI BOUZID' => 'SIDI BOUZID',
            'SIDIBOUZID' => 'SIDI BOUZID',
            'SILIANA' => 'SILIANA',
            'SOUSSE' => 'SOUSSE',
            'TATAOUINE' => 'TATAOUINE',
            'TOZEUR' => 'TOZEUR',
            'TUNIS' => 'TUNIS',
            'ZAGHOUAN' => 'ZAGHOUAN',
        ];

        if (isset($aliases[$key])) {
            return $aliases[$key];
        }
        if (isset($aliases[$compact])) {
            return $aliases[$compact];
        }
        return $key;
    }
}

if (!function_exists('prospect_norm_xy')) {
    function prospect_norm_xy($v): ?string {
        if ($v === null) return null;
        $s = trim((string)$v);
        if ($s === '') return null;
        if (preg_match('/^\s*(-?\d+)[,.](\d+)\s*[,;]\s*(-?\d+)[,.](\d+)\s*$/', $s, $m)) {
            return $m[1] . '.' . $m[2] . ',' . $m[3] . '.' . $m[4];
        }
        if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $s, $m)) {
            return $m[1] . ',' . $m[2];
        }
        return mb_substr($s, 0, 64);
    }
}

if (!function_exists('prospect_norm_cp')) {
    function prospect_norm_cp($v): ?string {
        if ($v === null) return null;
        $s = trim((string)$v);
        return $s === '' ? null : mb_substr($s, 0, 20);
    }
}
