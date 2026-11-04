<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

use Mifrial\Core\File\Dto\Action\FindFilePageInput;
use Mifrial\Core\File\Dto\FilePageQuery;
use Mifrial\Core\File\Exception\FileInvalidException;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Разворачивает JSON findPage в проверенный запрос репозитория.
 */
final class FilePageQueryParser
{
    /**
     * Собирает запрос страницы.
     *
     * @param FindFilePageInput $input JSON.
     *
     * @return FilePageQuery Запрос.
     *
     * @throws FileInvalidException Если страница или фильтр недопустимы.
     */
    public function parse(FindFilePageInput $input): FilePageQuery
    {
        $this->assertPageBounds($input->limit, $input->offset);
        $this->assertRange($input->from, $input->to);

        return new FilePageQuery(
            $input->limit,
            $input->offset,
            $this->parseMime($input->mime),
            $this->parseOwnerId($input->ownerId),
            $this->parseIsArchived($input->isArchived),
            $input->from,
            $input->to,
        );
    }

    /**
     * Проверяет limit и offset.
     *
     * @param int $limit Размер.
     * @param int $offset Сдвиг.
     *
     * @return void
     *
     * @throws FileInvalidException Если вне диапазона.
     */
    private function assertPageBounds(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new FileInvalidException('File page bounds are invalid');
        }
    }

    /**
     * Проверяет from ≤ to.
     *
     * @param int|null $fromUnix Нижняя граница.
     * @param int|null $toUnix Верхняя граница.
     *
     * @return void
     *
     * @throws FileInvalidException Если from > to.
     */
    private function assertRange(?int $fromUnix, ?int $toUnix): void
    {
        if ($fromUnix !== null && $toUnix !== null && $fromUnix > $toUnix) {
            throw new FileInvalidException('File time range is invalid');
        }
    }

    /**
     * Разбирает тип.
     *
     * @param OptionalString $mime Поле.
     *
     * @return string|null Mime.
     *
     * @throws FileInvalidException Если значение не type/subtype.
     */
    private function parseMime(OptionalString $mime): ?string
    {
        if (!$mime->isPresent()) {
            return null;
        }

        $mimeValue = $mime->getValue();
        if ($mimeValue === null) {
            return null;
        }

        $trimmed = trim($mimeValue);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#', $trimmed) !== 1) {
            throw new FileInvalidException('File mime filter is invalid');
        }

        return $trimmed;
    }

    /**
     * Разбирает владельца.
     *
     * @param OptionalInt $ownerId Поле.
     *
     * @return int|null Id или null.
     *
     * @throws FileInvalidException Если id не положителен.
     */
    private function parseOwnerId(OptionalInt $ownerId): ?int
    {
        if (!$ownerId->isPresent()) {
            return null;
        }

        $ownerIdValue = $ownerId->getValue();
        if ($ownerIdValue === null) {
            return null;
        }

        if ($ownerIdValue < 1) {
            throw new FileInvalidException('File owner filter is invalid');
        }

        return $ownerIdValue;
    }

    /**
     * Разбирает флаг архива.
     *
     * @param OptionalBool $isArchived Поле.
     *
     * @return bool|null Флаг или null.
     */
    private function parseIsArchived(OptionalBool $isArchived): ?bool
    {
        return $isArchived->isPresent() ? $isArchived->getValue() : null;
    }
}
