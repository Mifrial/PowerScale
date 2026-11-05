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
     * Проекция id → public_id для живых строк.
     *
     * @param array<int, int> $fileIds Внутренние id.
     *
     * @return array<int, string> Найденные пары; архивных и отсутствующих нет.
     *
     * @throws FileInvalidException Если запрос ST недопустим.
     */
    public function activePublicIds(array $fileIds): array
    {
        $ids = $this->positiveIds($fileIds);
        if ($ids === []) {
            return [];
        }

        return $this->publicIdMap($ids);
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
     * Страница неархивных картинок актора.
     *
     * @param int $ownerId Владелец.
     * @param int $limit Размер 1..100.
     * @param int $offset Сдвиг.
     *
     * @return FilePage Страница.
     *
     * @throws FileInvalidException Если запрос ST недопустим.
     */
    public function findOwnImagePage(int $ownerId, int $limit, int $offset): FilePage
    {
        $listResult = $this->readList(ListQuery::fromOptions([
            'limit' => $limit,
            'offset' => $offset,
            'countTotal' => true,
            'sort' => ['created_at' => 'desc', 'id' => 'desc'],
            'filter' => [
                'LOGIC' => 'AND',
                ['=owner_id' => $ownerId],
                ['=is_archived' => false],
                ['=is_derived' => false],
                ['%mime' => 'image/%'],
            ],
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
        $this->pushEquals($clauses, 'mime', $pageQuery->getMime());
        $this->pushEquals($clauses, 'owner_id', $pageQuery->getOwnerId());
        $this->pushEquals($clauses, 'is_archived', $pageQuery->getIsArchived());
        $this->pushDateTime($clauses, '>=', $pageQuery->getFromUnix());
        $this->pushDateTime($clauses, '<=', $pageQuery->getToUnix());

        return $this->andGroup($clauses);
    }

    /**
     * Добавляет равенство, если значение задано.
     *
     * @param array<int, array<string, mixed>> $clauses Условия.
     * @param string $fieldName Колонка.
     * @param int|string|bool|null $value Значение.
     *
     * @return void
     */
    private function pushEquals(array &$clauses, string $fieldName, int|string|bool|null $value): void
    {
        if ($value === null) {
            return;
        }

        $clauses[] = ['=' . $fieldName => $value];
    }

    /**
     * Добавляет границу created_at, если значение задано.
     *
     * @param array<int, array<string, mixed>> $clauses Условия.
     * @param string $operator Оператор сравнения.
     * @param int|null $unix Unix UTC.
     *
     * @return void
     */
    private function pushDateTime(array &$clauses, string $operator, ?int $unix): void
    {
        if ($unix === null) {
            return;
        }

        $clauses[] = [$operator . 'created_at' => DateTime::fromUnix($unix)];
    }

    /**
     * Склеивает условия через AND.
     *
     * @param array<int, array<string, mixed>> $clauses Условия.
     *
     * @return array<string|int, mixed>|null WHERE или null.
     */
    private function andGroup(array $clauses): ?array
    {
        if ($clauses === []) {
            return null;
        }

        if (count($clauses) === 1) {
            return $clauses[0];
        }

        /**
         * @var array<string|int, mixed> $andGroup
         */
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

    /**
     * Оставляет положительные id без повторов.
     *
     * @param array<int, int> $fileIds Идентификаторы.
     *
     * @return array<int, int> id → id.
     */
    private function positiveIds(array $fileIds): array
    {
        $ids = [];
        foreach ($fileIds as $fileId) {
            if ($fileId > 0) {
                $ids[$fileId] = $fileId;
            }
        }

        return $ids;
    }

    /**
     * Читает public_id живых строк по id.
     *
     * @param array<int, int> $ids Идентификаторы.
     *
     * @return array<int, string> id → public_id.
     *
     * @throws FileInvalidException Если запрос недопустим.
     */
    private function publicIdMap(array $ids): array
    {
        $listResult = $this->readList(ListQuery::fromOptions([
            'filter' => [
                'LOGIC' => 'AND',
                ['=id' => array_values($ids)],
                ['=is_archived' => false],
            ],
            'limit' => count($ids),
        ]));

        $publicIds = [];
        foreach ($listResult->rows() as $row) {
            $fileId = $row['id'] ?? null;
            $publicId = $row['public_id'] ?? null;
            if (is_int($fileId) && is_string($publicId)) {
                $publicIds[$fileId] = $publicId;
            }
        }

        return $publicIds;
    }
}
