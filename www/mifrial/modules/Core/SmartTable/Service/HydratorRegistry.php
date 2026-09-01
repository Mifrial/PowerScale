<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\SmartTable\Exception\Field\UnknownHydratorException;
use Mifrial\Core\SmartTable\Interface\Field\IFieldHydrator;

/**
 * Реестр hydrator'ов JSON-полей, на boot пустой.
 */
final class HydratorRegistry
{
    /**
     * @var array<string, IFieldHydrator>
     */
    private array $hydrators = [];

    /**
     * Регистрирует hydrator по коду.
     *
     * @param string $hydratorCode Код.
     * @param IFieldHydrator $fieldHydrator Hydrator.
     *
     * @return void
     */
    public function register(string $hydratorCode, IFieldHydrator $fieldHydrator): void
    {
        $this->hydrators[$hydratorCode] = $fieldHydrator;
    }

    /**
     * Возвращает hydrator по коду.
     *
     * @param string $hydratorCode Код.
     *
     * @return IFieldHydrator Hydrator.
     *
     * @throws UnknownHydratorException Если код неизвестен.
     */
    public function get(string $hydratorCode): IFieldHydrator
    {
        if (!isset($this->hydrators[$hydratorCode])) {
            throw new UnknownHydratorException();
        }

        return $this->hydrators[$hydratorCode];
    }
}
