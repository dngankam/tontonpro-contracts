<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;

/**
 * L'auteur d'un fait, d'une dérogation ou d'une gratuité, dans son système.
 */
final readonly class Actor
{
    public function __construct(
        public string $system,
        public string $id,
        public ?string $name = null,
    ) {
        SourceReference::requireSystem($system, 'system');

        if (trim($id) === '') {
            throw new ContractViolation(ViolationCode::ActorInvalid, 'id', 'L\'identifiant de l\'auteur est obligatoire.');
        }
    }

    /**
     * @return array{system: string, id: string, name: ?string}
     */
    public function toArray(): array
    {
        return ['system' => $this->system, 'id' => $this->id, 'name' => $this->name];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('system', 'id', 'name');

        return new self($reader->string('system'), $reader->string('id'), $reader->nullableString('name'));
    }
}
