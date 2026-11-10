<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Exception;

use Throwable;

/**
 * Локации, поля или элемента нет.
 */
final class SceneNotFoundException extends SceneException
{
    /**
     * Создаёт ошибку отсутствия.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Scene was not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('SCENE_NOT_FOUND', $message, $previous);
    }
}
