<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Interface\Service;

/**
 * Аватар учётки: производная картинка и ссылка на файл.
 */
interface IUserAvatar
{
    /**
     * Ставит или снимает аватар текущего актора.
     *
     * @param string|null $filePublicId Загруженный файл или null, чтобы снять.
     *
     * @return array<string, mixed> JSON User после изменения.
     */
    public function setAvatar(?string $filePublicId): array;
}
