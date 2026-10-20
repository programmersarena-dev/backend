<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class RichText
{
    public static function sanitize(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        static $sanitizer;
        $sanitizer ??= new HtmlSanitizer((new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['http', 'https'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(1_000_000));

        return $sanitizer->sanitize($html);
    }
}
