<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Service\ITransactionRunner;
use Mifrial\Roleplay\Scene\Dto\SceneDetail;
use Mifrial\Roleplay\Scene\Dto\SceneRecord;
use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;
use Mifrial\Roleplay\Scene\Exception\SceneVersionConflictException;

/**
 * Сохранение полного снимка локации.
 */
final class SceneSaveService
{
    /**
     * Создаёт сервис.
     *
     * @param SceneAccessService $accessService Доступ.
     * @param SceneSaveValidator $validator Проверка снимка.
     * @param SceneSaveWriter $writer Запись снимка.
     * @param ITransactionRunner $transactionRunner Единица работы.
     * @param SceneDetailService $detailService Снимок после записи.
     *
     * @return void
     */
    public function __construct(
        private readonly SceneAccessService $accessService,
        private readonly SceneSaveValidator $validator,
        private readonly SceneSaveWriter $writer,
        private readonly ITransactionRunner $transactionRunner,
        private readonly SceneDetailService $detailService,
    ) {
    }

    /**
     * Сохраняет снимок.
     *
     * @param RequestActor $actor Актор.
     * @param int|null $sceneId Локация или null для новой.
     * @param int|null $expectedVersion Версия, которую загрузил клиент.
     * @param array<string|int, mixed> $payload Тело `scene`.
     *
     * @return SceneDetail Сохранённая локация.
     *
     * @throws SceneInvalidException Если снимок недопустим.
     * @throws SceneVersionConflictException Если версия не совпала.
     */
    public function save(
        RequestActor $actor,
        ?int $sceneId,
        ?int $expectedVersion,
        array $payload,
    ): SceneDetail {
        $this->assertVersion($actor, $sceneId, $expectedVersion);
        $data = $this->validator->validate($payload);
        $savedId = $this->transactionRunner->run(
            fn (): int => $this->writer->write($actor, $sceneId, $data),
        );

        return $this->detailService->load($actor, $savedId);
    }

    /**
     * Проверяет доступ и версию.
     *
     * @param RequestActor $actor Актор.
     * @param int|null $sceneId Локация.
     * @param int|null $expectedVersion Ожидаемая версия.
     *
     * @return void
     *
     * @throws SceneInvalidException Если версии нет.
     * @throws SceneVersionConflictException Если версия не совпала.
     */
    private function assertVersion(RequestActor $actor, ?int $sceneId, ?int $expectedVersion): void
    {
        if ($sceneId === null) {
            $this->accessService->requireCreate();

            return;
        }

        $sceneRecord = $this->accessService->requireSceneForEdit($actor, $sceneId);
        if ($expectedVersion === null) {
            throw new SceneInvalidException('scene.expectedVersion is required');
        }

        if ($expectedVersion === $sceneRecord->getVersion()) {
            return;
        }

        throw new SceneVersionConflictException(
            $sceneRecord->getVersion(),
            $sceneRecord->getUpdatedAt()->toUnix(),
            $sceneRecord->getUpdatedBy(),
        );
    }
}
