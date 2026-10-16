<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;
use TontonPro\Contracts\Vocabulary\PartyKind;

/**
 * Le débiteur (ou le bénéficiaire), figé au moment du fait. Sa nature est
 * `null` quand la source ne la connaît pas.
 */
final readonly class Party
{
    public function __construct(
        public ?PartyKind $kind,
        public SourceReference $reference,
        public string $name,
        public ?string $taxIdentifier = null,
    ) {
        if (trim($name) === '') {
            throw new ContractViolation(ViolationCode::PartyInvalid, 'name', 'Le nom du débiteur est obligatoire.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind?->value,
            'reference' => $this->reference->toArray(),
            'name' => $this->name,
            'taxIdentifier' => $this->taxIdentifier,
        ];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('kind', 'reference', 'name', 'taxIdentifier');

        return new self(
            $reader->nullableEnum('kind', PartyKind::class),
            SourceReference::fromReader($reader->object('reference')),
            $reader->string('name'),
            $reader->nullableString('taxIdentifier'),
        );
    }
}
