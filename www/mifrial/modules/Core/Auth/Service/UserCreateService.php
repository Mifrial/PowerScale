<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Auth\Dto\Action\UserCreateInput;
use Mifrial\Core\Auth\Exception\AuthDuplicateException;
use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\Auth\Repository\UserIdentityRepository;
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Interface\Service\IUserViews;

/**
 * Admin-создание учётки с паролем, без сессии созданного.
 */
final class UserCreateService
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Guard.
     * @param IUserViews $userViews JSON.
     * @param IUserAccounts $userAccounts Учётки.
     * @param IUserGroups $userGroups Группы.
     * @param UserIdentityRepository $identityRepository Identity.
     * @param PasswordPolicyService $passwordPolicyService Политика.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IUserViews $userViews,
        private readonly IUserAccounts $userAccounts,
        private readonly IUserGroups $userGroups,
        private readonly UserIdentityRepository $identityRepository,
        private readonly PasswordPolicyService $passwordPolicyService,
    ) {
    }

    /**
     * Создаёт учётку и password-identity.
     *
     * @param UserCreateInput $input JSON create.
     *
     * @return array<string, mixed> User.
     */
    public function create(UserCreateInput $input): array
    {
        $this->userAccess->requireKey('user.create');
        $groupIds = $this->resolvedGroupIds($input->groups);
        $this->passwordPolicyService->assertPasswordForGroupIds($groupIds, $input->password);
        $userId = $this->addProfile(
            $input->name,
            $input->login,
            $input->email,
            $input->surname,
            $input->nickname,
        );
        $this->identityRepository->addPassword($userId, password_hash($input->password, PASSWORD_DEFAULT));
        foreach ($groupIds as $groupId) {
            $groupRecord = $this->userGroups->getById($groupId);
            $this->userAccess->assertCanAssignBypassMembership($groupRecord->isBypass());
            $this->userGroups->addMember($userId, $groupId);
        }

        return $this->userViews->assemble($this->userAccounts->getById($userId), null);
    }

    /**
     * Пустой список → id с assign_on_register; иначе как есть.
     *
     * @param array<int, mixed> $groups Вход.
     *
     * @return array<int, int> Id.
     *
     * @throws UserInvalidException Если id не int.
     * @throws AuthInvalidException Если нет групп с флагом.
     */
    private function resolvedGroupIds(array $groups): array
    {
        if ($groups === []) {
            $assignGroupIds = $this->userGroups->getAssignOnRegisterIds();
            if ($assignGroupIds === []) {
                throw new AuthInvalidException('Authentication failed');
            }

            return $assignGroupIds;
        }

        $groupIds = [];
        foreach ($groups as $groupId) {
            if (!is_int($groupId)) {
                throw new UserInvalidException('User group id is invalid');
            }

            $groupIds[] = $groupId;
        }

        return $groupIds;
    }

    /**
     * Пишет профиль.
     *
     * @param string $name Имя.
     * @param string $login Логин.
     * @param string|null $email Почта.
     * @param string|null $surname Фамилия.
     * @param string|null $nickname Псевдоним.
     *
     * @return int Id.
     *
     * @throws AuthInvalidException Если поля битые.
     * @throws AuthDuplicateException Если unique.
     */
    private function addProfile(
        string $name,
        string $login,
        ?string $email,
        ?string $surname,
        ?string $nickname,
    ): int {
        $profile = $this->profileFields($name, $login, $email, $surname, $nickname);
        try {
            return $this->userAccounts->addFromInput($profile);
        } catch (UserDuplicateException $exception) {
            throw new AuthDuplicateException($exception);
        } catch (UserInvalidException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        }
    }

    /**
     * Поля addFromInput.
     *
     * @param string $name Имя.
     * @param string $login Логин.
     * @param string|null $email Почта.
     * @param string|null $surname Фамилия.
     * @param string|null $nickname Псевдоним.
     *
     * @return array<string, mixed> Профиль.
     */
    private function profileFields(
        string $name,
        string $login,
        ?string $email,
        ?string $surname,
        ?string $nickname,
    ): array {
        $profile = [
            'login' => $login,
            'name' => $name,
            'active' => true,
        ];
        $normalizedEmail = trim($email ?? '');
        if ($normalizedEmail !== '') {
            $profile['email'] = $normalizedEmail;
        }

        if ($surname !== null) {
            $profile['surname'] = $surname;
        }

        if ($nickname !== null) {
            $profile['nickname'] = $nickname;
        }

        return $profile;
    }
}
