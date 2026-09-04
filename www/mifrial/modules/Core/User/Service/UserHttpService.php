<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;
use Mifrial\Core\User\Dto\Action\DeactivateUserInput;
use Mifrial\Core\User\Dto\Action\FindPageInput;
use Mifrial\Core\User\Dto\Action\UpdateUserInput;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserViews;

/**
 * HTTP-сценарии учётки: guard, фасад, JSON без lastLogin.
 */
final class UserHttpService
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Guard.
     * @param IUserViews $userViews JSON.
     * @param IUserAccounts $userAccounts Учётки.
     * @param UserMembershipSync $membershipSync Замена групп.
     * @param UserInputNormalizer $inputNormalizer Patch.
     * @param YmdDateParser $ymdDateParser Y-m-d deactivate.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IUserViews $userViews,
        private readonly IUserAccounts $userAccounts,
        private readonly UserMembershipSync $membershipSync,
        private readonly UserInputNormalizer $inputNormalizer = new UserInputNormalizer(),
        private readonly YmdDateParser $ymdDateParser = new YmdDateParser(),
    ) {
    }

    /**
     * Страница учёток.
     *
     * @param FindPageInput $input JSON findPage.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int} Страница.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     * @throws UserInvalidException Если limit/offset.
     */
    public function findPage(FindPageInput $input): array
    {
        $this->userAccess->requireKey('user.view');
        $userPage = $this->userAccounts->findPage(
            $input->limit,
            $input->offset,
            $this->optionalTrimmed($input->q),
            $this->optionalBool($input->active),
        );

        return [
            'items' => $this->userViews->assembleMany($userPage->getRecords()),
            'total' => $userPage->getTotal(),
        ];
    }

    /**
     * Одна учётка: view или self.
     *
     * @param int $id Id.
     *
     * @return array<string, mixed> User.
     */
    public function get(int $id): array
    {
        $this->userAccess->requireSelfOrKey($id, 'user.view');

        return $this->userViews->assemble($this->userAccounts->getById($id), null);
    }

    /**
     * Пачка id.
     *
     * @param array<int, mixed> $ids Id.
     *
     * @return array<int, array<string, mixed>> User[].
     *
     * @throws UserInvalidException Если список кривой.
     */
    public function getByIds(array $ids): array
    {
        $this->userAccess->requireKey('user.view');

        return $this->userViews->assembleMany($this->userAccounts->getByIds($this->intIdList($ids)));
    }

    /**
     * Обновляет профиль и опционально группы.
     *
     * @param UpdateUserInput $input JSON update.
     *
     * @return array<string, mixed> User.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function update(UpdateUserInput $input): array
    {
        $this->userAccess->assertCanUpdate($input->id, $input->groups->isPresent(), $input->active->isPresent());
        $this->userAccounts->getById($input->id);
        $profilePatch = $this->profilePatch($input);
        if ($profilePatch !== []) {
            $this->userAccounts->update($input->id, $this->inputNormalizer->patch($profilePatch));
        }

        if ($input->groups->isPresent()) {
            $this->membershipSync->replace($input->id, $input->groups->getValue());
        }

        return $this->userViews->assemble($this->userAccounts->getById($input->id), null);
    }

    /**
     * Деактивирует чужую учётку.
     *
     * @param DeactivateUserInput $input JSON deactivate.
     *
     * @return null Успех без data.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function deactivate(DeactivateUserInput $input): mixed
    {
        $this->userAccess->assertCanDeactivate($input->id);
        $this->userAccounts->getById($input->id);
        $this->userAccounts->update($input->id, $this->inputNormalizer->patch([
            'active' => false,
            'deactivate_reason' => $this->presentStringOrNull($input->reason),
            'deactivated_until' => $this->ymdDateParser->parseNullable(
                $this->presentStringOrNull($input->deactivatedUntil),
            ),
        ]));

        return null;
    }

    /**
     * Поля профиля без groups.
     *
     * @param UpdateUserInput $input JSON update.
     *
     * @return array<string, mixed> Patch-вход.
     */
    private function profilePatch(UpdateUserInput $input): array
    {
        $values = [];
        $this->putOptionalString($values, 'name', $input->name);
        $this->putOptionalString($values, 'surname', $input->surname);
        $this->putOptionalString($values, 'nickname', $input->nickname);
        $this->putOptionalString($values, 'email', $input->email);
        if ($input->active->isPresent()) {
            $values['active'] = $input->active->getValue();
            $values = $this->withClearedDeactivation($values, $values['active']);
        }

        return $values;
    }

    /**
     * Кладёт строку в patch, если ключ был.
     *
     * @param array<string, mixed> $values Patch.
     * @param string $fieldName Имя поля.
     * @param OptionalString $field Значение.
     *
     * @return void
     */
    private function putOptionalString(array &$values, string $fieldName, OptionalString $field): void
    {
        if ($field->isPresent()) {
            $values[$fieldName] = $field->getValue();
        }
    }

    /**
     * Значение ключа или null, если ключа не было.
     *
     * @param OptionalString $field Поле.
     *
     * @return string|null Строка или null.
     */
    private function presentStringOrNull(OptionalString $field): ?string
    {
        if (!$field->isPresent()) {
            return null;
        }

        return $field->getValue();
    }

    /**
     * Включение обратно обнуляет reason/until.
     *
     * @param array<string, mixed> $values Patch.
     * @param bool $active Новый active.
     *
     * @return array<string, mixed> Patch.
     */
    private function withClearedDeactivation(array $values, bool $active): array
    {
        if ($active === true) {
            $values['deactivate_reason'] = null;
            $values['deactivated_until'] = null;
        }

        return $values;
    }

    /**
     * Id как int[].
     *
     * @param array<int, mixed> $ids Вход.
     *
     * @return array<int, int> Id.
     *
     * @throws UserInvalidException Если не int.
     */
    private function intIdList(array $ids): array
    {
        $userIds = [];
        foreach ($ids as $userId) {
            if (!is_int($userId)) {
                throw new UserInvalidException('User id list is invalid');
            }

            $userIds[] = $userId;
        }

        return $userIds;
    }

    /**
     * Trim optional string или null.
     *
     * @param OptionalString $field Поле.
     *
     * @return string|null Текст.
     */
    private function optionalTrimmed(OptionalString $field): ?string
    {
        if (!$field->isPresent()) {
            return null;
        }

        return $field->getValue();
    }

    /**
     * Optional bool или null.
     *
     * @param OptionalBool $field Поле.
     *
     * @return bool|null Значение.
     */
    private function optionalBool(OptionalBool $field): ?bool
    {
        if (!$field->isPresent()) {
            return null;
        }

        return $field->getValue();
    }
}
