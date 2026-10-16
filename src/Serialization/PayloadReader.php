<?php

declare(strict_types=1);

namespace TontonPro\Contracts\Serialization;

use TontonPro\Contracts\Exceptions\MalformedFact;

/**
 * Accès typé à une forme sérialisée. Chaque erreur nomme son chemin
 * (« payload.operation.movements[1].amount.amount »).
 *
 * Un objet déclare ses clés par `expect()` : une clé absente ou une clé
 * inattendue est refusée (un livre immuable ne porte pas de champ que personne
 * ne relit).
 */
final readonly class PayloadReader
{
    /**
     * @param array<array-key, mixed> $data
     */
    private function __construct(
        private array $data,
        public string $path,
    ) {
    }

    public static function of(mixed $data, string $path): self
    {
        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new MalformedFact($path, sprintf('« %s » : un objet est attendu.', $path));
        }

        return new self($data, $path);
    }

    public function expect(string ...$keys): self
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $this->data)) {
                throw new MalformedFact($this->at($key), sprintf('« %s » : clé absente.', $this->at($key)));
            }
        }

        foreach (array_keys($this->data) as $key) {
            if (! in_array((string) $key, $keys, true)) {
                $path = $this->at((string) $key);

                throw new MalformedFact($path, sprintf('« %s » : clé inattendue.', $path));
            }
        }

        return $this;
    }

    public function string(string $key): string
    {
        $value = $this->value($key);

        if (! is_string($value)) {
            throw $this->unexpected($key, 'une chaîne');
        }

        return $value;
    }

    public function nullableString(string $key): ?string
    {
        return $this->value($key) === null ? null : $this->string($key);
    }

    public function int(string $key): int
    {
        $value = $this->value($key);

        if (! is_int($value)) {
            throw $this->unexpected($key, 'un entier');
        }

        return $value;
    }

    public function nullableInt(string $key): ?int
    {
        return $this->value($key) === null ? null : $this->int($key);
    }

    public function bool(string $key): bool
    {
        $value = $this->value($key);

        if (! is_bool($value)) {
            throw $this->unexpected($key, 'un booléen');
        }

        return $value;
    }

    public function object(string $key): self
    {
        return self::of($this->value($key), $this->at($key));
    }

    public function nullableObject(string $key): ?self
    {
        return $this->value($key) === null ? null : $this->object($key);
    }

    /**
     * @return list<self>
     */
    public function objects(string $key): array
    {
        $value = $this->value($key);

        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->unexpected($key, 'une liste');
        }

        $readers = [];

        foreach ($value as $index => $item) {
            $readers[] = self::of($item, sprintf('%s[%d]', $this->at($key), $index));
        }

        return $readers;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public function enum(string $key, string $enum): \BackedEnum
    {
        $value = $this->string($key);
        $case = $enum::tryFrom($value);

        if ($case === null) {
            throw new MalformedFact($this->at($key), sprintf('« %s » : valeur « %s » inconnue.', $this->at($key), $value));
        }

        return $case;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    public function nullableEnum(string $key, string $enum): ?\BackedEnum
    {
        return $this->value($key) === null ? null : $this->enum($key, $enum);
    }

    public function roundingMode(string $key): \RoundingMode
    {
        $value = $this->string($key);

        foreach (\RoundingMode::cases() as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }

        throw new MalformedFact($this->at($key), sprintf('« %s » : règle d\'arrondi « %s » inconnue.', $this->at($key), $value));
    }

    /**
     * Le contenu brut d'un objet, pour le confier à la classe qui le relit.
     *
     * @return array<string, mixed>
     */
    public function rawObject(string $key): array
    {
        $value = $this->value($key);

        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw $this->unexpected($key, 'un objet');
        }

        $object = [];

        foreach ($value as $name => $item) {
            $object[(string) $name] = $item;
        }

        return $object;
    }

    public function at(string $key): string
    {
        return $this->path === '' ? $key : $this->path.'.'.$key;
    }

    private function value(string $key): mixed
    {
        if (! array_key_exists($key, $this->data)) {
            throw new MalformedFact($this->at($key), sprintf('« %s » : clé absente.', $this->at($key)));
        }

        return $this->data[$key];
    }

    private function unexpected(string $key, string $expected): MalformedFact
    {
        return new MalformedFact($this->at($key), sprintf('« %s » : %s est attendu.', $this->at($key), $expected));
    }
}
