<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Чат одиночного удара.
 */
final class GameStrikeSingleChat
{
    /**
     * Создаёт чат.
     *
     * @param GameStrikeChat $chat Токены и подписи.
     * @param GameStrikeConsequences $consequences Публикация.
     * @param GameStrikeSpend $spend Стоимость действия.
     * @param GameStrikeRules $rules Правила.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeChat $chat,
        private readonly GameStrikeConsequences $consequences,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeRules $rules,
    ) {
    }

    /**
     * Чат одиночного удара.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $open Открытый удар.
     * @param int $actorUserId Актор.
     * @param int $strikeId Удар.
     * @param string $reaction Реакция.
     * @param array<string, mixed> $result Итог.
     * @param array<string, mixed>|null $strengthRoll Проверка Силы до итога.
     * @param int|null $stepAp Цена шага процесса.
     *
     * @return void
     */
    public function post(
        GameRecord $game,
        GameBattleRecord $battle,
        array $open,
        int $actorUserId,
        int $strikeId,
        string $reaction,
        array $result,
        ?array $strengthRoll = null,
        ?int $stepAp = null,
    ): void {
        $threadId = 'attack-' . $strikeId;
        $parentId = GameCombatThread::buildAttack($battle, $threadId)['parentId'];
        $defender = $this->chat->getEntityToken($open['defender_kind'] ?? 'character', $open['defender_id'] ?? 0);
        $attacker = $this->chat->getEntityToken($open['attacker_kind'] ?? 'character', $open['attacker_id'] ?? 0);
        $push = is_array($result['push'] ?? null) ? $result['push'] : null;
        $pushRating = $push !== null && is_int($push['rating'] ?? null) ? $push['rating'] : null;
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $actionCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $action = $actionCode !== '' ? '[[rule:' . $actionCode . ']]' : 'действие';
        $actionAp = $stepAp ?? $this->spend->getActionAp($slice, $actionCode, true);
        $weapon = is_string($open['item_rule_code'] ?? null) ? '[[rule:' . $open['item_rule_code'] . ']]' : 'оружием';
        $kind = match ($open['profile_type'] ?? 'strike') {
            'throw' => 'бросок',
            'shoot' => 'выстрел',
            default => 'удар',
        };
        $typeBit = $this->chat->getDamageTypeBit(
            $game,
            $open['item_rule_code'] ?? null,
            $open['profile_type'] ?? null,
            $open['profile_index'] ?? null,
            $slice,
        );
        $pushImplement = $push !== null ? $this->pushImplementText($slice, $open) : null;
        $reactionText = '';
        if ($reaction === 'ignore') {
            $reactionText = ' Тот не реагирует.';
        } else {
            $reactionCode = $this->rules->reactionRuleCode($game->getSpaceId(), $game->getRulesRevision(), $reaction);
            $turn = (GameStrikeTargetRow::conditions($open)['turn'] ?? false) === true;
            $turnCode = $turn
                ? $this->rules->reactionRuleCode($game->getSpaceId(), $game->getRulesRevision(), 'turn')
                : null;
            $reactionText = ' ' . $this->chat->getReactionBit(
                'Тот',
                $reaction,
                $reactionCode,
                $this->spend->getActionAp($slice, $reactionCode ?? ''),
                $turn,
                $this->spend->getActionAp($slice, $turnCode ?? ''),
            ) . '!';
        }

        $narrative = $push !== null
            ? $attacker . ' толкает ' . $defender . '.'
            : $attacker . ' наносит ' . $typeBit . ' ' . $kind
                . ' оружием ' . $weapon . ' по ' . $defender . '.';
        if ($push !== null && ($push['canceled'] ?? false) === true) {
            $narrative .= ' Тот уклоняется — толчок сорван.';
        } else {
            $narrative .= $reactionText;
        }

        if (($open['profile_type'] ?? null) === 'shoot' || ($open['profile_type'] ?? null) === 'throw') {
            $conditionsText = $this->chat->getRangedConditionsText(
                is_array($result['attackerRoll'] ?? null) ? $result['attackerRoll'] : [],
            );
            if ($conditionsText !== null) {
                $narrative .= ' ' . $conditionsText;
            }
        }

        $exhaustion = is_int($result['exhaustionDelta'] ?? null) ? $result['exhaustionDelta'] : 0;
        $wound = is_int($result['wound'] ?? null) ? $result['wound'] : 0;
        $success = is_int($result['success'] ?? null) ? $result['success'] : 0;
        $unstable = $push !== null && is_int($push['unstable'] ?? null) ? $push['unstable'] : 0;
        $unstableCode = $push !== null && is_string($push['unstableCode'] ?? null)
            ? $push['unstableCode']
            : null;
        $unstableName = $unstableCode === null
            ? null
            : ($slice->findLive($unstableCode)?->getName() ?? $unstableCode);
        $bits = [];
        if ($push === null || $exhaustion > 0) {
            $bits[] = $exhaustion . ' истощения';
        }

        if ($wound > 0) {
            $bits[] = $wound . ' рану';
        }

        if ($push === null && $bits === []) {
            $bits[] = '0 истощения';
        }

        $will = isset($result['will']) ? [
            'roll' => $result['will'],
            'text' => $this->chat->getExhaustionText(
                $defender,
                $result['will']['rating'] ?? 0,
                $result['willOutcome'] ?? 'clear',
            ),
        ] : null;
        $maim = isset($result['maim']) ? $this->chat->getMaimPayload($defender, $result['maim'], $typeBit) : null;
        $beforeOutcome = null;
        if ($strengthRoll !== null) {
            $label = is_string($strengthRoll['label'] ?? null) && $strengthRoll['label'] !== ''
                ? $strengthRoll['label']
                : 'проверка';
            $dodgeFailed = $reaction === 'dodge';
            $prefix = $dodgeFailed ? $defender . ' не успевает уклониться — ' : '';
            $adverb = $dodgeFailed ? 'беспрепятственно ' : '';
            $beforeOutcome = [
                'text' => $prefix . $attacker . ' ' . $adverb . 'совершает проверку «' . $label . '».',
                'roll' => $strengthRoll,
            ];
        }

        $resultText = $push !== null
            ? $this->pushResultText(
                $attacker,
                $defender,
                $reaction,
                $success,
                $pushRating ?? 0,
                $pushImplement ?? 'руками',
                $bits,
                $unstable,
                $unstableName,
                $push,
            )
            : ($success > 0
                ? $attacker . ' попадает по ' . $defender . ' с ' . $success
                    . ' РУ и наносит ' . implode(' и ', $bits) . '!'
                : $attacker . ' промахивается по ' . $defender . '!');
        if (($result['unconscious'] ?? false) === true && ($result['willOutcome'] ?? null) !== 'unconscious') {
            $resultText .= ' Попадание ' . $this->damageTypeInstrumental($typeBit)
                . ' уроном вызвало потерю сознания.';
        }

        $this->consequences->post(
            $game,
            $actorUserId,
            $threadId,
            true,
            $attacker . ' совершает действие ' . $action . ' за ' . $actionAp . 'ОД.',
            $narrative,
            $result['attackerRoll'],
            $resultText,
            [
                'raw' => $this->integerOf($result['injury'] ?? null),
                'damage' => is_array($result['damage'] ?? null) ? $result['damage'] : ['base' => 0, 'size' => 0],
                'damageTypeName' => $typeBit,
                'resistance' => is_array($result['resistance'] ?? null) ? ($result['resistance']['base'] ?? 0) : 0,
                'injury' => $this->pairOf($result['injury'] ?? null),
                'dodgeSoak' => $this->pairOf($result['S'] ?? null),
                'durabilityShave' => is_int($result['durabilityShave'] ?? null) ? $result['durabilityShave'] : 0,
                'endurance' => is_array($result['endurance'] ?? null)
                    ? $result['endurance']
                    : ['base' => 0, 'size' => 0],
                'defenseIgnored' => false,
                'attackSrLabel' => (string) $success,
                'pushSrLabel' => $pushRating === null ? null : (string) $pushRating,
                'appliedSr' => is_int($result['appliedSr'] ?? null) ? $result['appliedSr'] : $success,
                'srCap' => is_int($result['srCap'] ?? null) ? $result['srCap'] : null,
                'stability' => $push !== null && is_array($push['stability'] ?? null) ? $push['stability'] : null,
                'unstable' => $push !== null && is_int($push['unstable'] ?? null) ? $push['unstable'] : null,
                'knockback' => $push !== null && is_array($push['knockback'] ?? null) ? $push['knockback'] : null,
                'heading' => null,
                'stun' => null,
                'shock' => null,
                'exhaustion' => $exhaustion,
                'remainingHpDamage' => $this->pairOf($result['damageRemainder'] ?? null),
                'wound' => $wound,
                'knockout' => ($result['unconscious'] ?? false) === true,
                'cuttingWound' => null,
                'layers' => [],
            ],
            $will,
            $maim,
            $this->chat->getSpeakerOf($open['attacker_kind'] ?? 'character', $open['attacker_id'] ?? 0),
            is_array($result['defenderRoll'] ?? null) ? $result['defenderRoll'] : null,
            $parentId,
            $beforeOutcome,
        );

        $extraLines = [];
        $knockback = $push['knockback'] ?? null;
        if (
            is_array($knockback)
            && is_int($knockback['base'] ?? null)
            && is_int($knockback['size'] ?? null)
        ) {
            $extraLines[] = $defender . ' отбрасывается на '
                . $this->chat->getDimensionalText($knockback) . ' ипари.';
        }

        if ($extraLines !== []) {
            $this->consequences->postLines(
                $game,
                $actorUserId,
                $threadId,
                $extraLines,
                $this->chat->getSpeakerOf($open['defender_kind'] ?? 'character', $open['defender_id'] ?? 0),
                $parentId,
            );
        }
    }

