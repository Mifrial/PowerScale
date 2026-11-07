<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Трата жетонов концентрации в конверте удара.
 */
final class GameRunActionTokenSpend
{
    private const MODES = ['advantage', 'accuracy'];

    /**
     * Разбирает трату.
     *
     * @param mixed $value Значение.
     *
     * @return int|array<string, mixed>|null Трата.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(mixed $value): int|array|null
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $this->amount($value);
        }

        if (!is_array($value)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: token spend');
        }

        return $this->spend($value);
    }

    /**
     * Целая трата без настроек.
     *
     * @param int $amount Количество.
     *
     * @return int Количество.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function amount(int $amount): int
    {
        if ($amount < 0) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: token spend');
        }

        return $amount;
    }

    /**
     * Трата с источником и режимом.
     *
     * @param array<string, mixed> $value Значение.
     *
     * @return array<string, mixed> Трата.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function spend(array $value): array
    {
        $amount = $value['amount'] ?? null;
        if (!is_int($amount) || $amount < 0) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: token spend');
        }

        return $this->options($value, ['amount' => $amount]);
    }

    /**
     * Дополняет трату источником и режимом.
     *
     * @param array<string, mixed> $value Значение.
     * @param array<string, mixed> $spend Трата.
     *
     * @return array<string, mixed> Трата.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function options(array $value, array $spend): array
    {
        if (array_key_exists('source', $value)) {
            $spend['source'] = $this->source($value['source']);
        }

        if (array_key_exists('mode', $value)) {
            $spend['mode'] = $this->mode($value['mode']);
        }

        return $spend;
    }

    /**
     * Источник траты.
     *
     * @param mixed $source Значение.
     *
     * @return string Источник.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function source(mixed $source): string
    {
        if (!is_string($source) || $source === '') {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: token spend');
        }

        return $source;
    }

    /**
     * Режим траты.
     *
     * @param mixed $mode Значение.
     *
     * @return string Режим.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function mode(mixed $mode): string
    {
        if (!is_string($mode) || !in_array($mode, self::MODES, true)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: token spend');
        }

        return $mode;
    }
}
