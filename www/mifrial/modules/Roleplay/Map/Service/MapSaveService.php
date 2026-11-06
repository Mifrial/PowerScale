<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Service\ITransactionRunner;
use Mifrial\Roleplay\Map\Dto\MapDetail;
use Mifrial\Roleplay\Map\Dto\MapRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapVersionConflictException;

/**
 * Публикация снимка карты.
 */
final class MapSaveService
{
    /**
     * Создаёт сервис.
     *
     * @param MapAccessService $accessService Доступ.
     * @param MapSaveValidator $validator Проверка снимка.
     * @param MapSaveWriter $writer Запись снимка.
     * @param ITransactionRunner $transactionRunner Единица работы.
     * @param MapDetailService $detailService Снимок после записи.
     * @param MapBlockingCheck $blockingCheck Предупреждения.
     *
     * @return void
     */
    public function __construct(
        private readonly MapAccessService $accessService,
        private readonly MapSaveValidator $validator,
        private readonly MapSaveWriter $writer,
        private readonly ITransactionRunner $transactionRunner,
        private readonly MapDetailService $detailService,
        private readonly MapBlockingCheck $blockingCheck,
    ) {
    }

    /**
     * Публикует снимок.
     *
     * @param RequestActor $actor Актор.
     * @param int|null $mapId Карта или null для новой.
     * @param int|null $expectedVersion Версия, которую загрузил клиент.
     * @param bool $force Перезаписать без сверки.
     * @param array<string|int, mixed> $payload Тело `map`.
     *
     * @return array{detail: MapDetail, warnings: array<int, array<string, mixed>>} Снимок и предупреждения.
     *
     * @throws MapInvalidException Если снимок недопустим.
     * @throws MapVersionConflictException Если версия не совпала.
     */
    public function save(
        RequestActor $actor,
        ?int $mapId,
        ?int $expectedVersion,
        bool $force,
        array $payload,
    ): array {
        $this->assertVersion($actor, $mapId, $expectedVersion, $force);
        $data = $this->validator->validate($payload);
        $savedId = $this->transactionRunner->run(
            fn (): int => $this->writer->write($actor, $mapId, $data),
        );

        $detail = $this->detailService->load($actor, $savedId);

        return [
            'detail' => $detail,
            'warnings' => $this->blockingCheck->check($detail),
        ];
    }

    /**
     * Проверяет доступ и версию.
     *
     * @param RequestActor $actor Актор.
     * @param int|null $mapId Карта.
     * @param int|null $expectedVersion Ожидаемая версия.
     * @param bool $force Перезаписать.
     *
     * @return void
     *
     * @throws MapInvalidException Если версии нет или она неверна.
     * @throws MapVersionConflictException Если версия не совпала.
     */
    private function assertVersion(
        RequestActor $actor,
        ?int $mapId,
        ?int $expectedVersion,
        bool $force,
    ): void {
        if ($mapId === null) {
            $this->accessService->requireCreate();

            return;
        }

        $mapRecord = $this->accessService->requireMapForEdit($actor, $mapId);
        if ($force) {
            return;
        }

        $this->assertExpectedVersion($expectedVersion, $mapRecord);
    }

    /**
     * Сверяет ожидаемую версию с текущей.
     *
     * @param int|null $expectedVersion Ожидаемая версия.
     * @param MapRecord $mapRecord Текущая карта.
     *
     * @return void
     *
     * @throws MapInvalidException Если версии нет.
     * @throws MapVersionConflictException Если версия не совпала.
     */
    private function assertExpectedVersion(?int $expectedVersion, MapRecord $mapRecord): void
    {
        if ($expectedVersion === null) {
            throw new MapInvalidException('map.expectedVersion is required');
        }

        if ($expectedVersion === $mapRecord->getVersion()) {
            return;
        }

        throw new MapVersionConflictException(
            $mapRecord->getVersion(),
            $mapRecord->getUpdatedAt()->toUnix(),
            $mapRecord->getUpdatedBy(),
        );
    }
}
