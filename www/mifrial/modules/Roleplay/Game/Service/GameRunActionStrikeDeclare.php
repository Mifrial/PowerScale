<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Interface\Service\IGameStrikes;
use Mifrial\Roleplay\Game\Interface\Service\IGameWideStrikes;

/**
 * Объявление удара: проверки карточки и выбор сценария.
 */
final class GameRunActionStrikeDeclare
{
    /**
     * Создаёт сценарий объявления удара.
     *
     * @param IGameStrikes $strikes Одиночный удар.
     * @param IGameWideStrikes $wideStrikes Широкий удар.
     * @param IGames $games Игра.
     * @param GameStrikeRules $rules Карточки.
     *
     * @return void
     */
    public function __construct(
        private readonly IGameStrikes $strikes,
        private readonly IGameWideStrikes $wideStrikes,
        private readonly IGames $games,
        private readonly GameStrikeRules $rules,
    ) {
    }

    /**
     * Объявляет удар.
     *
     * @param array<string, mixed> $parsed Разбор конверта.
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если удары несовместимы.
     */
    public function declare(array $parsed, GameRunActionCall $call): array
    {
        $context = $parsed['context'];
        $strikes = $context['strikes'];
        $wide = $this->isWide($parsed, $call);
        $targets = $this->targetKeys($strikes);
        $this->assertProcessStep($context, $strikes);
        $this->assertConcentration($context, $wide, count($targets));
        $this->assertManeuver($context, $wide, count($strikes));
        $this->assertCardCount($parsed, $call, $wide, count($strikes), count($targets));
        $result = $this->declareAttack($parsed, $call, $wide, count($targets));

        return (new GameRunActionStrikeResult())->build($result, $strikes);
    }

    /**
     * Широкий ли удар по карточке.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return bool Истина, если карточка широкая.
     */
    private function isWide(array $parsed, GameRunActionCall $call): bool
    {
        $game = $this->games->get($call->gameId);

        return $this->rules->cards->isWideAttack(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $parsed['code'],
        );
    }

    /**
     * Ключи целей удара.
     *
     * @param list<array<string, mixed>> $strikes Строки.
     *
     * @return array<string, bool> Ключи.
     */
    private function targetKeys(array $strikes): array
    {
        $keys = [];
        foreach ($strikes as $strike) {
            $keys[$strike['target']['type'] . ':' . $strike['target']['id']] = true;
        }

        return $keys;
    }

    /**
     * Шаг процесса — ровно один удар.
     *
     * @param array<string, mixed> $context Контекст.
     * @param list<array<string, mixed>> $strikes Строки.
     *
     * @return void
     *
     * @throws GameInvalidException Если ударов несколько.
     */
    private function assertProcessStep(array $context, array $strikes): void
    {
        if (($context['processRuleCode'] ?? null) !== null && count($strikes) !== 1) {
            throw new GameInvalidException('Game process step is a single strike');
        }
    }

    /**
     * Концентрация — ровно один удар в одну цель.
     *
     * @param array<string, mixed> $context Контекст.
     * @param bool $wide Широкая карточка.
     * @param int $targets Число целей.
     *
     * @return void
     *
     * @throws GameInvalidException Если трата несовместима.
     */
    private function assertConcentration(array $context, bool $wide, int $targets): void
    {
        if (($context['tokenSpend'] ?? null) !== null && ($wide || $targets > 1)) {
            throw new GameInvalidException('Game concentration supports a single strike');
        }
    }

    /**
     * Манёвр — ровно один удар.
     *
     * @param array<string, mixed> $context Контекст.
     * @param bool $wide Широкая карточка.
     * @param int $strikes Число ударов.
     *
     * @return void
     *
     * @throws GameInvalidException Если манёвр несовместим.
     */
    private function assertManeuver(array $context, bool $wide, int $strikes): void
    {
        if (($context['maneuverRuleCode'] ?? null) !== null && ($wide || $strikes > 1)) {
            throw new GameInvalidException('Game maneuver supports a single strike');
        }
    }

