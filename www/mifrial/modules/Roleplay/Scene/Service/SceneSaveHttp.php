<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Roleplay\Scene\Dto\Action\SaveSceneInput;

/**
 * HTTP-сценарий сохранения локации.
 */
final class SceneSaveHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param SceneAccessService $accessService Доступ.
     * @param SceneSaveService $saveService Сохранение.
     * @param SceneViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly SceneAccessService $accessService,
        private readonly SceneSaveService $saveService,
        private readonly SceneViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Сохраняет снимок.
     *
     * @param SaveSceneInput $input JSON.
     *
     * @return array<string, mixed> Локация.
     */
    public function save(SaveSceneInput $input): array
    {
        $actor = $this->accessService->requireActor();
        $detail = $this->saveService->save(
            $actor,
            $this->sceneId($input),
            $this->expectedVersion($input),
            $input->scene,
        );

        return $this->viewAssembler->assembleDetail($detail);
    }

    /**
     * Локация из входа.
     *
     * @param SaveSceneInput $input JSON.
     *
     * @return int|null Id или null.
     */
    private function sceneId(SaveSceneInput $input): ?int
    {
        return $input->id->isPresent() ? $input->id->getValue() : null;
    }

    /**
     * Версия из входа.
     *
     * @param SaveSceneInput $input JSON.
     *
     * @return int|null Версия или null.
     */
    private function expectedVersion(SaveSceneInput $input): ?int
    {
        return $input->expectedVersion->isPresent() ? $input->expectedVersion->getValue() : null;
    }
}
