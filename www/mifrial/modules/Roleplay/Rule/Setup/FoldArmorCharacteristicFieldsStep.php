<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Setup;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Table\RuleVersionTable;

/**
 * Переносит armor.strength_penalty / max_agility в grants и characteristic_limits.
 */
final class FoldArmorCharacteristicFieldsStep implements ISetupStep
{
    /**
     * Создаёт шаг.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
    }

    /**
     * Id шага.
     *
     * @return string Ключ.
     */
    public function getId(): string
    {
        return 'Roleplay/Rule:fold-armor-characteristic-fields';
    }

    /**
     * Переписывает spec item и item_modifier.
     *
     * @return void
     *
     * @throws KernelException Если запись не удалась.
     * @throws RuleInvalidException Если оба источника лимита заданы.
     */
    public function run(): void
    {
        $opened = $this->smartTableGateway->open(RuleVersionTable::class);
        if (!$opened->schema()->exists()) {
            return;
        }

        $this->scan($opened->records());
    }

    /**
     * Сворачивает документ spec. null — ключей не было.
     *
     * @param array<string, mixed> $spec Документ.
     * @param string $type Тип правила.
     * @param int $versionId Id версии для ошибки.
     *
     * @return array<string, mixed>|null Новый spec.
     *
     * @throws RuleInvalidException Если конфликт лимитов.
     */
    public function foldDocument(array $spec, string $type, int $versionId = 0): ?array
    {
        if ($type === 'item') {
            return $this->foldItem($spec, $versionId);
        }

        if ($type === 'item_modifier') {
            return $this->foldModifier($spec);
        }

        return null;
    }

    /**
     * Страницы rule_version.
     *
     * @param IOpenedRecords $records Порт.
     *
     * @return void
     *
     * @throws KernelException Если запись не удалась.
     * @throws RuleInvalidException Если конфликт.
     */
    private function scan(IOpenedRecords $records): void
    {
        $offset = 0;
        do {
            $page = $records->getList(ListQuery::fromOptions([
                'limit' => 100,
                'offset' => $offset,
                'sort' => ['id' => 'ASC'],
            ]));
            $rows = $page->rows();
            foreach ($rows as $row) {
                $this->foldOne($records, $row);
            }

            $offset += 100;
        } while (count($rows) === 100);
    }

    /**
     * Одна строка.
     *
     * @param IOpenedRecords $records Порт.
     * @param array<string, mixed> $row Строка.
     *
     * @return void
     *
     * @throws KernelException Если запись не удалась.
     * @throws RuleInvalidException Если конфликт.
     */
    private function foldOne(IOpenedRecords $records, array $row): void
    {
        $type = $row['type'] ?? null;
        $spec = $row['spec'] ?? null;
        $id = $row['id'] ?? null;
        if (!is_string($type) || !is_array($spec) || !is_int($id)) {
            return;
        }

        $next = $this->foldDocument($spec, $type, $id);
        if ($next === null) {
            return;
        }

        try {
            $records->update($id, ['spec' => $next]);
        } catch (RowWriteFailedException $exception) {
            throw new KernelException('SETUP_FAILED', 'Rule armor field fold failed', $exception);
        }
    }

    /**
     * Предмет.
     *
     * @param array<string, mixed> $spec Документ.
     * @param int $versionId Id.
     *
     * @return array<string, mixed>|null Новый spec.
     *
     * @throws RuleInvalidException Если конфликт.
     */
    private function foldItem(array $spec, int $versionId): ?array
    {
        $armor = $spec['armor'] ?? null;
        if (!is_array($armor)) {
            return null;
        }

        $changed = false;
        if (array_key_exists('strength_penalty', $armor)) {
            $penalty = $armor['strength_penalty'];
            unset($armor['strength_penalty']);
            $changed = true;
            if (is_int($penalty) && $penalty !== 0) {
                $spec['grants'] = $this->withGrant(is_array($spec['grants'] ?? null) ? $spec['grants'] : [], $penalty);
            }
        }

        if (array_key_exists('max_agility', $armor)) {
            $cap = $armor['max_agility'];
            unset($armor['max_agility']);
            $changed = true;
            if (is_array($cap) && is_int($cap['base'] ?? null) && is_int($cap['size'] ?? null)) {
                $armor['characteristic_limits'] = $this->withLimit(
                    is_array($armor['characteristic_limits'] ?? null) ? $armor['characteristic_limits'] : [],
                    $cap,
                    $versionId,
                );
            }
        }

        if (!$changed) {
            return null;
        }

        $spec['armor'] = $armor;

        return $spec;
    }

