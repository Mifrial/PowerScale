<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Save;

use Mifrial\Roleplay\Character\Dto\CharacterProblem;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterRaceAbilityCodes;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ItemGrant;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Параметры экземпляров предметов, полученных грантом способности.
 *
 * Значение берётся из живого параметра донора, а не из присланной строки:
 * клиент не может выдать ядру чужую мощь.
 */
final class CharacterItemInstances
{
    private readonly CharacterDonorGrants $grants;

    private readonly CharacterRaceAbilityCodes $raceAbilityCodes;

    /**
     * Создаёт пересчёт экземпляров.
     *
     * @return void
     */
    public function __construct()
    {
        $this->grants = new CharacterDonorGrants();
        $this->raceAbilityCodes = new CharacterRaceAbilityCodes();
    }

    /**
     * Пересчитывает parameters у строк, у которых есть ссылка на донора.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param array<string, mixed> $choices Документ choices.
     *
     * @return array{choices: array<string, mixed>, problems: array<int, CharacterProblem>} Документ и отказы.
     */
    public function normalize(CharacterRuleSlice $slice, array $choices): array
    {
        $abilities = $this->abilities($slice, $choices);
        $automatic = $this->raceAbilityCodes->getAutomaticParameters($slice, $this->raceCode($choices));
        $inventory = is_array($choices['inventory'] ?? null) ? $choices['inventory'] : [];
        $problems = [];

        foreach ($inventory as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $donorCode = $this->donorCode($item);
            if ($donorCode === '') {
                $inventory[$index] = $item;
                continue;
            }

            $item = $this->withoutParameters($item);

            $ability = $abilities[$donorCode] ?? null;
            if ($ability === null) {
                $problems[] = new CharacterProblem(
                    'CHARACTER_ITEM_DONOR',
                    'Item donor ability is missing',
                    'reference',
                    'inventory.' . $index . '.grantedBy',
                );
                $inventory[$index] = $item;
                continue;
            }

            $itemCode = $this->itemCode($item);
            $level = $this->level($ability);
            $binding = $this->binding($slice, $donorCode, $itemCode, $level);
            $payloadBinding = $this->payloadBinding($slice, $donorCode, $itemCode, $level);
            if ($binding === [] && $payloadBinding === []) {
                $problems[] = new CharacterProblem(
                    'CHARACTER_ITEM_POWER',
                    'Item grant does not bind instance parameters',
                    'reference',
                    'inventory.' . $index . '.parameters',
                );
                $inventory[$index] = $item;
                continue;
            }

            $parameters = [];
            foreach ($binding as $instanceCode => $abilityCode) {
                $value = $this->abilityParameter($slice, $donorCode, $ability, $abilityCode, $automatic);
                if (!$value instanceof DimensionalNumber) {
                    $problems[] = new CharacterProblem(
                        'CHARACTER_ITEM_POWER',
                        'Item instance parameter is missing',
                        'reference',
                        'inventory.' . $index . '.parameters.' . $instanceCode,
                    );
                    continue;
                }

                $parameters[$instanceCode] = ['base' => $value->getBase(), 'size' => $value->getSize()];
            }

            if ($parameters !== []) {
                $item['parameters'] = $parameters;
            }

            $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
            foreach ($payloadBinding as $instanceCode => $abilityCode) {
                $value = $this->abilityParameter($slice, $donorCode, $ability, $abilityCode, $automatic);
                if (!$value instanceof DimensionalNumber || $value->getSize() !== 0) {
                    $problems[] = new CharacterProblem(
                        'CHARACTER_ITEM_POWER',
                        'Item instance payload is missing',
                        'reference',
                        'inventory.' . $index . '.payload.' . $instanceCode,
                    );
                    continue;
                }

                $payload[$instanceCode] = $value->getBase();
            }

            if ($payload !== []) {
                $item['payload'] = $payload;
            }

            $inventory[$index] = $item;
        }

        $choices['inventory'] = $inventory;

        return ['choices' => $choices, 'problems' => $problems];
    }

