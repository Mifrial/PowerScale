<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh
// phpcs:disable MifrialCodingStandard.Metrics.MethodQuality

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Dto\ResourceBackfillResult;
use Mifrial\Roleplay\Character\Dto\ResourceLimitBonus;
use Mifrial\Roleplay\Character\Dto\ResourceRow;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Exception\CharacterSaveRejectedException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceArithmetic;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceStorage;
use Mifrial\Roleplay\Character\Service\Runtime\Character;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Character\Service\Save\CharacterSheetDocument;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Меняет сохранённый actual операцией каталога и ожидаемой версией.
 */
final class CharacterActualMutations implements ICharacterActualMutations
{
    private readonly CharacterActualStateRows $stateRows;

    private readonly CharacterResourceStorage $resourceStorage;

    /**
     * Создаёт порт.
     *
     * @param ICharacters $characters Строка actual.
     * @param ICharacterRuleSlices $ruleSlices Срез ревизии.
     * @param ICharacterSheets $sheets Валидатор.
     * @param CharacterChoiceAssembler $choiceAssembler Модель choices.
     * @param CharacterSheetDocument $sheetDocument Девять ключей sheet.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacters $characters,
        private readonly ICharacterRuleSlices $ruleSlices,
        private readonly ICharacterSheets $sheets,
        private readonly CharacterChoiceAssembler $choiceAssembler,
        private readonly CharacterSheetDocument $sheetDocument,
    ) {
        $this->stateRows = new CharacterActualStateRows();
        $this->resourceStorage = new CharacterResourceStorage();
    }

    /**
     * Пишет actual, если версия совпала и операции разобраны.
     *
     * @param int $characterId Персонаж.
     * @param int $expectedActualVersion Текущий actual_version.
     * @param array $operations Список операций.
     *
     * @return CharacterRecord После записи.
     *
     * @throws CharacterConflictException Если версия устарела.
     * @throws CharacterInvalidException Если операция или форма листа.
     * @throws CharacterNotFoundException Если строки или ревизии нет.
     * @throws CharacterSaveRejectedException Если validate вернул problems.
     */
    public function apply(int $characterId, int $expectedActualVersion, array $operations): CharacterRecord
    {
        $record = $this->locked($characterId, $expectedActualVersion);
        $patched = $this->applyToDocument(
            $record->getSpaceId(),
            $record->getRulesRevision(),
            $record->getChoices(),
            $record->getSheet(),
            $operations,
        );

        return $this->characters->replacePayload(
            $characterId,
            $patched['choices'],
            $this->sheetAfter($record, $patched),
            $expectedActualVersion,
        );
    }

    /**
     * Инициализирует или выправляет server-owned resource rows.
     *
     * @param int $characterId Персонаж.
     * @param int $expectedActualVersion Ожидаемая actual_version.
     *
     * @return ResourceBackfillResult Результат backfill.
     *
     * @throws CharacterConflictException Если версия устарела.
     * @throws CharacterInvalidException Если лист или resources битые.
     * @throws CharacterNotFoundException Если строки или ревизии нет.
     * @throws CharacterSaveRejectedException Если validate вернул problems.
     */
    public function backfill(int $characterId, int $expectedActualVersion): ResourceBackfillResult
    {
        $record = $this->locked($characterId, $expectedActualVersion);
        $sheet = $this->nextSheet($record, $record->getChoices());
        $status = $this->backfillStatus($record->getSheet(), $sheet);
        if ($status === 'noop') {
            return new ResourceBackfillResult($record, $status);
        }

        $saved = $this->characters->replacePayload(
            $characterId,
            $record->getChoices(),
            $sheet,
            $expectedActualVersion,
            DateTime::now(),
        );

        return new ResourceBackfillResult($saved, $status);
    }

