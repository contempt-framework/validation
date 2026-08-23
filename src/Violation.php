<?php

declare(strict_types=1);

namespace Contempt\Validation;

final readonly class Violation
{
    /** @var array<string, bool|float|int|string|null> */
    public array $parameters;

    /** @param array<string, mixed> $parameters */
    public function __construct(
        public string $path,
        public string $code,
        public string $message,
        array $parameters = [],
    ) {
        if ($path !== '' && preg_match('/^(?:\/(?:[^~\/\x00-\x1F]|~[01])*)+$/u', $path) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Violation path "%s" is not a JSON Pointer.', $path));
        }

        if (preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $code) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Violation code "%s" is invalid.', $code));
        }

        if (trim($message) === '' || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $message) === 1) {
            throw new \InvalidArgumentException('A violation message must be non-blank and free of control bytes.');
        }

        $safe = [];

        foreach ($parameters as $name => $value) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
                throw new \InvalidArgumentException(\sprintf('Violation parameter name "%s" is invalid.', $name));
            }

            if (!\is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException(\sprintf('Violation parameter "%s" must be scalar or null.', $name));
            }

            $safe[$name] = $value;
        }

        ksort($safe, SORT_STRING);
        $this->parameters = $safe;
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->path,
            $this->code,
            $this->message,
            $this->parameters,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
