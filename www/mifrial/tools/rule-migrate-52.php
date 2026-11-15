<?php

declare(strict_types=1);

use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-52.php [--space=13] [--from=51] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 51);
$to = $from + 1;
$commit = isset($options['commit']);

$speciesCode = 'ptitselyud';
$raceCode = 'yappi';
$resistanceCode = 'electricity-resistance';
$note = 'C52: вид Птицелюд и раса Яппи — врождённый кристалл, сопротивление магии и электричеству.';

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$keywords = $locator->get(IKeywordContainer::class)->get(IKeywords::class);
if (!$keywords instanceof IKeywords) {
    fwrite(STDERR, "Не удалось получить сервис признаков\n");
    exit(1);
}

$humanoidKeyword = keywordId($keywords, 'humanoid', 'Гуманоид');
$speciesKeyword = keywordId($keywords, 'ptitselyud', 'Птицелюд');
$raceKeyword = keywordId($keywords, 'yappi', 'Яппи');
$racialKeyword = keywordId($keywords, 'racial', 'Расовое');
$traitKeyword = keywordId($keywords, 'trait', 'Черта');

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'changed' => [],
    'kept' => 0,
    'errors' => [],
];

$present = [];
foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    $present[$code] = true;

    if ($code === $speciesCode) {
        $stats['changed'][] = $code;
        $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
            'species',
            'Птицелюд',
            'Вид птицелюдей: гуманоидное тело, лёгкий шаг и врождённая склонность к магии.',
            speciesSpec(),
            [$humanoidKeyword, $speciesKeyword],
            [],
            'needs_work',
            $note,
        ));
        continue;
    }

    if ($code === $raceCode) {
        $stats['changed'][] = $code;
        $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
            'race',
            'Яппи',
            'Небольшие молниевые птички: крошечное тело, врождённый магический кристалл и сопротивление электричеству.',
            raceSpec(),
            [$humanoidKeyword, $speciesKeyword, $raceKeyword],
            [],
            'needs_work',
            $note,
        ));
        continue;
    }

    if ($code === $resistanceCode) {
        $stats['changed'][] = $code;
        $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
            'ability',
            'Сопротивление электричеству',
            'Сопротивление урону электричеством величиной X.',
            resistanceSpec(),
            [$racialKeyword, $traitKeyword],
            [],
            'needs_work',
            $note,
        ));
        continue;
    }

    ++$stats['kept'];
    $entries[] = RuleCommitEntry::keep($record->getVersionId());
}

if (!isset($present[$speciesCode])) {
    $stats['changed'][] = $speciesCode;
    $entries[] = RuleCommitEntry::put($speciesCode, new RuleVersionBody(
        'species',
        'Птицелюд',
        'Вид птицелюдей: гуманоидное тело, лёгкий шаг и врождённая склонность к магии.',
        speciesSpec(),
        [$humanoidKeyword, $speciesKeyword],
        [],
        'needs_work',
        $note,
    ));
}

if (!isset($present[$raceCode])) {
    $stats['changed'][] = $raceCode;
    $entries[] = RuleCommitEntry::put($raceCode, new RuleVersionBody(
        'race',
        'Яппи',
        'Небольшие молниевые птички: крошечное тело, врождённый магический кристалл и сопротивление электричеству.',
        raceSpec(),
        [$humanoidKeyword, $speciesKeyword, $raceKeyword],
        [],
        'needs_work',
        $note,
    ));
}

if (!isset($present[$resistanceCode])) {
    $stats['changed'][] = $resistanceCode;
    $entries[] = RuleCommitEntry::put($resistanceCode, new RuleVersionBody(
        'ability',
        'Сопротивление электричеству',
        'Сопротивление урону электричеством величиной X.',
        resistanceSpec(),
        [$racialKeyword, $traitKeyword],
        [],
        'needs_work',
        $note,
    ));
}

foreach ([[$speciesCode, 'species', speciesSpec()], [$raceCode, 'race', raceSpec()], [$resistanceCode, 'ability', resistanceSpec()]] as [$code, $type, $spec]) {
    $read = RuleSpecs::read($type, $spec);
    if ($read->isBroken()) {
        $stats['errors'][] = $code . ': ' . $read->getShapeKey();
    }
}

