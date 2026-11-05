<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Core\Image\Dto\ImageCrop;
use Mifrial\Core\Image\Dto\ImageTransformSpec;
use Mifrial\Core\Image\Enum\FitMode;
use Mifrial\Core\Image\Enum\OutputFormat;
use Mifrial\Core\Image\Exception\ImageException;
use Mifrial\Core\Image\Interface\Service\IImageTransform;
use Mifrial\Core\User\Dto\UserAvatarRecord;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserAvatar;
use Mifrial\Core\User\Interface\Service\IUserViews;
use Mifrial\Core\User\Repository\UserAvatarRepository;
use Throwable;

/**
 * Аватар учётки: выбранный круг, производная 256×256 webp и ссылка `user_avatar`.
 */
final class UserAvatarService implements IUserAvatar
{
    private const MAX_BYTES = 2097152;

    private const MAX_SIDE = 4096;

    private const DERIVED_SIDE = 256;

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
     * @param string|null $filePublicId Исходник или null, чтобы снять.
     * @param int|null $cropX Левый край кропа.
     * @param int|null $cropY Верхний край кропа.
     * @param int|null $cropSize Сторона квадрата кропа.
     *
     * @return array<string, mixed> JSON User после изменения.
     *
     * @throws UserInvalidException Если файл или кроп не годятся в аватар.
     */
    public function setAvatar(?string $filePublicId, ?int $cropX, ?int $cropY, ?int $cropSize): array
    {
        $requestActor = $this->userAccess->requireActor();
        $userId = $requestActor->getUserId();
        $previous = $this->userAvatarRepository->find($userId);

        if ($filePublicId === null) {
            $this->clearAvatar($userId, $previous);

            return $this->userView($userId);
        }

        $sourceRecord = $this->files->requireOwned($filePublicId);
        $this->assertAvatarSource($sourceRecord);
        $crop = $this->cropSpec($cropX, $cropY, $cropSize);
        $derivedRecord = $this->deriveAvatar($filePublicId, $crop);
        $this->userAvatarRepository->save(
            $userId,
            $derivedRecord->getId(),
            $sourceRecord->getId(),
            $crop?->getX(),
            $crop?->getY(),
            $crop?->getWidth(),
        );
        $this->archiveQuietly($this->publicIdOf($previous?->getFileId()));

        return $this->userView($userId);
    }

