<?php

declare(strict_types=1);

namespace Contempt\Validation\Tests\Compiler;

use Contempt\Compiler\Graph\SerializedFieldKind;
use Contempt\Compiler\Graph\SerializedFieldNode;
use Contempt\Compiler\Graph\SerializedTypeNode;
use Contempt\Compiler\Graph\SerializedTypeNodes;
use Contempt\Compiler\Graph\ValidationRuleKind;
use Contempt\Compiler\Graph\ValidationRuleNode;
use Contempt\Core\SourceLocation;
use Contempt\Validation\CompiledValidator;
use Contempt\Validation\Compiler\CompiledValidatorArtifactGenerator;
use Contempt\Validation\GeneratedValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompiledValidatorArtifactGenerator::class)]
final class CompiledValidatorArtifactGeneratorTest extends TestCase
{
    public function testGeneratedValidatorsEnforceEveryBuiltInConstraintAndCascadeWithStablePaths(): void
    {
        $validators = self::load(new CompiledValidatorArtifactGenerator()->generate(self::types())->contents);
        $validator = new CompiledValidator($validators);
        $result = $validator->validate(new GeneratedRegistration(
            ' ',
            'broken',
            'ab',
            101,
            'unknown',
            'ABC',
            new GeneratedContact('bad'),
        ));

        self::assertSame([
            '/contact/email:email.invalid',
            '/email:email.invalid',
            '/name:not_blank',
            '/password:length.too_short',
            '/role:choice.invalid',
            '/score:range.too_high',
            '/slug:pattern.mismatch',
        ], array_map(static fn($violation): string => $violation->path . ':' . $violation->code, $result->violations));
    }

    /** @return list<GeneratedValidator> */
    private static function load(string $contents): array
    {
        $path = sys_get_temp_dir() . '/contempt-validators-' . bin2hex(random_bytes(8)) . '.php';
        file_put_contents($path, $contents);

        try {
            $validators = require $path;
        } finally {
            unlink($path);
        }

        if (!\is_array($validators)) {
            self::fail('Generated validation artifact did not return a list.');
        }

        foreach ($validators as $validator) {
            if (!$validator instanceof GeneratedValidator) {
                self::fail('Generated validation artifact returned an invalid validator.');
            }
        }

        return array_values($validators);
    }

    private static function types(): SerializedTypeNodes
    {
        $source = new SourceLocation('src/GeneratedRegistration.php', 1);
        return new SerializedTypeNodes([
            new SerializedTypeNode(GeneratedContact::class, 'test.contact.v1', [
                self::field($source, 'email', 0, SerializedFieldKind::String, [new ValidationRuleNode(ValidationRuleKind::Email)]),
            ], $source),
            new SerializedTypeNode(GeneratedRegistration::class, 'test.registration.v1', [
                self::field($source, 'name', 0, SerializedFieldKind::String, [new ValidationRuleNode(ValidationRuleKind::NotBlank)]),
                self::field($source, 'email', 1, SerializedFieldKind::String, [new ValidationRuleNode(ValidationRuleKind::Email)]),
                self::field($source, 'password', 2, SerializedFieldKind::String, [new ValidationRuleNode(ValidationRuleKind::Length, ['max' => 20, 'min' => 3])]),
                self::field($source, 'score', 3, SerializedFieldKind::Integer, [new ValidationRuleNode(ValidationRuleKind::Range, ['max' => 100, 'min' => 1])]),
                self::field($source, 'role', 4, SerializedFieldKind::String, [new ValidationRuleNode(ValidationRuleKind::Choice, ['values' => ['admin', 'user']])]),
                self::field($source, 'slug', 5, SerializedFieldKind::String, [new ValidationRuleNode(ValidationRuleKind::Pattern, ['regex' => '/^[a-z]+$/D'])]),
                self::field($source, 'contact', 6, SerializedFieldKind::Dto, className: GeneratedContact::class, cascade: true),
            ], $source),
        ]);
    }

    /**
     * @param list<ValidationRuleNode> $rules
     * @param ?class-string $className
     */
    private static function field(
        SourceLocation $source,
        string $name,
        int $position,
        SerializedFieldKind $kind,
        array $rules = [],
        ?string $className = null,
        bool $cascade = false,
    ): SerializedFieldNode {
        return new SerializedFieldNode(
            $name,
            $position,
            $name,
            $kind,
            $className,
            false,
            false,
            null,
            $source,
            $rules,
            $cascade,
        );
    }
}

final readonly class GeneratedContact
{
    public function __construct(public string $email) {}
}

final readonly class GeneratedRegistration
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public int $score,
        public string $role,
        public string $slug,
        public GeneratedContact $contact,
    ) {}
}