    /**
     * Предпросмотр выдачи предметов из активных грантов. Ничего не пишет.
     *
     * Грант с привязкой параметров даёт строку инвентаря: существующая строка
     * с тем же донором обновляется, свободная строка того же кода подхватывается,
     * иначе создаётся новая. Строки без донора не трогаются.
     *
     * @param CharacterRuleSlice $slice Целевая ревизия.
     * @param array<string, mixed> $choices Документ choices.
     *
     * @return array{choices: array<string, mixed>, changed: bool} Документ и признак правки.
     */
    public function preview(CharacterRuleSlice $slice, array $choices): array
    {
        $abilities = $this->abilities($slice, $choices);
        $automatic = $this->raceAbilityCodes->getAutomaticParameters($slice, $this->raceCode($choices));
        $bindings = $this->bindings($slice, $abilities, $automatic);
        $inventory = array_values(is_array($choices['inventory'] ?? null) ? $choices['inventory'] : []);
        $nextId = 0;
        foreach ($inventory as $item) {
            if (is_array($item) && is_int($item['id'] ?? null)) {
                $nextId = max($nextId, $item['id']);
            }
        }

        $changed = false;
        $kept = [];
        foreach ($inventory as $item) {
            if (!is_array($item)) {
                $kept[] = $item;
                continue;
            }

            $donorCode = $this->donorCode($item);
            if ($donorCode === '') {
                $kept[] = $item;
                continue;
            }

            $binding = $bindings[$donorCode] ?? null;
            if ($binding === null || $binding['itemCode'] !== $this->itemCode($item)) {
                $changed = true;
                continue;
            }

            if (($item['parameters'] ?? []) !== $binding['parameters']) {
                $changed = true;
            }

            $item['parameters'] = $binding['parameters'];
            if ($binding['payload'] !== []) {
                $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
                if (array_intersect_key($payload, $binding['payload']) !== $binding['payload']) {
                    $changed = true;
                }

                $item['payload'] = [...$payload, ...$binding['payload']];
            }

            $kept[] = $item;
        }

        foreach ($bindings as $donorCode => $binding) {
            if ($this->hasDonor($kept, $donorCode)) {
                continue;
            }

            $adopted = false;
            foreach ($kept as $index => $item) {
                if (
                    !is_array($item)
                    || $this->itemCode($item) !== $binding['itemCode']
                    || $this->donorCode($item) !== ''
                ) {
                    continue;
                }

                $item['parameters'] = $binding['parameters'];
                $item['grantedBy'] = ['ruleCode' => $donorCode];
                if ($binding['payload'] !== []) {
                    $item['payload'] = [...(is_array($item['payload'] ?? null) ? $item['payload'] : []), ...$binding['payload']];
                }

                $kept[$index] = $item;
                $adopted = true;
                $changed = true;
                break;
            }

            if ($adopted) {
                continue;
            }

            ++$nextId;
            $row = [
                'id' => $nextId,
                'ruleCode' => $binding['itemCode'],
                'quantity' => 1,
                'equipped' => true,
                'parameters' => $binding['parameters'],
                'grantedBy' => ['ruleCode' => $donorCode],
            ];
            if ($binding['payload'] !== []) {
                $row['payload'] = $binding['payload'];
            }

            $kept[] = $row;
            $changed = true;
        }

        $choices['inventory'] = $kept;

        return ['choices' => $choices, 'changed' => $changed];
    }

    /**
     * Есть ли в списке строка этого донора.
     *
     * @param array<int, mixed> $inventory Строки.
     * @param string $donorCode Код донора.
     *
     * @return bool true, если есть.
     */
    private function hasDonor(array $inventory, string $donorCode): bool
    {
        foreach ($inventory as $item) {
            if (is_array($item) && $this->donorCode($item) === $donorCode) {
                return true;
            }
        }

        return false;
    }

