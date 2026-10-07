<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Interface\Service;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;

/**
 * Сборка узкого контекста формулы из уже записанного снимка листа.
 */
interface ICharacterFormulaContexts
{
    /**
     * Собирает контекст из документа sheet.
     *
     * @param array<string, mixed> $sheet Снимок листа.
     * @param array<string, int> $parameters Параметры формул.
     *
     * @return FormulaContext Контекст без баз действий.
     *
     * @throws CharacterInvalidException Если документ битый.
     */
    public function build(array $sheet, array $parameters = []): FormulaContext;

    /**
     * Собирает контекст из sheet строки персонажа.
     *
     * @param int $characterId Идентификатор.
     *
     * @return FormulaContext Контекст без параметров и баз действий.
     *
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterInvalidException Если документ битый.
     */
    public function buildStored(int $characterId): FormulaContext;
}