    /**
     * Текущий источник, кроп и производная актора.
     *
     * @return array{avatar: string|null, source: array<string, mixed>|null, crop: array<string, int>|null} Состояние.
     */
    public function avatarState(): array
    {
        $requestActor = $this->userAccess->requireActor();
        $avatarRecord = $this->userAvatarRepository->find($requestActor->getUserId());
        if ($avatarRecord === null) {
            return ['avatar' => null, 'source' => null, 'crop' => null];
        }

        return [
            'avatar' => $this->publicIdOf($avatarRecord->getFileId()),
            'source' => $this->sourceView($avatarRecord->getSourceFileId()),
            'crop' => $this->cropView($avatarRecord),
        ];
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
     * Проверяет и собирает квадрат кропа; все null — центр-кроп в Image.
     *
     * @param int|null $cropX Левый край.
     * @param int|null $cropY Верхний край.
     * @param int|null $cropSize Сторона.
     *
     * @return ImageCrop|null Кроп или null.
     *
     * @throws UserInvalidException Если кроп неполный или вне диапазона.
     */
    private function cropSpec(?int $cropX, ?int $cropY, ?int $cropSize): ?ImageCrop
    {
        if ($cropX === null && $cropY === null && $cropSize === null) {
            return null;
        }

        if ($cropX === null || $cropY === null || $cropSize === null) {
            throw new UserInvalidException('Avatar crop is incomplete');
        }

        $this->assertCropBounds($cropX, $cropY, $cropSize);

        return new ImageCrop($cropX, $cropY, $cropSize, $cropSize);
    }

    /**
     * Проверяет пределы кропа.
     *
     * @param int $cropX Левый край.
     * @param int $cropY Верхний край.
     * @param int $cropSize Сторона.
     *
     * @return void
     *
     * @throws UserInvalidException Если кроп вне диапазона.
     */
    private function assertCropBounds(int $cropX, int $cropY, int $cropSize): void
    {
        if ($cropX < 0 || $cropY < 0) {
            throw new UserInvalidException('Avatar crop offset is negative');
        }

        if ($cropSize < 1 || $cropSize > self::MAX_SIDE) {
            throw new UserInvalidException('Avatar crop size is out of bounds');
        }
    }

    /**
     * Готовит производную и кладёт её новой строкой.
     *
     * @param string $sourcePublicId Внешний id исходника.
     * @param ImageCrop|null $crop Выбранный круг или null для центра.
     *
     * @return FileRecord Строка производной.
     *
     * @throws UserInvalidException Если картинка не преобразовалась.
     */
    private function deriveAvatar(string $sourcePublicId, ?ImageCrop $crop): FileRecord
    {
        $copyPath = $this->temporaryPath();
        $derivedPath = null;

        try {
            $this->files->copyOwnedTo($sourcePublicId, $copyPath);
            $imageResult = $this->imageTransform->transform($copyPath, $this->avatarSpec($crop));
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
     * Параметры аватара: квадрат 256, webp, качество 90.
     *
     * @param ImageCrop|null $crop Выбранный круг.
     *
     * @return ImageTransformSpec Спецификация.
     */
    private function avatarSpec(?ImageCrop $crop): ImageTransformSpec
    {
        return new ImageTransformSpec(
            self::DERIVED_SIDE,
            self::DERIVED_SIDE,
            FitMode::Cover,
            OutputFormat::Webp,
            90,
            $crop,
        );
    }

    /**
     * Снимает строку и архивирует прежнюю производную.
     *
     * @param int $userId Учётка.
     * @param UserAvatarRecord|null $previous Прежняя строка.
     *
     * @return void
     */
    private function clearAvatar(int $userId, ?UserAvatarRecord $previous): void
    {
        $this->userAvatarRepository->clear($userId);
        if ($previous !== null) {
            $this->archiveQuietly($this->publicIdOf($previous->getFileId()));
        }
    }

    /**
     * JSON источника для диалога кропа.
     *
     * @param int|null $sourceFileId Исходник.
     *
     * @return array<string, mixed>|null Поля или null.
     */
    private function sourceView(?int $sourceFileId): ?array
    {
        if ($sourceFileId === null) {
            return null;
        }

        $publicId = $this->publicIdOf($sourceFileId);
        if ($publicId === null) {
            return null;
        }

        $sourceRecord = $this->files->requireOwned($publicId);

        return [
            'publicId' => $publicId,
            'originalName' => $sourceRecord->getOriginalName(),
            'width' => $sourceRecord->getWidth(),
            'height' => $sourceRecord->getHeight(),
        ];
    }

    /**
     * JSON кропа для диалога.
     *
     * @param UserAvatarRecord $avatarRecord Строка.
     *
     * @return array<string, int>|null Кроп или null.
     */
    private function cropView(UserAvatarRecord $avatarRecord): ?array
    {
        $cropX = $avatarRecord->getCropX();
        $cropY = $avatarRecord->getCropY();
        $cropSize = $avatarRecord->getCropSize();
        if ($cropX === null || $cropY === null || $cropSize === null) {
            return null;
        }

        return ['x' => $cropX, 'y' => $cropY, 'size' => $cropSize];
    }

    /**
     * public_id живого файла по внутреннему id.
     *
     * @param int|null $fileId Идентификатор.
     *
     * @return string|null public_id или null.
     */
    private function publicIdOf(?int $fileId): ?string
    {
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
