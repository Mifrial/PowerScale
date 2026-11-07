<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Repository;

use Mifrial\Core\Auth\Dto\PasswordPolicy;
use Mifrial\Core\Auth\Exception\AuthDuplicateException;
use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\Auth\Repository\Support\AuthWriteGuard;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Каталог `auth_security_policy`.
 */
final class PasswordPolicyRepository
{
    private readonly AuthWriteGuard $writeGuard;

    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $policyRecords Строки политик.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $policyRecords,
    ) {
        $this->writeGuard = new AuthWriteGuard();
    }

    /**
     * Политика с галкой default, меньший id.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function findDefault(): ?array
    {
        return $this->policyRecords->getFirst(ListQuery::fromOptions([
            'filter' => ['is_default' => true],
            'sort' => ['id' => 'asc'],
            'limit' => 1,
        ]));
    }

    /**
     * Строка по id.
     *
     * @param int $policyId Id.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function findById(int $policyId): ?array
    {
        return $this->policyRecords->getById($policyId);
    }

    /**
     * Пишет default, если галочки ещё нет.
     *
     * @return int Id default-строки.
     *
     * @throws AuthDuplicateException Если unique.
     * @throws AuthInvalidException Если поля недопустимы.
     */
    public function ensureDefaultRow(): int
    {
        $defaultRow = $this->findDefault();
        if (is_array($defaultRow)) {
            return (int) $defaultRow['id'];
        }

        $defaults = PasswordPolicy::defaults()->toJson();

        return $this->writeGuard->run(function () use ($defaults): int {
            return $this->policyRecords->add([
                'name' => 'По умолчанию',
                'min_length' => $defaults['minLength'],
                'require_mixed_case' => $defaults['requireMixedCase'],
                'require_digit' => $defaults['requireDigit'],
                'require_special_char' => $defaults['requireSpecialChar'],
                'is_default' => true,
            ]);
        });
    }
}
