<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Game\Dto\GameStrikeActorRef;
use Mifrial\Roleplay\Game\Dto\GameStrikeAttack;
use Mifrial\Roleplay\Game\Dto\GameStrikeTarget;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;

/**
 * Сборка снимка удара из запроса.
 *
 * Здесь заканчивается доступ к данным: дальше калькуляторы читают только
 * готовый GameStrikeAttack.
 */
final class GameStrikeAttackFactory
{
    /**
     * Принимает срезы, участников, снаряжение и контекст профиля.
     *
     * @param GameStrikeSlices $slices Срезы ревизии.
     * @param GameStrikeParticipants $participants Участники удара.
     * @param GameStrikeEquipment $equipment Снаряжение удара.
     * @param GameStrikeProfileContext $profiles Контекст профиля.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSlices $slices,
        private readonly GameStrikeParticipants $participants,
        private readonly GameStrikeEquipment $equipment,
        private readonly GameStrikeProfileContext $profiles,
    ) {
    }

    /**
     * Собирает снимок удара.
     *
     * @param GameStrikeTarget $target Запрос удара.
     *
     * @return GameStrikeAttack Снимок.
     *
     * @throws GameInvalidException Если вид, профиль или лист битые.
     * @throws RuleInvalidException Если база профиля битая.
     * @throws CharacterInvalidException Если снимок битый.
     * @throws CharacterNotFoundException Если персонажа нет.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function build(GameStrikeTarget $target): GameStrikeAttack
    {
        $profileRef = $target->profile;
        $documents = $this->documents($target->actor);
        $item = $this->equipment->selectedItem(
            $profileRef->spaceId,
            $profileRef->rulesRevision,
            $documents['choices'],
            $profileRef->itemInventoryId,
            $profileRef->itemRuleCode,
        );
        $profile = $this->profileOf($item, $profileRef->profileIndex, $profileRef->profileType);
        $slice = $this->slices->slice($profileRef->spaceId, $profileRef->rulesRevision);

        return new GameStrikeAttack(
            $slice,
            $documents,
            $item,
            $profile,
            $this->context($slice, $documents, $target, $profile),
        );
    }

    /**
     * Документы атакующего по ссылке на участника.
     *
     * @param GameStrikeActorRef $actor Атакующий.
     *
     * @return array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} Документы.
     *
     * @throws GameInvalidException Если вид участника чужой.
     */
    private function documents(GameStrikeActorRef $actor): array
    {
        if ($actor->kind !== 'character' && $actor->kind !== 'npc') {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        return $this->participants->attackerDocuments(
            $actor->kind,
            $actor->id,
            $actor->npcSheet,
            $actor->npcChoices,
            $actor->sheetVersion,
        );
    }

    /**
     * Профиль выбранного предмета.
     *
     * @param ItemSpec $item Предмет.
     * @param int $profileIndex Смещение профиля.
     * @param string $profileType Вид профиля.
     *
     * @return WeaponProfile Профиль.
     *
     * @throws GameInvalidException Если профиль не тот.
     */
    private function profileOf(ItemSpec $item, int $profileIndex, string $profileType): WeaponProfile
    {
        $profiles = $item->getWeapon()?->getWeaponProfiles() ?? [];
        $profile = $profiles[$profileIndex] ?? null;
        if (!$profile instanceof WeaponProfile || $profile->getType() !== $profileType) {
            throw new GameInvalidException('Game strike profile is invalid');
        }

        return $profile;
    }

    /**
     * Контекст формулы профиля.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array{sheet: array<string, mixed>, choices: array<string, mixed>, key: string|null} $documents Документы.
     * @param GameStrikeTarget $target Запрос удара.
     * @param WeaponProfile $profile Профиль.
     *
     * @return FormulaContext Контекст.
     *
     * @throws RuleInvalidException Если база профиля битая.
     */
    private function context(
        CharacterRuleSlice $slice,
        array $documents,
        GameStrikeTarget $target,
        WeaponProfile $profile,
    ): FormulaContext {
        $context = $this->participants->formulaContext(
            $slice,
            $documents['sheet'],
            $documents['choices'],
            $documents['key'],
        );

        return $this->profiles->build(
            $profile,
            $context,
            $target->actionCharacteristicModifier ?? 0,
            $target->strengthDrop,
            $target->gripBonus,
        );
    }
}
