<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ModifierTrigger;

/**
 * Исполнение модификаторов действия по их данным: момент, проверка, последствия.
 */
final class GameActionModifierRunner
{
    /**
     * Создаёт исполнитель.
     *
     * @param GameActionModifierPorts $ports Правила, листы и последствия.
     * @param GameActionModifiers $modifiers Модификаторы карточки.
     * @param GameActionModifierPresenter $presenter Подписи.
     * @param GameActionModifierOutcome $outcome Последствия.
     * @param GameActionModifierRows $rows Покрытые строки.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionModifierPorts $ports,
        private readonly GameActionModifiers $modifiers,
        private readonly GameActionModifierPresenter $presenter,
        private readonly GameActionModifierOutcome $outcome,
        private readonly GameActionModifierRows $rows,
    ) {
    }

    /**
     * Выполняет объявленные модификаторы.
     *
     * @param GameActionModifierInput $input Снимок удара.
     *
     * @return array<string, mixed> Итог: коды, бросок, успех, урон, пропуск помехи.
     *
     * @throws GameInvalidException Если код, момент, лист или запись битые.
     */
    public function perform(GameActionModifierInput $input): array
    {
        $game = $input->game;
        $slice = $this->ports->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $levels = $this->ports->sheets->abilityLevels($game, $input->attackerKind, $input->attackerId);
        $thread = $this->thread($input);
        $result = ['codes' => [], 'roll' => null, 'success' => false, 'skipped' => false, 'injuries' => []];
        foreach ($input->codes as $code) {
            $this->runCode($input, $slice, $levels, $thread, $code, $result);
        }

        return $result;
    }

    /**
     * Нити удара и подписи актора.
     *
     * @param GameActionModifierInput $input Снимок действия.
     *
     * @return array{
     *     threadId: string,
     *     speaker: array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string},
     *     parentId: string,
     *     attackerName: string
     * } Нить.
     */
    private function thread(GameActionModifierInput $input): array
    {
        $threadId = $input->threadId;
        $spellId = $this->spellThreadOf($input);

        return [
            'threadId' => $threadId,
            'speaker' => $this->ports->chat->getSpeakerOf($input->attackerKind, $input->attackerId),
            'parentId' => $spellId
                ?? GameCombatThread::buildAttack($input->battle, $threadId)['parentId'],
            'attackerName' => $this->ports->chat->getEntityName($input->attackerKind, $input->attackerId),
        ];
    }

    /**
     * Тред заклинания касания, если действие несёт заклинание.
     *
     * @param GameActionModifierInput $input Снимок действия.
     *
     * @return string|null Тред или null.
     */
    private function spellThreadOf(GameActionModifierInput $input): ?string
    {
        $actionId = $input->open['id'] ?? null;
        if (!is_int($actionId)) {
            return null;
        }

        foreach ($input->rows as $row) {
            $spell = $row['spell_cast'] ?? null;
            if (is_array($spell) && $spell !== []) {
                return GameCombatThread::spellThreadId($actionId);
            }
        }

        return null;
    }

    /**
     * Один объявленный модификатор.
     *
     * @param GameActionModifierInput $input Снимок удара.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, int> $levels Уровни способностей.
     * @param array<string, mixed> $thread Нить.
     * @param string $code Код модификатора.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     *
     * @throws GameInvalidException Если код, момент, лист или запись битые.
     */
    private function runCode(
        GameActionModifierInput $input,
        CharacterRuleSlice $slice,
        array $levels,
        array $thread,
        string $code,
        array &$result,
    ): void {
        $modifier = $this->modifiers->find($slice, $input->actionRuleCode, $levels, $code);
        $effect = $modifier['effect'];
        $this->assertTrigger($effect);
        $sheet = $this->sheetOf($input);
        $roll = $this->rollOf($input->game, $sheet, $effect);
        $success = $roll === null || ($roll['success'] ?? false) === true;
        $this->announce($input, $thread, $slice, $modifier['name'], $effect, $roll, $success);
        $this->record($result, $modifier['code'], $roll, $success);
        if (!$success) {
            return;
        }

        $this->outcome->apply($input, $effect, $this->rows->covered($input->rows, $effect), $result);
    }

    /**
     * Момент модификатора поддержан.
     *
     * @param ActionModifierEffect $effect Модификатор.
     *
     * @return void
     *
     * @throws GameInvalidException Если момент чужой.
     */
    private function assertTrigger(ActionModifierEffect $effect): void
    {
        if (!ModifierTrigger::supports($effect->getTrigger())) {
            throw new GameInvalidException('Game action modifier trigger is invalid');
        }
    }

