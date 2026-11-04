<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Repository;

use Mifrial\Core\File\Dto\FilePage;
use Mifrial\Core\File\Dto\FilePageQuery;
use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Dto\NewFile;
use Mifrial\Core\File\Exception\FileInvalidException;
use Mifrial\Core\File\Exception\FileNotFoundException;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Строки `file`.
 */
final class FileRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $fileRecords Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $fileRecords,
    ) {
    }

    /**
     * Добавляет строку.
     *
     * @param NewFile $newFile Поля.
     *
     * @return int Id.
     */
    public function add(NewFile $newFile): int
    {
        return $this->fileRecords->add($newFile->fields());
    }

    /**
     * Строка по внешнему id.
     *
     * @param string $publicId Внешний id.
     *
     * @return FileRecord|null Запись или null.
     *
     * @throws FileInvalidException Если строка неполная.
     */
    public function findByPublicId(string $publicId): ?FileRecord
    {
        if (preg_match('/^[a-f0-9]{32}$/', $publicId) !== 1) {
            return null;
        }

        return $this->findUnique(['public_id' => $publicId]);
    }

    /**
     * Строка по внутреннему id.
     *
     * @param int $fileId Идентификатор.
     *
     * @return FileRecord|null Запись или null.
     *
     * @throws FileInvalidException Если строка неполная.
     */
    public function findById(int $fileId): ?FileRecord
    {
        $row = $this->fileRecords->getById($fileId);

        return is_array($row) ? FileRecord::fromNormalized($row) : null;
    }

    /**
     * Строка по внутреннему id или ошибка.
     *
     * @param int $fileId Идентификатор.
     *
     * @return FileRecord Запись.
     *
     * @throws FileNotFoundException Если строки нет.
     * @throws FileInvalidException Если строка неполная.
     */
    public function getById(int $fileId): FileRecord
    {
        $fileRecord = $this->findById($fileId);
        if (!$fileRecord instanceof FileRecord) {
            throw new FileNotFoundException();
        }

        return $fileRecord;
    }

    /**
     * Ставит архивную отметку.
     *
     * @param int $fileId Идентификатор.
     * @param DateTime $archivedAt Момент архива.
     *
     * @return void
     */
    public function markArchived(int $fileId, DateTime $archivedAt): void
    {
        $this->fileRecords->update($fileId, [
            'is_archived' => true,
            'archived_at' => $archivedAt,
        ]);
    }

    /**
     * Страница файлов: created_at desc, id desc, COUNT фильтра.
     *
     * @param FilePageQuery $pageQuery Запрос.
     *
     * @return FilePage Страница.
     *
     * @throws FileInvalidException Если запрос ST недопустим.
     */
    public function findPage(FilePageQuery $pageQuery): FilePage
    {
        $listResult = $this->readList(ListQuery::fromOptions([
            'limit' => $pageQuery->getLimit(),
            'offset' => $pageQuery->getOffset(),
            'countTotal' => true,
            'sort' => ['created_at' => 'desc', 'id' => 'desc'],
            'filter' => $this->pageFilter($pageQuery),
        ]));
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = FileRecord::fromNormalized($row);
        }

        return new FilePage($records, $listResult->total() ?? 0);
    }

    /**
     * Ищет ровно одну строку по фильтру.
     *
     * @param array<string, mixed> $filter Фильтр.
     *
     * @return FileRecord|null Запись или null.
     *
     * @throws FileInvalidException Если совпадений больше одного.
     */
    private function findUnique(array $filter): ?FileRecord
    {
        try {
            $row = $this->fileRecords->getUnique(ListQuery::fromOptions([
                'filter' => $filter,
                'limit' => 1,
            ]));
        } catch (MapInvalidException $exception) {
            throw new FileInvalidException('File unique query is invalid', $exception);
        }

        return is_array($row) ? FileRecord::fromNormalized($row) : null;
    }

    /**
     * Дерево фильтра страницы.
     *
     * @param FilePageQuery $pageQuery Запрос.
     *
     * @return array<string|int, mixed>|null WHERE.
     */
    private function pageFilter(FilePageQuery $pageQuery): ?array
    {
        $clauses = [];
        if ($pageQuery->getMime() !== null) {
            $clauses[] = ['=mime' => $pageQuery->getMime()];
        }

        if ($pageQuery->getOwnerId() !== null) {
            $clauses[] = ['=owner_id' => $pageQuery->getOwnerId()];
        }

        if ($pageQuery->getIsArchived() !== null) {
            $clauses[] = ['=is_archived' => $pageQuery->getIsArchived()];
        }

        if ($pageQuery->getFromUnix() !== null) {
            $clauses[] = ['>=created_at' => DateTime::fromUnix($pageQuery->getFromUnix())];
        }

        if ($pageQuery->getToUnix() !== null) {
            $clauses[] = ['<=created_at' => DateTime::fromUnix($pageQuery->getToUnix())];
        }

        if ($clauses === []) {
            return null;
        }

        if (count($clauses) === 1) {
            return $clauses[0];
        }

        $andGroup = ['LOGIC' => 'AND'];
        foreach ($clauses as $clause) {
            $andGroup[] = $clause;
        }

        return $andGroup;
    }

    /**
     * Читает страницу через SmartTable.
     *
     * @param ListQuery $listQuery Запрос.
     *
     * @return ListResult Страница.
     *
     * @throws FileInvalidException Если запрос недопустим.
     */
    private function readList(ListQuery $listQuery): ListResult
    {
        try {
            return $this->fileRecords->getList($listQuery);
        } catch (MapInvalidException $exception) {
            throw new FileInvalidException('File list query is invalid', $exception);
        }
    }
}
