<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Repository\Support;

use Closure;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserNotFoundException;

/**
 * Ошибки строки User → USER_*, с меткой сущности в тексте.
 */
final class UserWriteGuard
{
    /**
     * Запоминает метку сущности для сообщений.
     *
     * @param string $entityLabel User, Group, Member или Avatar.
     *
     * @return void
     */
    public function __construct(
        private readonly string $entityLabel,
    ) {
    }

    /**
     * Выполняет запись и мапит ошибки строки.
     *
     * @param Closure $work Запись.
     *
     * @return mixed Результат.
     *
     * @throws UserDuplicateException Если unique.
     * @throws UserInvalidException Если поле/карта.
     * @throws UserNotFoundException Если строки нет.
     */
    public function run(Closure $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintException $exception) {
            throw new UserDuplicateException($exception);
        } catch (RowNotFoundException $exception) {
            throw new UserNotFoundException($exception);
        } catch (FieldRequiredException $exception) {
            throw new UserInvalidException($this->entityLabel . ' field is required', $exception);
        } catch (FieldInvalidException $exception) {
            throw new UserInvalidException($this->entityLabel . ' field is invalid', $exception);
        } catch (MapInvalidException $exception) {
            throw new UserInvalidException($this->entityLabel . ' map is invalid', $exception);
        }
    }
}