    /**
     * Дополнительные проверки после итога: прирост состояний.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $open Открытый удар.
     * @param int $actorUserId Актор.
     * @param int $strikeId Удар.
     * @param list<array{roll: array<string, mixed>, text: string, verdict: string|null}> $checks Проверки.
     *
     * @return void
     */
    public function postChecks(
        GameRecord $game,
        GameBattleRecord $battle,
        array $open,
        int $actorUserId,
        int $strikeId,
        array $checks,
    ): void {
        $threadId = 'attack-' . $strikeId;
        $parentId = GameCombatThread::buildAttack($battle, $threadId)['parentId'];
        $defender = $this->chat->getEntityToken($open['defender_kind'] ?? 'character', $open['defender_id'] ?? 0);
        $speaker = $this->chat->getSpeakerOf($open['defender_kind'] ?? 'character', $open['defender_id'] ?? 0);
        foreach ($checks as $entry) {
            if (!is_array($entry) || !is_array($entry['roll'] ?? null)) {
                continue;
            }

            $text = is_string($entry['text'] ?? null) ? $entry['text'] : '';
            $this->consequences->postCheck(
                $game,
                $actorUserId,
                $threadId,
                $defender . ' ' . $text,
                $entry['roll'],
                $speaker,
                $parentId,
            );
            $verdict = is_string($entry['verdict'] ?? null) ? $entry['verdict'] : '';
            if ($verdict !== '') {
                $this->consequences->postLines(
                    $game,
                    $actorUserId,
                    $threadId,
                    [$defender . ' ' . $verdict],
                    $speaker,
                    $parentId,
                );
            }
        }
    }