    /**
     * Патчит choices, sheet.money и sheet.states. Строку не пишет и validate не вызывает.
     *
     * @param int $spaceId Мир листа.
     * @param int $rulesRevision Ревизия листа.
     * @param array $choices Сохранённые choices.
     * @param array $sheet Сохранённый sheet.
     * @param array $operations Список операций.
     *
     * @return array{choices: array, sheet: array} Документ.
     *
     * @throws CharacterInvalidException Если операция или tombstone.
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    public function applyToDocument(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        array $sheet,
        array $operations,
    ): array {
        $choices = $this->patchedChoices($choices, $operations);
        $slice = $this->ruleSlices->get($spaceId, $rulesRevision);
        $this->assertItemsLive($slice, $operations);
        $sheet = $this->resourceSpends($sheet, $slice, $operations);
        $sheet = $this->resourceCurrents($sheet, $slice, $operations);
        $sheet = $this->refillTurnResources($sheet, $slice, $operations);
        $sheet = $this->stateRows->append(
            $this->moneyOnSheet($sheet, $operations, $choices),
            $operations,
            $slice,
        );
        $character = Character::fromDocument($slice, $choices, $sheet, $this->sheets);

        return [
            'choices' => $character->getChoices(),
            'sheet' => $character->getSheet(),
        ];
    }

    /**
     * Строка с совпавшей версией.
     *
     * @param int $characterId Персонаж.
     * @param int $expectedActualVersion Ожидание.
     *
     * @return CharacterRecord Строка.
     *
     * @throws CharacterConflictException Если версия другая.
     * @throws CharacterNotFoundException Если строки нет.
     */
    private function locked(int $characterId, int $expectedActualVersion): CharacterRecord
    {
        $record = $this->characters->get($characterId);
        if ($record->getActualVersion() !== $expectedActualVersion) {
            throw new CharacterConflictException(
                $record->getActualVersion(),
                currentSheet: [
                    'choices' => $record->getChoices(),
                    'sheet' => $record->getSheet(),
                ],
            );
        }

        return $record;
    }

    /**
     * Choices после всех операций. Ошибка не доходит до записи.
     *
     * @param array<string, mixed> $choices Сохранённый документ.
     * @param array $operations Список.
     *
     * @return array<string, mixed> Документ.
     *
     * @throws CharacterInvalidException Если список пуст или операция битая.
     */
    private function patchedChoices(array $choices, array $operations): array
    {
        if ($operations === []) {
            throw new CharacterInvalidException('Character patch has no operations');
        }

        foreach ($operations as $operation) {
            $choices = $this->applyOperation($choices, $operation);
        }

        return $choices;
    }

    /**
     * Одна операция каталога.
     *
     * @param array<string, mixed> $choices Документ.
     * @param mixed $operation Объект.
     *
     * @return array<string, mixed> Документ.
     *
     * @throws CharacterInvalidException Если kind чужой.
     */
    private function applyOperation(array $choices, mixed $operation): array
    {
        $kind = is_array($operation) ? ($operation['kind'] ?? null) : null;
        if ($kind === 'spendResources') {
            return $choices;
        }

        if ($kind === 'setResourceCurrent') {
            $this->assertResourceCurrentShape($operation);

            return $choices;
        }

        if ($kind === 'setInventoryQuantity' || $kind === 'putInventoryQuantity') {
            return $this->applyQuantity($choices, $operation, $kind === 'putInventoryQuantity');
        }

        if ($kind === 'decrementInventoryQuantity') {
            return $this->applyDecrement($choices, $operation);
        }

        if ($kind === 'setMoney') {
            return $this->applyMoney($choices, $operation);
        }

        if ($kind === 'putState') {
            $this->stateRows->accept($operation);

            return $choices;
        }

        if ($kind === 'putDamageSplit') {
            $this->stateRows->acceptDamage($operation);

            return $choices;
        }

        if ($kind === 'patchState') {
            $this->stateRows->acceptPatch($operation);

            return $choices;
        }

        if ($kind === 'removeState') {
            $this->stateRows->acceptRemove($operation);

            return $choices;
        }

        if ($kind === 'refillTurnResource') {
            $this->assertRefillShape($operation);

            return $choices;
        }

        throw new CharacterInvalidException('Character patch operation is invalid');
    }

    /**
     * Tombstone кодов количества.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param array $operations Список.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если код снят.
     */
    private function assertItemsLive(CharacterRuleSlice $slice, array $operations): void
    {
        foreach ($operations as $operation) {
            $this->assertOperationLive($slice, $operation);
        }
    }

