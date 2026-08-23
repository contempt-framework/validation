<?php

declare(strict_types=1);

namespace Contempt\Validation\Tests;

use Contempt\Validation\CompiledValidator;
use Contempt\Validation\Exception\UnknownValidationType;
use Contempt\Validation\GeneratedValidator;
use Contempt\Validation\ValidationResult;
use Contempt\Validation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversClass as Covers;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompiledValidator::class)]
#[Covers(ValidationResult::class)]
#[Covers(Violation::class)]
final class CompiledValidatorTest extends TestCase
{
    public function testViolationsAreStableAndDuplicateFree(): void
    {
        $validator = new CompiledValidator([new RegistrationValidator()]);

        $result = $validator->validate(new Registration('', 'broken'));

        self::assertFalse($result->isValid());
        self::assertSame([
            '/email:email.invalid',
            '/password:length.too_short',
        ], array_map(
            static fn(Violation $violation): string => $violation->path . ':' . $violation->code,
            $result->violations,
        ));
    }

    public function testExpectedValidationFailuresAreValuesNotExceptions(): void
    {
        $result = new CompiledValidator([new RegistrationValidator()])
            ->validate(new Registration('ada@example.org', 'long-enough-secret'));

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations);
    }

    public function testUnknownTypesFailInsteadOfSilentlySkippingRules(): void
    {
        $validator = new CompiledValidator([]);

        $this->expectException(UnknownValidationType::class);
        $this->expectExceptionMessage(Unvalidated::class);

        $validator->validate(new Unvalidated());
    }

    public function testDuplicateGeneratedValidatorsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CompiledValidator([new RegistrationValidator(), new RegistrationValidator()]);
    }

    public function testViolationRejectsUnsafeOrAmbiguousMetadata(): void
    {
        foreach ([
            static fn() => new Violation('email', 'email.invalid', 'Invalid.'),
            static fn() => new Violation('/email', 'Email Invalid', 'Invalid.'),
            static fn() => new Violation('/email', 'email.invalid', ''),
            static fn() => new Violation('/email', 'email.invalid', 'Invalid.', ['actual' => new \stdClass()]),
        ] as $create) {
            try {
                $create();
                self::fail('Expected invalid violation metadata to fail.');
            } catch (\InvalidArgumentException) {
            }
        }

        $this->addToAssertionCount(1);
    }
}

final readonly class Registration
{
    public function __construct(public string $email, public string $password) {}
}

final readonly class Unvalidated {}

final readonly class RegistrationValidator implements GeneratedValidator
{
    public function supports(): string
    {
        return Registration::class;
    }

    public function validate(object $value): array
    {
        if (!$value instanceof Registration) {
            throw new \LogicException('Compiler connected an invalid validator.');
        }

        $violations = [];

        if (filter_var($value->email, FILTER_VALIDATE_EMAIL) === false) {
            $violations[] = new Violation('/email', 'email.invalid', 'The value must be a valid email address.');
            $violations[] = new Violation('/email', 'email.invalid', 'The value must be a valid email address.');
        }

        if (\strlen($value->password) < 12) {
            $violations[] = new Violation(
                '/password',
                'length.too_short',
                'The value is shorter than the allowed minimum.',
                ['minimum' => 12],
            );
        }

        return $violations;
    }
}
