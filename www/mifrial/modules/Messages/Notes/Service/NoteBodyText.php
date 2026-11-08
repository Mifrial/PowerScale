<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Service;

/**
 * Производный текст заметки для поиска и превью.
 */
final class NoteBodyText
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
        $withBreaks = preg_replace(
            '/<br\s*\/?>|<\/(?:p|div|li|h[1-6]|blockquote|td|th)>/i',
            ' ',
            $html,
        );
        $text = strip_tags($withBreaks ?? '');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text ?? '');
    }
}