    /**
     * Строка исхода толчка.
     *
     * @param string $attacker Толкающий.
     * @param string $defender Цель.
     * @param string $reaction Реакция.
     * @param int $success Множитель урона.
     * @param int $rating Знаковая маржа проверки.
     * @param string $implement Чем толкают.
     * @param list<string> $bits Урон и раны.
     * @param int $unstable Неустойчивость.
     * @param string|null $unstableName Имя состояния Неустойчивости.
     * @param array<string, mixed> $push Данные толчка.
     *
     * @return string Текст.
     */
    private function pushResultText(
        string $attacker,
        string $defender,
        string $reaction,
        int $success,
        int $rating,
        string $implement,
        array $bits,
        int $unstable,
        ?string $unstableName,
        array $push,
    ): string {
        if (($push['canceled'] ?? false) === true) {
            return $attacker . ' не толкает ' . $defender . '.';
        }

        $text = $reaction === 'block' && $rating <= 0
            ? $attacker . ' толкает ' . $defender . ' ' . $implement
                . '; ' . $defender . ' блокирует — контакт ' . $success . ' РУ'
            : $attacker . ' толкает ' . $defender . ' ' . $implement . ' с ' . $rating . ' РУ';
        $knockback = $push['knockback'] ?? null;
        if (
            is_array($knockback)
            && is_int($knockback['base'] ?? null)
            && is_int($knockback['size'] ?? null)
        ) {
            $text .= ' и отбрасывает на ' . $this->chat->getDimensionalText($knockback) . ' ипари';
        }

        $text .= '!';
        if ($bits !== []) {
            $text .= ' ' . $attacker . ' наносит ' . implode(' и ', $bits) . '.';
        }

        if ($unstable > 0) {
            $name = $unstableName !== null && $unstableName !== '' ? $unstableName : 'состояние';
            $text .= ' ' . $defender . ' получает ' . $name . ' ' . $unstable . '.';
        }

        return $text;
    }

