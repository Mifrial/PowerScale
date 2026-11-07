<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\IExhaustionAmount;
use Mifrial\Roleplay\Mechanic\Interface\IExhaustionWound;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Состояния, флаги и хуки механик в срезе правил удара.
 */
final class GameStrikeConsequenceStates
{
    /**
     * Создаёт поиск состояний.
     *
     * @param GameMechanicResolver $resolver Резолв хендлеров.
     *
     * @return void
     */
    public function __construct(
        private readonly GameMechanicResolver $resolver,
    ) {
    }

    /**
     * Хуки механик типа урона из среза правил.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $damageTypeCode Тип урона.
     *
     * @return list<array{handler: IMechanicHandler, payload: array<string, mixed>}>
     *
     * @throws GameInvalidException Если тип урона или хук битый.
     */
    public function hooks(CharacterRuleSlice $slice, string $damageTypeCode): array
    {
        $rule = $slice->findLive($damageTypeCode);
        if (!$rule instanceof CharacterResolvedRule) {
            throw new GameInvalidException('Game strike damage type is invalid');
        }

        $hooks = [];
        foreach ($rule->getMechanics() as $row) {
            if (!is_int($row['mechanic_id'] ?? null) || !is_array($row['mechanic_payload'] ?? null)) {
                throw new GameInvalidException('Game strike hook is invalid');
            }

            $hooks[] = [
                'handler' => $this->handler($rule->getCode(), $row['mechanic_id']),
                'payload' => $row['mechanic_payload'],
            ];
        }

        return $hooks;
    }

    /**
     * Код состояния из payload хука.
     *
     * @param array<string, mixed> $payload Payload.
     *
     * @return string Код состояния.
     *
     * @throws GameInvalidException Если код состояния не задан.
     */
    public function code(array $payload): string
    {
        $code = $payload['stateRuleCode'] ?? $payload['state_code'] ?? null;
        if (!is_string($code) || $code === '') {
            throw new GameInvalidException('Game strike hook is invalid');
        }

        return $code;
    }

    /**
     * Прирост истощения по хуку и частному урона.
     *
     * @param IMechanicHandler $handler Хендлер.
     * @param array<string, mixed> $payload Payload.
     * @param int $quotient Частное урона.
     *
     * @return int|null Прирост или null, если хук не про истощение.
     *
     * @throws GameInvalidException Если множитель или состояние битые.
     */
    public function exhaustion(IMechanicHandler $handler, array $payload, int $quotient): ?int
    {
        if ($quotient === 0) {
            return null;
        }

        $multiplier = null;
        if ($handler instanceof IExhaustionAmount) {
            $multiplier = 1;
        } elseif ($handler instanceof IExhaustionWound) {
            $multiplier = $payload['multiplier'] ?? 1;
            if (!is_int($multiplier)) {
                throw new GameInvalidException('Game strike hook is invalid');
            }
        }

        if ($multiplier === null) {
            return null;
        }

        $this->code($payload);

        return $quotient * $multiplier;
    }

    /**
     * Спека состояния по коду, обязательная.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $code Код состояния.
     *
     * @return StateSpec Спека состояния.
     *
     * @throws GameInvalidException Если состояние не найдено.
     */
    public function spec(CharacterRuleSlice $slice, string $code): StateSpec
    {
        $spec = $this->optional($slice, $code);
        if (!$spec instanceof StateSpec) {
            throw new GameInvalidException('Game strike state is invalid');
        }

        return $spec;
    }

    /**
     * Единственный код состояния с заданным флагом.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $flag Флаг.
     *
     * @return string Код состояния.
     *
     * @throws GameInvalidException Если подходящих состояний не одно.
     */
    public function single(CharacterRuleSlice $slice, string $flag): string
    {
        $matched = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof StateSpec && $this->matches($spec, $flag)) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game strike state is invalid');
        }

        return $matched[0];
    }

    /**
     * Сохранённое значение истощения по коду состояния.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код состояния.
     *
     * @return int Значение истощения.
     *
     * @throws GameInvalidException Если строки состояний или значение битые.
     */
    public function stored(array $sheet, string $code): int
    {
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            throw new GameInvalidException('Game strike sheet is invalid');
        }

        $value = 0;
        foreach ($states as $row) {
            if (!is_array($row) || ($row['stateRuleCode'] ?? null) !== $code) {
                continue;
            }

            if (!is_int($row['value'] ?? null)) {
                throw new GameInvalidException('Game strike exhaustion is invalid');
            }

            $value = $row['value'];
        }

        return $value;
    }

    /**
     * Есть ли на листе состояние с заданным флагом.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     * @param string $flag Флаг.
     *
     * @return bool Признак наличия флага.
     */
    public function has(CharacterRuleSlice $slice, array $sheet, string $flag): bool
    {
        foreach ($this->rows($sheet) as $code) {
            $spec = $this->optional($slice, $code);
            if ($spec !== null && $this->matches($spec, $flag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Резолвит хендлер механики по коду правила и идентификатору.
     *
     * @param string $ruleCode Код правила.
     * @param int $mechanicId Идентификатор механики.
     *
     * @return IMechanicHandler Хендлер.
     *
     * @throws GameInvalidException Если механика не резолвится.
     */
    private function handler(string $ruleCode, int $mechanicId): IMechanicHandler
    {
        return $this->resolver->handler($ruleCode, $mechanicId, 'Game strike hook is invalid');
    }

    /**
     * Спека состояния по коду, если он есть и не битый.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $code Код состояния.
     *
     * @return StateSpec|null Спека или null.
     */
    private function optional(CharacterRuleSlice $slice, string $code): ?StateSpec
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();

        return $spec instanceof StateSpec ? $spec : null;
    }

    /**
     * Соответствует ли состояние заданному флагу.
     *
     * @param StateSpec $spec Спека состояния.
     * @param string $flag Флаг.
     *
     * @return bool Признак соответствия.
     */
    private function matches(StateSpec $spec, string $flag): bool
    {
        if ($flag === 'unconscious') {
            return $spec->isDeclineUnconscious();
        }

        if ($flag === 'exhaustion') {
            return $spec->isDamageExhaustion();
        }

        return $spec->isMaim();
    }

    /**
     * Коды состояний всех строк листа.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<int, string> Коды строк.
     *
     * @throws GameInvalidException Если список состояний битый.
     */
    private function rows(array $sheet): array
    {
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            throw new GameInvalidException('Game strike sheet is invalid');
        }

        $codes = [];
        foreach ($states as $row) {
            $code = is_array($row) ? ($row['stateRuleCode'] ?? null) : null;
            if (is_string($code) && $code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
