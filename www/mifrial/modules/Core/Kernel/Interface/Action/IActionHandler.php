<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Action;

/**
 * Маркер обработчика действия.
 *
 * Сигнатура handle задаётся самим классом: диспетчер биндит поля JSON
 * на имена и типы параметров. Метод возвращает данные успеха; доменная
 * ошибка — ActionException с кодом. Конверт ActionResponse собирает диспетчер.
 */
interface IActionHandler
{
}
