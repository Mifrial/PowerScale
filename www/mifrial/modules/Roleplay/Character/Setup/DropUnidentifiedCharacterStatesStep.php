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
        $sheet = $row['sheet'] ?? null;
        if (!is_array($sheet)) {
            return;
        }

        try {
            $next = (new CharacterActualStateRows())->withoutUnidentified($sheet);
        } catch (CharacterInvalidException) {
            return;
        }

        if ($next === $sheet) {
            return;
        }

        $id = $row['id'] ?? null;
        $version = $row['actual_version'] ?? null;
        if (!is_int($id) || !is_int($version)) {
            return;
        }

        try {
            $records->update($id, ['sheet' => $next, 'actual_version' => $version + 1]);
        } catch (RowWriteFailedException $exception) {
            throw new KernelException('SETUP_FAILED', 'Character unidentified states drop failed', $exception);
        }
    }
}
