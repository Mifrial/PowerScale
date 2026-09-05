<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;

/**
 * Код мира из явного code или имени.
 */
final class RuleSpaceSlug
{
    /**
     * @var array<string, string>
     */
    private const TRANSLIT = [
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ё' => 'e',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'y',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'h',
        'ц' => 'ts',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'shch',
        'ъ' => '',
        'ы' => 'y',
        'ь' => '',
        'э' => 'e',
        'ю' => 'yu',
        'я' => 'ya',
    ];

    /**
     * Явный code или slug имени.
     *
     * @param string|null $code Поле JSON.
     * @param string $name Подпись.
     *
     * @return string Код URL.
     *
     * @throws RuleSpaceInvalidException Если slug пуст.
     */
    public function resolve(?string $code, string $name): string
    {
        if ($code !== null) {
            $trimmedCode = trim($code);
            if ($trimmedCode !== '') {
                return $trimmedCode;
            }
        }

        $slug = $this->fromName($name);
        if ($slug === '') {
            throw new RuleSpaceInvalidException('Rule space code is empty');
        }

        return $slug;
    }

    /**
     * Транслит и `[a-z0-9_-]`.
     *
     * @param string $name Подпись.
     *
     * @return string Slug или пусто.
     */
    public function fromName(string $name): string
    {
        $lowered = mb_strtolower(trim($name));
        $transliterated = '';
        $length = mb_strlen($lowered);
        for ($offset = 0; $offset < $length; ++$offset) {
            $character = mb_substr($lowered, $offset, 1);
            $transliterated .= self::TRANSLIT[$character] ?? $character;
        }

        $slug = (string) preg_replace('/[^a-z0-9_-]+/', '-', $transliterated);
        $slug = (string) preg_replace('/^-+|-+$/', '', $slug);
        $slug = (string) preg_replace('/_+/', '_', $slug);
        $slug = (string) preg_replace('/-+/', '-', $slug);

        return $slug;
    }
}
