<?php
namespace Dashboard\Core;

/**
 * Central output-escaping and value-cleaning utilities.
 *
 * IMPORTANT — two different concerns live in this class's documentation,
 * but only one lives in its code (for now):
 *
 * 1. OUTPUT ESCAPING (implemented here): encoding an untrusted value at the
 *    moment it is printed into HTML. Always safe, never destructive. Use
 *    Sanitize::e() for every dynamic value echoed into a view.
 *
 * 2. INPUT / RICH-TEXT CLEANING (not implemented here): lossy filtering of
 *    user-supplied HTML (e.g. allow-listing TinyMCE tags, stripping scripts).
 *    If/when this is needed, add it as a clearly separate method (or a
 *    dedicated HtmlCleaner class) — never conflate it with output escaping.
 *    A value that only needs escaping must never be "cleaned", and cleaning
 *    a value never removes the need to escape it on output.
 *
 * Convention: NEVER call raw htmlspecialchars() in views or controllers.
 * Always use Sanitize::e().
 */
class Sanitize
{
    /**
     * Escape a value for safe output into HTML (element content and
     * double-quoted attributes).
     *
     * - Null-safe: null becomes '' (raw htmlspecialchars(null) triggers a
     *   PHP 8.1+ deprecation; this method makes that class of bug impossible).
     * - Non-string scalars (int, float) are cast to string automatically.
     *
     * @param mixed $value        Value to escape (null-safe).
     * @param bool  $doubleEncode True (default, matches htmlspecialchars)
     *                            to encode existing HTML entities again.
     * @return string The escaped string.
     */
    public static function e(mixed $value, bool $doubleEncode = true): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', $doubleEncode);
    }
}
