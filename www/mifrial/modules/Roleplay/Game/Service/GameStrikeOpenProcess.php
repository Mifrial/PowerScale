<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Шаг процесса и повтор оружия открытого удара.
 */
final class GameStrikeOpenProcess
{
    /**
     * Создаёт подготовку процесса.
     *
     * @param GameProcessFlow $processes Сессия процесса.
     *
     * @return void
     */
    public function __construct(private readonly GameProcessFlow $processes)
    {
    }

    /**
     * Проверяет шаг процесса и подставляет его действие.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $choice Выбор удара.
     *
     * @return array<string, mixed>|null Контекст шага или null.
     *
     * @throws GameInvalidException Если шаг недоступен.
     */
    public function context(GameRecord $game, GameBattleRecord $battle, array &$choice): ?array
    {
        $code = $choice['processRuleCode'] ?? null;
        if (!is_string($code) || $code === '') {
            return null;
        }

        $attacker = $this->attacker($choice);
        $context = $this->processes->prepare(
            $game,
            $battle,
            $attacker,
            $code,
            is_string($choice['processStepCode'] ?? null) ? $choice['processStepCode'] : null,
            'strike',
            is_array($choice['defender'] ?? null) ? $choice['defender'] : null,
        );
        if ($context === null) {
            return null;
        }

        $this->apply($choice, $context);

        return $context;
    }

    /**
     * Открытый процесс защиты, если удар его продолжает.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $choice Выбор удара.
     * @param string $actionCode Карточка действия.
     * @param array<string, mixed> $snapshot Снимок атакующего.
     *
     * @return int|null Id процесса или null.
     */
    public function closing(
        GameRecord $game,
        GameBattleRecord $battle,
        array $choice,
        string $actionCode,
        array $snapshot,
    ): ?int {
        $defender = $this->actor($choice['defender'] ?? null);
        $attacker = $this->actor($choice['attacker'] ?? null);
        if ($defender === null || $attacker === null) {
            return null;
        }

        $sheet = is_array($snapshot['sheet']['sheet'] ?? null) ? $snapshot['sheet']['sheet'] : [];
        $levels = is_array($sheet['abilityLevels'] ?? null) ? $sheet['abilityLevels'] : [];

        return $this->processes->closingFor($game, $battle, $attacker, $defender, $actionCode, $levels);
    }

    /**
     * Начинает шаг процесса.
     *
     * @param GameBattleRecord $battle Бой.
     * @param string $kind Вид атакующего.
     * @param int $id Атакующий.
     * @param array<string, mixed> $context Контекст шага.
     *
     * @return int Id процесса.
     */
    public function begin(GameBattleRecord $battle, string $kind, int $id, array $context): int
    {
        return $this->processes->begin($battle, ['type' => $kind, 'id' => $id], $context);
    }

    /**
     * Проставляет повтор оружия в строки удара.
     *
     * @param list<array<string, mixed>> $targets Строки.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return list<array<string, mixed>> Строки.
     */
    public function withRepeats(array $targets, array $choice): array
    {
        $prior = is_int($choice['repeatWeaponUses'] ?? null) ? $choice['repeatWeaponUses'] : 0;
        foreach ($targets as $index => $target) {
            $targets[$index]['repeat_weapon_uses'] = $prior;
        }

        return $targets;
    }

    /**
     * Подставляет контекст шага в выбор.
     *
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed> $context Контекст.
     *
     * @return void
     */
    private function apply(array &$choice, array $context): void
    {
        $choice['actionRuleCode'] = $context['actionCode'];
        $choice['processContext'] = $context;
        $choice['processStepCosts'] = $context['costs'];
        $choice['repeatWeaponUses'] = $this->processes->priorWeaponUses(
            $context['session'] ?? null,
            $context['spec'],
            $this->processes->weaponKey($choice),
        );
    }

    /**
     * Атакующий или цель процесса.
     *
     * @param mixed $value Ссылка выбора.
     *
     * @return array{type: string, id: int}|null Ссылка или null.
     */
    private function actor(mixed $value): ?array
    {
        $item = is_array($value) ? $value : null;
        if ($item === null || !is_string($item['type'] ?? null) || !is_int($item['id'] ?? null)) {
            return null;
        }

        return ['type' => $item['type'], 'id' => $item['id']];
    }

    /**
     * Атакующий выбора.
     *
     * @param array<string, mixed> $choice Выбор.
     *
     * @return array{type: string, id: int} Атакующий.
     *
     * @throws GameInvalidException Если атакующий битый.
     */
    private function attacker(array $choice): array
    {
        $attacker = $choice['attacker'] ?? null;
        if (!is_array($attacker) || !is_string($attacker['type'] ?? null) || !is_int($attacker['id'] ?? null)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        return ['type' => $attacker['type'], 'id' => $attacker['id']];
    }
}