    /**
     * Карточка требует не меньше ударов.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param GameRunActionCall $call Снимок вызова.
     * @param bool $wide Широкая карточка.
     * @param int $strikes Число ударов.
     * @param int $targets Число целей.
     *
     * @return void
     *
     * @throws GameInvalidException Если ударов меньше, чем требует карточка.
     */
    private function assertCardCount(
        array $parsed,
        GameRunActionCall $call,
        bool $wide,
        int $strikes,
        int $targets,
    ): void {
        if ($wide || $targets !== 1) {
            return;
        }

        $game = $this->games->get($call->gameId);
        $base = $this->rules->cards->actionBaseOf(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $parsed['code'],
        );
        $this->assertStrikeCount($base?->getStrikeCount(), $base?->getMinWeapons(), $strikes);
    }

    /**
     * Сравнивает число ударов с карточкой.
     *
     * @param mixed $cardCount Число ударов карточки.
     * @param mixed $minWeapons Минимум оружий.
     * @param int $strikes Число ударов.
     *
     * @return void
     *
     * @throws GameInvalidException Если ударов меньше, чем требует карточка.
     */
    private function assertStrikeCount(mixed $cardCount, mixed $minWeapons, int $strikes): void
    {
        if (
            (is_int($cardCount) && $cardCount > $strikes)
            || (is_int($minWeapons) && $minWeapons > $strikes)
        ) {
            throw new GameInvalidException('Game strike count is invalid');
        }
    }

    /**
     * Выбирает объявление удара.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param GameRunActionCall $call Снимок вызова.
     * @param bool $wide Широкая карточка.
     * @param int $targets Число целей.
     *
     * @return array<string, mixed> Итог домена.
     *
     * @throws GameInvalidException Если предметы широкого удара различаются.
     */
    private function declareAttack(array $parsed, GameRunActionCall $call, bool $wide, int $targets): array
    {
        $strikes = $parsed['context']['strikes'];
        if (!$wide && count($strikes) > 1 && $targets === 1) {
            return $this->declareMulti($parsed, $call, $strikes);
        }

        return $wide || $targets > 1
            ? $this->declareWide($parsed, $call, $strikes)
            : $this->declareSingle($parsed, $call, $strikes[0]);
    }

    /**
     * Серия экземпляров одного оружия.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param GameRunActionCall $call Снимок вызова.
     * @param list<array<string, mixed>> $strikes Строки.
     *
     * @return array<string, mixed> Итог домена.
     *
     * @throws GameInvalidException Если пришёл модификатор.
     */
    private function declareMulti(array $parsed, GameRunActionCall $call, array $strikes): array
    {
        return $this->strikes->declareMultiStrike(
            $call->gameId,
            $call->actorUserId,
            $call->editAll,
            $call->viewAll,
            $call->battleId,
            $call->idempotencyKey,
            $call->expectedVersion,
            $this->multiAttack($parsed, $strikes),
        );
    }

    /**
     * Широкий удар по нескольким целям.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param GameRunActionCall $call Снимок вызова.
     * @param list<array<string, mixed>> $strikes Строки.
     *
     * @return array<string, mixed> Итог домена.
     *
     * @throws GameInvalidException Если предметы различаются.
     */
    private function declareWide(array $parsed, GameRunActionCall $call, array $strikes): array
    {
        return $this->wideStrikes->declareWideStrike(
            $call->gameId,
            $call->actorUserId,
            $call->editAll,
            $call->viewAll,
            $call->battleId,
            $call->idempotencyKey,
            $call->expectedVersion,
            $this->wideAttack($parsed, $strikes),
        );
    }

    /**
     * Одиночный удар.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param GameRunActionCall $call Снимок вызова.
     * @param array<string, mixed> $strike Строка.
     *
     * @return array<string, mixed> Итог домена.
     */
    private function declareSingle(array $parsed, GameRunActionCall $call, array $strike): array
    {
        return $this->strikes->declareStrike(
            $call->gameId,
            $call->actorUserId,
            $call->editAll,
            $call->viewAll,
            $call->battleId,
            $call->idempotencyKey,
            $call->expectedVersion,
            $this->singleAttack($parsed, $strike),
        );
    }