fwrite(STDOUT, json_encode([
    'space' => $spaceId,
    'from' => $from,
    'to' => $to,
    'commit' => $commit,
    'stats' => $stats,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);

if ($stats['errors'] !== []) {
    fwrite(STDERR, "Есть ошибки разбора; публикация остановлена.\n");
    exit(2);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);

/**
 * Id признака по коду; отсутствующий создаётся.
 *
 * @param IKeywords $keywords Каталог.
 * @param string $code Код.
 * @param string $name Подпись.
 *
 * @return int Id.
 */
function keywordId(IKeywords $keywords, string $code, string $name): int
{
    try {
        return $keywords->getByCode($code)->getId();
    } catch (KeywordNotFoundException) {
        return $keywords->add($code, $name);
    }
}

/**
 * Спека вида Птицелюд.
 *
 * @return array<string, mixed> Спека.
 */
function speciesSpec(): array
{
    return [
        'parent_race_code' => null,
        'abilities' => [['automatic' => true, 'ability_code' => 'humanoid-body']],
        'age_years' => [],
    ];
}

/**
 * Спека расы Яппи.
 *
 * @return array<string, mixed> Спека.
 */
function raceSpec(): array
{
    return [
        'parent_race_code' => 'ptitselyud',
        'cost_os' => 0,
        'characteristics' => [
            ['mode' => 'fixed', 'characteristic_code' => 'strength', 'base' => ['base' => 5, 'size' => -2]],
            ['mode' => 'fixed', 'characteristic_code' => 'endurance', 'base' => ['base' => 3, 'size' => -1]],
            ['mode' => 'fixed', 'characteristic_code' => 'dexterity', 'base' => ['base' => 4, 'size' => -1]],
            ['mode' => 'fixed', 'characteristic_code' => 'reaction', 'base' => ['base' => 3, 'size' => 1]],
            ['mode' => 'fixed', 'characteristic_code' => 'attention', 'base' => ['base' => 3, 'size' => 1]],
            ['mode' => 'fixed', 'characteristic_code' => 'reasoning', 'base' => ['base' => 4, 'size' => 0]],
            ['mode' => 'fixed', 'characteristic_code' => 'memory', 'base' => ['base' => 3, 'size' => -1]],
        ],
        'abilities' => [
            ['automatic' => true, 'ability_code' => 'small-step'],
            ['automatic' => true, 'ability_code' => 'magic-resistance', 'parameters' => [
                'x' => ['base' => 3, 'size' => 0],
            ]],
            ['automatic' => false, 'ability_code' => 'magic-resistance', 'parameters' => [
                'x' => ['base' => 6, 'size' => 0],
            ]],
            ['automatic' => false, 'ability_code' => 'magic-crystal-capacity', 'parameters' => [
                'x' => ['base' => 4, 'size' => -1],
            ]],
            ['automatic' => false, 'ability_code' => 'magic-crystal-capacity', 'parameters' => [
                'x' => ['base' => 5, 'size' => 0],
            ]],
            ['automatic' => true, 'ability_code' => 'electricity-resistance', 'parameters' => [
                'x' => ['base' => 4, 'size' => 0],
            ]],
        ],
        'movement_step' => ['base' => 1, 'size' => 0],
    ];
}

/**
 * Спека сопротивления электричеству.
 *
 * @return array<string, mixed> Спека.
 */
function resistanceSpec(): array
{
    return [
        'type' => 'trait',
        'zones' => ['os' => [
            'kind' => 'parameter',
            'per_unit' => 1,
            'parameter_code' => 'x',
        ]],
        'grants' => [[
            'level' => 1,
            'grants' => [[
                'type' => 'resistance',
                'value' => [
                    'type' => 'parameter',
                    'per_unit' => 1,
                    'parameter_code' => 'x',
                ],
                'source_code' => 'innate',
                'damage_type_code' => 'electricity',
            ]],
        ]],
        'parameters' => [[
            'code' => 'x',
            'label' => 'X',
            'kind' => 'scalar',
            'resolution' => 'purchase',
            'default' => 1,
            'min' => 0,
            'max' => 5,
        ]],
        'requirements' => [],
        'parent_ability_code' => null,
    ];
}
