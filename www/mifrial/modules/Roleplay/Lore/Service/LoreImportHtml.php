<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Очистка импортируемого HTML от опасных тегов и неподдерживаемых ссылок.
 */
final class LoreImportHtml
{
    private const DANGEROUS_TAGS = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'form',
        'input',
        'button',
        'link',
        'meta',
        'base',
    ];

    /**
     * Чистит тело статьи.
     *
     * @param string $html HTML файла.
     *
     * @return array{html: string, warnings: array<int, string>} HTML и коды предупреждений.
     */
    public function clean(string $html): array
    {
        if (trim($html) === '') {
            return ['html' => $html, 'warnings' => []];
        }

        return class_exists(DOMDocument::class)
            ? $this->cleanWithDom($html)
            : $this->cleanWithRegex($html);
    }

    /**
     * Чистка через DOM.
     *
     * @param string $html HTML файла.
     *
     * @return array{html: string, warnings: array<int, string>} HTML и коды предупреждений.
     */
    private function cleanWithDom(string $html): array
    {
        $document = new DOMDocument();
        $loaded = false;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"?><html><body>' . $html . '</body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($loaded !== true) {
            return $this->cleanWithRegex($html);
        }

        $xpath = new DOMXPath($document);
        $warnings = [];
        foreach (self::DANGEROUS_TAGS as $tag) {
            $this->removeNodes($xpath->query('//' . $tag), $warnings, 'dangerous-tag');
        }

        $this->removeNodes($xpath->query('//img'), $warnings, 'img');
        $this->cleanAttributes($xpath, $warnings);

        return ['html' => $this->bodyHtml($document), 'warnings' => array_values($warnings)];
    }

    /**
     * Убирает href и обработчики событий.
     *
     * @param DOMXPath $xpath Искатель.
     * @param array<string, string> $warnings Накопитель.
     *
     * @return void
     */
    private function cleanAttributes(DOMXPath $xpath, array &$warnings): void
    {
        foreach ($xpath->query('//*[@href]') as $node) {
            if ($node instanceof DOMElement) {
                $node->removeAttribute('href');
                $warnings['href'] = 'href';
            }
        }

        foreach ($xpath->query('//@*') as $attribute) {
            $attributeName = $attribute->nodeName;
            $ownerElement = $attribute->parentNode;
            if (strpos($attributeName, 'on') === 0 && $ownerElement instanceof DOMElement) {
                $ownerElement->removeAttribute($attributeName);
                $warnings['event-handler'] = 'event-handler';
            }
        }
    }

    /**
     * Удаляет узлы и пишет предупреждение.
     *
     * @param mixed $nodes Узлы XPath.
     * @param array<string, string> $warnings Накопитель.
     * @param string $code Код предупреждения.
     *
     * @return void
     */
    private function removeNodes(mixed $nodes, array &$warnings, string $code): void
    {
        if (!is_iterable($nodes)) {
            return;
        }

        $removed = false;
        foreach ($nodes as $node) {
            if ($node instanceof DOMNode && $node->parentNode !== null) {
                $node->parentNode->removeChild($node);
                $removed = true;
            }
        }

        if ($removed) {
            $warnings[$code] = $code;
        }
    }

    /**
     * HTML содержимого body.
     *
     * @param DOMDocument $document Документ.
     *
     * @return string HTML.
     */
    private function bodyHtml(DOMDocument $document): string
    {
        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return '';
        }

        $html = '';
        foreach ($body->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }

    /**
     * Чистка регулярными выражениями без ext-dom.
     *
     * @param string $html HTML файла.
     *
     * @return array{html: string, warnings: array<int, string>} HTML и коды предупреждений.
     */
    private function cleanWithRegex(string $html): array
    {
        $warnings = [];
        $cleaned = preg_replace(
            '/<(script|style|iframe|object|embed|form|input|button|link|meta|base)\b[^>]*>.*?<\/\1>/is',
            '',
            $html,
        );
        $tagsPattern = '/<(script|style|iframe|object|embed|form|input|button|link|meta|base)\b[^>]*\/?>/i';
        $cleaned = preg_replace($tagsPattern, '', $cleaned ?? '');
        if (preg_match('/<img\b/i', $cleaned ?? '') === 1) {
            $warnings['img'] = 'img';
        }

        $cleaned = preg_replace('/<img\b[^>]*>/i', '', $cleaned ?? '');
        if (preg_match('/\bhref\s*=/i', $cleaned ?? '') === 1) {
            $warnings['href'] = 'href';
        }

        $cleaned = preg_replace('/\bhref\s*=\s*"[^"]*"/i', '', $cleaned ?? '');
        $cleaned = preg_replace("/\bhref\s*=\s*'[^']*'/i", '', $cleaned ?? '');
        if (preg_match('/\bon[a-z]+\s*=/i', $cleaned ?? '') === 1) {
            $warnings['event-handler'] = 'event-handler';
        }

        $cleaned = preg_replace('/\bon[a-z]+\s*=\s*"[^"]*"/i', '', $cleaned ?? '');
        $cleaned = preg_replace("/\bon[a-z]+\s*=\s*'[^']*'/i", '', $cleaned ?? '');

        return ['html' => $cleaned ?? '', 'warnings' => array_values($warnings)];
    }
}
