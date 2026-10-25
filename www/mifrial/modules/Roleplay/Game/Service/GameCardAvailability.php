<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterRequirementFacts;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\AutomaticZone;
use Mifrial\Roleplay\Rule\Service\AbilityRequirementCheck;

/**
 * Гейт принадлежности карточки действия актору и её требований при объявлении.
 */
final class GameCardAvailability
{
    /**
     * Создаёт гейт.
     *
     * @param GameStrikeRules $rules Срез и снимки.
     * @param GameStrikeSheets $sheets Листы NPC.
     * @param ICharacterSheets $characterSheets Пересчёт листа NPC.
     * @param CharacterRequirementFacts $facts Факты требований.
     * @param AbilityRequirementCheck $check Оценщик требований.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly ICharacterSheets $characterSheets,
        private readonly CharacterRequirementFacts $facts,
        private readonly AbilityRequirementCheck $check,
    ) {
    }

    /**
     * Проверяет, что карточка есть у актора и её требования выполнены.
     *
     * @param GameRecord $game Игра.
     * @param string $actorKind Вид: character или npc.
     * @param int $actorId Идентификатор актора.
     * @param string $actionRuleCode Карточка действия.
     *
     * @return AbilityActionSpec Спека карточки.
     *
     * @throws GameInvalidException Если карточка недоступна или требования не выполнены.
     */
    public function assertAvailable(
        GameRecord $game,
        string $actorKind,
        int $actorId,
        string $actionRuleCode,
    ): AbilityActionSpec {
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spec = $this->specOf($slice, $actionRuleCode);
        $document = $this->document($game, $actorKind, $actorId, $slice);
        $level = $this->levelOf($document['sheet'], $actionRuleCode);
        if (!$this->isAutomatic($spec) && $level < 1) {
            throw new GameInvalidException('Game action card is not available');
        }

        $this->assertRequirements($slice, $spec, $document, $level);

        return $spec;
    }

    /**
     * Живая спека карточки действия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Карточка.
     *
     * @return AbilityActionSpec Спека.
     *
     * @throws GameInvalidException Если карточка не живая или не действие.
     */
    private function specOf(CharacterRuleSlice $slice, string $code): AbilityActionSpec
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof AbilityActionSpec) {
            throw new GameInvalidException('Game action card is not live');
        }

        return $spec;
    }

    /**
     * Choices и посчитанный лист актора.
     *
     * @param GameRecord $game Игра.
     * @param string $actorKind Вид: character или npc.
     * @param int $actorId Актор.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Документ.
     *
     * @throws GameInvalidException Если вид актора чужой или лист NPC битый.
     */
    private function document(
        GameRecord $game,
        string $actorKind,
        int $actorId,
        CharacterRuleSlice $slice,
    ): array {
        if ($actorKind === 'character') {
            return $this->characterDocument($actorId);
        }

        if ($actorKind !== 'npc') {
            throw new GameInvalidException('Game action actor is invalid');
        }

        return $this->npcDocument($game, $actorId, $slice);
    }

    /**
     * Документ персонажа.
     *
     * @param int $actorId Персонаж.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Документ.
     */
    private function characterDocument(int $actorId): array
    {
        $snapshot = $this->rules->characterSnapshot($actorId);

        return ['choices' => $snapshot['choices'], 'sheet' => $snapshot['sheet']];
    }

    /**
     * Документ NPC с пересчитанным листом.
     *
     * @param GameRecord $game Игра.
     * @param int $actorId NPC.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Документ.
     *
     * @throws GameInvalidException Если лист NPC битый.
     */
    private function npcDocument(GameRecord $game, int $actorId, CharacterRuleSlice $slice): array
    {
        $snapshot = $this->sheets->getNpcSnapshot($game->getId(), $actorId);
        $choices = $snapshot['sheet']['choices'] ?? null;
        $sheet = $snapshot['sheet']['sheet'] ?? null;
        if (!is_array($choices) || !is_array($sheet)) {
            throw new GameInvalidException('Game action npc sheet is invalid');
        }

        return ['choices' => $choices, 'sheet' => $this->characterSheets->compute($slice, $choices, $sheet)];
    }

    /**
     * Уровень карточки в листе.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Карточка.
     *
     * @return int Уровень.
     */
    private function levelOf(array $sheet, string $code): int
    {
        $levels = $sheet['abilityLevels'] ?? null;
        $level = is_array($levels) ? ($levels[$code] ?? 0) : 0;

        return is_int($level) ? $level : 0;
    }

    /**
     * Есть ли у карточки automatic-зона.
     *
     * @param AbilityActionSpec $spec Спека.
     *
     * @return bool true, если карточка доступна без уровня.
     */
    private function isAutomatic(AbilityActionSpec $spec): bool
    {
        foreach ($spec->getBase()->getZones() as $zone) {
            if ($zone instanceof AutomaticZone) {
                return true;
            }
        }

        return false;
    }

    /**
     * Требования блоков до доступного уровня включительно.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param AbilityActionSpec $spec Спека.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>} $document Документ.
     * @param int $level Уровень карточки.
     *
     * @return void
     *
     * @throws GameInvalidException Если требование не выполнено или данных нет.
     */
    private function assertRequirements(
        CharacterRuleSlice $slice,
        AbilityActionSpec $spec,
        array $document,
        int $level,
    ): void {
        $facts = $this->facts->forDocuments($slice, $document['choices'], $document['sheet']);
        $maxLevel = max(1, $level);
        foreach ($spec->getBase()->getRequirements() as $block) {
            if ($block['level'] > $maxLevel) {
                continue;
            }

            foreach ($block['requirements'] as $requirement) {
                if ($this->check->check($requirement, $facts) !== true) {
                    throw new GameInvalidException('Game action card requirements are not met');
                }
            }
        }
    }
}
