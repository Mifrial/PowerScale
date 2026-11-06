<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\Action\SaveMapInput;

/**
 * HTTP-сценарий публикации карты.
 */
final class MapSaveHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param MapAccessService $accessService Доступ.
     * @param MapSaveService $saveService Публикация.
     * @param MapViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly MapAccessService $accessService,
        private readonly MapSaveService $saveService,
        private readonly MapViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Публикует снимок.
     *
     * @param SaveMapInput $input JSON.
     *
     * @return array<string, mixed> Карта и предупреждения.
     */
    public function save(SaveMapInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $result = $this->saveService->save(
            $actor,
            $this->mapId($input),
            $this->expectedVersion($input),
            $input->force->isPresent() && $input->force->getValue() === true,
            $input->map,
        );

        return $this->viewAssembler->assembleDetail($result['detail']) + ['warnings' => $result['warnings']];
    }

    /**
     * Карта из входа.
     *
     * @param SaveMapInput $input JSON.
     *
     * @return int|null Id или null.
     */
    private function mapId(SaveMapInput $input): ?int
    {
        return $input->id->isPresent() ? $input->id->getValue() : null;
    }

    /**
     * Версия из входа.
     *
     * @param SaveMapInput $input JSON.
     *
     * @return int|null Версия или null.
     */
    private function expectedVersion(SaveMapInput $input): ?int
    {
        return $input->expectedVersion->isPresent() ? $input->expectedVersion->getValue() : null;
    }
}
