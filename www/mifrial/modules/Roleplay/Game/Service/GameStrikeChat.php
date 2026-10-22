<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Тексты и бирки чата удара.
 */
final class GameStrikeChat
{
    /**
     * Создаёт билдеры.
     *
     * @param GameNpcRepository $npcs NPC.
     * @param GameStrikeRules $rules Карточки удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameNpcRepository $npcs,
        private readonly GameStrikeRules $rules,
    ) {
    }

    /**
     * Токен участника.
     *
     * @param mixed $kind Вид.
     * @param mixed $id Идентификатор.
     *
     * @return string Токен.
     */
    public function getEntityToken(mixed $kind, mixed $id): string
    {
        $prefix = $kind === 'npc' ? 'npc' : 'character';
        $numericId = is_int($id) ? $id : (is_string($id) && ctype_digit($id) ? (int) $id : 0);
        $name = $this->getEntityName(is_string($kind) ? $kind : 'character', $numericId);

        return '[[' . $prefix . ':' . $numericId . ',' . $name . ']]';
    }

    /**
     * Спикер сообщения.
     *
     * @param mixed $kind Вид.
     * @param mixed $id Идентификатор.
     *
     * @return array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}
     */
    public function getSpeakerOf(mixed $kind, mixed $id): array
    {
        $numericId = is_int($id) ? $id : 0;
        $name = $this->getEntityName(is_string($kind) ? $kind : 'character', $numericId);
        if ($kind === 'npc') {
            return ['kind' => 'npc', 'npcId' => $numericId, 'npcName' => $name];
        }

        return ['kind' => 'character', 'characterId' => $numericId, 'characterName' => $name];
    }

    /**
     * Имя участника.
     *
     * @param string $kind Вид.
     * @param int $id Идентификатор.
     *
     * @return string Имя.
     */
    public function getEntityName(string $kind, int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        try {
            if ($kind === 'npc') {
                return $this->npcs->getById($id)->getName();
            }

            return $this->rules->characterName($id);
        } catch (GameNotFoundException | CharacterNotFoundException) {
            return (string) $id;
        }
    }

    /**
     * Текст проверки истощения.
     *
     * @param string $target Цель.
     * @param int $rating Рейтинг.
     * @param string $outcome Исход.
     *
     * @return string Текст.
     */
    public function getExhaustionText(string $target, int $rating, string $outcome): string
    {
        $label = match ($outcome) {
            'weakness' => 'Слабость',
            'disabled' => 'Обессилен',
            'unconscious' => 'Потеря сознания',
            default => 'без Упадка сил',
        };
        if ($outcome === 'clear') {
            $shown = ($rating >= 0 ? '+' : '') . $rating;

            return $target . ' выдерживает истощение (РУ ' . $shown . ') — ' . $label . '.';
        }

        return $target . ' не выдерживает истощение (РУ ' . $rating . ') — ' . $label . '.';
    }

    /**
     * Текст и вложение проверки на увечье.
     *
     * @param string $target Цель.
     * @param array<string, mixed> $roll Бросок проверки.
     * @param string $damageTypeName Вид урона.
     *
     * @return array{roll: array<string, mixed>, text: string, details: array<string, mixed>}
     */
    public function getMaimPayload(string $target, array $roll, string $damageTypeName): array
    {
        $rating = is_int($roll['rating'] ?? null) ? $roll['rating'] : 0;

        return [
            'roll' => $roll,
            'text' => $this->getMaimText($target, $rating),
            'details' => [
                'injury' => [
                    'strength' => $rating < 0 ? -$rating : 0,
                    'permanent' => false,
                    'temporary' => true,
                    'lethal' => false,
                    'disfiguring' => false,
                    'rating' => $rating,
                    'difficulty' => is_array($roll['difficulty'] ?? null)
                        ? ($roll['difficulty']['base'] ?? null)
                        : null,
                ],
                'damageTypeName' => $damageTypeName,
            ],
        ];
    }

    /**
     * Текст проверки на увечье.
     *
     * @param string $target Цель.
     * @param int $rating Рейтинг.
     *
     * @return string Текст.
     */
    public function getMaimText(string $target, int $rating): string
    {
        if ($rating >= 0) {
            return $target . ' не получает увечье.';
        }

        return $target . ' получает временное увечье с силой ' . (-$rating) . '.';
    }

    /**
     * Перечисление имён.
     *
     * @param array<int, string> $names Имена.
     *
     * @return string Текст.
     */
    public function getJoinNames(array $names): string
    {
        if (count($names) < 2) {
            return $names[0] ?? '';
        }

        $last = array_pop($names);

        return implode(', ', $names) . ' и ' . $last;
    }

    /**
     * Реакция цели.
     *
     * @param string $name Имя цели.
     * @param string $reaction Реакция.
     * @param string|null $reactionCode Код карточки.
     * @param int $actionAp Стоимость.
     *
     * @return string Текст.
     */
    public function getReactionBit(string $name, string $reaction, ?string $reactionCode, int $actionAp): string
    {
        if ($reaction === 'ignore') {
            return $name . ' не реагирует';
        }

        $token = $reactionCode !== null ? '[[rule:' . $reactionCode . ']]' : $reaction;

        return $name . ' пытается совершить ' . $token . ' за ' . $actionAp . 'ОД';
    }

    /**
     * Вид урона строкой.
     *
     * @param GameRecord $game Игра.
     * @param mixed $itemRuleCode Код предмета.
     * @param mixed $profileType Тип профиля.
     * @param mixed $profileIndex Индекс профиля.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return string Вид урона.
     */
    public function getDamageTypeBit(
        GameRecord $game,
        mixed $itemRuleCode,
        mixed $profileType,
        mixed $profileIndex,
        CharacterRuleSlice $slice,
    ): string {
        if (!is_string($itemRuleCode) || !is_string($profileType) || !is_int($profileIndex)) {
            return 'безымянный';
        }

        $code = $this->rules->damageTypeCode(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
        );
        $name = $code === null ? null : $slice->findLive($code)?->getName();

        return is_string($name) && $name !== '' ? mb_strtolower($name) : 'безымянный';
    }
}
