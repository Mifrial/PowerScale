<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Ресурс и допустимость жетона концентрации.
 */
final class GameStrikeSpendConcentration
{
    /**
     * Ресурс концентрации листа.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return string Код ресурса.
     *
     * @throws GameInvalidException Если ресурса нет.
     */
    public function resource(CharacterRuleSlice $slice, array $sheet): string
    {
        $resource = (new GameConcentration())->resourceCode($slice, $sheet);
        if ($resource === null) {
            throw new GameInvalidException('Game concentration resource is missing');
        }

        return $resource;
    }

    /**
     * Проверяет, что жетон разрешён и хватает его запаса.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param string $hitCode Карточка проверки.
     * @param string $resource Ресурс.
     * @param int $amount Количество.
     *
     * @return void
     *
     * @throws GameInvalidException Если жетон недоступен или его не хватает.
     */
    public function verify(
        CharacterRuleSlice $slice,
        array $sheet,
        string $hitCode,
        string $resource,
        int $amount,
    ): void {
        $concentration = new GameConcentration();
        $levels = is_array($sheet['abilityLevels'] ?? null) ? $sheet['abilityLevels'] : [];
        if (!$concentration->allows($slice, $hitCode, null, $levels)) {
            throw new GameInvalidException('Game concentration is not allowed');
        }

        $concentration->assertAmount($slice, $sheet, $levels, $resource, $amount);
    }
}
