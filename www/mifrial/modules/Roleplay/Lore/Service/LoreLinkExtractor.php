<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

// phpcs:disable MifrialCodingStandard.Metrics.MethodQuality.CognitiveComplexityTooHigh -- сборка снимка/фильтра и разбор полей остаются одним сценарием.

use DOMDocument;
use DOMElement;
use DOMXPath;
use Mifrial\Roleplay\Lore\Dto\LoreLinkValues;

/**
 * Извлечение ссылок из HTML-тела статьи для `lore_link`.
 */
final class LoreLinkExtractor
{
    private const MAX_LINKS = 2000;

    /**
     * Собирает уникальные ссылки тела.
     *
     * @param string $html HTML статьи.
     *
     * @return array<int, LoreLinkValues> Ссылки.
     */
    public function extract(string $html): array
    {
        if (trim($html) === '' || !class_exists(DOMDocument::class)) {
            return $this->collect($this->extractByRegex($html));
        }

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
            return $this->collect($this->extractByRegex($html));
        }

        return $this->collect($this->documentLinks($document));
    }

    /**
     * Ссылки из разобранного документа.
     *
     * @param DOMDocument $document Документ.
     *
     * @return array<int, LoreLinkValues> Ссылки.
     */
    private function documentLinks(DOMDocument $document): array
    {
        $xpath = new DOMXPath($document);
        $links = [];
        $this->appendAttributeLinks($xpath, '//*[@data-lore-code]', 'data-lore-code', 'lore', $links);
        $this->appendAttributeLinks($xpath, '//*[@data-rule-code]', 'data-rule-code', 'rule', $links);
        $this->appendInlineLinks($xpath, $links);

        return $links;
    }

    /**
     * Ссылки одного атрибута.
     *
     * @param DOMXPath $xpath Искатель.
     * @param string $query Запрос.
     * @param string $attribute Атрибут кода.
     * @param string $type Тип цели.
     * @param array<int, LoreLinkValues> $links Накопитель.
     *
     * @return void
     */
    private function appendAttributeLinks(
        DOMXPath $xpath,
        string $query,
        string $attribute,
        string $type,
        array &$links,
    ): void {
        foreach ($xpath->query($query) as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $code = trim($node->getAttribute($attribute));
            if ($code !== '') {
                $links[] = new LoreLinkValues($type, $code);
            }
        }
    }

    /**
     * Inline-сущности заметок и чата.
     *
     * @param DOMXPath $xpath Искатель.
     * @param array<int, LoreLinkValues> $links Накопитель.
     *
     * @return void
     */
    private function appendInlineLinks(DOMXPath $xpath, array &$links): void
    {
        $query = '//*[@data-inline-type and @data-inline-value]';
        foreach ($xpath->query($query) as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $type = trim($node->getAttribute('data-inline-type'));
            $code = trim($node->getAttribute('data-inline-value'));
            if ($type !== '' && $code !== '') {
                $links[] = new LoreLinkValues($type, $code);
            }
        }
    }

    /**
     * Запасной разбор регулярными выражениями.
     *
     * @param string $html HTML статьи.
     *
     * @return array<int, LoreLinkValues> Ссылки.
     */
    private function extractByRegex(string $html): array
    {
        $links = [];
        $this->appendRegexLinks($html, 'data-lore-code', 'lore', $links);
        $this->appendRegexLinks($html, 'data-rule-code', 'rule', $links);
        $this->appendRegexInlineLinks($html, $links);

        return $links;
    }

    /**
     * Ссылки одного атрибута регулярным выражением.
     *
     * @param string $html HTML статьи.
     * @param string $attribute Атрибут кода.
     * @param string $type Тип цели.
     * @param array<int, LoreLinkValues> $links Накопитель.
     *
     * @return void
     */
    private function appendRegexLinks(string $html, string $attribute, string $type, array &$links): void
    {
        $pattern = '/\b' . preg_quote($attribute, '/') . '\s*=\s*["\']([^"\']*)["\']/i';
        if (preg_match_all($pattern, $html, $matches) !== false) {
            foreach ($matches[1] as $code) {
                $code = trim($code);
                if ($code !== '') {
                    $links[] = new LoreLinkValues($type, $code);
                }
            }
        }
    }

    /**
     * Inline-сущности регулярным выражением.
     *
     * @param string $html HTML статьи.
     * @param array<int, LoreLinkValues> $links Накопитель.
     *
     * @return void
     */
    private function appendRegexInlineLinks(string $html, array &$links): void
    {
        $patterns = [
            '/<[^>]*data-inline-type\s*=\s*["\']([^"\']*)["\'][^>]*data-inline-value\s*=\s*["\']([^"\']*)["\'][^>]*>/i',
            '/<[^>]*data-inline-value\s*=\s*["\']([^"\']*)["\'][^>]*data-inline-type\s*=\s*["\']([^"\']*)["\'][^>]*>/i',
        ];
        foreach ($patterns as $index => $pattern) {
            if (preg_match_all($pattern, $html, $matches) === false) {
                continue;
            }

            $this->appendInlineMatches($matches, $index === 0, $links);
        }
    }

    /**
     * Inline-сущности из совпадений регулярного выражения.
     *
     * @param array<int, array<int, string>> $matches Совпадения.
     * @param bool $typeFirst Тип идёт первым атрибутом.
     * @param array<int, LoreLinkValues> $links Накопитель.
     *
     * @return void
     */
    private function appendInlineMatches(array $matches, bool $typeFirst, array &$links): void
    {
        foreach ($matches[0] as $matchIndex => $matchedTag) {
            unset($matchedTag);
            $first = trim($matches[1][$matchIndex] ?? '');
            $second = trim($matches[2][$matchIndex] ?? '');
            $type = $typeFirst ? $first : $second;
            $code = $typeFirst ? $second : $first;
            if ($type === '' || $code === '') {
                continue;
            }

            $links[] = new LoreLinkValues($type, $code);
        }
    }

    /**
     * Дедупликация и потолок ссылок.
     *
     * @param array<int, LoreLinkValues> $links Ссылки.
     *
     * @return array<int, LoreLinkValues> Уникальные ссылки.
     */
    private function collect(array $links): array
    {
        $unique = [];
        foreach ($links as $linkValues) {
            if (mb_strlen($linkValues->getCode(), 'UTF-8') > 255) {
                continue;
            }

            if (!LoreCode::isValidLinkType($linkValues->getType())) {
                continue;
            }

            $key = $linkValues->getType() . "\0" . $linkValues->getCode();
            if (isset($unique[$key])) {
                continue;
            }

            $unique[$key] = $linkValues;
            if (count($unique) >= self::MAX_LINKS) {
                break;
            }
        }

        return array_values($unique);
    }
}
