<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Проверенный запрос импорта кодекса.
 */
final class LoreImportRequest
{
    /**
     * Собирает запрос.
     *
     * @param array<string|int, mixed> $payload Документ `powerscale.lore`.
     * @param string $mode New или merge.
     * @param string|null $targetWorldCode Мир для merge.
     * @param string $conflictPolicy Skip или overwrite.
     * @param bool $dryRun Только отчёт.
     *
     * @return void
     */
    public function __construct(
        private readonly array $payload,
        private readonly string $mode,
        private readonly ?string $targetWorldCode,
        private readonly string $conflictPolicy,
        private readonly bool $dryRun,
    ) {
    }

    /**
     * Документ.
     *
     * @return array<string|int, mixed> Документ.
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Режим.
     *
     * @return string New или merge.
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Мир для merge.
     *
     * @return string|null Код или null.
     */
    public function getTargetWorldCode(): ?string
    {
        return $this->targetWorldCode;
    }

    /**
     * Политика конфликтов.
     *
     * @return string Skip или overwrite.
     */
    public function getConflictPolicy(): string
    {
        return $this->conflictPolicy;
    }

    /**
     * Только отчёт.
     *
     * @return bool Флаг.
     */
    public function isDryRun(): bool
    {
        return $this->dryRun;
    }
}
