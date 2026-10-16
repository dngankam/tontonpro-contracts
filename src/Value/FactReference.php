<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;

/**
 * Désigne un autre fait sans le relire : par sa clé d'idempotence, la
 * comptabilité retrouve l'original dans son livre.
 */
final readonly class FactReference
{
    public function __construct(
        public string $factType,
        public SourceReference $source,
        public int $factVersion,
    ) {
        IdempotencyKey::requireFactType($factType, 'factType');
        IdempotencyKey::requireFactVersion($factVersion, 'factVersion');
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return new IdempotencyKey($this->source, $this->factType, $this->factVersion);
    }

    public function equals(self $other): bool
    {
        return $this->idempotencyKey()->toString() === $other->idempotencyKey()->toString();
    }

    /**
     * Le fait désigné doit être d'un type donné.
     */
    public function requireType(string $factType, string $field): void
    {
        if ($this->factType !== $factType) {
            throw new ContractViolation(
                ViolationCode::FactReferenceTypeInvalid,
                $field,
                sprintf('Un fait « %s » est attendu ici, pas « %s ».', $factType, $this->factType),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['factType' => $this->factType, 'source' => $this->source->toArray(), 'factVersion' => $this->factVersion];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('factType', 'source', 'factVersion');

        return new self(
            $reader->string('factType'),
            SourceReference::fromReader($reader->object('source')),
            $reader->int('factVersion'),
        );
    }
}
