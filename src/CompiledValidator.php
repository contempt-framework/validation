<?php

declare(strict_types=1);

namespace Contempt\Validation;

use Contempt\Validation\Exception\UnknownValidationType;

final readonly class CompiledValidator implements Validator
{
    /** @var array<class-string, GeneratedValidator> */
    private array $validators;

    /** @param list<GeneratedValidator> $validators */
    public function __construct(array $validators)
    {
        $indexed = [];

        foreach ($validators as $validator) {
            $type = $validator->supports();

            if (!class_exists($type)) {
                throw new \InvalidArgumentException(\sprintf('Validated class %s does not exist.', $type));
            }

            if (isset($indexed[$type])) {
                throw new \InvalidArgumentException(\sprintf('More than one generated validator targets %s.', $type));
            }

            $indexed[$type] = $validator;
        }

        ksort($indexed, SORT_STRING);
        $this->validators = $indexed;
    }

    #[\NoDiscard]
    public static function fromFile(string $artifact): self
    {
        if (!is_file($artifact)) {
            throw new \RuntimeException(\sprintf('Compiled validator artifact "%s" does not exist.', $artifact));
        }

        $validators = require $artifact;

        if (!\is_array($validators) || !array_is_list($validators)) {
            throw new \RuntimeException(\sprintf('Compiled validator artifact "%s" must return a list.', $artifact));
        }

        $validated = [];

        foreach ($validators as $validator) {
            if (!$validator instanceof GeneratedValidator) {
                throw new \RuntimeException(\sprintf(
                    'Compiled validator artifact "%s" returned a value that is not a GeneratedValidator.',
                    $artifact,
                ));
            }

            $validated[] = $validator;
        }

        return new self($validated);
    }

    #[\Override]
    public function validate(object $value): ValidationResult
    {
        $validator = $this->validators[$value::class]
            ?? throw new UnknownValidationType(\sprintf('No compiled validator exists for %s.', $value::class));

        return new ValidationResult($validator->validate($value));
    }
}
