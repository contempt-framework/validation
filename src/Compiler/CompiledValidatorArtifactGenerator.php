<?php

declare(strict_types=1);

namespace Contempt\Validation\Compiler;

use Contempt\Compiler\CodeGeneration\GeneratedArtifact;
use Contempt\Compiler\CodeGeneration\PhpStubRenderer;
use Contempt\Compiler\Graph\SerializedFieldNode;
use Contempt\Compiler\Graph\SerializedTypeNode;
use Contempt\Compiler\Graph\SerializedTypeNodes;
use Contempt\Compiler\Graph\ValidationRuleKind;
use Contempt\Compiler\Graph\ValidationRuleNode;

/** Generates direct property-access structural validators from the Application Graph. */
final readonly class CompiledValidatorArtifactGenerator
{
    public function __construct(private PhpStubRenderer $stubs = new PhpStubRenderer()) {}

    public function generate(SerializedTypeNodes $types): GeneratedArtifact
    {
        $cases = [];
        $registrations = [];

        foreach ($types as $type) {
            $cases[] = $this->typeCase($type);
            $registrations[] = \sprintf(
                '    new \\Contempt\\Validation\\CompiledGeneratedValidator(\\%s::class, static fn(object $value): array => $validate($value)),',
                $type->className,
            );
        }

        $recursive = $this->requiresRecursion($types);

        $caseSource = $cases === [] ? '' : "\n" . implode("\n", $cases) . "\n";
        $registrationSource = $registrations === [] ? '' : "\n" . implode("\n", $registrations) . "\n";
        $source = $this->stubs->render(__DIR__ . '/../../resources/stubs/validators.php.stub', [
            'CASES' => $caseSource,
            'REGISTRATIONS' => $registrationSource,
            'VALIDATE_USE' => $recursive ? ' use (&$validate)' : '',
        ]);

        return new GeneratedArtifact('validators.php', $source);
    }

    private function typeCase(SerializedTypeNode $type): string
    {
        $statements = [];

        foreach ($type->fields as $field) {
            foreach ($field->validationRules as $rule) {
                $statements[] = $this->rule($field, $rule);
            }

            if ($field->cascadeValidation) {
                $value = '$value->' . $field->parameter;
                $cascade = \sprintf(
                    'array_push($violations, ...$validate(%s, $prefix . %s));',
                    $value,
                    var_export(self::path($field->wireName), true),
                );
                $statements[] = $field->nullable
                    ? \sprintf("if (%s !== null) {\n    %s\n}", $value, $cascade)
                    : $cascade;
            }
        }

        $body = $statements === [] ? '' : "\n" . self::indent(implode("\n\n", $statements), 12) . "\n";

        $use = $this->typeRequiresRecursion($type) ? ', &$validate' : '';

        return \sprintf(
            <<<'PHP'
                    \%s::class => (static function () use ($value, $prefix%s): array {
                        $violations = [];%s

                        return $violations;
                    })(),
                PHP,
            $type->className,
            $use,
            $body,
        );
    }

    private function rule(SerializedFieldNode $field, ValidationRuleNode $rule): string
    {
        $value = '$value->' . $field->parameter;
        $path = '$prefix . ' . var_export(self::path($field->wireName), true);
        $statement = match ($rule->kind) {
            ValidationRuleKind::NotBlank => $this->violation(
                \sprintf('trim(%s) === %s', $value, var_export('', true)),
                $path,
                'not_blank',
                'The value must contain at least one non-whitespace character.',
            ),
            ValidationRuleKind::Email => $this->violation(
                \sprintf('filter_var(%s, FILTER_VALIDATE_EMAIL) === false', $value),
                $path,
                'email.invalid',
                'The value must be a syntactically valid email address.',
            ),
            ValidationRuleKind::Length => $this->length($value, $path, $rule),
            ValidationRuleKind::Range => $this->range($value, $path, $rule),
            ValidationRuleKind::Choice => $this->violation(
                \sprintf('!in_array(%s, %s, true)', $value, var_export(self::choiceValues($rule), true)),
                $path,
                'choice.invalid',
                'The value is not one of the permitted choices.',
            ),
            ValidationRuleKind::Pattern => $this->violation(
                \sprintf('preg_match(%s, %s) !== 1', var_export(self::regex($rule), true), $value),
                $path,
                'pattern.mismatch',
                'The value does not match the required pattern.',
            ),
        };

        return $field->nullable
            ? \sprintf("if (%s !== null) {\n%s\n}", $value, self::indent($statement, 4))
            : $statement;
    }

    private function length(string $value, string $path, ValidationRuleNode $rule): string
    {
        $statements = [\sprintf('$length = \\Contempt\\Validation\\Internal\\CodePointLength::of(%s);', $value)];
        $statements[] = $this->violation(
            '$length === null',
            $path,
            'string.invalid_utf8',
            'The value must contain valid UTF-8.',
        );
        $min = $rule->arguments['min'] ?? null;
        $max = $rule->arguments['max'] ?? null;

        if (\is_int($min)) {
            $statements[] = $this->violation(
                \sprintf('$length !== null && $length < %d', $min),
                $path,
                'length.too_short',
                'The value is shorter than the allowed minimum.',
                ['minimum' => $min],
            );
        }

        if (\is_int($max)) {
            $statements[] = $this->violation(
                \sprintf('$length !== null && $length > %d', $max),
                $path,
                'length.too_long',
                'The value is longer than the allowed maximum.',
                ['maximum' => $max],
            );
        }

        return implode("\n\n", $statements);
    }

    private function range(string $value, string $path, ValidationRuleNode $rule): string
    {
        $statements = [];
        $min = $rule->arguments['min'] ?? null;
        $max = $rule->arguments['max'] ?? null;

        if (\is_int($min) || \is_float($min)) {
            $statements[] = $this->violation(
                \sprintf('%s < %s', $value, var_export($min, true)),
                $path,
                'range.too_low',
                'The value is lower than the allowed minimum.',
                ['minimum' => $min],
            );
        }

        if (\is_int($max) || \is_float($max)) {
            $statements[] = $this->violation(
                \sprintf('%s > %s', $value, var_export($max, true)),
                $path,
                'range.too_high',
                'The value is higher than the allowed maximum.',
                ['maximum' => $max],
            );
        }

        return implode("\n\n", $statements);
    }

    /** @param array<string, bool|float|int|string|null> $parameters */
    private function violation(
        string $condition,
        string $path,
        string $code,
        string $message,
        array $parameters = [],
    ): string {
        return \sprintf(
            "if (%s) {\n    \$violations[] = new \\Contempt\\Validation\\Violation(%s, %s, %s, %s);\n}",
            $condition,
            $path,
            var_export($code, true),
            var_export($message, true),
            var_export($parameters, true),
        );
    }

    private static function path(string $wireName): string
    {
        return '/' . str_replace(['~', '/'], ['~0', '~1'], $wireName);
    }

    /** @return non-empty-list<scalar> */
    private static function choiceValues(ValidationRuleNode $rule): array
    {
        $values = $rule->arguments['values'] ?? null;

        if (!\is_array($values) || $values === [] || !array_is_list($values)) {
            throw new \LogicException('Validated Choice rule lost its non-empty scalar list.');
        }

        $result = [];

        foreach ($values as $value) {
            if (!\is_scalar($value)) {
                throw new \LogicException('Validated Choice rule contains a non-scalar value.');
            }

            $result[] = $value;
        }

        return $result;
    }

    private static function regex(ValidationRuleNode $rule): string
    {
        $regex = $rule->arguments['regex'] ?? null;

        if (!\is_string($regex)) {
            throw new \LogicException('Validated Pattern rule lost its regular expression.');
        }

        return $regex;
    }

    private static function indent(string $source, int $spaces): string
    {
        $indent = str_repeat(' ', $spaces);

        return $indent . str_replace("\n", "\n" . $indent, $source);
    }

    private function requiresRecursion(SerializedTypeNodes $types): bool
    {
        foreach ($types as $type) {
            if ($this->typeRequiresRecursion($type)) {
                return true;
            }
        }

        return false;
    }

    private function typeRequiresRecursion(SerializedTypeNode $type): bool
    {
        foreach ($type->fields as $field) {
            if ($field->cascadeValidation) {
                return true;
            }
        }

        return false;
    }
}
