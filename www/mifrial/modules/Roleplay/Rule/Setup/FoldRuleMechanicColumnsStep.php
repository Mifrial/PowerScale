<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Setup;

use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Rule\Table\RuleVersionTable;

/**
 * Копирует mechanic_id и mechanic_payload в mechanics и снимает старые колонки.
 */
final class FoldRuleMechanicColumnsStep implements ISetupStep
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
        return 'Roleplay/Rule:fold-mechanic-columns';
    }

    /**
     * Сворачивает legacy-колонки, если они ещё есть.
     *
     * @return void
     */
    public function run(): void
    {
        $openedSchema = $this->smartTableGateway->open(RuleVersionTable::class)->schema();
        if (!$openedSchema->exists()) {
            return;
        }

        $openedSchema->foldLegacyMechanicColumns();
    }
}
