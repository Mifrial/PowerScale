<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Save;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Белый список вложенных ключей входа save. Лишний ключ — INVALID_PARAMS.
 */
final class CharacterSaveKeys
{
    /**
     * @var array<int, string>
     */
    private const LIMITS = ['os', 'or', 'money'];

    /**
     * @var array<int, string>
     */
    private const ABILITY = [
        'ruleCode',
        'level',
        'zone',
        'parameters',
        'domain',
        'domainCode',
        'grantedBy',
        'studyPairId',
        'studyPairRole',
    ];

    /**
     * @var array<int, string>
     */
    private const GRANT = ['ruleCode', 'instanceKey'];

    /**
     * @var array<int, string>
     */
    private const INVENTORY = [
        'id',
        'ruleCode',
        'custom',
        'quantity',
        'equipped',
        'modifiers',
        'durabilityLeft',
        'note',
    ];

    /**
     * @var array<int, string>
     */
    private const CUSTOM = ['id', 'kind', 'name', 'description', 'status', 'replacedWithRuleCode'];

    /**
     * @var array<int, string>
     */
    private const PURCHASE = ['characteristicCode', 'cost'];

    /**
     * @var array<int, string>
     */
    private const SHEET = [
        'abilityLevels',
        'racialAbilityCodes',
        'osSurchargeTotal',
        'equippedModifiers',
        'coveredPaths',
        'characteristicPurchases',
        'characteristicPurchaseOs',
        'active',
        'money',
    ];

    /**
     * @var array<int, string>
     */
    private const EQUIPPED = ['ruleCode', 'strengthPenalty', 'maxAgility', 'characteristicLimits'];

    /**
     * Проверяет limits. money внутри — только если allowMoney.
     *
     * @param array<mixed> $limits Объект.
     * @param bool $allowMoney Ключ money допустим.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function assertLimits(array $limits, bool $allowMoney): void
    {
        $allowed = $allowMoney ? self::LIMITS : ['os', 'or'];
        $this->assertMap($limits, $allowed, 'limits');
    }

    /**
     * Проверяет список способностей и инвентаря.
     *
     * @param array<mixed> $abilities Способности.
     * @param array<mixed> $inventory Предметы.
     * @param array<mixed> $characteristicPurchases Закупки.
     * @param array<mixed> $customRules Свои правила.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function assertLists(array $abilities, array $inventory, array $characteristicPurchases, array $customRules): void
    {
        $this->assertRows($abilities, self::ABILITY, 'abilities');
        $this->assertRows($inventory, self::INVENTORY, 'inventory');
        $this->assertRows($characteristicPurchases, self::PURCHASE, 'characteristicPurchases');
        $this->assertRows($customRules, self::CUSTOM, 'customRules');
    }

    /**
     * Проверяет expectedSheet, если ключ прислали.
     *
     * @param array<mixed>|null $expectedSheet Снимок клиента.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function assertExpectedSheet(?array $expectedSheet): void
    {
        if ($expectedSheet === null) {
            return;
        }

        $this->assertMap($expectedSheet, self::SHEET, 'expectedSheet');
        $equipped = $expectedSheet['equippedModifiers'] ?? [];
        if (is_array($equipped)) {
            $this->assertRows($equipped, self::EQUIPPED, 'expectedSheet.equippedModifiers');
        }
    }

    /**
     * Строки списка — объекты с белым списком ключей.
     *
     * @param array<mixed> $rows Список.
     * @param array<int, string> $allowed Ключи.
     * @param string $path Путь.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertRows(array $rows, array $allowed, string $path): void
    {
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new ActionException('INVALID_PARAMS', 'Invalid parameter: ' . $path);
            }

            $this->assertMap($row, $allowed, $path . '.' . $index);
            $this->assertGrant($row, $path . '.' . $index);
        }
    }

    /**
     * grantedBy, если ключ есть.
     *
     * @param array<mixed> $row Строка способности.
     * @param string $path Путь.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertGrant(array $row, string $path): void
    {
        if (!isset($row['grantedBy'])) {
            return;
        }

        if (!is_array($row['grantedBy'])) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: ' . $path . '.grantedBy');
        }

        $this->assertMap($row['grantedBy'], self::GRANT, $path . '.grantedBy');
    }

    /**
     * Лишний ключ объекта.
     *
     * @param array<mixed> $map Объект.
     * @param array<int, string> $allowed Ключи.
     * @param string $path Путь.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertMap(array $map, array $allowed, string $path): void
    {
        foreach (array_keys($map) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                $name = is_string($key) ? $key : '0';
                throw new ActionException('INVALID_PARAMS', 'Unknown parameter: ' . $path . '.' . $name);
            }
        }
    }
}
