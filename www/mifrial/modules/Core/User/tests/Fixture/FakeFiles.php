<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Tests\Fixture;

use LogicException;
use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Interface\Service\IFiles;

/**
 * Порт файлов для тестов сборщика JSON: отдаёт только проекцию.
 */
final class FakeFiles implements IFiles
{
    /**
     * Создаёт заглушку.
     *
     * @param array<int, string> $publicIds Карта id → public_id.
     *
     * @return void
     */
    public function __construct(
        private readonly array $publicIds = [],
    ) {
    }

    /**
     * Не используется.
     *
     * @param string $publicId Внешний id.
     *
     * @return FileRecord Не возвращается.
     */
    public function requireReadable(string $publicId): FileRecord
    {
        throw new LogicException('requireReadable must not be used');
    }

    /**
     * Не используется.
     *
     * @param string $publicId Внешний id.
     *
     * @return FileRecord Не возвращается.
     */
    public function requireOwned(string $publicId): FileRecord
    {
        throw new LogicException('requireOwned must not be used');
    }

    /**
     * Не используется.
     *
     * @param string $publicId Внешний id.
     *
     * @return void Не возвращается.
     */
    public function archive(string $publicId): void
    {
        throw new LogicException('archive must not be used');
    }

    /**
     * Не используется.
     *
     * @param string $publicId Внешний id.
     * @param string $targetPath Назначение.
     *
     * @return FileRecord Не возвращается.
     */
    public function copyOwnedTo(string $publicId, string $targetPath): FileRecord
    {
        throw new LogicException('copyOwnedTo must not be used');
    }

    /**
     * Не используется.
     *
     * @param string $sourcePublicId Внешний id.
     * @param string $sourcePath Байты.
     * @param string $originalName Имя.
     *
     * @return FileRecord Не возвращается.
     */
    public function storeDerived(string $sourcePublicId, string $sourcePath, string $originalName): FileRecord
    {
        throw new LogicException('storeDerived must not be used');
    }

    /**
     * Проекция id → public_id.
     *
     * @param array<int, int> $fileIds Id.
     *
     * @return array<int, string> Карта.
     */
    public function activePublicIds(array $fileIds): array
    {
        return $this->publicIds;
    }
}
