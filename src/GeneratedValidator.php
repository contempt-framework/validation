<?php

declare(strict_types=1);

namespace Contempt\Validation;

interface GeneratedValidator
{
    /** @return class-string */
    public function supports(): string;

    /** @return list<Violation> */
    public function validate(object $value): array;
}
