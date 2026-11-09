<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Объявление открытия удара в чате.
 */
final class GameStrikeOpenAnnouncement
{
    /**
     * Создаёт объявление открытия.
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
     * Пишет объявление удара в его тред до реакций.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $choice Выбор удара.
     * @param array<string, mixed> $result Итог открытия.
     * @param int $actorUserId Автор сообщения.
     *
     * @return void
     */
    public function post(
        GameRecord $game,
        GameBattleRecord $battle,
        array $choice,
        array $result,
        int $actorUserId,
    ): void {
        $actor = $this->actor($choice, $result);
        if ($actor === null) {
            return;
        }

        $threadId = 'attack-' . $actor['strikeId'];
        $parentId = GameCombatThread::buildAttack($battle, $threadId)['parentId'];
        $token = $this->chat->getEntityToken($actor['type'], $actor['id']);
        $speaker = $this->chat->getSpeakerOf($actor['type'], $actor['id']);
        $cost = $this->actionAp($game, $choice, $actor['actionCode'], $result);
        $spell = $this->spellCode($choice);
        if ($spell === null) {
            $line = $token . ' совершает действие [[rule:' . $actor['actionCode'] . ']] за ' . $cost . 'ОД.';
        } else {
            $spellThread = GameCombatThread::buildSpell($battle, $actor['strikeId']);
            $this->consequences->chat->postLines(
                $game,
                $actorUserId,
                $spellThread['id'],
                [$token . ' совершает [[rule:' . $spell . ']] за ' . $cost . ' ОД. Заклинание требует касания:'],
                $speaker,
                $spellThread['parentId'],
                $spellThread['kind'],
            );
            $line = $token . ' совершает касание [[rule:' . $actor['actionCode'] . ']].';
            $parentId = $spellThread['id'];
        }

        $this->consequences->chat->postLines(
            $game,
            $actorUserId,
            $threadId,
            [$line],
            $speaker,
            $parentId,
        );
    }

    /**
     * Код заклинания касания в выборе.
     *
     * @param array<string, mixed> $choice Выбор удара.
     *
     * @return string|null Код или null.
     */
    private function spellCode(array $choice): ?string
    {
        $spell = $choice['spellCast'] ?? null;
        $code = is_array($spell) ? ($spell['spellCode'] ?? null) : null;

        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * Атакующий и удар объявления.
     *
     * @param array<string, mixed> $choice Выбор удара.
     * @param array<string, mixed> $result Итог открытия.
     *
     * @return array{strikeId: int, type: string, id: int, actionCode: string}|null Данные или null.
     */
    private function actor(array $choice, array $result): ?array
    {
        $strikeId = $result['strikeId'] ?? null;
        $attacker = $choice['attacker'] ?? null;
        $actionCode = $choice['actionRuleCode'] ?? null;
        if (!is_int($strikeId) || !is_array($attacker) || !is_string($actionCode) || $actionCode === '') {
            return null;
        }

        if (!is_string($attacker['type'] ?? null) || !is_int($attacker['id'] ?? null)) {
            return null;
        }

        return [
            'strikeId' => $strikeId,
            'type' => $attacker['type'],
            'id' => $attacker['id'],
            'actionCode' => $actionCode,
        ];
    }

    /**
     * Стоимость действия: фактически списанные ОД из итога открытия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор удара.
     * @param string $actionCode Код действия.
     * @param array<string, mixed> $result Итог открытия.
     *
     * @return int ОД.
     */
    private function actionAp(GameRecord $game, array $choice, string $actionCode, array $result): int
    {
        $spent = $result['spent'] ?? null;
        if (is_int($spent) && $spent >= 0) {
            return $spent;
        }

        $contextCosts = is_array($choice['processContext']['costs'] ?? null)
            ? $choice['processContext']['costs']
            : null;
        if ($contextCosts !== null) {
            return $this->spend->getSpendAmounts($contextCosts)['action-points'] ?? 0;
        }

        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());

        return $this->spend->getActionAp($slice, $actionCode, true);
    }
}
