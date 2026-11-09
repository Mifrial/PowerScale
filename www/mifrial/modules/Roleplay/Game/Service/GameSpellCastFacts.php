<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterRaceAbilityCodes;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityParameter;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ItemGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellValue;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Доступность заклинания, параметры, мощь и траты компонентов.
 */
final class GameSpellCastFacts
{
    private readonly CharacterChoiceAssembler $assembler;

    private readonly CharacterAbilityGrantReader $grants;

    /**
     * Создаёт подготовку каста.
     *
     * @param GameStrikeRules $rules Срез и расчёты.
     * @param GameStrikeSheets $sheets Снимки листов.
     * @param ICharacterSheets $characterSheets Пересчёт листа.
     * @param GameCardAvailability $availability Гейт карточки.
     * @param GameActionResourceResolver $resolver Компоненты.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly ICharacterSheets $characterSheets,
        private readonly GameCardAvailability $availability,
        private readonly GameActionResourceResolver $resolver,
    ) {
        $this->assembler = new CharacterChoiceAssembler();
        $this->grants = new CharacterAbilityGrantReader(new CharacterDonorGrants(), new CharacterRaceAbilityCodes());
    }

    /**
     * Проверяет запрос и собирает подготовку каста.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $body Тело команды.
     *
     * @return GameSpellCastPrepared Подготовка.
     *
     * @throws GameInvalidException Если карточка, путь, экземпляр или траты не подходят.
     */
    public function prepare(GameRecord $game, array $body): GameSpellCastPrepared
    {
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spellCode = $this->string($body, 'spellCode');
        $pathCode = $this->string($body, 'pathCode');
        $spell = $this->spellSpec($slice, $spellCode);
        $path = $this->pathSpec($slice, $pathCode);

        $actor = $this->side($body, 'actor');
        $availability = $this->availability->assertAvailable(
            $game,
            $actor['type'],
            $actor['id'],
            $spellCode,
            $pathCode,
        );
        if (!$availability instanceof AbilitySpellSpec) {
            throw new GameInvalidException('Game spell card is invalid');
        }

        $caster = $this->participant($game, $slice, $actor['type'], $actor['id']);
        $model = $this->model($caster['choices']);
        $this->assertInstance($model, $spellCode, $pathCode);

        $target = $this->target($game, $slice, $body, $spell);
        $parameters = $this->parameterMap($body, $spell);
        $requiredPower = $this->value($spell->getSpell()->getPower(), $parameters);
        $requiredControl = $this->value($spell->getSpell()->getControl(), $parameters);
        $characteristics = $this->characteristicsOf($caster['sheet']);
        $powerCode = $this->pathCharacteristicCode($path->getPowerCharacteristicCode());
        $controlCode = $this->pathCharacteristicCode($path->getControlCharacteristicCode());
        $usedPower = $this->usedPower($body, $path, $caster, $model, $characteristics, $powerCode, $slice);
        $availableControl = $characteristics[$controlCode] ?? null;
        if (!$availableControl instanceof CharacteristicNumber) {
            throw new GameInvalidException('Game spell control characteristic is missing');
        }

        $spends = $this->resolver->resolve(
            $slice,
            $spellCode,
            0,
            '',
            $caster['choices'],
            $caster['sheet'],
        );
        $spent = $this->spentOf($spends, $this->turnResourceCode($slice));

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
            $parameters,
            $usedPower,
            $requiredPower,
            $requiredControl,
            $availableControl,
            $spends,
            $spent,
            $this->int($body, 'distanceIpari', 0),
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
     * Ровно один экземпляр заклинания на пути.
     *
     * @param CharacterChoices $model Выборы.
     * @param string $spellCode Заклинание.
     * @param string $pathCode Путь.
     *
     * @return void
     *
     * @throws GameInvalidException Если экземпляра нет или их несколько.
     */
    private function assertInstance(CharacterChoices $model, string $spellCode, string $pathCode): void
    {
        $count = 0;
        foreach ($model->getAbilities() as $ability) {
            if ($ability->getRuleCode() === $spellCode && $ability->getDomainCode() === $pathCode) {
                $count++;
            }
        }

        if ($count !== 1) {
            throw new GameInvalidException('Game spell instance is invalid');
        }
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

        /** @var array<string, int|array{base: int, size: int}> $given */
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
     * Источник, значение мощи и потолок характеристики пути.
     *
     * @param array<string, mixed> $body Тело.
     * @param MagicPathSpec $path Путь.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>, version: int} $caster Кастер.
     * @param CharacterChoices $model Выборы.
     * @param array<string, CharacteristicNumber> $characteristics Характеристики.
     * @param string $powerCode Характеристика мощи.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return CharacteristicNumber Используемая мощь.
     *
     * @throws GameInvalidException Если источник чужой или мощь выше потолка.
     */
    private function usedPower(
        array $body,
        MagicPathSpec $path,
        array $caster,
        CharacterChoices $model,
        array $characteristics,
        string $powerCode,
        CharacterRuleSlice $slice,
    ): CharacteristicNumber {
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

        $cap = $characteristics[$powerCode] ?? null;
        if (!$cap instanceof CharacteristicNumber || $used->characteristicDiffTo($cap) > 0) {
            throw new GameInvalidException('Game spell used power is above the cap');
        }

        $this->assertSource($source, $caster, $model, $path, $slice);

        return $used;
    }

    /**
     * Принадлежность источника кастеру.
     *
     * @param array<string, mixed> $source Источник.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>, version: int} $caster Кастер.
     * @param CharacterChoices $model Выборы.
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
        CharacterChoices $model,
        MagicPathSpec $path,
        CharacterRuleSlice $slice,
    ): void {
        $kind = $source['kind'] ?? null;
        if ($kind === 'characteristic') {
            if (($source['code'] ?? null) !== $path->getPowerCharacteristicCode()) {
                throw new GameInvalidException('Game spell power source is invalid');
            }

            return;
        }

        if ($kind === 'item') {
            if (!$this->inventoryContains($caster['choices'], $source['inventoryId'] ?? 0)) {
                throw new GameInvalidException('Game spell power source is invalid');
            }

            return;
        }

        if ($kind === 'grant') {
            if (!$this->grantSourcePasses($caster, $model, $source['abilityCode'] ?? '', $slice)) {
                throw new GameInvalidException('Game spell power source is invalid');
            }

            return;
        }

        throw new GameInvalidException('Game spell power source is invalid');
    }

    /**
     * Строка инвентаря кастера.
     *
     * @param array<string, mixed> $choices Choices.
     * @param mixed $id Идентификатор.
     *
     * @return bool Наличие.
     */
    private function inventoryContains(array $choices, mixed $id): bool
    {
        if (!is_int($id)) {
            return false;
        }

        foreach (is_array($choices['inventory'] ?? null) ? $choices['inventory'] : [] as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Активная способность выдаёт предмет-источник.
     *
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>, version: int} $caster Кастер.
     * @param CharacterChoices $model Выборы.
     * @param mixed $abilityCode Код способности.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return bool Наличие.
     */
    private function grantSourcePasses(
        array $caster,
        CharacterChoices $model,
        mixed $abilityCode,
        CharacterRuleSlice $slice,
    ): bool {
        $rawLevels = is_array($caster['sheet']['abilityLevels'] ?? null) ? $caster['sheet']['abilityLevels'] : [];
        $levels = [];
        foreach ($rawLevels as $code => $level) {
            if (is_string($code) && is_int($level)) {
                $levels[$code] = $level;
            }
        }

        if (!is_string($abilityCode) || (($levels[$abilityCode] ?? 0) < 1)) {
            return false;
        }

        foreach ($this->grants->grants($slice, $model, $levels) as $row) {
            if ($row->getDonorCode() === $abilityCode && $row->getGrant() instanceof ItemGrant) {
                return true;
            }
        }

        return false;
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
