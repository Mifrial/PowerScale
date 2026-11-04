<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Core\Image\Dto\ImageTransformSpec;
use Mifrial\Core\Image\Enum\FitMode;
use Mifrial\Core\Image\Enum\OutputFormat;
use Mifrial\Core\Image\Exception\ImageException;
use Mifrial\Core\Image\Interface\Service\IImageTransform;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserAvatar;
use Mifrial\Core\User\Interface\Service\IUserViews;
use Mifrial\Core\User\Repository\UserAvatarRepository;
use Throwable;

/**
 * Аватар учётки: проверка файла, производная 512×512 webp, ссылка `user_avatar`.
 */
final class UserAvatarService implements IUserAvatar
{
    private const MAX_BYTES = 2097152;

    private const MAX_SIDE = 4096;

    private const DERIVED_NAME = 'avatar.webp';

    private const ALLOWED_MIME = ['image/png', 'image/jpeg', 'image/webp'];

    /**
     * Создаёт сервис.
     *
     * @param IUserAccess $userAccess Guard.
     * @param IUserAccounts $userAccounts Учётки.
     * @param IUserViews $userViews JSON User.
     * @param UserAvatarRepository $userAvatarRepository Ссылки на файлы.
     * @param IFiles $files Порт файлов.
     * @param IImageTransform $imageTransform Производные картинки.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IUserAccounts $userAccounts,
        private readonly IUserViews $userViews,
        private readonly UserAvatarRepository $userAvatarRepository,
        private readonly IFiles $files,
        private readonly IImageTransform $imageTransform,
    ) {
    }

    /**
     * Ставит или снимает аватар текущего актора.
     *
     * @param string|null $filePublicId Загруженный файл или null, чтобы снять.
     *
     * @return array<string, mixed> JSON User после изменения.
     *
     * @throws UserInvalidException Если файл не годится в аватар.
     */
    public function setAvatar(?string $filePublicId): array
    {
        $requestActor = $this->userAccess->requireActor();
        $userId = $requestActor->getUserId();
        $previousPublicId = $this->previousAvatarPublicId($userId);

        if ($filePublicId === null) {
            $this->userAvatarRepository->clear($userId);
            $this->archiveQuietly($previousPublicId);

            return $this->userView($userId);
        }

        $sourceRecord = $this->files->requireOwned($filePublicId);
        $this->assertAvatarSource($sourceRecord);
        $derivedRecord = $this->deriveAvatar($filePublicId);
        $this->userAvatarRepository->save($userId, $derivedRecord->getId());
        if ($previousPublicId !== null && $previousPublicId !== $filePublicId) {
            $this->archiveQuietly($previousPublicId);
        }

        return $this->userView($userId);
    }

    /**
     * Проверяет, что файл годится в аватар.
     *
     * @param FileRecord $fileRecord Запись файла.
     *
     * @return void
     *
     * @throws UserInvalidException Если архив, не картинка, слишком большой или широкий.
     */
    private function assertAvatarSource(FileRecord $fileRecord): void
    {
        if ($fileRecord->isArchived()) {
            throw new UserInvalidException('Avatar file is archived');
        }

        if (!in_array($fileRecord->getMime(), self::ALLOWED_MIME, true)) {
            throw new UserInvalidException('Avatar file must be png, jpeg or webp');
        }

        if ($fileRecord->getSize() > self::MAX_BYTES) {
            throw new UserInvalidException('Avatar file is too large');
        }

        $width = $fileRecord->getWidth();
        $height = $fileRecord->getHeight();
        if ($width === null || $height === null) {
            throw new UserInvalidException('Avatar file has no image dimensions');
        }

        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw new UserInvalidException('Avatar image sides are too large');
        }
    }

    /**
     * Готовит производную и кладёт её новой строкой.
     *
     * @param string $sourcePublicId Внешний id исходника.
     *
     * @return FileRecord Строка производной.
     *
     * @throws UserInvalidException Если картинка не преобразовалась.
     */
    private function deriveAvatar(string $sourcePublicId): FileRecord
    {
        $copyPath = $this->temporaryPath();
        $derivedPath = null;

        try {
            $this->files->copyOwnedTo($sourcePublicId, $copyPath);
            $imageResult = $this->imageTransform->transform($copyPath, $this->avatarSpec());
            $derivedPath = $imageResult->getPath();

            return $this->files->storeDerived($sourcePublicId, $derivedPath, self::DERIVED_NAME);
        } catch (ImageException $imageException) {
            throw new UserInvalidException('Avatar image is invalid', $imageException);
        } finally {
            $this->removeFile($copyPath);
            if ($derivedPath !== null) {
                $this->removeFile($derivedPath);
            }
        }
    }

    /**
     * Параметры аватара: квадрат 512, webp, качество 90.
     *
     * @return ImageTransformSpec Спецификация.
     */
    private function avatarSpec(): ImageTransformSpec
    {
        return new ImageTransformSpec(512, 512, FitMode::Cover, OutputFormat::Webp, 90);
    }

    /**
     * Внешний id текущей производной.
     *
     * @param int $userId Учётка.
     *
     * @return string|null public_id или null.
     */
    private function previousAvatarPublicId(int $userId): ?string
    {
        $fileId = $this->userAvatarRepository->findFileId($userId);
        if ($fileId === null) {
            return null;
        }

        $publicIds = $this->files->activePublicIds([$fileId]);

        return $publicIds[$fileId] ?? null;
    }

    /**
     * Архивирует файл, если он ещё жив; сбои уборки не ломают ответ.
     *
     * @param string|null $publicId Внешний id.
     *
     * @return void
     */
    private function archiveQuietly(?string $publicId): void
    {
        if ($publicId === null) {
            return;
        }

        try {
            $this->files->archive($publicId);
        } catch (Throwable $throwable) {
            unset($throwable);
        }
    }

    /**
     * JSON User после изменения.
     *
     * @param int $userId Учётка.
     *
     * @return array<string, mixed> JSON.
     */
    private function userView(int $userId): array
    {
        return $this->userViews->assemble($this->userAccounts->getById($userId), null);
    }

    /**
     * Создаёт временный файл.
     *
     * @return string Абсолютный путь.
     *
     * @throws UserInvalidException Если файл не создался.
     */
    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mifrial-avatar-');
        if ($path === false) {
            throw new UserInvalidException('Cannot create a temporary avatar file');
        }

        return $path;
    }

    /**
     * Удаляет временный файл, если он есть.
     *
     * @param string $path Абсолютный путь.
     *
     * @return void
     */
    private function removeFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
