<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\PushSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;

/**
 * Проверки выбора удара: живая карточка, профиль, цели, блок.
 */
final class GameStrikeChecks
{
    /**
     * Принимает срезы, участников, снаряжение и карточки.
     *
     * @param GameStrikeSlices $slices Срезы ревизии.
     * @param GameStrikeParticipants $participants Участники удара.
     * @param GameStrikeEquipment $equipment Снаряжение удара.
     * @param GameStrikeActionCards $cards Живые карточки действий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSlices $slices,
        private readonly GameStrikeParticipants $participants,
        private readonly GameStrikeEquipment $equipment,
        private readonly GameStrikeActionCards $cards,
    ) {
    }
    /**
     * Проверяет выбранный удар.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $actionRuleCode Действие.
     * @param int $itemInventoryId Строка инвентаря.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param string $attackerKind Вид атакующего.
     * @param int $attackerId Атакующий.
     * @param array<string, mixed>|null $npcChoices Выборы NPC.
     * @param int $instanceIndex Экземпляр внутри стопки.
     *
     * @return void
     *
     * @throws GameInvalidException Если правила или профиля нет.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function assertAttack(
        int $spaceId,
        int $rulesRevision,
        string $actionRuleCode,
        int $itemInventoryId,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        string $attackerKind,
        int $attackerId,
        ?array $npcChoices,
        int $instanceIndex = 0,
    ): void {
        $this->assertLive($spaceId, $rulesRevision, $actionRuleCode);
        if ($this->cards->pushOf($spaceId, $rulesRevision, $actionRuleCode) === null) {
            $this->slices->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex);
        }

        $choices = $attackerKind === 'character'
            ? $this->participants->characterSnapshot($attackerId)['choices']
            : $npcChoices;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game strike attacker choices are invalid');
        }

        $this->equipment->assertInventoryItem($choices, $itemInventoryId, $itemRuleCode, $spaceId, $rulesRevision, $instanceIndex);
        $this->assertPushProfile($spaceId, $rulesRevision, $actionRuleCode, $itemRuleCode, $profileType, $profileIndex);
    }
    /**
     * Проверяет число целей карточки: потолок `max_targets` и режим.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $actionRuleCode Действие.
     * @param int $targetCount Число целей.
     *
     * @return void
     *
     * @throws GameInvalidException Если карточка битая или число целей вне потолка.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function assertAttackTargets(
        int $spaceId,
        int $rulesRevision,
        string $actionRuleCode,
        int $targetCount,
    ): void {
        $base = $this->cards->actionBaseOf($spaceId, $rulesRevision, $actionRuleCode);
        if ($base === null) {
            throw new GameInvalidException('Game strike action is invalid');
        }

        $maxTargets = $base->getMaxTargets() ?? 1;
        if ($maxTargets < 1) {
            throw new GameInvalidException('Game strike max targets is invalid');
        }

        $mode = $base->getAttackMode();
        if ($mode !== null && ($mode === 'wide') !== ($maxTargets > 1)) {
            throw new GameInvalidException('Game strike attack mode is invalid');
        }

        if ($targetCount < 1 || $targetCount > $maxTargets) {
            throw new GameInvalidException('Game strike target count is invalid');
        }
    }
    /**
     * Предмет блока — живой предмет со щитом или оружием.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия игры.
     * @param string $blockItemRuleCode Код.
     *
     * @return void
     *
     * @throws GameInvalidException Если предмета блока нет.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function assertBlock(int $spaceId, int $rulesRevision, string $blockItemRuleCode): void
    {
        $item = $this->slices->item($spaceId, $rulesRevision, $blockItemRuleCode);
        if ($item->getWeapon() === null && $item->getShield() === null) {
            throw new GameInvalidException('Game strike block item is invalid');
        }
    }
    /**
     * Предмет годится для блока против толчка.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $actionRuleCode Действие.
     * @param string $blockItemRuleCode Предмет блока.
     *
     * @return void
     *
     * @throws GameInvalidException Если предмет не подходит.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function assertPushBlock(
        int $spaceId,
        int $rulesRevision,
        string $actionRuleCode,
        string $blockItemRuleCode,
    ): void {
        $push = $this->cards->pushOf($spaceId, $rulesRevision, $actionRuleCode);
        if ($push === null) {
            return;
        }

        $item = $this->slices->item($spaceId, $rulesRevision, $blockItemRuleCode);
        if ($this->pushBlockFits($push, $item)) {
            return;
        }

        throw new GameInvalidException('Game push block item is invalid');
    }
    /**
     * Проверяет выбранную строку и профиль блока.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $choices Выборы защитника.
     * @param int $blockItemInventoryId Строка инвентаря.
     * @param int $blockItemProfileIndex Индекс профиля.
     * @param string|null $blockItemRuleCode Код для проверки согласованности.
     *
     * @return void
     *
     * @throws GameInvalidException Если выбор невалиден.
     * @throws GameNotFoundException Если ревизия отсутствует.
     */
    public function assertBlockSelection(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        int $blockItemInventoryId,
        int $blockItemProfileIndex,
        ?string $blockItemRuleCode,
    ): void {
        if ($blockItemProfileIndex !== 0) {
            throw new GameInvalidException('Game strike block profile is invalid');
        }

        $row = $this->equipment->blockRow($choices, $blockItemInventoryId);
        $ruleCode = $row['ruleCode'] ?? null;
        if (($row['equipped'] ?? false) !== true || !is_string($ruleCode)) {
            throw new GameInvalidException('Game strike block item is invalid');
        }

        $item = $this->slices->item($spaceId, $rulesRevision, $ruleCode);
        if ($item->getBlockProfile() === null) {
            throw new GameInvalidException('Game strike block profile is invalid');
        }
    }
    /**
     * Текущие проверочные модификаторы карточки действия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $checkCode Код проверки.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    public function actionCheckAdvantages(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $checkCode,
    ): array {
        $base = GameStrikeRuleLookup::actionBase($slice, $actionRuleCode);
        if ($base === null) {
            return [];
        }

        $rows = [];
        foreach ($base->getActionEffects() as $effect) {
            $row = GameStrikeCheckAdvantage::of($effect, $checkCode);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
    /**
     * Живое правило.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код.
     *
     * @return void
     *
     * @throws GameInvalidException Если кода нет.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function assertLive(int $spaceId, int $rulesRevision, string $ruleCode): void
    {
        $live = $this->slices->slice($spaceId, $rulesRevision)->findLive($ruleCode);
        if ($live === null) {
            throw new GameInvalidException('Game strike rule is not live');
        }
    }
    /**
     * Профиль допустим для толчка.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $actionRuleCode Действие.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Профиль.
     * @param int $profileIndex Индекс.
     *
     * @return void
     *
     * @throws GameInvalidException Если профиль не подходит.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function assertPushProfile(
        int $spaceId,
        int $rulesRevision,
        string $actionRuleCode,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
    ): void {
        $push = $this->cards->pushOf($spaceId, $rulesRevision, $actionRuleCode);
        if ($push === null) {
            return;
        }

        if ($profileType !== 'strike') {
            throw new GameInvalidException('Game push profile is invalid');
        }

        $item = $this->slices->item($spaceId, $rulesRevision, $itemRuleCode);
        if ($push->getProfiles() === 'hands_or_shield') {
            $this->assertHandsOrShieldPush($item);

            return;
        }

        $profile = $this->slices->profile($spaceId, $rulesRevision, $itemRuleCode, $profileType, $profileIndex);
        if (!$this->pushProfileFits($push, $item, $profile)) {
            throw new GameInvalidException('Game push profile is invalid');
        }
    }
    /**
     * Предмет подходит для толчка руками или щитом.
     *
     * @param ItemSpec $item Предмет.
     *
     * @return void
     *
     * @throws GameInvalidException Если предмет не подходит.
     */
    private function assertHandsOrShieldPush(ItemSpec $item): void
    {
        if (!$item->isInnate() && $item->getShield() === null) {
            throw new GameInvalidException('Game push profile is invalid');
        }
    }
    /**
     * Предмет годится для выбранного толчка.
     *
     * @param PushSpec $push Толчок.
     * @param ItemSpec $item Предмет.
     * @param WeaponProfile $profile Профиль.
     *
     * @return bool true, если годится.
     */
    private function pushProfileFits(PushSpec $push, ItemSpec $item, WeaponProfile $profile): bool
    {
        $type = $profile->getDamage()->getDamageTypeCode();

        return match ($push->getProfiles()) {
            'hands_or_shield' => $item->isInnate() || $item->getShield() !== null,
            'slashing_or_blunt_strike' => $type === 'slashing' || $type === 'blunt',
            default => false,
        };
    }
    /**
     * Предмет закрывает толчок по спеке.
     *
     * @param PushSpec $push Толчок.
     * @param ItemSpec $item Предмет.
     *
     * @return bool true, если закрывает.
     */
    private function pushBlockFits(PushSpec $push, ItemSpec $item): bool
    {
        return match ($push->getBlockProfiles()) {
            'any' => true,
            'hands_or_shield' => $item->isInnate() || $item->getShield() !== null,
            'shield_only' => $item->getShield() !== null,
            'slashing_or_blunt_strike' => $this->hasSlashingOrBluntStrike($item),
            default => false,
        };
    }
    /**
     * Есть ли у предмета рубящий или дробящий удар.
     *
     * @param ItemSpec $item Предмет.
     *
     * @return bool true, если есть.
     */
    private function hasSlashingOrBluntStrike(ItemSpec $item): bool
    {
        $weaponProfiles = $item->getWeapon()?->getWeaponProfiles() ?? [];
        $shieldProfiles = $item->getShield()?->getWeaponProfiles() ?? [];
        foreach (array_merge($weaponProfiles, $shieldProfiles) as $profile) {
            $type = $profile->getDamage()->getDamageTypeCode();
            if ($profile->getType() === 'strike' && ($type === 'slashing' || $type === 'blunt')) {
                return true;
            }
        }

        return false;
    }
}
