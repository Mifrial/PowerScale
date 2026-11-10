<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Exception;

use Throwable;

/**
 * Поля, границы или геометрия локации недопустимы.
 */
final class SceneInvalidException extends SceneException
{
    /**
     * Создаёт ошибку значений.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Scene values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('SCENE_INVALID', $message, $previous);
    }
}
