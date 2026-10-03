<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Накопитель отказов одного прогона валидатора.
 */
final class CharacterProblemList
{
    /**
     * Отказы в порядке добавления.
     *
     * @var array<int, CharacterProblem>
     */
    private array $problems = [];

    /**
     * Кладёт отказ.
     *
     * @param string $code Код шага.
     * @param string $message Текст.
     * @param string $stage Стадия.
     * @param string|null $path Путь поля.
     *
     * @return void
     */
    public function add(string $code, string $message, string $stage, ?string $path = null): void
    {
        $this->problems[] = new CharacterProblem($code, $message, $stage, $path);
    }

    /**
     * Список отказов.
     *
     * @return array<int, CharacterProblem> Отказы.
     */
    public function all(): array
    {
        return $this->problems;
    }
}
