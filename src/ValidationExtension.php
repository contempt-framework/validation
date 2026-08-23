<?php

declare(strict_types=1);

namespace Contempt\Validation;

use Contempt\Attribute\Choice;
use Contempt\Attribute\Email;
use Contempt\Attribute\Length;
use Contempt\Attribute\NotBlank;
use Contempt\Attribute\Pattern;
use Contempt\Attribute\Range;
use Contempt\Attribute\Valid;
use Contempt\Attribute\Validate;
use Contempt\Attribute\ValidateWith;
use Contempt\Compiler\Extension\PackageExtension;

final readonly class ValidationExtension extends PackageExtension
{
    protected function package(): string
    {
        return 'contempt/validation';
    }

    protected function attributes(): array
    {
        return [Choice::class, Email::class, Length::class, NotBlank::class, Pattern::class, Range::class, Valid::class, Validate::class, ValidateWith::class];
    }
}
