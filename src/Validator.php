<?php

declare(strict_types=1);

namespace Contempt\Validation;

interface Validator
{
    public function validate(object $value): ValidationResult;
}
