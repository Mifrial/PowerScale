<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods -- один мутабельный снимок броска; конструктор входит в счётчик.

/**
 * Контекст броска: хендлеры событий мутируют пул, грани и успехи.
 */
final class RollMechanicContext
{
    /**
     * Брошенные грани до сброса.
     *
     * @var array<int, int>
     */
    private array $rolls = [];

    /**
     * Грани после сброса.
     *
     * @var array<int, int>
     */
    private array $adjustedRolls = [];

    /**
     * Сброшенные грани.
     *
     * @var array<int, int>
     */
    private array $droppedRolls = [];

    /**
     * Успехи по оставшимся граням.
     *
     * @var array<int, int>
     */
    private array $successes = [];

    /**
     * Сумма успехов.
     */
    private int $totalSuccesses = 0;

    /**
     * Коды механик, которые изменили бросок.
     *
     * @var array<int, string>
     */
    private array $applied = [];

    /**
     * Собирает контекст. Пул задаёт вызывающий, списки пустые.
     *
     * @param int $dieFaces Число граней.
     * @param int $efficiency Порог успеха грани.
     * @param array<int, RollAdvantage> $advantages Преимущества и помехи.
     * @param int $poolSize Число кубов к броску.
     *
     * @return void
     */
    public function __construct(
        private readonly int $dieFaces,
        private readonly int $efficiency,
        private readonly array $advantages,
        private int $poolSize,
    ) {
    }

    /**
     * Контекст из спеки.
     *
     * @param RollSpec $spec Спека после дефолтов.
     *
     * @return self Контекст до событий.
     */
    public static function open(RollSpec $spec): self
    {
        return new self(
            $spec->getDieFaces(),
            $spec->getEfficiency(),
            $spec->getAdvantages(),
            $spec->getDiceCount(),
        );
    }

    /**
     * Число граней.
     *
     * @return int Целое.
     */
    public function getDieFaces(): int
    {
        return $this->dieFaces;
    }

    /**
     * Порог успеха грани.
     *
     * @return int Целое.
     */
    public function getEfficiency(): int
    {
        return $this->efficiency;
    }

    /**
     * Преимущества и помехи.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    public function getAdvantages(): array
    {
        return $this->advantages;
    }

    /**
     * Число кубов к броску.
     *
     * @return int Целое.
     */
    public function getPoolSize(): int
    {
        return $this->poolSize;
    }

    /**
     * Добавляет кубы в пул.
     *
     * @param int $count Сколько кубов добавить.
     *
     * @return void
     */
    public function growPool(int $count): void
    {
        $this->poolSize += $count;
    }

    /**
     * Брошенные грани.
     *
     * @return array<int, int> Грани.
     */
    public function getRolls(): array
    {
        return $this->rolls;
    }

    /**
     * Записывает брошенные грани.
     *
     * @param array<int, int> $rolls Грани.
     *
     * @return void
     */
    public function setRolls(array $rolls): void
    {
        $this->rolls = $rolls;
    }

    /**
     * Грани после сброса.
     *
     * @return array<int, int> Грани.
     */
    public function getAdjustedRolls(): array
    {
        return $this->adjustedRolls;
    }

    /**
     * Записывает сброс.
     *
     * @param array<int, int> $dropped Сброшенные грани.
     * @param array<int, int> $kept Оставшиеся грани.
     *
     * @return void
     */
    public function applyDrop(array $dropped, array $kept): void
    {
        $this->droppedRolls = $dropped;
        $this->adjustedRolls = $kept;
    }

    /**
     * Сброшенные грани.
     *
     * @return array<int, int> Грани.
     */
    public function getDroppedRolls(): array
    {
        return $this->droppedRolls;
    }

    /**
     * Успехи.
     *
     * @return array<int, int> Успехи.
     */
    public function getSuccesses(): array
    {
        return $this->successes;
    }

    /**
     * Копирует грани в подсчёт, если сброса не было, и ставит базовые успехи.
     *
     * @return void
     */
    public function scoreBase(): void
    {
        if ($this->adjustedRolls === []) {
            $this->adjustedRolls = $this->rolls;
        }

        $this->successes = [];
        foreach ($this->adjustedRolls as $value) {
            $this->successes[] = $value <= $this->efficiency ? 1 : 0;
        }
    }

    /**
     * Прибавляет дельту к успеху грани.
     *
     * @param int $index Индекс грани.
     * @param int $delta Дельта успехов.
     *
     * @return void
     */
    public function addSuccess(int $index, int $delta): void
    {
        $this->successes[$index] += $delta;
    }

    /**
     * Суммирует успехи.
     *
     * @return void
     */
    public function sumSuccesses(): void
    {
        $this->totalSuccesses = array_sum($this->successes);
    }

    /**
     * Сумма успехов.
     *
     * @return int Сумма.
     */
    public function getTotalSuccesses(): int
    {
        return $this->totalSuccesses;
    }

    /**
     * Коды сработавших механик.
     *
     * @return array<int, string> Коды.
     */
    public function getApplied(): array
    {
        return $this->applied;
    }

    /**
     * Помечает механику сработавшей.
     *
     * @param string $code Код семейства.
     *
     * @return void
     */
    public function markApplied(string $code): void
    {
        $this->applied[] = $code;
    }
}
