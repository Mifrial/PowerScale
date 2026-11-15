<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameActiveSpellPrepared;
use Mifrial\Roleplay\Game\Dto\GameActiveSpellRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActiveSpellRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellValue;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;

/**
 * Загружает строку поддержания и живой снимок кастера и карточки.
 */
final class GameActiveSpellFacts
{
    /**
     * Создаёт подготовку.
     *
     * @param GameActiveSpellRepository $spells Строки поддержания.
     * @param GameStrikeRules $rules Срез и данные.
     * @param GameStrikeSheets $sheets Снимки листов.
     * @param GameSpellCastFacts $castFacts Пересчёт листа.
     * @param GameSpellSource $source Источник и его доступность.
     * @param GameSpellDurationRules $durations Форма длительности.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActiveSpellRepository $spells,
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly GameSpellCastFacts $castFacts,
        private readonly GameSpellSource $source,
        private readonly GameSpellDurationRules $durations,
    ) {
    }

    /**
     * Находит строку боя и проверяет версию и кастера.
     *
     * @param int $battleId Бой.
     * @param int $spellId Id строки.
     * @param int $expectedVersion Ожидаемая версия строки.
     * @param array{type: string, id: int} $current Текущий участник.
     * @param bool $isWriter Владелец, gm или edit_all.
     *
     * @return GameActiveSpellRecord Строка.
     *
     * @throws GameNotFoundException Если строки нет или она из другого боя.
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameInvalidException Если кастер не текущий.
     */
    public function load(
        int $battleId,
        int $spellId,
        int $expectedVersion,
        array $current,
        bool $isWriter,
    ): GameActiveSpellRecord {
        $record = $this->spells->getById($spellId);
        if ($record->getBattleId() !== $battleId) {
            throw new GameNotFoundException('Game active spell was not found');
        }

        if ($record->getStateVersion() !== $expectedVersion) {
            throw new GameBattleConflictException($record->getStateVersion());
        }

        if (!$isWriter && $record->getCasterKey() !== $current['type'] . ':' . $current['id']) {
            throw new GameInvalidException('Game active spell caster is not current');
        }

        return $record;
    }

    /**
     * Собирает снимок карточки, пути, листа и источника.
     *
     * @param GameRecord $game Игра.
     * @param GameActiveSpellRecord $record Строка.
     *
     * @return GameActiveSpellPrepared Снимок.
     *
     * @throws GameInvalidException Если карточка, путь или лист битые.
     * @throws GameNotFoundException Если листа кастера нет.
     */
    public function prepare(GameRecord $game, GameActiveSpellRecord $record): GameActiveSpellPrepared
    {
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $card = $this->card($slice, $record->getSpellCode());
        $path = $this->path($slice, $record->getPathCode());
        $duration = $card->getSpell()->getDuration();
        $this->durations->assertSupported($duration);
        if (!$this->durations->isSustained($duration)) {
            throw new GameInvalidException('Game active spell duration is not sustained');
        }

        $snapshot = $this->sheets->snapshot($game, $record->getCasterKind(), $record->getCasterId());
        $choices = $snapshot['sheet']['choices'] ?? null;
        $rawSheet = $snapshot['sheet']['sheet'] ?? null;
        $version = $snapshot['actualVersion'] ?? null;
        if (!is_array($choices) || !is_array($rawSheet) || !is_int($version)) {
            throw new GameInvalidException('Game active spell caster sheet is invalid');
        }

        $sheet = $this->castFacts->compute($game, $choices, $rawSheet);

        return new GameActiveSpellPrepared(
            $record,
            $slice,
            $card,
            $path,
            $choices,
            $this->castFacts->choicesOf($choices),
            $sheet,
            $version,
            $this->resolveValue($duration->getPower(), $record->getParameterValues()),
            $this->source->isAvailable(
                $record->getSourceKey(),
                $choices,
                $sheet,
                $path,
                $slice,
            ),
        );
    }

    /**
     * Живая карточка заклинания.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return AbilitySpellSpec Карточка.
     *
     * @throws GameInvalidException Если это не живое заклинание.
     */
    private function card(CharacterRuleSlice $slice, string $code): AbilitySpellSpec
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof AbilitySpellSpec) {
            throw new GameInvalidException('Game active spell card is invalid');
        }

        return $spec;
    }

    /**
     * Живой путь заклинания.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return MagicPathSpec Путь.
     *
     * @throws GameInvalidException Если это не живой путь.
     */
    private function path(CharacterRuleSlice $slice, string $code): MagicPathSpec
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof MagicPathSpec) {
            throw new GameInvalidException('Game active spell path is invalid');
        }

        return $spec;
    }

    /**
     * Мощь из формы карточки по сохранённым параметрам.
     *
     * @param SpellValue|null $value Значение формы.
     * @param array<string, int|array{base: int, size: int}> $parameters Параметры строки.
     *
     * @return array{base: int, size: int}|null Пара или null.
     */
    private function resolveValue(?SpellValue $value, array $parameters): ?array
    {
        if (!$value instanceof SpellValue) {
            return null;
        }

        $number = $value->getNumber();
        if ($number !== null) {
            return ['base' => $number->getBase(), 'size' => $number->getSize()];
        }

        $code = $value->getParameterCode();
        if ($code === null) {
            return null;
        }

        $entry = $parameters[$code] ?? null;
        if (is_int($entry)) {
            return ['base' => $entry, 'size' => 0];
        }

        return is_array($entry) ? ['base' => $entry['base'], 'size' => $entry['size']] : null;
    }
}