    /**
     * Один код количества не tombstone.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param mixed $operation Операция.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если код снят.
     */
    private function assertOperationLive(CharacterRuleSlice $slice, mixed $operation): void
    {
        if (!is_array($operation)) {
            return;
        }

        $kind = $operation['kind'] ?? null;
        if ($kind !== 'setInventoryQuantity' && $kind !== 'putInventoryQuantity') {
            return;
        }

        $ruleCode = $operation['ruleCode'] ?? null;
        if (is_string($ruleCode) && $slice->hasTombstone($ruleCode)) {
            throw new CharacterInvalidException('Character item is tombstoned');
        }
    }

    /**
     * sheet.money после setMoney. Остальные ключи прежние.
     *
     * @param array<string, mixed> $sheet Снимок.
     * @param array $operations Список.
     * @param array<string, mixed> $choices Документ после патча.
     *
     * @return array<string, mixed> Sheet.
     */
    private function moneyOnSheet(array $sheet, array $operations, array $choices): array
    {
        foreach ($operations as $operation) {
            if (is_array($operation) && ($operation['kind'] ?? null) === 'setMoney') {
                $sheet['money'] = $choices['money'];
            }
        }

        return $sheet;
    }

    /**
     * Применяет все подготовленные resource spends одной мутацией.
     *
     * @param array<string, mixed> $sheet Sheet.
     * @param CharacterRuleSlice $slice Live rules.
     * @param array<int, mixed> $operations Operations.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws CharacterInvalidException If resources or spends are invalid.
     */
    private function resourceSpends(
        array $sheet,
        CharacterRuleSlice $slice,
        array $operations,
    ): array {
        $spends = [];
        foreach ($operations as $operation) {
            if (!is_array($operation) || ($operation['kind'] ?? null) !== 'spendResources') {
                continue;
            }

            $operationSpends = $operation['spends'] ?? null;
            if (!is_array($operationSpends) || !array_is_list($operationSpends)) {
                throw new CharacterInvalidException('Resource spend operation is invalid');
            }

            foreach ($operationSpends as $spend) {
                if (!$spend instanceof ResourceSpend) {
                    throw new CharacterInvalidException('Resource spend operation is invalid');
                }

                $spends[] = $spend;
            }
        }

        if ($spends === []) {
            return $sheet;
        }

        $rows = $sheet['resources'] ?? null;
        if (!is_array($rows)) {
            throw new CharacterInvalidException('Character resources are missing');
        }

        $typedRows = $this->resourceStorage->parseRows($rows, $slice);

        return [
            ...$sheet,
            'resources' => $this->resourceStorage->serializeRows(
                $this->resourceStorage->spendRows($typedRows, $slice, $spends),
            ),
        ];
    }

    /**
     * Выставляет current одной строки ресурса с клампом 0..limit.
     *
     * @param array<string, mixed> $sheet Sheet.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, mixed> $operations Операции.
     * @param array<string, mixed> $choices Выборы листа.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws CharacterInvalidException Если форма или ресурс.
     */
    private function resourceCurrents(
        array $sheet,
        CharacterRuleSlice $slice,
        array $operations,
    ): array {
        foreach ($operations as $operation) {
            if (!is_array($operation) || ($operation['kind'] ?? null) !== 'setResourceCurrent') {
                continue;
            }

            $rows = $sheet['resources'] ?? null;
            if (!is_array($rows)) {
                throw new CharacterInvalidException('Character resources are missing');
            }

            $typedRows = $this->resourceStorage->parseRows($rows, $slice);
            $written = $this->resourceStorage->setCurrentRows(
                $typedRows,
                $slice,
                $operation['resourceCode'],
                $operation['current'],
            );
            $sheet = [
                ...$sheet,
                'resources' => $this->resourceStorage->serializeRows($written),
            ];
        }

        return $sheet;
    }

    /**
     * Ключи setResourceCurrent.
     *
     * @param mixed $operation Операция.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если форма чужая.
     */
    private function assertResourceCurrentShape(mixed $operation): void
    {
        $keys = is_array($operation) ? array_keys($operation) : [];
        sort($keys);
        $code = is_array($operation) ? ($operation['resourceCode'] ?? null) : null;
        if (
            $keys !== ['current', 'kind', 'resourceCode']
            || !is_string($code)
            || $code === ''
            || !array_key_exists('current', is_array($operation) ? $operation : [])
        ) {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }
    }

