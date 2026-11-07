<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;

/**
 * Живые карточки среза: срезы, листы и единственные проверки попадания и инициативы.
 */
final class GameCheckSpecs
{
    /**
     * Создаёт каталог.
     *
     * @param ICharacterRuleSlices $slices Срез ревизии.
     * @param ICharacters $characters Персонажи.
     * @param ICharacterSheets|null $sheets Пересчёт листа на чтении.
     * @param GameCheckChain $chain Цепочка карточек.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterRuleSlices $slices,
        private readonly ICharacters $characters,
        private readonly ?ICharacterSheets $sheets,
        private readonly GameCheckChain $chain,
    ) {
    }

    /**
     * Срез правил игры.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return CharacterRuleSlice Срез.
     *
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    public function slice(int $spaceId, int $rulesRevision): CharacterRuleSlice
    {
        return $this->slices->get($spaceId, $rulesRevision);
    }

    /**
     * Лист персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return array{version: int, sheet: array<string, mixed>} Пара.
     *
     * @throws GameNotFoundException Если строки нет.
     */
    public function characterSheet(int $characterId): array
    {
        try {
            $record = $this->characters->get($characterId);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        }

        $sheet = $record->getSheet();
        if ($this->sheets !== null) {
            $sheet = $this->sheets->compute(
                $this->slices->get($record->getSpaceId(), $record->getRulesRevision()),
                $record->getChoices(),
                $sheet,
            );
        }

        return ['version' => $record->getActualVersion(), 'sheet' => $sheet];
    }

    /**
     * Имя персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return string Имя.
     *
     * @throws GameNotFoundException Если персонажа нет.
     */
    public function characterName(int $characterId): string
    {
        try {
            return $this->characters->get($characterId)->getName();
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        }
    }

    /**
     * Проверяет карточку, не бросая кубы.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код.
     * @param string $flow Поток solo или joint.
     * @param array{base: int, size: int}|null $asked Трудность ask; в joint не нужна.
     *
     * @return void
     *
     * @throws GameInvalidException Если карточка чужая.
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    public function assertRule(int $spaceId, int $rulesRevision, string $ruleCode, string $flow, ?array $asked): void
    {
        $slice = $this->slices->get($spaceId, $rulesRevision);
        $check = $this->chain->checkOf($slice, $ruleCode);
        $this->chain->assertFlow($check, $flow);
        $kind = $check->getDifficulty()->getKind();
        if ($flow === 'solo' && $kind === 'ask' && $asked === null) {
            throw new GameInvalidException('Game check difficulty is invalid');
        }

        if ($kind !== 'ask' && $kind !== 'none') {
            throw new GameInvalidException('Game check difficulty is invalid');
        }
    }

    /**
     * Код единственной живой проверки попадания.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return string Код карточки.
     *
     * @throws GameInvalidException Если карточек нет или их несколько.
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    public function findHitCode(int $spaceId, int $rulesRevision): string
    {
        $matched = [];
        foreach ($this->slices->get($spaceId, $rulesRevision)->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof CheckSpec && $spec->isHitCheck()) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game hit check is invalid');
        }

        return $matched[0];
    }

    /**
     * Код единственной живой проверки с признаком инициативы.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return string Код карточки.
     *
     * @throws GameInvalidException Если карточек нет или их несколько.
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    public function findInitiativeCode(int $spaceId, int $rulesRevision): string
    {
        $matched = [];
        foreach ($this->slices->get($spaceId, $rulesRevision)->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof CheckSpec && $spec->isInitiative()) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game initiative check is invalid');
        }

        return $matched[0];
    }

    /**
     * Живая карточка единственной проверки инициативы.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return CheckSpec Карточка.
     *
     * @throws GameInvalidException Если карточек нет или их несколько.
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    public function initiativeCheck(int $spaceId, int $rulesRevision): CheckSpec
    {
        $slice = $this->slices->get($spaceId, $rulesRevision);

        return $this->chain->checkOf($slice, $this->findInitiativeCode($spaceId, $rulesRevision));
    }
}
