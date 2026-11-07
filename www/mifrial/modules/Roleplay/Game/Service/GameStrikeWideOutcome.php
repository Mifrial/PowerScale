<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Итог одной цели широкого удара в чате.
 */
final class GameStrikeWideOutcome
{
    /**
     * Создаёт итог.
     *
     * @param GameStrikeChat $chat Токены и подписи.
     * @param GameStrikeConsequences $consequences Публикация.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeChat $chat,
        private readonly GameStrikeConsequences $consequences,
    ) {
    }

    /**
     * Итог одной цели в чат.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param string $threadId Тред.
     * @param string $attacker Атакующий.
     * @param array<string, mixed> $record Итог.
     * @param string $typeBit Тип урона.
     * @param array<string, mixed> $speaker Спикер.
     * @param string|null $parentId Родитель.
     *
     * @return void
     */
    public function post(
        GameRecord $game, int $actorUserId, string $threadId, string $attacker, array $record,
        string $typeBit, array $speaker, ?string $parentId = null,
    ): void {
        $target = is_array($record['target'] ?? null) ? $record['target'] : ['type' => 'character', 'id' => 0];
        $defender = $this->chat->getEntityToken($target['type'] ?? 'character', $target['id'] ?? 0);
        $success = is_int($record['success'] ?? null) ? $record['success'] : 0;
        $exhaustion = $record['exhaustionDelta'] ?? 0;
        $wound = $record['wound'] ?? 0;
        $bits = [$exhaustion . ' истощения'];
        if ($wound > 0) {
            $bits[] = $wound . ' рану';
        }

        $this->consequences->chat->postOutcome(
            $game,
            $actorUserId,
            $threadId,
            $this->text($attacker, $defender, $success, $bits),
            $this->payload($record, $typeBit, $success, $exhaustion, $wound),
            $this->will($defender, $record),
            isset($record['maim']) ? $this->chat->getMaimPayload($defender, $record['maim'], $typeBit) : null,
            $speaker,
            $parentId,
        );
    }

    /**
     * Текст итога.
     *
     * @param string $attacker Атакующий.
     * @param string $defender Цель.
     * @param int $success Успех.
     * @param list<string> $bits Последствия.
     *
     * @return string Текст.
     */
    private function text(string $attacker, string $defender, int $success, array $bits): string
    {
        if ($success <= 0) {
            return $attacker . ' промахивается по ' . $defender . '!';
        }

        return $attacker . ' попадает по ' . $defender . ' с ' . $success
            . ' РУ и наносит ' . implode(' и ', $bits) . '!';
    }

    /**
     * Проверка истощения цели.
     *
     * @param string $defender Цель.
     * @param array<string, mixed> $record Итог.
     *
     * @return array{roll: mixed, text: string}|null Проверка.
     */
    private function will(string $defender, array $record): ?array
    {
        if (!isset($record['will'])) {
            return null;
        }

        return [
            'roll' => $record['will'],
            'text' => $this->chat->getExhaustionText(
                $defender,
                $record['will']['rating'] ?? 0,
                $record['willOutcome'] ?? 'clear',
            ),
        ];
    }

    /**
     * Payload attack-calc итога.
     *
     * @param array<string, mixed> $record Итог.
     * @param string $typeBit Тип урона.
     * @param int $success Успех.
     * @param mixed $exhaustion Истощение.
     * @param mixed $wound Рана.
     *
     * @return array<string, mixed> Payload.
     */
    private function payload(array $record, string $typeBit, int $success, mixed $exhaustion, mixed $wound): array
    {
        return [
            'raw' => $this->integerOf($record['injury'] ?? null),
            'damage' => is_array($record['damage'] ?? null) ? $record['damage'] : ['base' => 0, 'size' => 0],
            'damageTypeName' => $typeBit,
            'resistance' => is_array($record['resistance'] ?? null) ? ($record['resistance']['base'] ?? 0) : 0,
            'injury' => $this->pairOf($record['injury'] ?? null),
            'dodgeSoak' => $this->pairOf($record['S'] ?? null),
            'durabilityShave' => is_int($record['durabilityShave'] ?? null) ? $record['durabilityShave'] : 0,
            'endurance' => is_array($record['endurance'] ?? null) ? $record['endurance'] : ['base' => 0, 'size' => 0],
            'defenseIgnored' => false,
            'attackSrLabel' => (string) $success,
        ] + $this->successRating($record, $success) + [
            'heading' => null,
            'stun' => null,
            'shock' => null,
            'exhaustion' => $exhaustion,
            'remainingHpDamage' => $this->pairOf($record['damageRemainder'] ?? null),
            'wound' => $wound,
            'knockout' => ($record['unconscious'] ?? false) === true,
            'cuttingWound' => null,
            'layers' => [],
        ];
    }

    /**
     * Поля успеха и потолка РУ.
     *
     * @param array<string, mixed> $record Итог.
     * @param int $success Успех.
     *
     * @return array{appliedSr: int, srCap: int|null} Поля.
     */
    private function successRating(array $record, int $success): array
    {
        return [
            'appliedSr' => is_int($record['appliedSr'] ?? null) ? $record['appliedSr'] : $success,
            'srCap' => is_int($record['srCap'] ?? null) ? $record['srCap'] : null,
        ];
    }

    /**
     * Пара base/size или ноль.
     *
     * @param mixed $pair Пара base/size.
     *
     * @return array{base: int, size: int} Пара.
     */
    private function pairOf(mixed $pair): array
    {
        if (!is_array($pair) || !is_int($pair['base'] ?? null) || !is_int($pair['size'] ?? null)) {
            return ['base' => 0, 'size' => 0];
        }

        return ['base' => $pair['base'], 'size' => $pair['size']];
    }

    /**
     * Целое значение пары или 0.
     *
     * @param mixed $pair Пара base/size.
     *
     * @return int Число.
     */
    private function integerOf(mixed $pair): int
    {
        if (!is_array($pair) || !is_int($pair['base'] ?? null) || !is_int($pair['size'] ?? null)) {
            return 0;
        }

        return (new DimensionalNumber($pair['base'], $pair['size']))->toInteger();
    }
}
