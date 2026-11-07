<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpacePatch;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceNotFoundException;
use Mifrial\Roleplay\RuleSpace\Repository\RuleSpaceRepository;

/**
 * Мета мира: sidecar, title часов и выключение.
 */
final class RuleSpaceMetaService
{
    /**
     * Создаёт сервис мета.
     *
     * @param IRules $rules Часы правила.
     * @param RuleSpaceRepository $ruleSpaceRepository Sidecar.
     * @param ISmartTableGateway $smartTableGateway TX.
     *
     * @return void
     */
    public function __construct(
        private readonly IRules $rules,
        private readonly RuleSpaceRepository $ruleSpaceRepository,
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
    }

    /**
     * Применяет присутствующие поля патча.
     *
     * @param int $spaceId Мир.
     * @param RuleSpacePatch $patch Присутствующие свойства.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если patch пуст, name пуст или published_read недопустим.
     * @throws RuleSpaceNotFoundException Если часов нет.
     */
    public function apply(int $spaceId, RuleSpacePatch $patch): void
    {
        $sidecarFields = $this->sidecarFieldsFromPatch($patch);
        (new RuleSpaceGuard())->run(
            fn (): mixed => $this->writeMeta($spaceId, $sidecarFields),
        );
    }

    /**
     * Выключает мир в продукте.
     *
     * @param int $spaceId Мир.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если запись не удалась.
     * @throws RuleSpaceNotFoundException Если часов нет.
     */
    public function deactivate(int $spaceId): void
    {
        (new RuleSpaceGuard())->run(
            function () use ($spaceId): mixed {
                $this->ruleSpaceRepository->updateBySpaceId($spaceId, ['active' => false]);

                return null;
            },
        );
    }

    /**
     * Колонки sidecar из Patch.
     *
     * @param RuleSpacePatch $patch Вход.
     *
     * @return array<string, string> name и/или description.
     *
     * @throws RuleSpaceInvalidException Если пусто или name пуст.
     */
    private function sidecarFieldsFromPatch(RuleSpacePatch $patch): array
    {
        $patchFields = $patch->fields();
        if ($patchFields === []) {
            throw new RuleSpaceInvalidException('Rule space patch is empty');
        }

        $sidecarFields = [];
        if (array_key_exists('name', $patchFields)) {
            $trimmedName = trim((string) $patchFields['name']);
            if ($trimmedName === '') {
                throw new RuleSpaceInvalidException('Rule space name must not be empty');
            }

            $sidecarFields['name'] = $trimmedName;
        }

        if (array_key_exists('description', $patchFields)) {
            $sidecarFields['description'] = (string) $patchFields['description'];
        }

        return $sidecarFields + $this->publishedReadField($patchFields);
    }

    /**
     * Колонка published_read, если ключ есть в patch.
     *
     * @param array<string, mixed> $patchFields Patch.
     *
     * @return array<string, string> Колонка или пусто.
     *
     * @throws RuleSpaceInvalidException Если значение не owner и не all.
     */
    private function publishedReadField(array $patchFields): array
    {
        if (!array_key_exists('published_read', $patchFields)) {
            return [];
        }

        $publishedRead = $patchFields['published_read'];
        if ($publishedRead !== 'owner' && $publishedRead !== 'all') {
            throw new RuleSpaceInvalidException('Rule space published read is invalid');
        }

        return ['published_read' => $publishedRead];
    }

    /**
     * Sidecar и опционально title часов.
     *
     * @param int $spaceId Мир.
     * @param array<string, string> $sidecarFields Колонки.
     *
     * @return null Пусто.
     *
     * @throws RuleSpaceInvalidException Если unique или поле.
     * @throws RuleSpaceNotFoundException Если часов нет.
     */
    private function writeMeta(int $spaceId, array $sidecarFields): mixed
    {
        $spaceName = $sidecarFields['name'] ?? null;
        if (is_string($spaceName)) {
            $this->smartTableGateway->transaction(
                function () use ($spaceId, $sidecarFields, $spaceName): int {
                    $this->rules->updateSpace($spaceId, $spaceName);
                    $this->ruleSpaceRepository->updateBySpaceId($spaceId, $sidecarFields);

                    return $spaceId;
                },
            );

            return null;
        }

        $this->ruleSpaceRepository->updateBySpaceId($spaceId, $sidecarFields);

        return null;
    }
}
