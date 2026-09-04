<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Repository;

use Closure;
use Mifrial\Core\Auth\Exception\AuthDuplicateException;
use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Связи `auth_group_security_policy`.
 */
final class GroupSecurityPolicyRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $bindingRecords Строки связей.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $bindingRecords,
    ) {
    }

    /**
     * Id политик для набора групп.
     *
     * @param array<int, int> $groupIds Группы.
     *
     * @return array<int, int> policy id, уникальные.
     */
    public function findPolicyIdsByGroupIds(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        $policyIds = [];
        $offset = 0;
        do {
            $listResult = $this->bindingRecords->getList(ListQuery::fromOptions([
                'filter' => ['group_id' => array_values($groupIds)],
                'limit' => 500,
                'offset' => $offset,
            ]));
            $pageRows = $listResult->rows();
            foreach ($pageRows as $bindingRow) {
                $policyIds[(int) $bindingRow['policy_id']] = (int) $bindingRow['policy_id'];
            }

            $offset += 500;
        } while (count($pageRows) === 500);

        return array_values($policyIds);
    }

    /**
     * Пишет связь, если для группы ещё нет.
     *
     * @param int $groupId Группа.
     * @param int $policyId Политика.
     *
     * @return void
     *
     * @throws AuthDuplicateException Если unique.
     * @throws AuthInvalidException Если поля недопустимы.
     */
    public function bindIfAbsent(int $groupId, int $policyId): void
    {
        $existing = $this->bindingRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['group_id' => $groupId],
            'limit' => 1,
        ]));
        if (is_array($existing)) {
            return;
        }

        $this->write(function () use ($groupId, $policyId): mixed {
            $this->bindingRecords->add([
                'group_id' => $groupId,
                'policy_id' => $policyId,
            ]);

            return null;
        });
    }

    /**
     * Мапит ошибки строки.
     *
     * @param Closure $work Запись.
     *
     * @return mixed Результат.
     *
     * @throws AuthDuplicateException Если unique.
     * @throws AuthInvalidException Если поле/карта/нет строки.
     */
    private function write(Closure $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintException $exception) {
            throw new AuthDuplicateException($exception);
        } catch (RowNotFoundException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        } catch (FieldRequiredException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        } catch (FieldInvalidException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        } catch (MapInvalidException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        }
    }
}
