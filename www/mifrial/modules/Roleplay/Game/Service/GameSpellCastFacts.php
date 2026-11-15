<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterStudyPaths;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterRaceAbilityCodes;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Dto\GameSpellCastUpgradeResolution;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActiveSpellRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityParameter;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellValue;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Доступность заклинания, параметры, мощь и траты компонентов.
 */
final class GameSpellCastFacts
{
    /**
     * Технический предел размера параметра: защита от переполнения,
     * а не игровое ограничение. Верхнюю мощь задаёт характеристика кастера.
     */
    private const MAX_PARAMETER_SIZE = 20;
    private readonly CharacterChoiceAssembler $assembler;

    private readonly CharacterAbilityGrantReader $grants;

    private readonly GameSpellSource $source;

    private readonly GameSpellDurationRules $durations;

    private readonly GameSpellCastUpgrades $upgrades;

    private readonly GameCheckEfficiency $efficiency;

    private readonly GameSpellCastSaturation $saturation;

    private readonly GameSpellCastNextCast $nextCast;

    private readonly GameSpellCharge $charge;

    private readonly GameSpellSourcePower $sourcePower;

    private readonly GameSpellMana $mana;

    /**
     * Создаёт подготовку каста.
     *
     * @param GameStrikeRules $rules Срез и расчёты.
     * @param GameStrikeSheets $sheets Снимки листов.
     * @param ICharacterSheets $characterSheets Пересчёт листа.
     * @param GameCardAvailability $availability Гейт карточки.
     * @param GameActionResourceResolver $resolver Компоненты.
     * @param GameActionHands $hands Руки кастера.
     * @param GameSpellDeviation $deviation Отклонение и штраф к мощи.
     * @param GameActiveSpellRepository $activeSpells Строки поддержания.
     * @param CharacterStudyPaths $paths Включения путей.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly ICharacterSheets $characterSheets,
        private readonly GameCardAvailability $availability,
        private readonly GameActionResourceResolver $resolver,
        private readonly GameActionHands $hands,
        private readonly GameSpellDeviation $deviation,
        private readonly GameActiveSpellRepository $activeSpells,
        private readonly CharacterStudyPaths $paths = new CharacterStudyPaths(),
    ) {
        $this->assembler = new CharacterChoiceAssembler();
        $this->grants = new CharacterAbilityGrantReader(new CharacterDonorGrants(), new CharacterRaceAbilityCodes());
        $this->source = new GameSpellSource($this->grants);
        $this->durations = new GameSpellDurationRules();
        $this->upgrades = new GameSpellCastUpgrades();
        $this->efficiency = new GameCheckEfficiency($this->grants, new GameCheckChain());
        $this->saturation = new GameSpellCastSaturation();
        $this->nextCast = new GameSpellCastNextCast();
        $this->charge = new GameSpellCharge();
        $this->sourcePower = new GameSpellSourcePower(
            $deviation,
            new GameSpellSourceBonus(new FormulaEvaluations()),
        );
        $this->mana = new GameSpellMana();
    }

    /**
     * Проверяет запрос и собирает подготовку каста.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $body Тело команды.
     * @param array<string, mixed>|null $touchTarget Цель касания, если заклинание доставляется ударом.
     *
     * @return GameSpellCastPrepared Подготовка.
     *
     * @throws GameInvalidException Если карточка, путь, экземпляр или траты не подходят.
     */
    public function prepare(GameRecord $game, array $body, ?array $touchTarget = null): GameSpellCastPrepared
    {
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spellCode = $this->string($body, 'spellCode');
        $pathCode = $this->string($body, 'pathCode');
        $spell = $this->spellSpec($slice, $spellCode);
        $path = $this->pathSpec($slice, $pathCode);
        $this->durations->assertSupported($spell->getSpell()->getDuration());
        $recorded = $path->castsRecorded();

        $actor = $this->side($body, 'actor');
        $availability = $this->availability->assertAvailable(
            $game,
            $actor['type'],
            $actor['id'],
            $spellCode,
            $pathCode,
            $recorded,
        );
        if (!$availability instanceof AbilitySpellSpec) {
            throw new GameInvalidException('Game spell card is invalid');
        }

        $caster = $this->participant($game, $slice, $actor['type'], $actor['id']);
        $model = $this->model($caster['choices']);
        $power = $this->usedPower($body, $path, $caster, $model, $slice);
        $this->assertCastingSource($path, $power['key'], $spellCode, $caster);
        if (!$recorded) {
            $this->assertInstance($model, $slice, $spellCode, $pathCode);
        }

        $this->hands->assertAvailable(
            $game,
            $slice,
            $caster['choices'],
            ['kind' => $actor['type'], 'id' => $actor['id']],
            $spell,
        );

        $target = $this->target($game, $slice, $body, $spell);
        $damageTarget = $target ?? $touchTarget;
        $parameters = $this->parameterMap($body, $spell);
        $spellPower = $this->value($spell->getSpell()->getPower(), $parameters);
        $upgrades = $this->resolveUpgrades(
            $slice,
            $model,
            $caster,
            $spellCode,
            $pathCode,
            $body,
            $recorded ? $this->recordedUpgrades($caster['choices'], $power['key'], $spellCode) : null,
        );
        $levels = $caster['sheet']['abilityLevels'] ?? [];
        $levelMap = is_array($levels) ? $levels : [];
        $efficiency = $this->efficiency->resolve(
            $slice,
            $model,
            $levelMap,
            $path->getCastCheckCode(),
        );
        $saturationSource = $this->saturation->source($slice, $model, $levelMap);
        $nextCastSources = $this->nextCast->sources($slice, $model, $levelMap);
        $requiredPower = (new CharacteristicNumber($spellPower->getBase(), $spellPower->getSize()))
            ->modify($upgrades->getRequiredPowerDelta());
        $requiredControl = $this->value($spell->getSpell()->getControl(), $parameters);
        $characteristics = $this->characteristicsOf($caster['sheet']);
        $usedPower = $power['value'];
        $availableControl = $this->availableControl($path, $requiredControl, $characteristics);

        $spends = $this->resolver->resolve(
            $slice,
            $spellCode,
            0,
            '',
            $caster['choices'],
            $caster['sheet'],
        );
        $turnCode = $this->turnResourceCode($slice);
        $baseSpent = $this->spentOf($spends, $turnCode);
        $spends = $this->mergeUpgradeSpends($spends, $upgrades->getActionPointDelta(), $turnCode);
        $spent = $this->spentOf($spends, $turnCode);
        $charge = $this->chargePlan(
            $body,
            $slice,
            $spell,
            $spellCode,
            $pathCode,
            $actor,
            $caster['sheet'],
            $power['key'],
            $spends,
            $turnCode,
            $baseSpent,
        );
        if ($charge !== null) {
            $spends = $charge['spends'];
            $spent = $charge['spent'];
        }

        // Каст через электрозаряд не платит ману: сила уже оплачена
        // поддержанием Генератора, а цена этого каста — 2 ОД и заряд.
        $mana = $charge === null
            ? $this->mana->plan(
                $slice,
                $caster['sheet'],
                $power['key'],
                $usedPower,
                $spent,
                $turnCode,
            )
            : null;

        return new GameSpellCastPrepared(
            $slice,
            $spell,
            $path,
            $spellCode,
            $pathCode,
            $actor['type'],
            $actor['id'],
            $model,
            $caster['choices'],
            $caster['sheet'],
            $caster['version'],
            $target,
            $damageTarget,
            $parameters,
            $usedPower,
            $power['key'],
            $spellPower,
            $requiredPower,
            $requiredControl,
            $availableControl,
            $efficiency,
            $upgrades,
            $spends,
            $spent,
            $this->int($body, 'distanceIpari', 0),
            $saturationSource,
            $nextCastSources,
            $charge['spellId'] ?? null,
            $charge['spellCode'] ?? null,
            $charge['spend'] ?? null,
            $mana,
        );
    }

