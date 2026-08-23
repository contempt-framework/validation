<?php

declare(strict_types=1);

namespace Contempt\Validation;

/** @internal runtime shell instantiated by the generated validator artifact */
final readonly class CompiledGeneratedValidator implements GeneratedValidator
{
    /** @var \Closure(object): list<Violation> */
    private \Closure $validator;

    /**
     * @param class-string $type
     * @param \Closure(object): list<Violation> $validator
     */
    public function __construct(private string $type, \Closure $validator)
    {
        $this->validator = $validator;
    }

    #[\Override]
    public function supports(): string
    {
        return $this->type;
    }

    #[\Override]
    public function validate(object $value): array
    {
        if (!$value instanceof $this->type) {
            throw new \LogicException(\sprintf('Compiled validator for %s received %s.', $this->type, $value::class));
        }

        return ($this->validator)($value);
    }
}
