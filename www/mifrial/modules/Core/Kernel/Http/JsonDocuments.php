<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Http;

use JsonException;
use Mifrial\Core\Kernel\Value\JsonObject;
use stdClass;

/**
 * Разбор JSON с сохранением объекта и массива.
 */
final class JsonDocuments
{
    /**
     * Декодирует JSON-текст.
     *
     * @param string $payload Текст.
     *
     * @return mixed Значение.
     *
     * @throws JsonException Если JSON битый.
     */
    public static function decode(string $payload): mixed
    {
        return self::preserve(json_decode($payload, false, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * JSON-объект — JsonObject, JSON-массив — список.
     *
     * @param mixed $node Узел json_decode без assoc.
     *
     * @return mixed PHP-значение.
     */
    private static function preserve(mixed $node): mixed
    {
        if ($node instanceof stdClass) {
            $members = [];
            foreach ($node as $key => $child) {
                $members[$key] = self::preserve($child);
            }

            return new JsonObject($members);
        }

        if (is_array($node)) {
            $list = [];
            foreach ($node as $child) {
                $list[] = self::preserve($child);
            }

            return $list;
        }

        return $node;
    }
}
