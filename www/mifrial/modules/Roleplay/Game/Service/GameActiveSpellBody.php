<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\Action\DropGameSpellInput;
use Mifrial\Roleplay\Game\Dto\Action\SustainGameSpellInput;

/**
 * Разбор тел game.sustainSpell и game.dropSpell.
 */
final class GameActiveSpellBody
{
    /**
     * Нормализует тело поддержания.
     *
     * @param SustainGameSpellInput $input Вход.
     *
     * @return array<string, mixed> Тело.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function sustain(SustainGameSpellInput $input): array
    {
        return $this->common(
            $input->gameId,
            $input->battleId,
            $input->idempotencyKey,
            $input->expectedBattleVersion,
            $input->expectedSheetVersion,
            $input->spellId,
            $input->expectedSpellVersion,
        ) + ['sustainPower' => $this->power($input->sustainPower)];
    }

    /**
     * Нормализует тело прекращения.
     *
     * @param DropGameSpellInput $input Вход.
     *
     * @return array<string, mixed> Тело.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function drop(DropGameSpellInput $input): array
    {
        return $this->common(
            $input->gameId,
            $input->battleId,
            $input->idempotencyKey,
            $input->expectedBattleVersion,
            $input->expectedSheetVersion,
            $input->spellId,
            $input->expectedSpellVersion,
        );
    }

    /**
     * Общие поля команды.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param string $spellId Строковый id.
     * @param int $expectedSpellVersion Версия строки.
     *
     * @return array<string, mixed> Тело.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function common(
        int $gameId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
        string $spellId,
        int $expectedSpellVersion,
    ): array {
        if ($gameId < 1 || $battleId < 1 || $idempotencyKey === '') {
            throw $this->invalid();
        }

        if ($expectedBattleVersion < 0 || $expectedSheetVersion < 0 || $expectedSpellVersion < 1) {
            throw $this->invalid();
        }

        return [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => $idempotencyKey,
            'expectedBattleVersion' => $expectedBattleVersion,
            'expectedSheetVersion' => $expectedSheetVersion,
            'spellId' => $this->spellId($spellId),
            'expectedSpellVersion' => $expectedSpellVersion,
        ];
    }

    /**
     * Размерная пара мощи.
     *
     * @param array $power Мощь.
     *
     * @return array{base: int, size: int} Пара.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function power(array $power): array
    {
        $base = $power['base'] ?? null;
        $size = $power['size'] ?? null;
        if (!is_int($base) || !is_int($size) || array_is_list($power) || count($power) !== 2) {
            throw $this->invalid();
        }

        return ['base' => $base, 'size' => $size];
    }

    /**
     * Строковый id строки в целое.
     *
     * @param string $spellId Идентификатор.
     *
     * @return int Положительный id.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function spellId(string $spellId): int
    {
        if (preg_match('/^[1-9][0-9]{0,18}$/', $spellId) !== 1) {
            throw $this->invalid();
        }

        return (int) $spellId;
    }

    /**
     * Ошибка формы запроса.
     *
     * @return ActionException Исключение.
     */
    private function invalid(): ActionException
    {
        return new ActionException('INVALID_PARAMS', 'Game active spell command is invalid');
    }
}
