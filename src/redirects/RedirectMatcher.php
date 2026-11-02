<?php

namespace rondodevs\toolkit\redirects;

/**
 * Matches a request against a redirect's source the way Ether SEO did, so
 * redirects imported from it keep behaving the same:
 *
 * - a source equal to the path, slashes around it ignored, redirects;
 * - otherwise the source is a regex: `/pattern/flags` as written, or a bare
 *   pattern such as `blog/(.*)`, matched whole and case-insensitively, with
 *   `$1`... in the destination.
 *
 * A source with `://` is matched against the full URL (`https://host/path`),
 * for frontends serving several domains; any other against the path.
 */
final class RedirectMatcher
{
    /**
     * The destination for a request, or null when the source doesn't match.
     *
     * @param string $path The path without slashes around it, plus `?query` when there's one
     * @param string $url The full URL, path without a trailing slash, plus `?query`
     */
    public static function match(string $source, string $destination, string $path, string $url): ?string
    {
        $subject = str_contains($source, '://') ? $url : $path;
        $exact = str_contains($source, '://') ? rtrim($source, '/') : trim($source, '/');

        if ($exact === $subject) {
            return $destination;
        }

        $regex = self::regex($source);

        if ($regex !== null && @preg_match($regex, $subject) === 1) {
            return (string)preg_replace($regex, $destination, $subject);
        }

        return null;
    }

    public static function isRegex(string $source): bool
    {
        return preg_match('/[()*+\[\]^$|\\\\]/', $source) === 1 || preg_match('#^/.+/[imsxuADSUXJ]+$#', $source) === 1;
    }

    /**
     * Ether's reading of a source as a regex (RedirectsService::_isRedirectRegex()).
     */
    private static function regex(string $source): ?string
    {
        // Not written as /pattern/flags: escape the `?` and `/` outside groups
        if (preg_match('/\/(.*)\/([g|m|i|x|X|s|u|U|A|J|D]+)/m', $source) === 0) {
            foreach (['?', '/'] as $char) {
                $quoted = preg_quote($char, '/');
                preg_match_all("/(?<!\\\\){$quoted}(?![^(]*\\))/", $source, $matches, PREG_OFFSET_CAPTURE);

                foreach (array_reverse($matches[0]) as [, $offset]) {
                    $source = substr_replace($source, '\\' . $char, $offset, 1);
                }
            }
        }

        if (@preg_match($source, '') === false) {
            $source = '/^' . $source . '$/i';
        }

        return @preg_match($source, '') === false ? null : $source;
    }
}
