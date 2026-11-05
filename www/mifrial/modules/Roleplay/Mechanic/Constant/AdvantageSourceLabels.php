<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Constant;

/**
 * Русские подписи встроенных источников преимуществ и помех.
 *
 * Реестр — единственный источник подписи для встроенных кодов. Живое правило
 * подпись не берёт отсюда: вызывающий передаёт имя правила явно.
 */
final class AdvantageSourceLabels
{
    /**
     * Код источника → подпись.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'circumstances' => 'Обстоятельства',
        'action' => 'Действие',
        'flank' => 'Фланговая атака',
        'multi_attack' => 'множественная атака',
        'distance' => 'Штраф дистанции',
        'roll' => 'Бросок',
        'manual' => 'вручную',
        'tool' => 'инструмент',
        'training' => 'тренировки',
        'preparation' => 'Подготовка',
    ];

    /**
     * Подпись встроенного источника.
     *
     * Неизвестный код возвращает null: подпись не выдумывается.
     *
     * @param string|null $code Код источника или null.
     *
     * @return string|null Подпись или null.
     */
    public static function getLabel(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::LABELS[$code] ?? null;
    }
}