    /**
     * Документ choices в модель.
     *
     * @param array<string, mixed> $choices Документ.
     *
     * @return CharacterChoices Выборы.
     */
    public function choicesOf(array $choices): CharacterChoices
    {
        return $this->model($choices);
    }

    /**
     * План траты заряда поддержания, если каст идёт через него.
     *
     * @param array<string, mixed> $body Тело.
     * @param CharacterRuleSlice $slice Срез.
     * @param AbilitySpellSpec $spell Карточка цели.
     * @param string $spellCode Код цели.
     * @param string $pathCode Путь цели.
     * @param array{type: string, id: int} $actor Кастер.
     * @param array<string, mixed> $sheet Лист кастера.
     * @param string $sourceKey Ключ источника.
     * @param list<ResourceSpend> $spends Траты цели.
     * @param string $turnCode Код ресурса хода.
     * @param int $baseSpent Создание цели в ОД без улучшений.
     *
     * @return array{
     *     spellId: int,
     *     spellCode: string,
     *     spend: array{stateCode: string, sourceKey: string, amount: int},
     *     spends: list<ResourceSpend>,
     *     spent: int
     * }|null План или null.
     *
     * @throws GameInvalidException Если заряд, строка, цель или трата не подходят.
     */
    private function chargePlan(
        array $body,
        CharacterRuleSlice $slice,
        AbilitySpellSpec $spell,
        string $spellCode,
        string $pathCode,
        array $actor,
        array $sheet,
        string $sourceKey,
        array $spends,
        string $turnCode,
        int $baseSpent,
    ): ?array {
        $spellId = $this->chargeSpellId($body);
        if ($spellId === null) {
            return null;
        }

        $battleId = is_int($body['battleId'] ?? null) ? $body['battleId'] : 0;
        $row = $this->activeSpells->findById($spellId);
        if ($row === null || $row->getBattleId() !== $battleId) {
            throw new GameInvalidException('Game spell charge row is not found');
        }

        if ($row->getCasterKind() !== $actor['type'] || $row->getCasterId() !== $actor['id']) {
            throw new GameInvalidException('Game spell charge row belongs to another caster');
        }

        if ($row->getSourceKey() !== $sourceKey || $row->getPathCode() !== $pathCode) {
            throw new GameInvalidException('Game spell charge source is unexpected');
        }

        $charge = $this->charge->spec($this->spellSpec($slice, $row->getSpellCode()));
        if ($charge === null) {
            throw new GameInvalidException('Game spell charge card is invalid');
        }

        if (in_array($spell->getSpell()->getDuration()->getType(), $charge->getExcludedDurations(), true)) {
            throw new GameInvalidException('Game spell charge cannot cast this duration');
        }

        $keywords = $slice->findLive($spellCode)?->getKeywordCodes() ?? [];
        if (!in_array($charge->getKeywordCode(), $keywords, true)) {
            throw new GameInvalidException('Game spell charge keyword is unexpected');
        }

        if ($this->charge->value($sheet, $charge->getStateCode(), $sourceKey) < $charge->getAmount()) {
            throw new GameInvalidException('Game spell charge is not enough');
        }

        if ($charge->getCreationMax() !== 'turn_ap') {
            throw new GameInvalidException('Game spell charge creation limit is unknown');
        }

        if ($turnCode === '') {
            throw new GameInvalidException('Game spell turn resource is missing');
        }

        if ($baseSpent > $this->charge->turnLimit($sheet, $slice, $turnCode)) {
            throw new GameInvalidException('Game spell charge creation cost is above the turn limit');
        }

        // Заряд — сопутствующее действие: мощь цели ограничена только обычной
        // проверкой характеристики каста, а не мощью поддержания Генератора.

        $merged = [];
        foreach ($spends as $spend) {
            if ($spend->getResourceCode() !== $turnCode) {
                $merged[] = $spend;
            }
        }

        if ($charge->getActionPoints() > 0) {
            $merged[] = new ResourceSpend($turnCode, new ScalarResourceValue($charge->getActionPoints()));
        }

        return [
            'spellId' => $spellId,
            'spellCode' => $row->getSpellCode(),
            'spend' => [
                'stateCode' => $charge->getStateCode(),
                'sourceKey' => $sourceKey,
                'amount' => $charge->getAmount(),
            ],
            'spends' => $merged,
            'spent' => $this->spentOf($merged, $turnCode),
        ];
    }