    /**
     * Привязки активных грантов: донор → предмет и значения параметров.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, array<string, mixed>> $abilities Способности по коду.
     *
     * @return array<string, array{
     *     itemCode: string,
     *     parameters: array<string, array{base: int, size: int}>,
     *     payload: array<string, int>
     * }> Привязки.
     */
    private function bindings(CharacterRuleSlice $slice, array $abilities, array $automatic): array
    {
        $result = [];
        foreach ($abilities as $donorCode => $ability) {
            $rule = $slice->findLive($donorCode);
            if ($rule === null || $rule->isSpecBroken()) {
                continue;
            }

            foreach ($this->grants->getForLevel($rule, $donorCode, $this->level($ability)) as $grant) {
                $body = $grant->getGrant();
                if (!$body instanceof ItemGrant || ($body->getParameters() === [] && $body->getPayload() === [])) {
                    continue;
                }

                if ($slice->findLive($body->getItemCode()) === null) {
                    continue;
                }

                $parameters = [];
                foreach ($body->getParameters() as $instanceCode => $abilityCode) {
                    $value = $this->abilityParameter($slice, $donorCode, $ability, $abilityCode, $automatic);
                    if (!$value instanceof DimensionalNumber) {
                        continue;
                    }

                    $parameters[$instanceCode] = ['base' => $value->getBase(), 'size' => $value->getSize()];
                }

                $payload = [];
                foreach ($body->getPayload() as $instanceCode => $abilityCode) {
                    $value = $this->abilityParameter($slice, $donorCode, $ability, $abilityCode, $automatic);
                    if ($value instanceof DimensionalNumber && $value->getSize() === 0) {
                        $payload[$instanceCode] = $value->getBase();
                    }
                }

                if ($parameters !== [] || $payload !== []) {
                    $result[$donorCode] = [
                        'itemCode' => $body->getItemCode(),
                        'parameters' => $parameters,
                        'payload' => $payload,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Доноры предметов по коду: выбранные способности (первый экземпляр уровня ≥ 1)
     * и автоматические ссылки расы — «Даёт способности».
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param array<string, mixed> $choices Документ choices.
     *
     * @return array<string, array<string, mixed>> Код → способность.
     */
    private function abilities(CharacterRuleSlice $slice, array $choices): array
    {
        $rows = [];
        foreach (is_array($choices['abilities'] ?? null) ? $choices['abilities'] : [] as $ability) {
            if (!is_array($ability) || !is_string($ability['ruleCode'] ?? null)) {
                continue;
            }

            if ($this->level($ability) < 1 || isset($rows[$ability['ruleCode']])) {
                continue;
            }

            $rows[$ability['ruleCode']] = $ability;
        }

        $automatic = $this->raceAbilityCodes->getAutomaticParameters($slice, $this->raceCode($choices));
        foreach ($automatic as $code => $parameters) {
            if (isset($rows[$code])) {
                continue;
            }

            $rows[$code] = ['ruleCode' => $code, 'level' => 1, 'parameters' => $parameters];
        }

        return $rows;
    }

    /**
     * Код расы выборов.
     *
     * @param array<string, mixed> $choices Документ choices.
     *
     * @return string|null Код или null.
     */
    private function raceCode(array $choices): ?string
    {
        $code = $choices['raceCode'] ?? null;

        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * Уровень способности.
     *
     * @param array<string, mixed> $ability Способность.
     *
     * @return int Уровень.
     */
    private function level(array $ability): int
    {
        $level = $ability['level'] ?? null;

        return is_int($level) ? $level : 0;
    }

    /**
     * Код предмета строки.
     *
     * @param array<string, mixed> $item Строка.
     *
     * @return string Код или пустая строка.
     */
    private function itemCode(array $item): string
    {
        $code = $item['ruleCode'] ?? null;

        return is_string($code) ? $code : '';
    }

    /**
     * Код способности-донора строки.
     *
     * @param array<string, mixed> $item Строка.
     *
     * @return string Код или пустая строка.
     */
    private function donorCode(array $item): string
    {
        $grant = is_array($item['grantedBy'] ?? null) ? $item['grantedBy'] : [];
        $code = $grant['ruleCode'] ?? null;

        return is_string($code) ? $code : '';
    }

    /**
     * Строка без клиентских параметров: сервер пишет их заново.
     *
     * @param array<string, mixed> $item Строка.
     *
     * @return array<string, mixed> Строка.
     */
    private function withoutParameters(array $item): array
    {
        unset($item['parameters']);

        return $item;
    }

    /**
     * Привязка параметров экземпляра из гранта предмета.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $donorCode Код донора.
     * @param string $itemCode Код предмета.
     * @param int $level Уровень способности.
     *
     * @return array<string, string> Код экземпляра → код параметра способности.
     */
    private function binding(CharacterRuleSlice $slice, string $donorCode, string $itemCode, int $level): array
    {
        $rule = $slice->findLive($donorCode);
        if ($rule === null || $rule->isSpecBroken()) {
            return [];
        }

        foreach ($this->grants->getForLevel($rule, $donorCode, $level) as $grant) {
            $body = $grant->getGrant();
            if ($body instanceof ItemGrant && $body->getItemCode() === $itemCode) {
                return $body->getParameters();
            }
        }

        return [];
    }

    /**
     * Привязка полей payload экземпляра из гранта предмета.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $donorCode Код донора.
     * @param string $itemCode Код предмета.
     * @param int $level Уровень способности.
     *
     * @return array<string, string> Код поля payload → код параметра способности.
     */
    private function payloadBinding(CharacterRuleSlice $slice, string $donorCode, string $itemCode, int $level): array
    {
        $rule = $slice->findLive($donorCode);
        if ($rule === null || $rule->isSpecBroken()) {
            return [];
        }

        foreach ($this->grants->getForLevel($rule, $donorCode, $level) as $grant) {
            $body = $grant->getGrant();
            if ($body instanceof ItemGrant && $body->getItemCode() === $itemCode) {
                return $body->getPayload();
            }
        }

        return [];
    }

    /**
     * Живое значение параметра способности: выбор, затем default spec.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $donorCode Код донора.
     * @param array<string, mixed> $ability Способность из документа.
     * @param string $code Код параметра.
     * @param array<string, array<string, array{base: int, size: int}>> $automatic Automatic-значения расы.
     *
     * @return DimensionalNumber|null Значение или null.
     */
    private function abilityParameter(
        CharacterRuleSlice $slice,
        string $donorCode,
        array $ability,
        string $code,
        array $automatic = [],
    ): ?DimensionalNumber {
        $chosen = is_array($ability['parameters'] ?? null) ? $ability['parameters'] : [];
        $value = $this->number($chosen[$code] ?? null);
        if ($value instanceof DimensionalNumber) {
            return $value;
        }

        $fromRace = $this->number($automatic[$donorCode][$code] ?? null);
        if ($fromRace instanceof DimensionalNumber) {
            return $fromRace;
        }

        return $this->defaultOf($slice, $donorCode, $code);
    }

    /**
     * Значение параметра по умолчанию из спеки донора.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $donorCode Код донора.
     * @param string $code Код параметра.
     *
     * @return DimensionalNumber|null Значение или null.
     */
    private function defaultOf(CharacterRuleSlice $slice, string $donorCode, string $code): ?DimensionalNumber
    {
        $rule = $slice->findLive($donorCode);
        $spec = $rule?->getSpec();
        if ($rule === null || $rule->isSpecBroken() || !$spec instanceof AbilityWithBase) {
            return null;
        }

        foreach ($spec->getBase()->getParameters() as $parameter) {
            if ($parameter->getCode() === $code) {
                $default = $parameter->getDefault();

                return $default instanceof DimensionalNumber
                    ? $default
                    : new DimensionalNumber($default, 0);
            }
        }

        return null;
    }

    /**
     * Размерное значение из документа.
     *
     * @param mixed $value Число или объект.
     *
     * @return DimensionalNumber|null Значение или null.
     */
    private function number(mixed $value): ?DimensionalNumber
    {
        if (is_int($value)) {
            return new DimensionalNumber($value, 0);
        }

        if (!is_array($value) && !$value instanceof DimensionalNumber) {
            return null;
        }

        $base = is_array($value) ? ($value['base'] ?? null) : $value->getBase();
        $size = is_array($value) ? ($value['size'] ?? null) : $value->getSize();
        if (!is_int($base) || !is_int($size) || abs($size) > 20) {
            return null;
        }

        return new DimensionalNumber($base, $size);
    }
}
