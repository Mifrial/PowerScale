<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Итоги одиночного удара в чате.
 */
final class GameStrikeSingleOutcome
{
    /**
     * Создаёт публикацию итогов.
     *
     * @param GameStrikeChat $chat Токены и подписи.
     * @param GameStrikeConsequences $consequences Публикация.
     * @param GameStrikeSinglePush $push Тексты толчка.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeChat $chat,
        private readonly GameStrikeConsequences $consequences,
        private readonly GameStrikeSinglePush $push,
    ) {
    }

    /**
     * Публикует итог удара.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $result Итог.
     * @param string $narrative Нарратив.
     * @param string $resultText Строка итога.
     * @param array{preRollChecks: list<array<string, mixed>>, beforeOutcome: array<string, mixed>|null} $pre Проверки.
     *
     * @return void
     */
    public function post(
        GameRecord $game,
        int $actorUserId,
        array $header,
        array $result,
        string $narrative,
        string $resultText,
        array $pre,
    ): void {
        $success = $this->intOf($result['success'] ?? null);
        $this->consequences->post(
            $game,
            $actorUserId,
            $header['threadId'],
            true,
            '',
            $narrative,
            $result['attackerRoll'],
            $resultText,
            $this->calc($header, $result, $success),
            $this->will($header, $result),
            $this->maim($header, $result),
            $header['attackerSpeaker'],
            is_array($result['defenderRoll'] ?? null) ? $result['defenderRoll'] : null,
            $header['parentId'],
            $pre['beforeOutcome'] ?? null,
            $pre['preRollChecks'],
        );
    }
    /**
     * Строка итога: толчок или урон.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $result Итог.
     * @param string $reaction Реакция.
     * @param list<string> $bits Урон и раны.
     *
     * @return string Текст.
     */
    public function text(array $header, array $open, array $result, string $reaction, array $bits): string
    {
        $text = $header['push'] !== null
            ? $this->push->resultText($header, $open, $result, $reaction, $bits)
            : $this->damageText($header, $result, $bits);
        if (($result['unconscious'] ?? false) === true && ($result['willOutcome'] ?? null) !== 'unconscious') {
            $text .= ' Попадание ' . $this->instrumental($header['typeBit'])
                . ' уроном вызвало потерю сознания.';
        }

        return $text;
    }

    /**
     * Битсы урона и ран.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $result Итог.
     *
     * @return list<string> Битсы.
     */
    public function bits(array $header, array $result): array
    {
        $exhaustion = $this->intOf($result['exhaustionDelta'] ?? null);
        $wound = $this->intOf($result['wound'] ?? null);
        $bits = [];
        if ($header['push'] === null || $exhaustion > 0) {
            $bits[] = $exhaustion . ' истощения';
        }

        if ($wound > 0) {
            $bits[] = $wound . ' рану';
        }

        return $bits;
    }

    /**
     * Дополнительные строки отбрасывания толчком.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param array<string, mixed> $header Подписи.
     *
     * @return void
     */
    public function extras(GameRecord $game, int $actorUserId, array $header): void
    {
        $knockback = $header['push']['knockback'] ?? null;
        if (
            !is_array($knockback)
            || !is_int($knockback['base'] ?? null)
            || !is_int($knockback['size'] ?? null)
        ) {
            return;
        }

        $this->consequences->chat->postLines(
            $game,
            $actorUserId,
            $header['threadId'],
            [$header['defender'] . ' отбрасывается на ' . $this->chat->getDimensionalText($knockback) . ' ипари.'],
            $header['defenderSpeaker'],
            $header['parentId'],
        );
    }

    /**
     * Строка урона дополнительного пакета.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param array{threadId: string, parentId: string|null} $thread Тред.
     * @param string $defender Цель.
     * @param array<string, mixed> $speaker Спикер.
     * @param int $packet Номер пакета.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     */
    public function packet(
        GameRecord $game,
        int $actorUserId,
        array $thread,
        string $defender,
        array $speaker,
        int $packet,
        array $result,
    ): void {
        $injury = $this->integerOf($result['injury'] ?? null);
        $this->consequences->chat->postOutcome(
            $game,
            $actorUserId,
            $thread['threadId'],
            'Пакет ' . $packet . ': ' . $defender . ' получает ещё ' . $injury . ' повреждения.',
            $this->packetCalc($result, $injury),
            null,
            null,
            $speaker,
            $thread['parentId'],
        );
    }

    /**
     * Строка урона без толчка.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $result Итог.
     * @param list<string> $bits Урон и раны.
     *
     * @return string Текст.
     */
    private function damageText(array $header, array $result, array $bits): string
    {
        $success = $this->intOf($result['success'] ?? null);
        if ($success > 0) {
            return $header['attacker'] . ' попадает по ' . $header['defender'] . ' с ' . $success
                . ' РУ и наносит ' . implode(' и ', $bits) . '!';
        }

        return $header['attacker'] . ' промахивается по ' . $header['defender'] . '!';
    }

