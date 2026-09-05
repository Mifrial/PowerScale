<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Keyword\Dto\Action\CreateKeywordInput;
use Mifrial\Roleplay\Keyword\Dto\Action\UpdateKeywordInput;
use Mifrial\Roleplay\Keyword\Dto\KeywordRecord;
use Mifrial\Roleplay\Keyword\Exception\KeywordInvalidException;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;

/**
 * HTTP-сценарии справочника признаков: актор, фасад, JSON.
 */
final class KeywordHttpService
{
    private const LIST_LIMIT = 500;

    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Guard.
     * @param IKeywords $keywords Фасад.
     * @param KeywordViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IKeywords $keywords,
        private readonly KeywordViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Каталог признаков.
     *
     * @return array<int, array<string, mixed>> Keyword[].
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws KeywordInvalidException Если строк больше капа.
     */
    public function getList(): array
    {
        $this->userAccess->requireActor();
        $records = $this->keywords->getList();
        if (count($records) > self::LIST_LIMIT) {
            throw new KeywordInvalidException('Keyword catalog exceeds list cap');
        }

        $views = [];
        foreach ($records as $keywordRecord) {
            $views[] = $this->viewAssembler->assemble($keywordRecord);
        }

        return $views;
    }

    /**
     * Признак по id.
     *
     * @param int $id Keyword id.
     *
     * @return array<string, mixed> Keyword.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function get(int $id): array
    {
        $this->userAccess->requireActor();

        return $this->viewAssembler->assemble($this->keywords->get($id));
    }

    /**
     * Создаёт признак.
     *
     * @param CreateKeywordInput $input JSON.
     *
     * @return array<string, mixed> Keyword.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function create(CreateKeywordInput $input): array
    {
        $this->userAccess->requireKey(KeywordPermissionKeys::CREATE);
        $keywordId = $this->keywords->add($input->code, $input->name, $input->description);

        return $this->viewAssembler->assemble($this->keywords->get($keywordId));
    }

    /**
     * Пишет name и/или description.
     *
     * @param UpdateKeywordInput $input JSON.
     *
     * @return array<string, mixed> Keyword.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     * @throws KeywordInvalidException Если нет полей или JSON null.
     */
    public function update(UpdateKeywordInput $input): array
    {
        $this->userAccess->requireKey(KeywordPermissionKeys::EDIT);
        if (!$input->name->isPresent() && !$input->description->isPresent()) {
            throw new KeywordInvalidException('Keyword update needs a field');
        }

        $current = $this->keywords->get($input->id);
        $this->keywords->update(
            $input->id,
            $this->mergedName($input, $current),
            $this->mergedDescription($input, $current),
        );

        return $this->viewAssembler->assemble($this->keywords->get($input->id));
    }

    /**
     * Выключает признак.
     *
     * @param int $id Keyword id.
     *
     * @return null Успех.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function deactivate(int $id): mixed
    {
        $this->userAccess->requireKey(KeywordPermissionKeys::DELETE);
        $this->keywords->deactivate($id);

        return null;
    }

    /**
     * Имя patch или текущее.
     *
     * @param UpdateKeywordInput $input JSON.
     * @param KeywordRecord $current Строка.
     *
     * @return string Имя.
     *
     * @throws KeywordInvalidException Если JSON null.
     */
    private function mergedName(UpdateKeywordInput $input, KeywordRecord $current): string
    {
        if (!$input->name->isPresent()) {
            return $current->getName();
        }

        return $this->presentString($input->name->getValue());
    }

    /**
     * Описание patch или текущее.
     *
     * @param UpdateKeywordInput $input JSON.
     * @param KeywordRecord $current Строка.
     *
     * @return string Текст.
     *
     * @throws KeywordInvalidException Если JSON null.
     */
    private function mergedDescription(UpdateKeywordInput $input, KeywordRecord $current): string
    {
        if (!$input->description->isPresent()) {
            return $current->getDescription();
        }

        return $this->presentString($input->description->getValue());
    }

    /**
     * Строка JSON, не null.
     *
     * @param string|null $value JSON.
     *
     * @return string Строка.
     *
     * @throws KeywordInvalidException Если null.
     */
    private function presentString(?string $value): string
    {
        if ($value === null) {
            throw new KeywordInvalidException('Keyword patch field is invalid');
        }

        return $value;
    }
}
