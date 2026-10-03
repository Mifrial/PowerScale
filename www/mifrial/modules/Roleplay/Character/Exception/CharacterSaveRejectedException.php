<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Exception;

use Mifrial\Roleplay\Character\Dto\CharacterProblem;
use Throwable;

/**
 * Create/update отклонены валидатором: строки нет, problems в конверте.
 */
final class CharacterSaveRejectedException extends CharacterException
{
    /**
     * Создаёт отказ записи.
     *
     * @param array<int, CharacterProblem> $problems Отказы.
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        private readonly array $problems,
        string $message = 'Character is invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('CHARACTER_INVALID', $message, $previous);
    }

    /**
     * Problems для конверта ошибки.
     *
     * @return array<string, mixed> Поле problems.
     */
    public function getErrorDetails(): array
    {
        return ['problems' => self::rows($this->problems)];
    }

    /**
     * Список problems в JSON.
     *
     * @param array<int, CharacterProblem> $problems Отказы.
     *
     * @return array<int, array<string, string>> Строки.
     */
    public static function rows(array $problems): array
    {
        $rows = [];
        foreach ($problems as $problem) {
            $rows[] = self::row($problem);
        }

        return $rows;
    }

    /**
     * Одна problem. Пустой path не кладётся.
     *
     * @param CharacterProblem $problem Отказ.
     *
     * @return array<string, string> Поля.
     */
    private static function row(CharacterProblem $problem): array
    {
        $row = [
            'code' => $problem->getCode(),
            'message' => $problem->getMessage(),
            'stage' => $problem->getStage(),
        ];
        if ($problem->getPath() !== null) {
            $row['path'] = $problem->getPath();
        }

        return $row;
    }
}