    /**
     * Id строки заряда из тела.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return int|null Id или null.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function chargeSpellId(array $body): ?int
    {
        $raw = $body['chargeSpellId'] ?? null;
        if ($raw === null) {
            return null;
        }

        if (is_string($raw) && preg_match('/^\d+$/', $raw) === 1) {
            $raw = (int) $raw;
        }

        if (!is_int($raw) || $raw < 1) {
            throw new GameInvalidException('Game spell charge id is invalid');
        }

        return $raw;
    }

    /**
     * Пересчитывает лист участника от сохранённых choices.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choices Выборы.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, mixed> Пересчитанный лист.
     */
    public function compute(GameRecord $game, array $choices, array $sheet): array
    {
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());

        return $this->characterSheets->compute($slice, $choices, $sheet);
    }

    /**
     * Текущая версия листа участника.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Character или npc.
     * @param int $id Идентификатор.
     *
     * @return int Версия или 0, если листа нет.
     */
    public function actualVersion(GameRecord $game, string $kind, int $id): int
    {
        try {
            $snapshot = $this->sheets->snapshot($game, $kind, $id);
        } catch (GameNotFoundException) {
            return 0;
        }

        $version = $snapshot['actualVersion'] ?? null;

        return is_int($version) ? $version : 0;
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
    private function spellSpec(CharacterRuleSlice $slice, string $code): AbilitySpellSpec
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof AbilitySpellSpec) {
            throw new GameInvalidException('Game spell card is invalid');
        }

        return $spec;
    }

    /**
     * Живой путь с проверкой кастовых полей.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return MagicPathSpec Путь.
     *
     * @throws GameInvalidException Если путь без проверки или характеристик.
     */
    private function pathSpec(CharacterRuleSlice $slice, string $code): MagicPathSpec
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof MagicPathSpec) {
            throw new GameInvalidException('Game spell path is invalid');
        }

        foreach ([$spec->getCastCheckCode(), $spec->getCheckCode()] as $check) {
            if ($check === null || $check === '') {
                throw new GameInvalidException('Game spell path is invalid');
            }
        }

        return $spec;
    }

    /**
     * Проверяет код характеристики пути.
     *
     * @param string|null $code Код.
     *
     * @return string Код.
     *
     * @throws GameInvalidException Если код пуст.
     */
    private function pathCharacteristicCode(?string $code): string
    {
        if ($code === null || $code === '') {
            throw new GameInvalidException('Game spell path characteristic is missing');
        }

        return $code;
    }

    /**
     * Лист участника с пересчётом от сохранённых choices.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param string $kind Вид.
     * @param int $id Идентификатор.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>, version: int} Документ.
     *
     * @throws GameInvalidException Если снимок битый.
     */
    private function participant(GameRecord $game, CharacterRuleSlice $slice, string $kind, int $id): array
    {
        $snapshot = $this->sheets->snapshot($game, $kind, $id);
        $sheet = $snapshot['sheet']['sheet'] ?? null;
        $choices = $snapshot['sheet']['choices'] ?? null;
        $version = $snapshot['actualVersion'] ?? null;
        if (!is_array($sheet) || !is_array($choices) || !is_int($version)) {
            throw new GameInvalidException('Game spell sheet is invalid');
        }

        return [
            'choices' => $choices,
            'sheet' => $this->characterSheets->compute($slice, $choices, $sheet),
            'version' => $version,
        ];
    }

    /**
     * Цель и проверка её обязательности.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $body Тело.
     * @param AbilitySpellSpec $spell Карточка.
     *
     * @return array{
     *     type: string,
     *     id: int,
     *     choices: array<string, mixed>,
     *     sheet: array<string, mixed>,
     *     version: int
     * }|null Цель.
     *
     * @throws GameInvalidException Если цель не нужна или не найдена.
     */
    private function target(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $body,
        AbilitySpellSpec $spell,
    ): ?array {
        $target = $body['target'] ?? null;
        $targeting = $spell->getSpell()->getTargeting();
        if ($targeting === 'entity' && !is_array($target)) {
            throw new ActionException('INVALID_PARAMS', 'Game spell target is required');
        }

        if ($targeting !== 'entity' && $target !== null) {
            throw new ActionException('INVALID_PARAMS', 'Game spell target is not allowed');
        }

        if (!is_array($target)) {
            return null;
        }

        $side = $this->side($body, 'target');
        $participant = $this->participant($game, $slice, $side['type'], $side['id']);

        return [
            'type' => $side['type'],
            'id' => $side['id'],
            'choices' => $participant['choices'],
            'sheet' => $participant['sheet'],
            'version' => $participant['version'],
        ];
    }

    /**
     * Экземпляр заклинания на выбранном пути или на включённом им пути.
     *
     * Псионическое заклинание, изученное на Псионике, Шаман кастует без
     * повторного изучения: выбранный путь покрывает домен экземпляра.
     * Точный экземпляр выбранного пути приоритетен; иначе покрытый домен
     * должен быть единственным, иначе непонятно, какой экземпляр кастовать.
     *
     * @param CharacterChoices $model Выборы.
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param string $spellCode Заклинание.
     * @param string $pathCode Путь.
     *
     * @return void
     *
     * @throws GameInvalidException Если экземпляра нет, их несколько или домен не покрыт.
     */
    private function assertInstance(
        CharacterChoices $model,
        CharacterRuleSlice $slice,
        string $spellCode,
        string $pathCode,
    ): void {
        $exact = false;
        $covered = [];
        foreach ($model->getAbilities() as $ability) {
            if ($ability->getRuleCode() !== $spellCode) {
                continue;
            }

            $domain = $ability->getDomainCode();
            if ($domain === $pathCode) {
                $exact = true;
                continue;
            }

            if ($domain !== '' && $this->paths->reaches($slice, $pathCode, $domain)) {
                $covered[$domain] = true;
            }
        }

        if ($exact || count($covered) === 1) {
            return;
        }

        throw new GameInvalidException('Game spell instance is invalid');
    }

    /**
     * Контроль каста: характеристика пути или требуемый, если Контроля нет.
     *
     * @param MagicPathSpec $path Путь.
     * @param DimensionalNumber $requiredControl Требуемый контроль.
     * @param array<string, mixed> $characteristics Живые характеристики.
     *
     * @return DimensionalNumber Контроль.
     *
     * @throws GameInvalidException Если характеристики пути нет.
     */
    private function availableControl(
        MagicPathSpec $path,
        DimensionalNumber $requiredControl,
        array $characteristics,
    ): DimensionalNumber {
        if ($path->isControlless()) {
            return new DimensionalNumber($requiredControl->getBase(), $requiredControl->getSize());
        }

        $code = $this->pathCharacteristicCode($path->getControlCharacteristicCode());
        $value = $characteristics[$code] ?? null;
        if (!$value instanceof CharacteristicNumber) {
            throw new GameInvalidException('Game spell control characteristic is missing');
        }

        return $value;
    }

    /**
     * Источник каста: записи для пути записей, обычный источник для остальных.
     *
     * @param MagicPathSpec $path Путь.
     * @param string $sourceKey Ключ источника.
     * @param string $spellCode Код заклинания.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>, version: int} $caster Кастер.
     *
     * @return void
     *
     * @throws GameInvalidException Если источник не тот или заклинания нет в записях.
     */
    private function assertCastingSource(
        MagicPathSpec $path,
        string $sourceKey,
        string $spellCode,
        array $caster,
    ): void {
        $storage = $this->mana->row($caster['sheet'], $sourceKey);
        if ($path->castsRecorded()) {
            if ($storage === null || !$this->isRecorded($caster['choices'], $sourceKey, $spellCode)) {
                throw new GameInvalidException('Game spell is not recorded in the source');
            }

            return;
        }

        if ($storage !== null) {
            throw new GameInvalidException('Game spell source requires recorded casting');
        }
    }

    /**
     * Улучшения, записанные в источнике под это заклинание.
     *
     * @param array<string, mixed> $choices Документ choices.
     * @param string $sourceKey Ключ источника.
     * @param string $spellCode Код заклинания.
     *
     * @return array<int, string> Коды улучшений.
     */
    private function recordedUpgrades(array $choices, string $sourceKey, string $spellCode): array
    {
        $entry = $this->recordedEntry($choices, $sourceKey, $spellCode);
        if ($entry === null) {
            return [];
        }

        $codes = [];
        foreach (is_array($entry['upgrades'] ?? null) ? $entry['upgrades'] : [] as $code) {
            if (is_string($code) && $code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * Запись заклинания в строке инвентаря.
     *
     * @param array<string, mixed> $choices Документ choices.
     * @param string $sourceKey Ключ источника.
     * @param string $spellCode Код заклинания.
     *
     * @return array<string, mixed>|null Запись или null.
     */
    private function recordedEntry(array $choices, string $sourceKey, string $spellCode): ?array
    {
        $inventoryId = (int) substr($sourceKey, strlen('inventory:'));
        foreach (is_array($choices['inventory'] ?? null) ? $choices['inventory'] : [] as $row) {
            if (!is_array($row) || ($row['id'] ?? null) !== $inventoryId) {
                continue;
            }

            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
            foreach (is_array($payload['recorded_spells'] ?? null) ? $payload['recorded_spells'] : [] as $entry) {
                if (is_array($entry) && ($entry['ruleCode'] ?? null) === $spellCode) {
                    return $entry;
                }
            }
        }

        return null;
    }

    /**
     * Заклинание записано в источнике.
     *
     * @param array<string, mixed> $choices Документ choices.
     * @param string $sourceKey Ключ источника.
     * @param string $spellCode Код заклинания.
     *
     * @return bool true, если запись есть.
     */
    private function isRecorded(array $choices, string $sourceKey, string $spellCode): bool
    {
        return $this->recordedEntry($choices, $sourceKey, $spellCode) !== null;
    }

    /**
     * Карта параметров с проверкой kind, min/max и activation.
     *
     * @param array<string, mixed> $body Тело.
     * @param AbilitySpellSpec $spell Карточка.
     *
     * @return array<string, int|array{base: int, size: int}> Параметры.
     *
     * @throws GameInvalidException Если параметр неизвестен, не activation или вне границ.
     */
    private function parameterMap(array $body, AbilitySpellSpec $spell): array
    {
        $given = $body['parameters'] ?? null;
        if (!is_array($given)) {
            throw new GameInvalidException('Game spell parameters are invalid');
        }

        $known = [];
        foreach ($spell->getBase()->getParameters() as $parameter) {
            $known[$parameter->getCode()] = $parameter;
        }

        foreach ($given as $code => $value) {
            if (!is_string($code) || !isset($known[$code])) {
                throw new GameInvalidException('Game spell parameter is unknown');
            }

            $this->assertParameter($known[$code], $value);
        }

        foreach ([$spell->getSpell()->getPower(), $spell->getSpell()->getControl()] as $value) {
            $code = $value->getParameterCode();
            if ($code !== null && !array_key_exists($code, $given)) {
                throw new GameInvalidException('Game spell parameter is required');
            }
        }

        /**
         * @var array<string, int|array{base: int, size: int}> $given
         */
        return $given;
    }

    /**
     * Один параметр против его спеки.
     *
     * @param AbilityParameter $parameter Спека.
     * @param mixed $value Значение.
     *
     * @return void
     *
     * @throws GameInvalidException Если параметр не активационный или вне границ.
     */
    private function assertParameter(AbilityParameter $parameter, mixed $value): void
    {
        if ($parameter->getResolution() !== 'activation') {
            throw new GameInvalidException('Game spell parameter is not activation');
        }

        if ($parameter->getKind() === 'dimensional') {
            if (!is_array($value) || !is_int($value['base'] ?? null) || !is_int($value['size'] ?? null)) {
                throw new GameInvalidException('Game spell parameter shape is invalid');
            }

            if ($value['size'] < -self::MAX_PARAMETER_SIZE || $value['size'] > self::MAX_PARAMETER_SIZE) {
                throw new GameInvalidException('Game spell parameter shape is invalid');
            }

            $number = new DimensionalNumber($value['base'], $value['size']);
            $min = $parameter->getMin();
            $max = $parameter->getMax();
            if ($min instanceof DimensionalNumber && $number->compare($min) < 0) {
                throw new GameInvalidException('Game spell parameter is out of range');
            }

            if ($max instanceof DimensionalNumber && $number->compare($max) > 0) {
                throw new GameInvalidException('Game spell parameter is out of range');
            }

            return;
        }

        if (!is_int($value)) {
            throw new GameInvalidException('Game spell parameter shape is invalid');
        }

        $min = $parameter->getMin();
        $max = $parameter->getMax();
        if (is_int($min) && $value < $min) {
            throw new GameInvalidException('Game spell parameter is out of range');
        }

        if (is_int($max) && $value > $max) {
            throw new GameInvalidException('Game spell parameter is out of range');
        }
    }

    /**
     * Значение SpellValue.
     *
     * @param SpellValue $value Значение.
     * @param array<string, int|array{base: int, size: int}> $parameters Параметры.
     *
     * @return DimensionalNumber Число.
     *
     * @throws GameInvalidException Если параметр отсутствует или не размерный.
     */
    private function value(SpellValue $value, array $parameters): DimensionalNumber
    {
        $number = $value->getNumber();
        if ($number !== null) {
            return $number;
        }

        $code = $value->getParameterCode() ?? '';
        $raw = $parameters[$code] ?? null;
        if (!is_array($raw)) {
            throw new GameInvalidException('Game spell parameter value is invalid');
        }

        return new DimensionalNumber($raw['base'], $raw['size']);
    }

    /**
     * Источник и значение мощи; потолок берётся у источника.
     *
     * @param array<string, mixed> $body Тело.
     * @param MagicPathSpec $path Путь.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>, version: int} $caster Кастер.
     * @param CharacterChoices $model Выборы.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array{value: CharacteristicNumber, key: string} Мощь и ключ источника.
     *
     * @throws GameInvalidException Если источник чужой или мощь выше потолка.
     */
    private function usedPower(
        array $body,
        MagicPathSpec $path,
        array $caster,
        CharacterChoices $model,
        CharacterRuleSlice $slice,
    ): array {
        $usedPower = $body['usedPower'] ?? null;
        if (!is_array($usedPower)) {
            throw new GameInvalidException('Game spell used power is invalid');
        }

        $source = $usedPower['source'] ?? null;
        $value = $usedPower['value'] ?? null;
        if (!is_array($source) || !is_array($value)) {
            throw new GameInvalidException('Game spell used power is invalid');
        }

        $base = $value['base'] ?? null;
        $size = $value['size'] ?? null;
        if (!is_int($base) || !is_int($size)) {
            throw new GameInvalidException('Game spell used power is invalid');
        }

        try {
            $used = new CharacteristicNumber($base, $size);
        } catch (RuleInvalidException $exception) {
            throw new GameInvalidException('Game spell used power is invalid', $exception);
        }

        $this->assertSource($source, $caster, $path, $slice);
        $key = GameSpellSourceKey::of($source);

        $cap = $this->sourcePower->resolve(
            $slice,
            $path,
            $source,
            $caster['choices'],
            $caster['sheet'],
            $model,
        );
        if (!$cap instanceof CharacteristicNumber) {
            throw new GameInvalidException('Game spell source has no power');
        }

        if ($used->characteristicDiffTo($cap) > 0) {
            throw new GameInvalidException('Game spell used power is above the cap');
        }

        return ['value' => $used, 'key' => $key];
    }

    /**
     * Принадлежность источника кастеру.
     *
     * @param array<string, mixed> $source Источник.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>, version: int} $caster Кастер.
     * @param MagicPathSpec $path Путь.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return void
     *
     * @throws GameInvalidException Если источник чужой.
     */
    private function assertSource(
        array $source,
        array $caster,
        MagicPathSpec $path,
        CharacterRuleSlice $slice,
    ): void {
        $this->source->assertOwned(
            $source,
            $caster['choices'],
            $caster['sheet'],
            $path,
            $slice,
        );
    }

    /**
     * Документ choices в модель.
     *
     * @param array<string, mixed> $choices Документ.
     *
     * @return CharacterChoices Выборы.
     */
    private function model(array $choices): CharacterChoices
    {
        return $this->assembler->assemble(
            is_string($choices['name'] ?? null) ? $choices['name'] : '',
            is_string($choices['raceCode'] ?? null) ? $choices['raceCode'] : '',
            is_array($choices['abilities'] ?? null) ? $choices['abilities'] : [],
            is_array($choices['inventory'] ?? null) ? $choices['inventory'] : [],
            is_array($choices['characteristicPurchases'] ?? null) ? $choices['characteristicPurchases'] : [],
            is_array($choices['customRules'] ?? null) ? $choices['customRules'] : [],
            is_bool($choices['active'] ?? null) ? $choices['active'] : null,
        );
    }

    /**
     * Живые характеристики из листа.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, CharacteristicNumber> Код → значение.
     */
    private function characteristicsOf(array $sheet): array
    {
        $live = [];
        foreach (is_array($sheet['characteristics'] ?? null) ? $sheet['characteristics'] : [] as $row) {
            $code = is_array($row) ? ($row['characteristicCode'] ?? null) : null;
            $value = is_array($row) ? ($row['value'] ?? null) : null;
            $base = is_array($value) ? ($value['base'] ?? null) : null;
            $size = is_array($value) ? ($value['size'] ?? null) : null;
            if (!is_string($code) || !is_int($base) || !is_int($size)) {
                continue;
            }

            $live[$code] = new CharacteristicNumber($base, $size);
        }

        return $live;
    }

    /**
     * Сторона тела команды.
     *
     * @param array<string, mixed> $body Тело.
     * @param string $key Ключ.
     *
     * @return array{type: string, id: int} Сторона.
     *
     * @throws GameInvalidException Если сторона битая.
     */
    private function side(array $body, string $key): array
    {
        $side = $body[$key] ?? null;
        $type = is_array($side) ? ($side['type'] ?? null) : null;
        $id = is_array($side) ? ($side['id'] ?? null) : null;
        if (($type !== 'character' && $type !== 'npc') || !is_int($id) || $id < 1) {
            throw new GameInvalidException('Game spell actor is invalid');
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Строковое поле.
     *
     * @param array<string, mixed> $body Тело.
     * @param string $key Ключ.
     *
     * @return string Значение.
     *
     * @throws GameInvalidException Если поле пустое.
     */
    private function string(array $body, string $key): string
    {
        $value = $body[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new GameInvalidException('Game spell request is invalid');
        }

        return $value;
    }

    /**
     * Неотрицательное целое поля.
     *
     * @param array<string, mixed> $body Тело.
     * @param string $key Ключ.
     * @param int $default Значение по умолчанию.
     *
     * @return int Значение.
     */
    private function int(array $body, string $key, int $default): int
    {
        $value = $body[$key] ?? null;

        return is_int($value) && $value >= 0 ? $value : $default;
    }

    /**
     * Код ресурса хода.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return string Код или пусто.
     */
    private function turnResourceCode(CharacterRuleSlice $slice): string
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof ResourceSpec && $spec->isAutoAdd()) {
                return $rule->getCode();
            }
        }

        return '';
    }

    /**
     * Разрешает улучшения каста.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $model Выборы кастера.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>, version: int} $caster Кастер.
     * @param string $spellCode Заклинание.
     * @param string $pathCode Путь.
     * @param array<string, mixed> $body Тело.
     * @param array<int, string>|null $recordedCodes Улучшения записи источника или null.
     *
     * @return GameSpellCastUpgradeResolution Разрешение.
     *
     * @throws GameInvalidException Если улучшение не подходит.
     */
    private function resolveUpgrades(
        CharacterRuleSlice $slice,
        CharacterChoices $model,
        array $caster,
        string $spellCode,
        string $pathCode,
        array $body,
        ?array $recordedCodes = null,
    ): GameSpellCastUpgradeResolution {
        $levels = $caster['sheet']['abilityLevels'] ?? [];

        return $this->upgrades->resolve(
            $slice,
            $model,
            is_array($levels) ? $levels : [],
            $spellCode,
            $pathCode,
            $this->upgradeSelection($body),
            $recordedCodes,
        );
    }

    /**
     * Выбранные улучшения из тела в строгой форме.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return array<int, array{code: string, value: int|null}> Выбор.
     *
     * @throws GameInvalidException Если форма списка чужая.
     */
    private function upgradeSelection(array $body): array
    {
        $rows = $body['upgrades'] ?? [];
        if (!is_array($rows)) {
            throw new GameInvalidException('Game spell upgrades are invalid');
        }

        $selected = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new GameInvalidException('Game spell upgrades are invalid');
            }

            $code = $row['code'] ?? null;
            $value = $row['value'] ?? null;
            if (!is_string($code) || ($value !== null && !is_int($value))) {
                throw new GameInvalidException('Game spell upgrades are invalid');
            }

            $selected[] = ['code' => $code, 'value' => $value];
        }

        return $selected;
    }

    /**
     * Сливает дельту улучшений с тратой ресурса хода.
     *
     * @param list<ResourceSpend> $spends Траты компонентов.
     * @param int $delta Дельта ОД.
     * @param string $code Код ресурса хода.
     *
     * @return list<ResourceSpend> Траты.
     *
     * @throws GameInvalidException Если ресурс хода не найден.
     */
    private function mergeUpgradeSpends(array $spends, int $delta, string $code): array
    {
        if ($delta === 0) {
            return $spends;
        }

        if ($code === '') {
            throw new GameInvalidException('Game spell turn resource is missing');
        }

        $merged = [];
        $found = false;
        foreach ($spends as $spend) {
            if ($spend->getResourceCode() !== $code) {
                $merged[] = $spend;
                continue;
            }

            $found = true;
            $amount = $spend->getAmount()->toNative();
            $total = max(0, (is_int($amount) ? $amount : 0) + $delta);
            if ($total > 0) {
                $merged[] = new ResourceSpend($code, new ScalarResourceValue($total));
            }
        }

        if (!$found && $delta > 0) {
            $merged[] = new ResourceSpend($code, new ScalarResourceValue($delta));
        }

        return $merged;
    }

    /**
     * Списанные ОД компонентов.
     *
     * @param list<ResourceSpend> $spends Траты.
     * @param string $code Код ресурса хода.
     *
     * @return int Сумма.
     */
    private function spentOf(array $spends, string $code): int
    {
        $sum = 0;
        foreach ($spends as $spend) {
            $amount = $spend->getAmount()->toNative();
            if ($spend->getResourceCode() === $code && is_int($amount)) {
                $sum += $amount;
            }
        }

        return $sum;
    }
}
