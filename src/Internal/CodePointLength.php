<?php

declare(strict_types=1);

namespace Contempt\Validation\Internal;

/** @internal */
final class CodePointLength
{
    private function __construct() {}

    public static function of(string $value): ?int
    {
        $length = preg_match_all('/./us', $value);

        return $length === false ? null : $length;
    }
}
