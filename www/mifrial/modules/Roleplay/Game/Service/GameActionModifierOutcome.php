<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\SelfDamageOutcomeEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\SkipAfterActionOutcomeEffect;

/**
 * Последствия модификатора действия: пропуск и урон по себе.
 */
final class GameActionModifierOutcome
{
    /**
     * Создаёт исполнитель последствий.
     *
     * @param GameActionModifierSelfDamage $selfDamage Урон по себе.
     *
     * @return void
     */
    public function __construct(private readonly GameActionModifierSelfDamage $selfDamage)
    {
    }

    /**
     * Применяет последствия к листу атакующего.
     *
     * @param GameActionModifierInput $input Снимок действия.
     * @param ActionModifierEffect $effect Модификатор.
     * @param list<array<string, mixed>> $covered Покрытые строки.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     *
     * @throws GameInvalidException Если исход или лист битые.
     */
    public function apply(
        GameActionModifierInput $input,
        ActionModifierEffect $effect,
        array $covered,
        array &$result,
    ): void {
        foreach ($effect->getEffects() as $outcome) {
            if ($outcome instanceof SkipAfterActionOutcomeEffect) {
                $result['skipped'] = true;
                continue;
            }

            if (!$outcome instanceof SelfDamageOutcomeEffect) {
                throw new GameInvalidException('Game action modifier outcome is invalid');
            }

            $this->selfDamage->apply($input, $outcome, $covered, $result);
        }
    }
}
