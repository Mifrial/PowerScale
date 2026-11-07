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
            return $kind === 'npc'
                ? $this->npcs->getById($id)->getName()
                : $this->rules->data->characterName($id);
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
            'text' => $this->maimText($target, $rating),
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
     * Строка условий дальнего боя из пересчитанного броска.
     *
     * @param array<string, mixed> $roll Бросок атаки.
     *
     * @return string|null Текст или null.
     */
    public function getRangedConditionsText(array $roll): ?string
    {
        $detail = $roll['ranged_hit'] ?? null;
        if (!is_array($detail)) {
            return null;
        }

        $distance = $this->getDimensionalText($detail['distance_value'] ?? null);
        $falloff = $this->getDimensionalText($detail['falloff_value'] ?? null);
        $cover = $this->getDimensionalText($detail['cover_value'] ?? null);
        $defense = $this->getDimensionalText($detail['defense_value'] ?? null);
        $difficulty = $this->getDimensionalText($detail['difficulty'] ?? null);
        $rangeSize = is_int($detail['range_size'] ?? null) ? $detail['range_size'] : 1;
        $extra = max(0, $rangeSize - 1);
        $falloffText = $falloff === '?' ? 'без дальнобойности' : 'дальнобойность ' . $falloff;
        $range = $extra === 0
            ? $falloffText . ', без штрафа'
            : $falloffText . ', +' . $extra . ' ' . $this->sizeWord($extra);

        return 'Дистанция ' . $distance . ' ипари (' . $range . '), укрытие ' . $cover
            . ', защита ' . $defense . ' → сложность ' . $difficulty . '.';
    }

    /**
     * Размерное число для текста.
     *
     * @param mixed $value Пара base/size.
     *
     * @return string Текст.
     */
    public function getDimensionalText(mixed $value): string
    {
        if (!is_array($value) || !is_int($value['base'] ?? null) || !is_int($value['size'] ?? null)) {
            return '?';
        }

        $base = $value['base'];
        $size = $value['size'];
        if ($size === 0) {
            return (string) $base;
        }

        $arrow = $size > 0 ? '↑' : '↓';
        $power = abs($size);

        return $base . $arrow . ($power > 1 ? (string) $power : '');
    }

    /**
     * Реакция цели.
     *
     * @param string $name Имя цели.
     * @param string $reaction Реакция.
     * @param string|null $reactionCode Код карточки.
     * @param int $actionAp Стоимость.
     * @param bool $turn Поворот вместе с реакцией.
     * @param int $turnAp Стоимость поворота.
     *
     * @return string Текст.
     */
    public function getReactionBit(
        string $name,
        string $reaction,
        ?string $reactionCode,
        int $actionAp,
        bool $turn = false,
        int $turnAp = 0,
    ): string {
        if ($reaction === 'ignore') {
            return $name . ' не реагирует';
        }

        $token = $reactionCode !== null ? '[[rule:' . $reactionCode . ']]' : $reaction;
        $totalAp = $actionAp + ($turn ? $turnAp : 0);
        $suffix = $turn ? ' с [[rule:turn]] (+' . $turnAp . 'ОД)' : '';

        return $name . ' пытается совершить ' . $token . ' за ' . $totalAp . 'ОД' . $suffix;
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

        $code = $this->rules->outcome->damageTypeCode(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
        );
        $name = $code === null ? null : $slice->findLive($code)?->getName();

        return is_string($name) && $name !== '' ? mb_strtolower($name) : 'безымянный';
    }

    /**
     * Текст проверки на увечье.
     *
     * @param string $target Цель.
     * @param int $rating Рейтинг.
     *
     * @return string Текст.
     */
    private function maimText(string $target, int $rating): string
    {
        if ($rating >= 0) {
            return $target . ' не получает увечье.';
        }

        return $target . ' получает временное увечье с силой ' . (-$rating) . '.';
    }

    /**
     * Слово «размер» в нужном числе.
     *
     * @param int $count Количество.
     *
     * @return string Слово.
     */
    private function sizeWord(int $count): string
    {
        $mod100 = $count % 100;
        if ($mod100 >= 11 && $mod100 <= 14) {
            return 'размеров';
        }

        return match ($count % 10) {
            1 => 'размер',
            2, 3, 4 => 'размера',
            default => 'размеров',
        };
    }
}
