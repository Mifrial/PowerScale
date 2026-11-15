<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Отчёт импорта кодекса.
 */
final class LoreImportReport
{
    /**
     * Собирает отчёт.
     *
     * @param string $worldCode Код мира.
     * @param bool $dryRun Только отчёт.
     * @param array{sections: int, articles: int} $created Создано.
     * @param array{sections: int, articles: int} $updated Обновлено.
     * @param array{sections: int, articles: int} $skipped Пропущено.
     * @param array<int, array{code: string, path: string, message: string}> $warnings Предупреждения.
     *
     * @return void
     */
    public function __construct(
        private readonly string $worldCode,
        private readonly bool $dryRun,
        private readonly array $created,
        private readonly array $updated,
        private readonly array $skipped,
        private readonly array $warnings,
    ) {
    }

    /**
     * Код мира.
     *
     * @return string Код.
     */
    public function getWorldCode(): string
    {
        return $this->worldCode;
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

    /**
     * Создано.
     *
     * @return array{sections: int, articles: int} Счётчики.
     */
    public function getCreated(): array
    {
        return $this->created;
    }

    /**
     * Обновлено.
     *
     * @return array{sections: int, articles: int} Счётчики.
     */
    public function getUpdated(): array
    {
        return $this->updated;
    }

    /**
     * Пропущено.
     *
     * @return array{sections: int, articles: int} Счётчики.
     */
    public function getSkipped(): array
    {
        return $this->skipped;
    }

    /**
     * Предупреждения.
     *
     * @return array<int, array{code: string, path: string, message: string}> Предупреждения.
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
