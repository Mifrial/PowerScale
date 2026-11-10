<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Exception;

use Throwable;

/**
 * Нет доступа к локации или её правке.
 */
final class SceneDeniedException extends SceneException
{
    /**
     * Создаёт ошибку доступа.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Scene access is denied',
        ?Throwable $previous = null,
    ) {
        parent::__construct('SCENE_DENIED', $message, $previous);
    }
}
