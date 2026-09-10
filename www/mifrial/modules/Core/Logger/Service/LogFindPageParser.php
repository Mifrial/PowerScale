<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Service;

use Mifrial\Core\Kernel\Value\Optional\OptionalString;
use Mifrial\Core\Logger\Dto\Action\FindLogPageInput;
use Mifrial\Core\Logger\Dto\LogPageQuery;
use Mifrial\Core\Logger\Exception\LoggerInvalidException;

/**
 * Разворачивает JSON findPage в проверенный запрос репозитория.
 */
final class LogFindPageParser
{
    private const LEVELS = ['error' => true, 'warning' => true, 'info' => true];

    /**
     * Собирает запрос страницы.
     *
     * @param FindLogPageInput $input JSON.
     *
     * @return LogPageQuery Запрос.
     *
     * @throws LoggerInvalidException Если страница или фильтр недопустимы.
     */
    public function parse(FindLogPageInput $input): LogPageQuery
    {
        $this->assertPageBounds($input->limit, $input->offset);
        $this->assertRange($input->from, $input->to);
        $source = $this->parseTextFilter($input->source, $input->sourceMode);
        $errorCode = $this->parseTextFilter($input->errorCode, $input->errorCodeMode);

        return new LogPageQuery(
            $input->limit,
            $input->offset,
            $this->parseLevel($input->level),
            $source['value'],
            $source['contains'],
            $errorCode['value'],
            $errorCode['contains'],
            $input->from,
            $input->to,
        );
    }

    /**
     * Проверяет limit и offset.
     *
     * @param int $limit Размер.
     * @param int $offset Сдвиг.
     *
     * @return void
     *
     * @throws LoggerInvalidException Если вне диапазона.
     */
    private function assertPageBounds(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new LoggerInvalidException('Log page bounds are invalid');
        }
    }

    /**
     * Проверяет from ≤ to.
     *
     * @param int|null $fromUnix Нижняя граница.
     * @param int|null $toUnix Верхняя граница.
     *
     * @return void
     *
     * @throws LoggerInvalidException Если from > to.
     */
    private function assertRange(?int $fromUnix, ?int $toUnix): void
    {
        if ($fromUnix !== null && $toUnix !== null && $fromUnix > $toUnix) {
            throw new LoggerInvalidException('Log time range is invalid');
        }
    }

    /**
     * Разбирает уровень.
     *
     * @param OptionalString $level Поле.
     *
     * @return string|null Уровень.
     *
     * @throws LoggerInvalidException Если значение чужое.
     */
    private function parseLevel(OptionalString $level): ?string
    {
        $trimmed = $this->optionalTrimmed($level);
        if ($trimmed === null) {
            return null;
        }

        if (!isset(self::LEVELS[$trimmed])) {
            throw new LoggerInvalidException('Log level is invalid');
        }

        return $trimmed;
    }

    /**
     * Разбирает строковый фильтр и mode.
     *
     * @param OptionalString $text Значение.
     * @param OptionalString $mode equals|contains.
     *
     * @return array{value: string|null, contains: bool} Фильтр.
     *
     * @throws LoggerInvalidException Если mode без строки или mode чужой.
     */
    private function parseTextFilter(OptionalString $text, OptionalString $mode): array
    {
        $trimmed = $this->optionalTrimmed($text);
        $modeValue = $this->optionalTrimmed($mode);
        if ($trimmed === null) {
            if ($modeValue !== null) {
                throw new LoggerInvalidException('Log filter mode needs a value');
            }

            return ['value' => null, 'contains' => false];
        }

        if ($modeValue === null || $modeValue === 'equals') {
            return ['value' => $trimmed, 'contains' => false];
        }

        if ($modeValue === 'contains') {
            return ['value' => $trimmed, 'contains' => true];
        }

        throw new LoggerInvalidException('Log filter mode is invalid');
    }

    /**
     * Trim optional string или null.
     *
     * @param OptionalString $field Поле.
     *
     * @return string|null Текст.
     */
    private function optionalTrimmed(OptionalString $field): ?string
    {
        if (!$field->isPresent()) {
            return null;
        }

        $value = $field->getValue();
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
