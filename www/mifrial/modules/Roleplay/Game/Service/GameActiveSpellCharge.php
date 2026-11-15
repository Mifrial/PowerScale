<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameActiveSpellPrepared;
use Mifrial\Roleplay\Game\Dto\GameActiveSpellRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Заряд активного заклинания: выдача при создании, трата каста и снятие.
 */
final class GameActiveSpellCharge
{
    /**
     * Создаёт сценарий.
     *
     * @param GameSpellCharge $charge Расчёт заряда.
     * @param GameStrikeSheetWrites $writes Запись листа.
     * @param GameActiveSpellChat $chat Чат поддержания.
     *
     * @return void
     */
    public function __construct(
        private readonly GameSpellCharge $charge,
        private readonly GameStrikeSheetWrites $writes,
        private readonly GameActiveSpellChat $chat,
    ) {
    }

    /**
     * Выдаёт заряд созданному поддержанию и пишет чат.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param GameActiveSpellRecord $spell Строка поддержания.
     * @param GameSpellCastPrepared $prepared Подготовка каста.
     * @param int $version Версия листа кастера.
     * @param array{id: string, parentId: string, kind: string} $thread Нить хода.
     *
     * @return int Версия листа после выдачи.
     *
     * @throws GameInvalidException Если карточка, лист или операция битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameBattleConflictException Если версия листа другая.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    public function grantOnCreate(
        GameRecord $game,
        int $actorUserId,
        GameActiveSpellRecord $spell,
        GameSpellCastPrepared $prepared,
        int $version,
        array $thread,
    ): int {
        $spec = $this->charge->spec($prepared->spell);
        if ($spec === null) {
            return $version;
        }

        $levels = $prepared->casterSheet['abilityLevels'] ?? [];
        $cap = $this->charge->cap(
            $prepared->slice,
            $prepared->casterModel,
            is_array($levels) ? $levels : [],
            $prepared->spellCode,
            $spec,
        );
        $operations = $this->charge->grant($prepared->casterSheet, $spec, $prepared->sourceKey, $cap, true);
        if ($operations === []) {
            return $version;
        }

        $updated = $this->writes->write(
            $game,
            $prepared->casterKind,
            $prepared->casterId,
            $version,
            $operations,
        );
        $name = $prepared->slice->findLive($spec->getStateCode())?->getName() ?? $spec->getStateCode();
        $this->chat->chargeGrantedAt(
            $game,
            $actorUserId,
            $spell,
            $thread,
            $name,
            min($cap, $spec->getGrant()),
        );

        return $updated;
    }

    /**
     * Операции траты заряда для записи каста.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если карточка держателя битая или заряда мало.
     */
    public function spendOperations(GameSpellCastPrepared $prepared): array
    {
        if ($prepared->chargeSpend === null) {
            return [];
        }

        $spec = $this->charge->specOf($prepared->slice, $prepared->chargeSpellCode ?? '');
        if ($spec === null) {
            throw new GameInvalidException('Game spell charge card is invalid');
        }

        return $this->charge->spend($prepared->casterSheet, $spec, $prepared->sourceKey);
    }

    /**
     * Снимает заряд поддержания и пишет лист.
     *
     * @param GameRecord $game Игра.
     * @param GameActiveSpellPrepared $prepared Снимок строки.
     *
     * @return int Версия листа после снятия.
     *
     * @throws GameInvalidException Если карточка или лист битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameBattleConflictException Если версия листа другая.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    public function removeFor(GameRecord $game, GameActiveSpellPrepared $prepared): int
    {
        $spec = $this->charge->spec($prepared->card);
        if ($spec === null) {
            return $prepared->sheetVersion;
        }

        $operations = $this->charge->remove(
            $prepared->sheet,
            $spec->getStateCode(),
            $prepared->record->getSourceKey(),
        );
        if ($operations === []) {
            return $prepared->sheetVersion;
        }

        return $this->writes->write(
            $game,
            $prepared->record->getCasterKind(),
            $prepared->record->getCasterId(),
            $prepared->sheetVersion,
            $operations,
        );
    }
}
