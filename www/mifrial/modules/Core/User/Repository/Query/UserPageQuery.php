<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Repository\Query;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\User\Exception\UserInvalidException;

/**
 * Общие правила страницы коллекций User: границы, фильтр active/LIKE и чтение списка.
 */
final class UserPageQuery
{
    /**
     * Создаёт построитель страницы.
     *
     * @param string $entityLabel Название сущности для сообщений об ошибке.
     *
     * @return void
     */
    public function __construct(
        private readonly string $entityLabel,
    ) {
    }

    /**
     * Границы страницы.
     *
     * @param int $limit Размер.
     * @param int $offset Сдвиг.
     *
     * @return void
     *
     * @throws UserInvalidException Если вне диапазона.
     */
    public function assertBounds(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 500 || $offset < 0) {
            throw new UserInvalidException($this->entityLabel . ' page bounds are invalid');
        }
    }

    /**
     * Фильтр страницы: active AND (OR LIKE).
     *
     * @param string|null $searchQuery Подстрока.
     * @param bool|null $active Active.
     * @param array<int, string> $likeFields Поля LIKE.
     *
     * @return array<string|int, mixed>|null Дерево или нет WHERE.
     *
     * @throws UserInvalidException Если q слишком длинный.
     */
    public function filter(?string $searchQuery, ?bool $active, array $likeFields): ?array
    {
        $clauses = [];
        if ($active !== null) {
            $clauses[] = ['=active' => $active];
        }

        $likePattern = $this->likePattern($searchQuery);
        if ($likePattern !== null) {
            $clauses[] = $this->likeOrGroup($likeFields, $likePattern);
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
     * getList ST с мапом ошибок.
     *
     * @param IOpenedRecords $records Коллекция.
     * @param ListQuery $listQuery Запрос.
     *
     * @return ListResult Страница.
     *
     * @throws UserInvalidException Если запрос недопустим.
     */
    public function readList(IOpenedRecords $records, ListQuery $listQuery): ListResult
    {
        try {
            return $records->getList($listQuery);
        } catch (MapInvalidException $exception) {
            throw new UserInvalidException($this->entityLabel . ' list query is invalid', $exception);
        }
    }

    /**
     * OR LIKE по полям.
     *
     * @param array<int, string> $likeFields Поля.
     * @param string $likePattern Шаблон.
     *
     * @return array<string|int, mixed> Группа OR.
     */
    private function likeOrGroup(array $likeFields, string $likePattern): array
    {
        $orGroup = ['LOGIC' => 'OR'];
        foreach ($likeFields as $fieldName) {
            $orGroup[] = ['%' . $fieldName => $likePattern];
        }

        return $orGroup;
    }

    /**
     * Шаблон contains или null.
     *
     * @param string|null $searchQuery Вход.
     *
     * @return string|null %q%.
     *
     * @throws UserInvalidException Если длиннее 100.
     */
    private function likePattern(?string $searchQuery): ?string
    {
        if ($searchQuery === null) {
            return null;
        }

        $trimmedQuery = trim($searchQuery);
        if ($trimmedQuery === '') {
            return null;
        }

        if (strlen($trimmedQuery) > 100) {
            throw new UserInvalidException('Search query is too long');
        }

        return '%' . addcslashes($trimmedQuery, '%_\\') . '%';
    }
}
