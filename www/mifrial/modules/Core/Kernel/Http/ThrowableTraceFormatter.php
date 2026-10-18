<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Http;

use Throwable;

/**
 * Строка стека throwable вместе с цепочкой previous.
 */
final class ThrowableTraceFormatter
{
    /**
     * Собирает класс, сообщение, файл и стек по всей цепочке.
     *
     * @param Throwable $throwable Верхнее исключение.
     *
     * @return string Текст для журнала и debug-ответа.
     */
    public function format(Throwable $throwable): string
    {
        $frames = [];
        $current = $throwable;
        while ($current instanceof Throwable) {
            $frames[] = $this->formatFrame($current);
            $current = $current->getPrevious();
        }

        return implode("\n", $frames);
    }

    /**
     * Один кадр цепочки.
     *
     * @param Throwable $throwable Исключение кадра.
     *
     * @return string Класс, сообщение, место и стек.
     */
    private function formatFrame(Throwable $throwable): string
    {
        return $throwable::class
            . ': '
            . $throwable->getMessage()
            . ' in '
            . $throwable->getFile()
            . ':'
            . $throwable->getLine()
            . "\n"
            . $throwable->getTraceAsString();
    }
}