    /**
     * Payload attack-calc итога.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $result Итог.
     * @param int $success Успех.
     *
     * @return array<string, mixed> Payload.
     */
    private function calc(array $header, array $result, int $success): array
    {
        $exhaustion = $this->intOf($result['exhaustionDelta'] ?? null);
        $wound = $this->intOf($result['wound'] ?? null);

        return [
            'raw' => $this->integerOf($result['injury'] ?? null),
            'damage' => is_array($result['damage'] ?? null) ? $result['damage'] : ['base' => 0, 'size' => 0],
            'damageTypeName' => $header['typeBit'],
            'resistance' => is_array($result['resistance'] ?? null) ? ($result['resistance']['base'] ?? 0) : 0,
            'injury' => $this->pairOf($result['injury'] ?? null),
            'dodgeSoak' => $this->pairOf($result['S'] ?? null),
            'durabilityShave' => is_int($result['durabilityShave'] ?? null) ? $result['durabilityShave'] : 0,
            'endurance' => is_array($result['endurance'] ?? null) ? $result['endurance'] : ['base' => 0, 'size' => 0],
            'defenseIgnored' => false,
            'attackSrLabel' => (string) $success,
        ] + $this->pushCalc($header, $result, $success) + [
            'heading' => null,
            'stun' => null,
            'shock' => null,
            'exhaustion' => $exhaustion,
            'remainingHpDamage' => $this->pairOf($result['damageRemainder'] ?? null),
            'wound' => $wound,
            'knockout' => ($result['unconscious'] ?? false) === true,
            'cuttingWound' => null,
            'layers' => [],
        ];
    }

    /**
     * Поля толчка в payload.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $result Итог.
     * @param int $success Успех.
     *
     * @return array<string, mixed> Поля.
     */
    private function pushCalc(array $header, array $result, int $success): array
    {
        $push = $header['push'];

        return [
            'pushSrLabel' => $header['pushRating'] === null ? null : (string) $header['pushRating'],
            'appliedSr' => is_int($result['appliedSr'] ?? null) ? $result['appliedSr'] : $success,
            'srCap' => is_int($result['srCap'] ?? null) ? $result['srCap'] : null,
            'stability' => $push !== null && is_array($push['stability'] ?? null) ? $push['stability'] : null,
            'unstable' => $push !== null && is_int($push['unstable'] ?? null) ? $push['unstable'] : null,
            'knockback' => $push !== null && is_array($push['knockback'] ?? null) ? $push['knockback'] : null,
        ];
    }

    /**
     * Проверка истощения цели.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $result Итог.
     *
     * @return array{roll: mixed, text: string}|null Проверка.
     */
    private function will(array $header, array $result): ?array
    {
        if (!isset($result['will'])) {
            return null;
        }

        return [
            'roll' => $result['will'],
            'text' => $this->chat->getExhaustionText(
                $header['defender'],
                $result['will']['rating'] ?? 0,
                $result['willOutcome'] ?? 'clear',
            ),
        ];
    }

    /**
     * Payload увечья.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $result Итог.
     *
     * @return array<string, mixed>|null Payload.
     */
    private function maim(array $header, array $result): ?array
    {
        return isset($result['maim'])
            ? $this->chat->getMaimPayload($header['defender'], $result['maim'], $header['typeBit'])
            : null;
    }

    /**
     * Payload дополнительного пакета.
     *
     * @param array<string, mixed> $result Итог.
     * @param int $injury Повреждение.
     *
     * @return array<string, mixed> Payload.
     */
    private function packetCalc(array $result, int $injury): array
    {
        return [
            'raw' => $injury,
            'damage' => is_array($result['damage'] ?? null) ? $result['damage'] : ['base' => 0, 'size' => 0],
            'damageTypeName' => '',
            'resistance' => is_array($result['resistance'] ?? null) ? ($result['resistance']['base'] ?? 0) : 0,
            'injury' => $this->pairOf($result['injury'] ?? null),
            'dodgeSoak' => $this->pairOf($result['S'] ?? null),
            'durabilityShave' => is_int($result['durabilityShave'] ?? null) ? $result['durabilityShave'] : 0,
            'endurance' => is_array($result['endurance'] ?? null) ? $result['endurance'] : ['base' => 0, 'size' => 0],
            'defenseIgnored' => false,
            'attackSrLabel' => '',
            'appliedSr' => is_int($result['appliedSr'] ?? null)
                ? $result['appliedSr']
                : $this->intOf($result['success'] ?? null),
            'srCap' => is_int($result['srCap'] ?? null) ? $result['srCap'] : null,
            'heading' => null,
            'stun' => null,
            'shock' => null,
            'exhaustion' => 0,
            'remainingHpDamage' => ['base' => 0, 'size' => 0],
            'wound' => 0,
            'knockout' => false,
            'cuttingWound' => null,
            'layers' => [],
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
     * Целое значение или 0.
     *
     * @param mixed $value Значение.
     *
     * @return int Число.
     */
    private function intOf(mixed $value): int
    {
        return is_int($value) ? $value : 0;
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

    /**
     * Творительный падеж названия типа урона.
     *
     * @param string $typeBit Название типа урона.
     *
     * @return string Творительный падеж.
     */
    private function instrumental(string $typeBit): string
    {
        return match ($typeBit) {
            'дробящий' => 'дробящим',
            'рубящий' => 'рубящим',
            'колющий' => 'колющим',
            default => $typeBit,
        };
    }
}
