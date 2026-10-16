<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Value;

use TontonPro\Contracts\Exceptions\ContractViolation;
use TontonPro\Contracts\Exceptions\ViolationCode;

/**
 * Un instant en UTC. Un instant dans un autre fuseau est refusé, jamais
 * converti en silence. Sérialisé avec ses microsecondes.
 */
final readonly class UtcInstant
{
    private const string FORMAT = 'Y-m-d\TH:i:s.u\Z';

    public function __construct(
        public \DateTimeImmutable $value,
    ) {
        $zone = $value->getTimezone()->getName();

        if ($value->getOffset() !== 0 || ! in_array($zone, ['UTC', 'Z', '+00:00'], true)) {
            throw new ContractViolation(
                ViolationCode::InstantNotUtc,
                'value',
                sprintf('Un instant est exprimé en UTC, pas dans le fuseau « %s ».', $zone),
            );
        }
    }

    /**
     * Accepte seulement « 2026-10-16T09:30:00.000000Z » ou « 2026-10-16T09:30:00Z ».
     */
    public static function fromString(string $value): self
    {
        $format = str_contains($value, '.') ? self::FORMAT : 'Y-m-d\TH:i:s\Z';
        $parsed = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{6})?Z$/', $value) === 1
            ? \DateTimeImmutable::createFromFormat('!'.$format, $value, new \DateTimeZone('UTC'))
            : false;

        if ($parsed === false || $parsed->format($format) !== $value) {
            throw new ContractViolation(
                ViolationCode::InstantInvalid,
                'value',
                sprintf('« %s » n\'est pas un instant UTC au format AAAA-MM-JJTHH:MM:SS[.uuuuuu]Z.', $value),
            );
        }

        return new self($parsed);
    }

    public function toString(): string
    {
        return $this->value->format(self::FORMAT);
    }
}
