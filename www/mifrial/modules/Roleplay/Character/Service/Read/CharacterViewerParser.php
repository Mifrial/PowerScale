<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Read;

use Mifrial\Roleplay\Character\Dto\CharacterViewerRecord;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;

/**
 * Разбор списка зрителей видимости до записи.
 */
final class CharacterViewerParser
{
    /**
     * Создаёт разбор.
     *
     * @param CharacterSectionCodes $sectionCodes Коды секций.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterSectionCodes $sectionCodes,
    ) {
    }

    /**
     * Проверяет list зрителей.
     *
     * @param mixed $viewers Вход.
     *
     * @return list<CharacterViewerRecord> Гранты.
     *
     * @throws CharacterInvalidException Если форма, пустые поля или повтор userId.
     */
    public function parse(mixed $viewers): array
    {
        if (!is_array($viewers) || !array_is_list($viewers)) {
            throw new CharacterInvalidException('Character viewers must be a list');
        }

        $grants = [];
        $seenUserIds = [];
        foreach ($viewers as $viewer) {
            $grant = $this->one($viewer);
            if (isset($seenUserIds[$grant->getUserId()])) {
                throw new CharacterInvalidException('Character viewer is duplicated');
            }

            $seenUserIds[$grant->getUserId()] = true;
            $grants[] = $grant;
        }

        return $grants;
    }

    /**
     * Один зритель: userId и непустые секции.
     *
     * @param mixed $viewer Элемент.
     *
     * @return CharacterViewerRecord Грант.
     *
     * @throws CharacterInvalidException Если форма неверна.
     */
    private function one(mixed $viewer): CharacterViewerRecord
    {
        if (!is_array($viewer) || !isset($viewer['userId'], $viewer['fields']) || !is_int($viewer['userId'])) {
            throw new CharacterInvalidException('Character viewer is invalid');
        }

        if ($viewer['userId'] < 1) {
            throw new CharacterInvalidException('Character viewer user id is invalid');
        }

        $fields = $this->sectionCodes->normalize($viewer['fields']);
        if ($fields === []) {
            throw new CharacterInvalidException('Character viewer fields must not be empty');
        }

        return CharacterViewerRecord::fromNormalized([
            'user_id' => $viewer['userId'],
            'fields' => $fields,
        ]);
    }
}