    /**
     * Лист атакующего.
     *
     * @param GameActionModifierInput $input Снимок удара.
     *
     * @return array<string, mixed> Лист.
     *
     * @throws GameInvalidException Если лист битый.
     */
    private function sheetOf(GameActionModifierInput $input): array
    {
        $snapshot = $this->ports->sheets->snapshot($input->game, $input->attackerKind, $input->attackerId);
        $sheet = $snapshot['sheet']['sheet'] ?? null;
        if (!is_array($sheet)) {
            throw new GameInvalidException('Game strike sheet is invalid');
        }

        return $sheet;
    }

    /**
     * Записывает код и бросок в итог.
     *
     * @param array<string, mixed> $result Итог.
     * @param string $code Код модификатора.
     * @param array<string, mixed>|null $roll Бросок или null.
     * @param bool $success Успех.
     *
     * @return void
     */
    private function record(array &$result, string $code, ?array $roll, bool $success): void
    {
        $result['codes'][] = $code;
        $result['roll'] = $roll;
        $result['success'] = $success;
    }

    /**
     * Пишет бросок и строку исхода в чат.
     *
     * @param GameActionModifierInput $input Снимок удара.
     * @param array<string, mixed> $thread Нить.
     * @param CharacterRuleSlice $slice Срез.
     * @param string $modifierName Название модификатора.
     * @param ActionModifierEffect $effect Модификатор.
     * @param array<string, mixed>|null $roll Бросок или null.
     * @param bool $success Успех.
     *
     * @return void
     */
    private function announce(
        GameActionModifierInput $input,
        array $thread,
        CharacterRuleSlice $slice,
        string $modifierName,
        ActionModifierEffect $effect,
        ?array $roll,
        bool $success,
    ): void {
        if ($roll !== null) {
            $this->postRolls($input, $thread, $roll);
        }

        $line = $this->outcomeLine($slice, $thread['attackerName'], $modifierName, $effect, $roll !== null, $success);
        $this->postLine($input, $thread, $line);
    }

    /**
     * Сообщение с броском.
     *
     * @param GameActionModifierInput $input Снимок удара.
     * @param array<string, mixed> $thread Нить.
     * @param array<string, mixed> $roll Бросок.
     *
     * @return void
     */
    private function postRolls(GameActionModifierInput $input, array $thread, array $roll): void
    {
        $this->ports->consequences->chat->postRolls(
            $input->game,
            $input->actorUserId,
            $thread['threadId'],
            [$roll],
            $thread['speaker'],
            $thread['parentId'],
        );
    }

    /**
     * Сообщение со строкой исхода.
     *
     * @param GameActionModifierInput $input Снимок удара.
     * @param array<string, mixed> $thread Нить.
     * @param string $line Строка.
     *
     * @return void
     */
    private function postLine(GameActionModifierInput $input, array $thread, string $line): void
    {
        $this->ports->consequences->chat->postLines(
            $input->game,
            $input->actorUserId,
            $thread['threadId'],
            [$line],
            $thread['speaker'],
            $thread['parentId'],
        );
    }

    /**
     * Бросок модификатора, если проверка задана.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист.
     * @param ActionModifierEffect $effect Модификатор.
     *
     * @return array<string, mixed>|null Бросок или null.
     */
    private function rollOf(GameRecord $game, array $sheet, ActionModifierEffect $effect): ?array
    {
        $check = $effect->getCheck();
        if ($check === null) {
            return null;
        }

        return $this->ports->consequences->optionalCheck($game, $sheet, $check->getCode(), $check->getDifficulty());
    }

    /**
     * Строка исхода в чат.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $attackerName Имя атакующего.
     * @param string $modifierName Название модификатора.
     * @param ActionModifierEffect $effect Модификатор.
     * @param bool $checked Была ли проверка.
     * @param bool $success Успех.
     *
     * @return string Текст.
     */
    private function outcomeLine(
        CharacterRuleSlice $slice,
        string $attackerName,
        string $modifierName,
        ActionModifierEffect $effect,
        bool $checked,
        bool $success,
    ): string {
        if (!$success) {
            return sprintf('%s: %s — провал, проверка не пройдена.', $attackerName, $modifierName);
        }

        $effects = $this->presenter->effects($slice, $effect->getEffects());

        return sprintf(
            '%s: %s — %s%s.',
            $attackerName,
            $modifierName,
            $checked ? 'успех: ' : '',
            $effects,
        );
    }
}
