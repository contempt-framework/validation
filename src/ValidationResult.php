<?php

declare(strict_types=1);

namespace Contempt\Validation;

final readonly class ValidationResult
{
    /** @var list<Violation> */
    public array $violations;

    /** @param list<Violation> $violations */
    public function __construct(array $violations)
    {
        $unique = [];

        foreach ($violations as $violation) {
            $unique[$violation->fingerprint()] = $violation;
        }

        $violations = array_values($unique);
        usort($violations, static fn(Violation $left, Violation $right): int => [
            $left->path,
            $left->code,
            $left->message,
            $left->fingerprint(),
        ] <=> [
            $right->path,
            $right->code,
            $right->message,
            $right->fingerprint(),
        ]);
        $this->violations = $violations;
    }

    public function isValid(): bool
    {
        return $this->violations === [];
    }
}
