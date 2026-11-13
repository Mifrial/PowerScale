<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Interface\Service;

use Mifrial\Library\Asset\Exception\AssetInvalidException;

/**
 * Публичный порт коллекций для модулей-потребителей.
 */
interface IAssetCollections
{
    /**
     * Гарантирует существование коллекции назначения.
     *
     * Идемпотентен: повторный вызов с теми же модулем и кодом ничего не меняет.
     * Подпись существующей коллекции не перезаписывается.
     *
     * @param string $module Модуль-владелец в форме `Group/Module`.
     * @param string $code Код внутри модуля.
     * @param string $title Подпись для UI.
     *
     * @return void
     *
     * @throws AssetInvalidException Если ключ или подпись недопустимы.
     */
    public function ensure(string $module, string $code, string $title): void;
}
