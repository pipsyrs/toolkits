<?php

namespace App\Services\Links;

// Homograph-safe host display for interstitial / link pages.
class HostPresenter
{
    /**
     * @return array{displayHost: string, isIdn: bool, unicodeHost: string|null}
     */
    public function describe(string $url): array
    {
        $host = strtolower(rtrim(trim((string) parse_url($url, PHP_URL_HOST), '[]'), '.'));

        $ascii = $host !== '' ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : false;
        $displayHost = $ascii !== false && $ascii !== '' ? strtolower($ascii) : $host;

        $unicode = $displayHost !== '' ? idn_to_utf8($displayHost, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : false;
        $unicodeHost = $unicode !== false && $unicode !== '' ? $unicode : null;

        $isIdn = $unicodeHost !== null && $unicodeHost !== $displayHost;

        return [
            'displayHost' => $displayHost,
            'isIdn' => $isIdn,
            'unicodeHost' => $isIdn ? $unicodeHost : null,
        ];
    }
}
