<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Vocabulary\Concerns;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;
use TontonPro\Contracts\Serialization\PayloadReader;

/**
 * Régime A — vocabulaire ouvert et justifié.
 *
 * Une valeur connue s'emploie telle quelle. Une valeur inconnue est acceptée
 * seulement si elle est espacée par module (« x.<système>.<code> », par exemple
 * « x.hotelpro.overbooking_gesture ») ET porte une justification non vide.
 * Une valeur éprouvée entre ensuite dans la liste par version mineure.
 *
 * Réservé aux vocabulaires dont la valeur n'entre dans AUCUN invariant.
 */
trait ExtensibleVocabulary
{
    public const string EXTENSION_PATTERN = '/^x\.[a-z][a-z0-9_-]{1,31}\.[a-z][a-z0-9_]{0,63}$/';

    public readonly string $code;

    public readonly ?string $justification;

    public function __construct(string $code, ?string $justification = null)
    {
        if ($justification !== null && trim($justification) === '') {
            throw new ContractViolation(
                ViolationCode::VocabularyJustificationMissing,
                'justification',
                'Une justification, quand elle est donnée, n\'est pas vide.',
            );
        }

        if (! in_array($code, self::knownCodes(), true)) {
            if (preg_match(self::EXTENSION_PATTERN, $code) !== 1) {
                throw new ContractViolation(
                    ViolationCode::VocabularyCodeUnknown,
                    'code',
                    sprintf('« %s » n\'est pas une valeur connue ; une extension s\'écrit « x.<système>.<code> ».', $code),
                );
            }

            if ($justification === null) {
                throw new ContractViolation(
                    ViolationCode::VocabularyJustificationMissing,
                    'justification',
                    sprintf('La valeur étendue « %s » exige une justification.', $code),
                );
            }
        }

        $this->code = $code;
        $this->justification = $justification;
    }

    /**
     * @return list<string>
     */
    abstract public static function knownCodes(): array;

    public function isKnown(): bool
    {
        return in_array($this->code, self::knownCodes(), true);
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code && $this->justification === $other->justification;
    }

    /**
     * @return array{code: string, justification: ?string}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'justification' => $this->justification];
    }

    public static function fromReader(PayloadReader $reader): self
    {
        $reader->expect('code', 'justification');

        return new self($reader->string('code'), $reader->nullableString('justification'));
    }
}