    /**
     * Строка урона дополнительного пакета.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $open Открытый удар.
     * @param int $actorUserId Актор.
     * @param int $strikeId Удар.
     * @param int $packet Номер пакета.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     */
    public function postPacket(
        GameRecord $game,
        GameBattleRecord $battle,
        array $open,
        int $actorUserId,
        int $strikeId,
        int $packet,
        array $result,
    ): void {
        $threadId = 'attack-' . $strikeId;
        $parentId = GameCombatThread::buildAttack($battle, $threadId)['parentId'];
        $defender = $this->chat->getEntityToken($open['defender_kind'] ?? 'character', $open['defender_id'] ?? 0);
        $injury = $this->integerOf($result['injury'] ?? null);
        $this->consequences->postOutcome(
            $game,
            $actorUserId,
            $threadId,
            'Пакет ' . $packet . ': ' . $defender . ' получает ещё ' . $injury . ' повреждения.',
            [
                'raw' => $injury,
                'damage' => is_array($result['damage'] ?? null) ? $result['damage'] : ['base' => 0, 'size' => 0],
                'damageTypeName' => '',
                'resistance' => is_array($result['resistance'] ?? null) ? ($result['resistance']['base'] ?? 0) : 0,
                'injury' => $this->pairOf($result['injury'] ?? null),
                'dodgeSoak' => $this->pairOf($result['S'] ?? null),
                'durabilityShave' => is_int($result['durabilityShave'] ?? null) ? $result['durabilityShave'] : 0,
                'endurance' => is_array($result['endurance'] ?? null)
                    ? $result['endurance']
                    : ['base' => 0, 'size' => 0],
                'defenseIgnored' => false,
                'attackSrLabel' => '',
                'appliedSr' => is_int($result['appliedSr'] ?? null)
                    ? $result['appliedSr']
                    : (is_int($result['success'] ?? null) ? $result['success'] : 0),
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
            ],
            null,
            null,
            $this->chat->getSpeakerOf($open['attacker_kind'] ?? 'character', $open['attacker_id'] ?? 0),
            null,
            $parentId,
        );
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

    /**
     * Чем толкают: руками, щитом или оружием.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return string Подпись.
     */
    private function pushImplementText(CharacterRuleSlice $slice, array $open): string
    {
        $code = is_string($open['item_rule_code'] ?? null) ? $open['item_rule_code'] : '';
        $rule = $code === '' ? null : $slice->findLive($code);
        $spec = $rule !== null && !$rule->isSpecBroken() ? $rule->getSpec() : null;
        if ($spec instanceof ItemSpec) {
            if ($spec->getShield() !== null) {
                return 'щитом «' . $rule->getName() . '»';
            }

            if ($spec->isInnate()) {
                return 'руками';
            }
        }

        $name = $rule?->getName() ?? '';

        return $name === '' ? 'руками' : 'оружием «' . $name . '»';
    }

    /**
     * Творительный падеж названия типа урона.
     *
     * @param string $typeBit Название типа урона.
     *
     * @return string Творительный падеж.
     */
    private function damageTypeInstrumental(string $typeBit): string
    {
        return match ($typeBit) {
            'дробящий' => 'дробящим',
            'рубящий' => 'рубящим',
            'колющий' => 'колющим',
            default => $typeBit,
        };
    }
}
