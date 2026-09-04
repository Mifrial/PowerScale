<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests\Fixture;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Фикстура биндинга IActionInput и отказа DateTime.
 */
final class ParameterBindingInputTarget implements IActionHandler
{
    /**
     * Плоский DTO.
     *
     * @param SampleUpdateInput $input Вход.
     *
     * @return array{id: int, namePresent: bool} Снимок.
     */
    public function handle(SampleUpdateInput $input): array
    {
        return [
            'id' => $input->id,
            'namePresent' => $input->name->isPresent(),
        ];
    }

    /**
     * Смесь скаляра и DTO.
     *
     * @param int $id Id.
     * @param SampleUpdateInput $input DTO.
     *
     * @return array{id: int} Снимок.
     */
    public function withMix(int $id, SampleUpdateInput $input): array
    {
        return ['id' => $id, 'dto' => $input->id];
    }

    /**
     * DateTime на handle не гидрируем.
     *
     * @param DateTime $moment Момент.
     *
     * @return array{unix: int} Unix.
     */
    public function withDateTime(DateTime $moment): array
    {
        return ['unix' => $moment->toUnix()];
    }
}
