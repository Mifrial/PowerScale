<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Dto;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;

/**
 * Выбор публикации: база, put и tombstone-коды.
 */
final class RuleSpaceSelection
{
    /**
     * Создаёт выбор.
     *
     * @param int $baseRevision Номер базы ≥ 1.
     * @param array<int, RuleCommitEntry> $puts Только put.
     * @param array<int, string> $removedCodes Tombstone code.
     *
     * @return void
     */
    private function __construct(
        private readonly int $baseRevision,
        private readonly array $puts,
        private readonly array $removedCodes,
    ) {
    }

    /**
     * Собирает выбор из частей.
     *
     * @param int $baseRevision Номер базы.
     * @param array<int|string, mixed> $puts Put.
     * @param array<int|string, mixed> $removedCodes Code tombstone.
     *
     * @return self Выбор.
     *
     * @throws RuleSpaceInvalidException Если вход недопустим.
     */
    public static function fromParts(int $baseRevision, array $puts, array $removedCodes = []): self
    {
        if ($baseRevision < 1) {
            throw new RuleSpaceInvalidException('Base revision is invalid');
        }

        $parsedPuts = self::parsedPuts($puts);
        $removedCodes = self::parsedRemovedCodes($removedCodes, self::putCodeSet($parsedPuts));

        return new self($baseRevision, $parsedPuts, $removedCodes);
    }

    /**
     * Номер базовой ревизии.
     *
     * @return int Номер.
     */
    public function getBaseRevision(): int
    {
        return $this->baseRevision;
    }

    /**
     * Выбранные put.
     *
     * @return array<int, RuleCommitEntry> Put.
     */
    public function getPuts(): array
    {
        return $this->puts;
    }

    /**
     * Коды правил для tombstone.
     *
     * @return array<int, string> Коды.
     */
    public function getRemovedCodes(): array
    {
        return $this->removedCodes;
    }

    /**
     * Проверяет list put.
     *
     * @param array<int|string, mixed> $puts Вход.
     *
     * @return array<int, RuleCommitEntry> Put.
     *
     * @throws RuleSpaceInvalidException Если keep, не list или дубль.
     */
    private static function parsedPuts(array $puts): array
    {
        if (!array_is_list($puts)) {
            throw new RuleSpaceInvalidException('Selection puts are invalid');
        }

        $parsedPuts = [];
        $seenCodes = [];
        foreach ($puts as $putEntry) {
            if (!$putEntry instanceof RuleCommitEntry || $putEntry->getVersionId() !== null) {
                throw new RuleSpaceInvalidException('Selection puts are invalid');
            }

            $putCode = $putEntry->getCode();
            if ($putCode === null || isset($seenCodes[$putCode])) {
                throw new RuleSpaceInvalidException('Selection puts are invalid');
            }

            $seenCodes[$putCode] = true;
            $parsedPuts[] = $putEntry;
        }

        return $parsedPuts;
    }

    /**
     * Множество code из put.
     *
     * @param array<int, RuleCommitEntry> $parsedPuts Put.
     *
     * @return array<string, true> Ключ = code.
     */
    private static function putCodeSet(array $parsedPuts): array
    {
        $putCodes = [];
        foreach ($parsedPuts as $putEntry) {
            $putCode = $putEntry->getCode();
            if ($putCode !== null) {
                $putCodes[$putCode] = true;
            }
        }

        return $putCodes;
    }

    /**
     * Trim и уникальность removed.
     *
     * @param array<int|string, mixed> $removedCodes Вход.
     * @param array<string, true> $putCodes Code put.
     *
     * @return array<int, string> Коды.
     *
     * @throws RuleSpaceInvalidException Если форма или пересечение с put.
     */
    private static function parsedRemovedCodes(array $removedCodes, array $putCodes): array
    {
        if (!array_is_list($removedCodes)) {
            throw new RuleSpaceInvalidException('Removed codes are invalid');
        }

        $parsedCodes = [];
        $seenCodes = [];
        foreach ($removedCodes as $removedCode) {
            $trimmedCode = self::trimmedRemovedCode($removedCode, $seenCodes, $putCodes);
            $seenCodes[$trimmedCode] = true;
            $parsedCodes[] = $trimmedCode;
        }

        return $parsedCodes;
    }

    /**
     * Один removed code.
     *
     * @param mixed $removedCode Вход.
     * @param array<string, true> $seenCodes Уже были.
     * @param array<string, true> $putCodes Code put.
     *
     * @return string Код.
     *
     * @throws RuleSpaceInvalidException Если пусто, дубль или пересечение.
     */
    private static function trimmedRemovedCode(mixed $removedCode, array $seenCodes, array $putCodes): string
    {
        if (!is_string($removedCode)) {
            throw new RuleSpaceInvalidException('Removed codes are invalid');
        }

        $trimmedCode = trim($removedCode);
        if ($trimmedCode === '' || isset($seenCodes[$trimmedCode]) || isset($putCodes[$trimmedCode])) {
            throw new RuleSpaceInvalidException('Removed codes are invalid');
        }

        return $trimmedCode;
    }
}
