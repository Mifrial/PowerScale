<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;

/**
 * Долг действия: планирование, закрытие и итог списания.
 */
final class GameActionDebtStep
{
    /**
     * Создаёт шаг долга.
     *
     * @param GameActionPorts $ports Порты команды.
     * @param GameActionValue $value Значения строк.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionPorts $ports,
        private readonly GameActionValue $value,
    ) {
    }

    /**
     * Планирует долг и закрывает его, если действий не хватает.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $input Вход.
     * @param bool $followUp Нужна подготовка.
     * @param ProcessStep|null $step Шаг.
     * @param bool $descriptive Описательный шаг.
     * @param array<string, mixed> $state Состояние.
     *
     * @return array<string, mixed> Состояние.
     *
     * @throws GameInvalidException Если действие отвергнуто.
     */
    public function state(
        GameRecord $game,
        CharacterRuleSlice $slice,
        GameBattleRecord $battle,
        array $input,
        bool $followUp,
        ?ProcessStep $step,
        bool $descriptive,
        array $state,
    ): array {
        $debt = new GameActionDebt();
        $debtKey = $debt->key($input['actor']['type'], $input['actor']['id']);
        $debt->assertFree($battle->getActionDebt(), $debtKey);
        if (!$descriptive) {
            $state = $this->quoted($game, $battle, $input, $followUp, $step, $state);
        }

        $planned = $this->plan($debt, $slice, $state, $input);
        if ($planned['open']) {
            $state['planned'] = $planned;

            return $state;
        }

        $state['debt'] = $this->close($game, $battle, $input, $debtKey, $state['priced'], $planned);

        return $state;
    }

    /**
     * Расчёт обычного шага до плана долга.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $input Вход.
     * @param bool $followUp Нужна подготовка.
     * @param ProcessStep|null $step Шаг.
     * @param array<string, mixed> $state Состояние.
     *
     * @return array<string, mixed> Состояние.
     *
     * @throws GameInvalidException Если списание чужое.
     */
    private function quoted(
        GameRecord $game,
        GameBattleRecord $battle,
        array $input,
        bool $followUp,
        ?ProcessStep $step,
        array $state,
    ): array {
        $quote = $this->ports->perform->quote(
            $game,
            $input['actor']['type'],
            $input['actor']['id'],
            $input['actionRuleCode'],
            $battle->getPendingActionEffects(),
            !$followUp,
            $input['chosenAmounts'],
            $step,
        );
        $state['quoted'] = $quote;
        $state['priced'] = $quote['priced'];

        return $state;
    }

    /**
     * План долга.
     *
     * @param GameActionDebt $debt Долг.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $state Состояние.
     * @param array<string, mixed> $input Вход.
     *
     * @return array<string, mixed> План.
     */
    private function plan(GameActionDebt $debt, CharacterRuleSlice $slice, array $state, array $input): array
    {
        return $debt->plan(
            $slice,
            $state['quoted']['document']['sheet'],
            $state['quoted']['spends'],
            $debt->refillCode($slice),
            'action',
            $this->body($input),
        );
    }

    /**
     * Тело сохранённого объявления долга.
     *
     * @param array<string, mixed> $input Вход.
     *
     * @return array<string, mixed> Тело.
     */
    private function body(array $input): array
    {
        return [
            'battleId' => $input['battleId'],
            'expectedVersion' => $input['expectedBattle'],
            'expectedSheetVersion' => $input['expectedSheet'],
            'actionRuleCode' => $input['actionRuleCode'] ?? $input['processRuleCode'] ?? '',
            'followUpTargetKey' => $input['targetKey'],
            'chosenAmounts' => $input['chosenAmounts'],
            'actor' => $input['actor'],
            'processRuleCode' => $input['processRuleCode'],
            'processStepCode' => $input['processStepCode'],
        ];
    }

    /**
     * Закрывает долг и возвращает итог.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $input Вход.
     * @param string $debtKey Ключ долга.
     * @param array<string, mixed> $priced Цена.
     * @param array<string, mixed> $planned План.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если списание чужое.
     */
    private function close(
        GameRecord $game,
        GameBattleRecord $battle,
        array $input,
        string $debtKey,
        array $priced,
        array $planned,
    ): array {
        $data = $this->persist($game, $battle, $input, $debtKey, $priced, $planned);

        return $this->result($priced, $planned, $data['sheetVersion'], $data['version']);
    }

    /**
     * Списывает план и пишет версию боя.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $input Вход.
     * @param string $debtKey Ключ долга.
     * @param array<string, mixed> $priced Цена.
     * @param array<string, mixed> $planned План.
     *
     * @return array{sheetVersion: int, version: int} Версии.
     *
     * @throws GameInvalidException Если списание чужое.
     */
    private function persist(
        GameRecord $game,
        GameBattleRecord $battle,
        array $input,
        string $debtKey,
        array $priced,
        array $planned,
    ): array {
        $sheetVersion = $this->ports->perform->spend(
            $game,
            $input['actor']['type'],
            $input['actor']['id'],
            $input['expectedSheet'],
            $planned['spends'],
        );
        $debtMap = $battle->getActionDebt();
        $debtMap[$debtKey] = $planned['slot'];
        $saved = $this->ports->battles->replaceTracked(
            $battle->getId(),
            $input['expectedBattle'],
            $priced['pending'],
            $debtMap,
            null,
            null,
        );

        return ['sheetVersion' => $sheetVersion, 'version' => $saved->getStateVersion()];
    }

    /**
     * Итог открытого долга.
     *
     * @param array<string, mixed> $priced Цена.
     * @param array<string, mixed> $planned План.
     * @param int $sheetVersion Версия листа.
     * @param int $version Версия боя.
     *
     * @return array<string, mixed> Итог.
     */
    private function result(array $priced, array $planned, int $sheetVersion, int $version): array
    {
        return [
            'debt' => true,
            'pending' => $priced['pending'],
            'performed' => [
                'pending' => $priced['pending'],
                'sheetVersion' => $sheetVersion,
                'spent' => $this->value->spentOf($planned['spends']),
                'amounts' => $priced['amounts'],
                'rolls' => [],
            ],
            'version' => $version,
            'reaction' => null,
            'remaining' => $planned['slot']['remaining'] ?? 0,
        ];
    }
}
