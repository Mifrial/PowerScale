<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;

/**
 * Срезы ревизии и live-предметы удара с кэшем на время запроса.
 */
final class GameStrikeSlices
{
    /**
     * @var array<string, CharacterRuleSlice>
     */
    private array $cache = [];

    /**
     * Принимает источник срезов.
     *
     * @param ICharacterRuleSlices $ruleSlices Срез.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterRuleSlices $ruleSlices,
    ) {
    }

    /**
     * Срез ревизии игры. Код Character наружу не выходит.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return CharacterRuleSlice Срез.
     *
     * @throws GameInvalidException Если срез битый.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function slice(int $spaceId, int $rulesRevision): CharacterRuleSlice
    {
        $key = $spaceId . ':' . $rulesRevision;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        try {
            $slice = $this->ruleSlices->get($spaceId, $rulesRevision);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game strike revision was not found', $exception);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game strike revision is invalid', $exception);
        }

        $this->cache[$key] = $slice;

        return $slice;
    }

    /**
     * Живой предмет ревизии.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $ruleCode Код.
     *
     * @return ItemSpec Spec.
     *
     * @throws GameInvalidException Если это не предмет.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function item(int $spaceId, int $rulesRevision, string $ruleCode): ItemSpec
    {
        $rule = $this->slice($spaceId, $rulesRevision)->findLive($ruleCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof ItemSpec) {
            throw new GameInvalidException('Game strike item is invalid');
        }

        return $spec;
    }

    /**
     * Профиль оружия предмета.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $itemRuleCode Предмет.
     * @param string $profileType Вид.
     * @param int $profileIndex Смещение.
     *
     * @return WeaponProfile Профиль.
     *
     * @throws GameInvalidException Если профиля нет.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function profile(
        int $spaceId,
        int $rulesRevision,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
    ): WeaponProfile {
        $weapon = $this->item($spaceId, $rulesRevision, $itemRuleCode)->getWeapon();
        $profiles = $weapon === null ? [] : $weapon->getWeaponProfiles();
        $profile = $profiles[$profileIndex] ?? null;
        if (!$profile instanceof WeaponProfile || $profile->getType() !== $profileType) {
            throw new GameInvalidException('Game strike profile is invalid');
        }

        return $profile;
    }
}
