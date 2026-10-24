<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Setup;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Service\CharacterActualStateRows;
use Mifrial\Roleplay\Game\Table\GameNpcTable;

/**
 * Удаляет version.sheet.states NPC без уникального id.
 */
final class DropUnidentifiedNpcStatesStep implements ISetupStep
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
        return 'Roleplay/Game:drop-unidentified-npc-states';
    }

    /**
     * Чистит game_npc.version.sheet.states.
     *
     * @return void
     *
     * @throws KernelException Если запись не удалась.
     */
    public function run(): void
    {
        $opened = $this->smartTableGateway->open(GameNpcTable::class);
        if (!$opened->schema()->exists()) {
            return;
        }

        $this->scan($opened->records());
    }

    /**
     * Страницы NPC.
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
     * Один NPC.
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
        $version = $row['version'] ?? null;
        if (!is_array($version)) {
            return;
        }

        $sheet = $version['sheet'] ?? null;
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
        $actual = $row['actual_version'] ?? null;
        if (!is_int($id) || !is_int($actual)) {
            return;
        }

        $version['sheet'] = $next;
        try {
            $records->update($id, ['version' => $version, 'actual_version' => $actual + 1]);
        } catch (RowWriteFailedException $exception) {
            throw new KernelException('SETUP_FAILED', 'NPC unidentified states drop failed', $exception);
        }
    }
}
