<?php
/**
 * Shared list/export limits — large datasets (500k+ rows) via pagination.
 * Single source of truth for max page size across all list endpoints.
 */

if (!defined('CRM_LIST_MAX_PER_PAGE')) {
    /** Max rows per HTTP page (prospects/contracts/opportunities paginated fetch). */
    define('CRM_LIST_MAX_PER_PAGE', 999999999);
}

if (!defined('CRM_LIST_DEFAULT_PER_PAGE')) {
    define('CRM_LIST_DEFAULT_PER_PAGE', 50000);
}

if (!defined('CRM_LIST_MAX_ROWS')) {
    /** Hard safety ceiling for unpaginated legacy full-list responses. */
    define('CRM_LIST_MAX_ROWS', 999999999);
}

if (!function_exists('crm_clamp_int')) {
    function crm_clamp_int(int $value, int $min, int $max): int {
        return max($min, min($max, $value));
    }
}

/**
 * Resolve ?limit= / ?per_page= with a high ceiling.
 * Pass $default = 0 to mean "no explicit limit" (returns $max).
 */
if (!function_exists('crm_list_limit')) {
    function crm_list_limit($requested, int $default = CRM_LIST_DEFAULT_PER_PAGE, int $max = CRM_LIST_MAX_PER_PAGE): int {
        if ($requested === null || $requested === '' || $requested === false) {
            return $default > 0 ? min($default, $max) : $max;
        }
        $n = (int)$requested;
        if ($n <= 0) {
            return $max;
        }
        return crm_clamp_int($n, 1, $max);
    }
}

if (!function_exists('crm_list_offset')) {
    function crm_list_offset($requested, int $max = CRM_LIST_MAX_ROWS): int {
        $n = max(0, (int)($requested ?? 0));
        return min($n, $max);
    }
}
