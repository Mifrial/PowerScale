<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

/**
 * Производный текст статьи кодекса для поиска и превью.
 */
final class LoreBodyText
{
    /**
     * Превращает HTML в плоский текст.
     *
     * @param string $html HTML редактора.
     *
     * @return string Текст без разметки.
     */
    public static function derive(string $html): string
    {
        $withoutScripts = preg_replace(
            '/<(script|style)\b[^>]*>.*?<\/\1>/is',
            ' ',
            $html,
        );
        $withBreaks = preg_replace(
            '/<br\s*\/?>|<\/(?:p|div|li|h[1-6]|blockquote|td|th|tr|details|summary)>/i',
            ' ',
            $withoutScripts ?? '',
        );
        $text = strip_tags($withBreaks ?? '');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text ?? '');
    }
}