    /**
     * Модификатор: operations и effects[].ops.
     *
     * @param array<string, mixed> $spec Документ.
     *
     * @return array<string, mixed>|null Новый spec.
     */
    private function foldModifier(array $spec): ?array
    {
        $changed = false;
        if (isset($spec['operations']) && is_array($spec['operations'])) {
            $folded = $this->foldOps($spec['operations']);
            if ($folded !== $spec['operations']) {
                $spec['operations'] = $folded;
                $changed = true;
            }
        }

        $effects = $spec['effects'] ?? null;
        if (is_array($effects)) {
            foreach ($effects as $index => $effect) {
                if (!is_array($effect) || !isset($effect['ops']) || !is_array($effect['ops'])) {
                    continue;
                }

                $folded = $this->foldOps($effect['ops']);
                if ($folded !== $effect['ops']) {
                    $effects[$index]['ops'] = $folded;
                    $changed = true;
                }
            }

            $spec['effects'] = $effects;
        }

        return $changed ? $spec : null;
    }

    /**
     * Список операций.
     *
     * @param array<mixed> $ops Список.
     *
     * @return array<mixed> Список.
     */
    private function foldOps(array $ops): array
    {
        $next = [];
        foreach ($ops as $op) {
            $next[] = is_array($op) ? $this->foldOp($op) : $op;
        }

        return $next;
    }

    /**
     * Одна операция.
     *
     * @param array<string, mixed> $op Операция.
     *
     * @return array<string, mixed> Операция.
     */
    private function foldOp(array $op): array
    {
        $type = $op['type'] ?? null;
        if ($type === 'strength_penalty') {
            $op['type'] = 'characteristic_modify';
            $op['characteristic_code'] = 'strength';
        }

        if ($type === 'max_agility') {
            $op['type'] = 'characteristic_limit';
            $op['characteristic_code'] = 'dexterity';
        }

        return $op;
    }

    /**
     * Добавляет грант штрафа.
     *
     * @param array<mixed> $grants Список.
     * @param int $amount Величина.
     *
     * @return array<mixed> Список.
     */
    private function withGrant(array $grants, int $amount): array
    {
        $grants[] = [
            'type' => 'characteristic_modify',
            'characteristic_code' => 'strength',
            'source_code' => 'armor',
            'check_codes' => [],
            'amount' => $amount,
            'permanent' => true,
        ];

        return $grants;
    }

    /**
     * Добавляет потолок ловкости.
     *
     * @param array<mixed> $limits Список.
     * @param array{base: int, size: int} $cap Пара.
     * @param int $versionId Id.
     *
     * @return array<mixed> Список.
     *
     * @throws RuleInvalidException Если код уже есть.
     */
    private function withLimit(array $limits, array $cap, int $versionId): array
    {
        foreach ($limits as $row) {
            if (is_array($row) && ($row['characteristic_code'] ?? null) === 'dexterity') {
                throw new RuleInvalidException(
                    'Rule version ' . $versionId . ' already has a dexterity characteristic_limit',
                );
            }
        }

        $limits[] = [
            'characteristic_code' => 'dexterity',
            'limit' => ['type' => 'dimensional', 'base' => $cap['base'], 'size' => $cap['size']],
        ];

        return $limits;
    }
}