    /**
     * Восстанавливает current карточек turn_refill до базового лимита.
     *
     * @param array<string, mixed> $sheet Sheet.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, mixed> $operations Операции.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws CharacterInvalidException Если resources битые.
     */
    private function refillTurnResources(array $sheet, CharacterRuleSlice $slice, array $operations): array
    {
        $refill = false;
        foreach ($operations as $operation) {
            if (is_array($operation) && ($operation['kind'] ?? null) === 'refillTurnResource') {
                $refill = true;
            }
        }

        if (!$refill) {
            return $sheet;
        }

        $rows = $sheet['resources'] ?? null;
        if (!is_array($rows)) {
            throw new CharacterInvalidException('Character resources are missing');
        }

        $arithmetic = new CharacterResourceArithmetic();
        $next = [];
        foreach ($this->resourceStorage->parseRows($rows, $slice) as $row) {
            $next[] = $this->refilledRow($slice, $arithmetic, $row, $sheet);
        }

        return [...$sheet, 'resources' => $this->resourceStorage->serializeRows($next)];
    }

    /**
     * Строка после refill или прежняя.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterResourceArithmetic $arithmetic Арифметика.
     * @param ResourceRow $row Строка.
     * @param array<string, mixed> $sheet Sheet.
     *
     * @return ResourceRow Строка.
     */
    private function refilledRow(
        CharacterRuleSlice $slice,
        CharacterResourceArithmetic $arithmetic,
        ResourceRow $row,
        array $sheet,
    ): ResourceRow {
        $rule = $slice->findLive($row->getRuleCode());
        $spec = $rule instanceof CharacterResolvedRule && !$rule->isSpecBroken() ? $rule->getSpec() : null;
        if (!$spec instanceof ResourceSpec || !$spec->isTurnRefill()) {
            return $row;
        }

        $base = $row->getBase() ?? $arithmetic->getBase($spec, []);
        $bonuses = $this->withLimitAdjustments($spec, $row->getBonuses(), $sheet);
        $refilled = $arithmetic->value($arithmetic->effective($base, $bonuses));

        return new ResourceRow(
            $row->getRuleCode(),
            $refilled,
            $base,
            $row->getBonuses(),
        );
    }

    /**
     * Бонусы строки плюс поправки спеки, которых в строке ещё нет.
     *
     * @param ResourceSpec $spec Карточка.
     * @param array<int, ResourceLimitBonus> $bonuses Вклады строки.
     * @param array<string, mixed> $sheet Sheet.
     *
     * @return array<int, ResourceLimitBonus> Вклады для потолка.
     *
     * @throws CharacterInvalidException Если формула лимита битая.
     */
    private function withLimitAdjustments(ResourceSpec $spec, array $bonuses, array $sheet): array
    {
        $limit = $spec->getLimit();
        if ($limit === null) {
            return $bonuses;
        }

        $known = [];
        foreach ($bonuses as $bonus) {
            $source = $bonus->getSourceRuleCode();
            if ($source !== null && $source !== '') {
                $known[$source] = true;
            }
        }

        $evaluations = new FormulaEvaluations();
        $context = $this->characteristicContext($sheet);
        foreach ($limit->getAdjustments() as $adjustment) {
            $source = $adjustment->getSourceCode();
            if ($source !== '' && isset($known[$source])) {
                continue;
            }

            try {
                $delta = $evaluations->evaluateScalar($adjustment->getValue(), $context);
            } catch (RuleInvalidException $exception) {
                throw new CharacterInvalidException('Resource limit formula is invalid', $exception);
            }

            $bonuses[] = new ResourceLimitBonus($source !== '' ? $source : null, $delta);
        }

        return $bonuses;
    }

    /**
     * Характеристики sheet для формул лимита.
     *
     * @param array<string, mixed> $sheet Sheet.
     *
     * @return FormulaContext Контекст.
     */
    private function characteristicContext(array $sheet): FormulaContext
    {
        $values = [];
        $rows = $sheet['characteristics'] ?? [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $code = $row['characteristicCode'] ?? null;
                $value = $row['value'] ?? null;
                $base = is_array($value) ? ($value['base'] ?? null) : null;
                $size = is_array($value) ? ($value['size'] ?? null) : null;
                if (!is_string($code) || !is_int($base) || !is_int($size)) {
                    continue;
                }

                $values[$code] = new CharacteristicNumber($base, $size);
            }
        }