    /**
     * Старый выбор одиночного удара.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param array<string, mixed> $strike Строка.
     *
     * @return array<string, mixed> Выбор.
     */
    private function singleAttack(array $parsed, array $strike): array
    {
        $attack = $this->attackBase($parsed, $strike);
        $attack['modifiers'] = $strike['modifiers'] ?? [];
        $attack['tokenSpend'] = $parsed['context']['tokenSpend'] ?? null;
        $attack['maneuverRuleCode'] = $parsed['context']['maneuverRuleCode'] ?? null;
        if ($strike['ammunitionInventoryId'] !== null) {
            $attack['ammunitionInventoryId'] = $strike['ammunitionInventoryId'];
        }

        if (($parsed['context']['processRuleCode'] ?? null) !== null) {
            $attack['processRuleCode'] = $parsed['context']['processRuleCode'];
            $attack['processStepCode'] = $parsed['context']['processStepCode'];
        }

        return $attack;
    }

    /**
     * Общая часть выбора удара.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param array<string, mixed> $strike Строка.
     *
     * @return array<string, mixed> Выбор.
     */
    private function attackBase(array $parsed, array $strike): array
    {
        return [
            'attacker' => $parsed['actor'],
            'defender' => $strike['target'],
            'actionRuleCode' => $parsed['code'],
            'itemInventoryId' => $strike['itemInventoryId'],
            'itemRuleCode' => $strike['itemRuleCode'],
            'profileType' => $strike['profileType'],
            'profileIndex' => $strike['profileIndex'],
            'instanceIndex' => $strike['instanceIndex'],
            'conditions' => $strike['conditions'],
            'chosenAmounts' => $parsed['context']['chosenAmounts'],
        ];
    }

    /**
     * Выбор нескольких экземпляров одного оружия.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param list<array<string, mixed>> $strikes Строки.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws GameInvalidException Если пришёл модификатор.
     */
    private function multiAttack(array $parsed, array $strikes): array
    {
        $this->assertNoModifiers($strikes);
        $first = $strikes[0];
        $attack = $this->attackBase($parsed, $first);
        $attack['strikes'] = $strikes;
        $attack['tokenSpend'] = $parsed['context']['tokenSpend'] ?? null;
        if ($first['ammunitionInventoryId'] !== null) {
            $attack['ammunitionInventoryId'] = $first['ammunitionInventoryId'];
        }

        return $attack;
    }

    /**
     * Старый выбор широкого удара.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param list<array<string, mixed>> $strikes Строки.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws GameInvalidException Если строки различаются предметом или профилем.
     */
    private function wideAttack(array $parsed, array $strikes): array
    {
        $this->assertNoModifiers($strikes);
        $first = $strikes[0];
        $targets = [];
        foreach ($strikes as $strike) {
            $this->assertSameItem($strike, $first);
            $targets[] = [
                'type' => $strike['target']['type'],
                'id' => $strike['target']['id'],
                'conditions' => $strike['conditions'],
            ];
        }

        $attack = [
            'attacker' => $parsed['actor'],
            'targets' => $targets,
            'actionRuleCode' => $parsed['code'],
            'itemInventoryId' => $first['itemInventoryId'],
            'itemRuleCode' => $first['itemRuleCode'],
            'profileType' => $first['profileType'],
            'profileIndex' => $first['profileIndex'],
            'chosenAmounts' => $parsed['context']['chosenAmounts'],
        ];
        if ($first['ammunitionInventoryId'] !== null) {
            $attack['ammunitionInventoryId'] = $first['ammunitionInventoryId'];
        }

        return $attack;
    }

    /**
     * Строки широкого удара — один предмет и профиль.
     *
     * @param array<string, mixed> $strike Строка.
     * @param array<string, mixed> $first Первая строка.
     *
     * @return void
     *
     * @throws GameInvalidException Если предмет или профиль различаются.
     */
    private function assertSameItem(array $strike, array $first): void
    {
        foreach (['itemInventoryId', 'itemRuleCode', 'profileType', 'profileIndex', 'instanceIndex'] as $key) {
            if ($strike[$key] !== $first[$key]) {
                throw new GameInvalidException('Game wide strike item is invalid');
            }
        }
    }

    /**
     * Модификаторы действия исполняются только одиночным ударом.
     *
     * @param list<array<string, mixed>> $strikes Строки.
     *
     * @return void
     *
     * @throws GameInvalidException Если модификатор пришёл на серию или широкий удар.
     */
    private function assertNoModifiers(array $strikes): void
    {
        foreach ($strikes as $strike) {
            if (($strike['modifiers'] ?? []) !== []) {
                throw new GameInvalidException('Game action modifier is not supported for multi-strike');
            }
        }
    }
}
