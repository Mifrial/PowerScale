<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Чтение карточки, бросок, состояние и штраф — одна причина изменения.

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\DamageTypeSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\SpellDeviationSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Spec\SpellDeviationSpecs;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Магическое отклонение: 2d6 после провала, состояние на источнике и штраф.
 */
final class GameSpellDeviation
{
    private const MECHANIC_CODE = 'spell_deviation';

    private const SMALL_SIZE = -1;

    private ?int $mechanicId = null;

    /**
     * Создаёт расчёт отклонения.
     *
     * @param IMechanics $mechanics Каталог механик.
     *
     * @return void
     */
    public function __construct(private readonly IMechanics $mechanics)
    {
    }

    /**
     * Исход отклонения после проверки или null, если его нет.
     *
     * @param GameSpellCastPrepared $prepared Подготовка каста.
     * @param array<string, mixed> $check Итог проверки.
     * @param Closure|null $rng Источник float в [0, 1) или null.
     *
     * @return array<string, mixed>|null Исход или null.
     *
     * @throws GameInvalidException Если карточки отклонения битые.
     */
    public function afterCheck(
        GameSpellCastPrepared $prepared,
        array $check,
        ?Closure $rng = null,
    ): ?array {
        $roll = $check['roll'] ?? null;
        if (($check['needsCheck'] ?? false) !== true || !is_array($roll)) {
            return null;
        }

        if (($roll['success'] ?? null) !== false) {
            return null;
        }

        $spec = $this->spec($prepared->slice, $prepared->path->getCastCheckCode());
        if ($spec === null) {
            return null;
        }

        return $this->roll($spec, $rng);
    }

    /**
     * Отклонение открывает паузу, если при дубле есть взрыв.
     *
     * @param array<string, mixed>|null $deviation Исход или null.
     *
     * @return bool true, если нужен выбор целей.
     */
    public function needsPause(?array $deviation): bool
    {
        return $deviation !== null
            && ($deviation['isDoubles'] ?? false) === true
            && ($deviation['hasBurst'] ?? false) === true;
    }

    /**
     * Бросок и сила по карточке.
     *
     * @param SpellDeviationSpec $spec Карточка.
     * @param Closure|null $rng Источник float в [0, 1) или null.
     *
     * @return array<string, mixed> Исход.
     */
    public function roll(SpellDeviationSpec $spec, ?Closure $rng = null): array
    {
        $rng ??= static fn (): float => mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax();
        $faces = [];
        for ($index = 0; $index < $spec->getDiceCount(); $index++) {
            $faces[] = $this->face($rng, $spec->getDieFaces());
        }

        $sum = array_sum($faces);
        $strength = $sum - $spec->getNeutralSum();

        return [
            'faces' => $faces,
            'dieFaces' => $spec->getDieFaces(),
            'hasBurst' => $spec->getBurst() !== null,
            'firstFace' => $faces[0],
            'secondFace' => $faces[count($faces) - 1],
            'faceSum' => $sum,
            'strength' => $strength,
            'hasEffect' => $strength > 0,
            'isDoubles' => count(array_unique($faces)) === 1,
            'dieDigit' => $faces[0],
        ];
    }

    /**
     * Блок отклонения для ответа и панели.
     *
     * @param GameSpellCastPrepared $prepared Подготовка каста.
     * @param array<string, mixed>|null $deviation Исход или null.
     *
     * @return array<string, mixed>|null Блок или null.
     *
     * @throws GameInvalidException Если карточка отклонения битая.
     */
    public function view(GameSpellCastPrepared $prepared, ?array $deviation): ?array
    {
        if ($deviation === null) {
            return null;
        }

        $spec = $this->spec($prepared->slice, $prepared->path->getCastCheckCode());
        $burst = $spec?->getBurst();
        $radius = $burst === null
            ? null
            : (new DimensionalNumber($prepared->usedPower->getBase(), $prepared->usedPower->getSize()))->toInteger();

        return [
            'firstFace' => $deviation['firstFace'] ?? 0,
            'secondFace' => $deviation['secondFace'] ?? 0,
            'faceSum' => $deviation['faceSum'] ?? 0,
            'strength' => $deviation['strength'] ?? 0,
            'hasEffect' => $deviation['hasEffect'] ?? false,
            'isDoubles' => $deviation['isDoubles'] ?? false,
            'dieDigit' => $deviation['dieDigit'] ?? 0,
            'burst' => $burst === null
                ? null
                : [
                    'damageTypeCode' => $burst->getDamageTypeCode(),
                    'successRating' => $burst->getSuccessRating(),
                    'radius' => $radius,
                ],
        ];
    }

    /**
     * Операции листа кастера: отклонение на источнике.
     *
     * @param SpellDeviationSpec $spec Карточка.
     * @param array<string, mixed> $sheet Лист кастера.
     * @param string $sourceKey Ключ источника.
     * @param int $strength Сила отклонения.
     *
     * @return list<array<string, mixed>> Операции.
     */
    public function operations(
        SpellDeviationSpec $spec,
        array $sheet,
        string $sourceKey,
        int $strength,
    ): array {
        if ($strength <= 0 || $sourceKey === '') {
            return [];
        }

        $code = $spec->getStateRuleCode();
        foreach (is_array($sheet['states'] ?? null) ? $sheet['states'] : [] as $row) {
            if (!is_array($row) || ($row['stateRuleCode'] ?? null) !== $code) {
                continue;
            }

            if (($row['boundSourceKey'] ?? null) !== $sourceKey) {
                continue;
            }

            $id = $row['id'] ?? null;
            $value = $row['value'] ?? null;
            if (is_int($id) && $id >= 1 && is_int($value)) {
                return [['kind' => 'patchState', 'id' => $id, 'value' => $value + $strength]];
            }
        }

        return [[
            'kind' => 'putState',
            'stateRuleCode' => $code,
            'value' => $strength,
            'boundSourceKey' => $sourceKey,
        ]];
    }