        return new FormulaContext($values, []);
    }

    /**
     * Только kind у refill.
     *
     * @param mixed $operation Операция.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если форма чужая.
     */
    private function assertRefillShape(mixed $operation): void
    {
        $keys = is_array($operation) ? array_keys($operation) : [];
        sort($keys);
        if ($keys !== ['kind']) {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }
    }

    /**
     * Пишет choices.money.
     *
     * @param array<string, mixed> $choices Документ.
     * @param mixed $operation Объект.
     *
     * @return array<string, mixed> Документ.
     *
     * @throws CharacterInvalidException Если форма или сумма.
     */
    private function applyMoney(array $choices, mixed $operation): array
    {
        $keys = is_array($operation) ? array_keys($operation) : [];
        sort($keys);
        if ($keys !== ['amount', 'kind']) {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }

        $amount = $operation['amount'];
        if (!is_int($amount) || $amount < 0) {
            throw new CharacterInvalidException('Character money is invalid');
        }

        $choices['money'] = $amount;

        return $choices;
    }

    /**
     * Одна смена quantity по ruleCode.
     *
     * @param array<string, mixed> $choices Документ.
     * @param mixed $operation Объект операции.
     * @param bool $createMissing Разрешить создание отсутствующей строки.
     *
     * @return array<string, mixed> Документ.
     *
     * @throws CharacterInvalidException Если kind, ключи или строка инвентаря.
     */
    private function applyQuantity(array $choices, mixed $operation, bool $createMissing): array
    {
        $ruleCode = $this->ruleCodeOf($operation, $createMissing);
        $quantity = $this->quantityOf($operation);
        $inventory = $this->inventoryOf($choices);
        $index = $this->onlyIndex($inventory, $ruleCode, $createMissing);
        if ($index === null) {
            $inventory[] = [
                'ruleCode' => $ruleCode,
                'quantity' => $quantity,
                'equipped' => false,
            ];
        } else {
            $inventory[$index]['quantity'] = $quantity;
        }

        $choices['inventory'] = $inventory;

        return $choices;
    }

    /**
     * Уменьшает quantity строки инвентаря по id.
     *
     * @param array<string, mixed> $choices Документ.
     * @param mixed $operation Объект.
     *
     * @return array<string, mixed> Документ.
     *
     * @throws CharacterInvalidException Если форма чужая или quantity ≤ 0.
     */
    private function applyDecrement(array $choices, mixed $operation): array
    {
        $keys = is_array($operation) ? array_keys($operation) : [];
        sort($keys);
        if ($keys !== ['inventoryId', 'kind', 'ruleCode']) {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }

        $inventoryId = $operation['inventoryId'];
        $ruleCode = $operation['ruleCode'];
        if (!is_int($inventoryId) || !is_string($ruleCode) || $ruleCode === '') {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }

        $inventory = $choices['inventory'] ?? null;
        if (!is_array($inventory)) {
            throw new CharacterInvalidException('Character inventory is invalid');
        }

        $matched = [];
        foreach ($inventory as $index => $row) {
            if (is_array($row) && ($row['id'] ?? null) === $inventoryId) {
                $matched[] = $index;
            }
        }

        if (count($matched) !== 1) {
            throw new CharacterInvalidException('Character inventory item is unavailable');
        }

        $index = $matched[0];
        $row = $inventory[$index];
        $quantity = $row['quantity'] ?? null;
        if (($row['ruleCode'] ?? null) !== $ruleCode || !is_int($quantity) || $quantity < 1) {
            throw new CharacterInvalidException('Character inventory quantity is invalid');
        }

        $inventory[$index]['quantity'] = $quantity - 1;
        $choices['inventory'] = $inventory;

        return $choices;
    }

    /**
     * Код операции setInventoryQuantity.
     *
     * @param mixed $operation Объект.
     * @param bool $createMissing Разрешить создание отсутствующей строки.
     *
     * @return string Код предмета.
     *
     * @throws CharacterInvalidException Если форма чужая.
     */
    private function ruleCodeOf(mixed $operation, bool $createMissing): string
    {
        $keys = is_array($operation) ? array_keys($operation) : [];
        sort($keys);
        if ($keys !== ['kind', 'quantity', 'ruleCode']) {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }

        $kind = $operation['kind'];
        $expected = $createMissing ? 'putInventoryQuantity' : 'setInventoryQuantity';
        $ruleCode = $operation['ruleCode'];
        if ($kind !== $expected || !is_string($ruleCode) || $ruleCode === '') {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }

        return $ruleCode;
    }

    /**
     * Неотрицательное целое quantity.
     *
     * @param array $operation Уже проверенный объект.
     *
     * @return int Количество.
     *
     * @throws CharacterInvalidException Если не целое или меньше нуля.
     */
    private function quantityOf(array $operation): int
    {
        $quantity = $operation['quantity'];
        if (!is_int($quantity) || $quantity < 0) {
            throw new CharacterInvalidException('Character inventory quantity is invalid');
        }

        return $quantity;
    }

    /**
     * Список инвентаря документа.
     *
     * @param array<string, mixed> $choices Документ.
     *
     * @return array<int, mixed> Строки.
     *
     * @throws CharacterInvalidException Если ключа нет или это не список.
     */
    private function inventoryOf(array $choices): array
    {
        $inventory = $choices['inventory'] ?? null;
        if (!is_array($inventory)) {
            throw new CharacterInvalidException('Character inventory was not found');
        }

        return $inventory;
    }

    /**
     * Единственная строка с этим ruleCode.
     *
     * @param array<int, mixed> $inventory Строки.
     * @param string $ruleCode Код.
     * @param bool $createMissing true — отсутствие строки не ошибка.
     *
     * @return int|null Индекс или null, если строки нет и её можно создать.
     *
     * @throws CharacterInvalidException Если строки нет или их две.
     */
    private function onlyIndex(array $inventory, string $ruleCode, bool $createMissing): ?int
    {
        $found = null;
        foreach ($inventory as $index => $row) {
            $found = $this->matchedIndex($found, $index, $row, $ruleCode);
        }

        if (!is_int($found) && !$createMissing) {
            throw new CharacterInvalidException('Character inventory rule code was not found');
        }

        return is_int($found) ? $found : null;
    }

    /**
     * Учитывает строку, если ruleCode совпал.
     *
     * @param int|null $found Уже найденный индекс.
     * @param int|string $index Индекс списка.
     * @param mixed $row Строка.
     * @param string $ruleCode Код.
     *
     * @return int|null Индекс или прежний.
     *
     * @throws CharacterInvalidException Если код встретился второй раз.
     */
    private function matchedIndex(int|null $found, int|string $index, mixed $row, string $ruleCode): int|null
    {
        if (!is_array($row) || ($row['ruleCode'] ?? null) !== $ruleCode) {
            return $found;
        }

        if (!is_int($index)) {
            throw new CharacterInvalidException('Character inventory shape is invalid');
        }

        if ($found !== null) {
            throw new CharacterInvalidException('Character inventory rule code is ambiguous');
        }

        return $index;
    }

    /**
     * Производный sheet и чужие ключи старого снимка.
     *
     * @param CharacterRecord $record Строка до записи.
     * @param array<string, mixed> $choices Документ после патча.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws CharacterInvalidException Если форма, наличные или срез.
     * @throws CharacterNotFoundException Если ревизии нет.
     * @throws CharacterSaveRejectedException Если есть problems.
     */
    private function nextSheet(CharacterRecord $record, array $choices): array
    {
        $this->accepted($record, $choices);
        $slice = $this->ruleSlices->get($record->getSpaceId(), $record->getRulesRevision());
        $character = Character::fromDocument($slice, $choices, $record->getSheet(), $this->sheets);

        return $this->keepUnknown($record->getSheet(), $character->getSheet());
    }

    /**
     * Определяет результат явной сборки resources.
     *
     * @param array<string, mixed> $previous Старый sheet.
     * @param array<string, mixed> $next Новый sheet.
     *
     * @return string initialized, changed или noop.
     */
    private function backfillStatus(array $previous, array $next): string
    {
        if ($this->normalizeForComparison($previous) === $this->normalizeForComparison($next)) {
            return 'noop';
        }

        if (!array_key_exists('resources', $previous)) {
            return 'initialized';
        }

        return 'changed';
    }

    /**
     * Нормализует associative keys, не меняя порядок list-элементов.
     *
     * @param mixed $value Значение документа.
     *
     * @return mixed Нормализованное значение.
     */
    private function normalizeForComparison(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->normalizeForComparison(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeForComparison($item);
        }

        return $value;
    }

    /**
     * Сборка девяти ключей и список states после разбора.
     *
     * @param CharacterRecord $record Строка до записи.
     * @param array{choices: array, sheet: array} $patched Документ после разбора.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws CharacterInvalidException Если форма, наличные или срез.
     * @throws CharacterNotFoundException Если ревизии нет.
     * @throws CharacterSaveRejectedException Если есть problems.
     */
    private function sheetAfter(CharacterRecord $record, array $patched): array
    {
        $this->accepted($record, $patched['choices']);

        return $this->keepUnknown($record->getSheet(), $patched['sheet']);
    }

    /**
     * Валидатор без problems.
     *
     * @param CharacterRecord $record Строка.
     * @param array<string, mixed> $choices Документ.
     *
     * @return CharacterValidation Снимок.
     *
     * @throws CharacterInvalidException Если форма или мир.
     * @throws CharacterNotFoundException Если ревизии нет.
     * @throws CharacterSaveRejectedException Если есть problems.
     */
    private function accepted(CharacterRecord $record, array $choices): CharacterValidation
    {
        $slice = $this->ruleSlices->get($record->getSpaceId(), $record->getRulesRevision());
        $validation = $this->sheets->validate($slice, $this->modelOf($choices));
        if ($validation->getProblems() !== []) {
            throw new CharacterSaveRejectedException($validation->getProblems());
        }

        return $validation;
    }

    /**
     * Модель для validate. Сырой JSON в валидатор не идёт.
     *
     * @param array<string, mixed> $choices Документ.
     *
     * @return CharacterChoices Модель.
     *
     * @throws CharacterInvalidException Если форма не как у сохранённого листа.
     */
    private function modelOf(array $choices): CharacterChoices
    {
        $this->assertStoredShape($choices);

        return $this->choiceAssembler->assemble(
            $choices['name'],
            $choices['raceCode'],
            $choices['abilities'],
            $choices['inventory'],
            $choices['characteristicPurchases'],
            $choices['customRules'],
            array_key_exists('active', $choices) ? $choices['active'] : null,
        );
    }

    /**
     * Та же форма, что CharacterSheets::storedShape.
     *
     * @param array<string, mixed> $choices Документ.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если assemble не вызвать.
     */
    private function assertStoredShape(array $choices): void
    {
        $active = array_key_exists('active', $choices) ? $choices['active'] : null;
        $lists = is_array($choices['abilities'] ?? null)
            && is_array($choices['inventory'] ?? null)
            && is_array($choices['characteristicPurchases'] ?? null)
            && is_array($choices['customRules'] ?? null);
        $shaped = is_string($choices['name'] ?? null) && is_string($choices['raceCode'] ?? null) && $lists;
        if (!$shaped || ($active !== null && !is_bool($active))) {
            throw new CharacterInvalidException('Character choices shape is invalid');
        }
    }

    /**
     * Наличные уже лежащего листа.
     *
     * @param array<string, mixed> $choices Документ.
     *
     * @return int Сумма.
     *
     * @throws CharacterInvalidException Если ключа нет или это не целое.
     */
    private function moneyOf(array $choices): int
    {
        $money = $choices['money'] ?? null;
        if (!is_int($money)) {
            throw new CharacterInvalidException('Character money is invalid');
        }

        return $money;
    }

    /**
     * Ключи, которых сборка не пишет, остаются.
     *
     * @param array<string, mixed> $previous Старый sheet.
     * @param array<string, mixed> $built Девять ключей.
     *
     * @return array<string, mixed> Sheet.
     */
    private function keepUnknown(array $previous, array $built): array
    {
        foreach ($previous as $key => $value) {
            if (!array_key_exists($key, $built)) {
                $built[$key] = $value;
            }
        }

        return $built;
    }
}
