<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Interface\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;

/**
 * Срез правил мира для построения персонажа: без persist и без HTTP.
 */
interface ICharacterRuleSlices
{
    /**
     * Загружает immutable срез (spaceId, revision).
     *
     * @param int $spaceId Id часов / мира.
     * @param int $revision Номер ревизии.
     *
     * @return CharacterRuleSlice Состав live и tombstone.
     *
     * @throws CharacterInvalidException Если мир выключен, номер битый или состав/признаки.
     * @throws CharacterNotFoundException Если нет sidecar или ревизии.
     */
    public function get(int $spaceId, int $revision): CharacterRuleSlice;
}