    /**
     * Сила отклонения, привязанная к источнику.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param string $checkCode Код проверки сотворения.
     * @param array<string, mixed> $sheet Лист кастера.
     * @param string $sourceKey Ключ источника.
     *
     * @return int Сила; 0 — отклонения нет.
     *
     * @throws GameInvalidException Если карточки отклонения битые.
     */
    public function strengthOnSource(
        CharacterRuleSlice $slice,
        string $checkCode,
        array $sheet,
        string $sourceKey,
    ): int {
        $spec = $this->spec($slice, $checkCode);
        if ($spec === null || $sourceKey === '') {
            return 0;
        }

        $code = $spec->getStateRuleCode();
        $total = 0;
        foreach (is_array($sheet['states'] ?? null) ? $sheet['states'] : [] as $row) {
            if (!is_array($row) || ($row['stateRuleCode'] ?? null) !== $code) {
                continue;
            }

            if (($row['boundSourceKey'] ?? null) !== $sourceKey) {
                continue;
            }

            if (is_int($row['value'] ?? null)) {
                $total += max(0, $row['value']);
            }
        }

        return $total;
    }

    /**
     * Карточка отклонения из проверки сотворения.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param string $checkCode Код проверки.
     *
     * @return SpellDeviationSpec|null Карточка или null.
     *
     * @throws GameInvalidException Если ссылка или карточка битые.
     */
    public function spec(CharacterRuleSlice $slice, string $checkCode): ?SpellDeviationSpec
    {
        $check = $slice->findLive($checkCode);
        $spec = $check?->getSpec();
        if (!$spec instanceof CheckSpec) {
            return null;
        }

        foreach ($spec->getFailureRuleCodes() ?? [] as $code) {
            $rule = $slice->findLive($code);
            if ($rule === null) {
                throw new GameInvalidException('Game spell deviation card is missing');
            }

            $deviation = $this->ofRule($rule->getMechanics());
            if ($deviation === null) {
                continue;
            }

            $this->assertCards($slice, $deviation);

            return $deviation;
        }

        return null;
    }

    /**
     * Карточка из списка механик правила.
     *
     * @param array<int, array<string, mixed>> $mechanics Строки механик.
     *
     * @return SpellDeviationSpec|null Карточка или null.
     *
     * @throws GameInvalidException Если payload битый.
     */
    private function ofRule(array $mechanics): ?SpellDeviationSpec
    {
        $mechanicId = $this->mechanicId();
        foreach ($mechanics as $row) {
            if (($row['mechanic_id'] ?? null) !== $mechanicId) {
                continue;
            }

            $payload = is_array($row['mechanic_payload'] ?? null) ? $row['mechanic_payload'] : [];

            return SpellDeviationSpecs::read($payload);
        }

        return null;
    }

    /**
     * Проверяет состояние и тип урона взрыва.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param SpellDeviationSpec $spec Карточка.
     *
     * @return void
     *
     * @throws GameInvalidException Если карточки нет или она чужая.
     */
    private function assertCards(CharacterRuleSlice $slice, SpellDeviationSpec $spec): void
    {
        $state = $slice->findLive($spec->getStateRuleCode());
        $stateSpec = $state?->getSpec();
        if (!$stateSpec instanceof StateSpec || !$stateSpec->isMagicDeviation()) {
            throw new GameInvalidException('Game spell deviation state is invalid');
        }

        $burst = $spec->getBurst();
        if ($burst === null) {
            return;
        }

        $damage = $slice->findLive($burst->getDamageTypeCode());
        $damageSpec = $damage?->getSpec();
        if (!$damageSpec instanceof DamageTypeSpec) {
            throw new GameInvalidException('Game spell deviation damage type is invalid');
        }
    }

    /**
     * Идентификатор семейства механики.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если семейства нет в каталоге.
     */
    private function mechanicId(): int
    {
        if ($this->mechanicId !== null) {
            return $this->mechanicId;
        }

        foreach ($this->mechanics->getList() as $mechanic) {
            if ($mechanic->getCode() === self::MECHANIC_CODE) {
                $this->mechanicId = $mechanic->getId();

                return $this->mechanicId;
            }
        }

        throw new GameInvalidException('Game spell deviation mechanic is missing');
    }

    /**
     * Грань куба.
     *
     * @param Closure $rng Источник float.
     * @param int $faces Число граней.
     *
     * @return int Грань 1..faces.
     */
    private function face(Closure $rng, int $faces): int
    {
        $value = (int) floor($rng() * $faces) + 1;

        return min($faces, max(1, $value));
    }

    /**
     * Потолок мощи источника после штрафа отклонения.
     *
     * @param CharacteristicNumber $cap Потолок без штрафа.
     * @param int $strength Сила отклонения.
     *
     * @return CharacteristicNumber Потолок.
     */
    public function penalizedCap(CharacteristicNumber $cap, int $strength): CharacteristicNumber
    {
        if ($strength <= 0) {
            return $cap;
        }

        $reduced = $cap->modify(-$strength);
        if ($reduced->getSize() < self::SMALL_SIZE) {
            return new CharacteristicNumber(0, 0);
        }

        return $reduced;
    }
}
