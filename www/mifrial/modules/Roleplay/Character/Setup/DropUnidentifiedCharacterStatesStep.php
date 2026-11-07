<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Setup;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Service\CharacterActualStateRows;
use Mifrial\Roleplay\Character\Table\CharacterTable;

/**
 * Удаляет sheet.states без уникального id.
 */
final class DropUnidentifiedCharacterStatesStep implements ISetupStep
{
    /**
     * Создаёт шаг.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз таблиц.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
    }

    /**
     * Возвращает id шага.
     *
     * @return string Ключ шага.
     */
    public function getId(): string
    {
        return 'Roleplay/Character:drop-unidentified-states';
    }

    /**
     * Чистит character.sheet.states.
     *
     * @return void
     *
     * @throws KernelException Если запись не удалась.
     */
    public function run(): void
    {
        $opened = $this->smartTableGateway->open(CharacterTable::class);
        if (!$opened->schema()->exists()) {
            return;
        }

        $this->scan($opened->records());
    }

    /**
     * Страницы строк.
     *
     * @param IOpenedRecords $records Порт строк.
     *
     * @return void
     *
     * @throws KernelException Если запись не удалась.
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
                $this->cleanOne($records, $row);
            }

            $offset += 100;
        } while (count($rows) === 100);
    }

    /**
     * Одна actual-строка.
     *
     * @param IOpenedRecords $records Порт.
     * @param array<string, mixed> $row Строка.
     *
     * @return void
     *
     * @throws KernelException Если запись не удалась.
     */
    private function cleanOne(IOpenedRecords $records, array $row): void
    {
        $update = $this->updateOf($row);
        if ($update === null) {
            return;
        }

        try {
            $records->update($update['id'], ['sheet' => $update['sheet'], 'actual_version' => $update['version'] + 1]);
        } catch (RowWriteFailedException $exception) {
            throw new KernelException('SETUP_FAILED', 'Character unidentified states drop failed', $exception);
        }
    }

    /**
     * Что и куда писать, если строку нужно очистить.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array{id: int, sheet: array<string, mixed>, version: int}|null Правка или null.
     */
    private function updateOf(array $row): ?array
    {
        $sheet = $row['sheet'] ?? null;
        $id = $row['id'] ?? null;
        $version = $row['actual_version'] ?? null;
        if (!is_array($sheet) || !is_int($id) || !is_int($version)) {
            return null;
        }

        $next = $this->cleaned($sheet);
        if ($next === null || $next === $sheet) {
            return null;
        }

        return ['id' => $id, 'sheet' => $next, 'version' => $version];
    }

    /**
     * Лист без неизвестных состояний. Битая строка — null.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, mixed>|null Лист или null.
     */
    private function cleaned(array $sheet): ?array
    {
        try {
            return (new CharacterActualStateRows())->withoutUnidentified($sheet);
        } catch (CharacterInvalidException) {
            return null;
        }
    }
}
