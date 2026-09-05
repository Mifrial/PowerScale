<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Dto;

/**
 * Пять class-string карт кластера.
 */
final class ClusterSpec
{
    /**
     * Создаёт спецификацию кластера.
     *
     * @param string $identityClass Identity.
     * @param string $versionClass Экземпляр.
     * @param string $spaceClass Пространство.
     * @param string $revisionClass Ревизия.
     * @param string $itemClass Состав.
     *
     * @return void
     */
    public function __construct(
        private readonly string $identityClass,
        private readonly string $versionClass,
        private readonly string $spaceClass,
        private readonly string $revisionClass,
        private readonly string $itemClass,
    ) {
    }

    /**
     * Класс identity.
     *
     * @return string Class-string.
     */
    public function getIdentityClass(): string
    {
        return $this->identityClass;
    }

    /**
     * Класс экземпляра.
     *
     * @return string Class-string.
     */
    public function getVersionClass(): string
    {
        return $this->versionClass;
    }

    /**
     * Класс пространства.
     *
     * @return string Class-string.
     */
    public function getSpaceClass(): string
    {
        return $this->spaceClass;
    }

    /**
     * Класс ревизии.
     *
     * @return string Class-string.
     */
    public function getRevisionClass(): string
    {
        return $this->revisionClass;
    }

    /**
     * Класс состава.
     *
     * @return string Class-string.
     */
    public function getItemClass(): string
    {
        return $this->itemClass;
    }
}
