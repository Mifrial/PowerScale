<?php

declare(strict_types=1);

use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-51.php [--space=13] [--from=50] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 50);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$crystalCode = 'magic-crystal';
$capacityCode = 'magic-crystal-capacity';
$pathCode = 'simple-magic';
$note = 'C51: кристалл-хранилище — Предельная мощь, Объём, записи и путь простейшего волшебства.';

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

$traitKeywords = [
    keywordId($keywords, 'characteristic', 'Характеристика'),
    keywordId($keywords, 'gift', 'Дар'),
    keywordId($keywords, 'innate', 'Врождённое'),
    keywordId($keywords, 'trait', 'Черта'),
];

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
    if ($code === $crystalCode) {
        $stats['changed'][] = $code;
        $entries[] = put($record, crystalSpec(), $note, $stats);
        continue;
    }

    if ($code === $capacityCode) {
        $stats['changed'][] = $code;
        $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
            'ability',
            'Врождённый кристалл',
            'Кристалл-хранилище магии: предел мощи, Объём и записи заклинаний. Даёт путь простейшего волшебства.',
            capacitySpec($capacityCode),
            $traitKeywords,
            [],
            'needs_work',
            $note,
        ));
        continue;
    }

    if ($code === $pathCode) {
        $stats['changed'][] = $code;
        $entries[] = put($record, pathSpec(), $note, $stats);
        continue;
    }

    ++$stats['kept'];
    $entries[] = RuleCommitEntry::keep($record->getVersionId());
}

if (!isset($present[$crystalCode])) {
    $stats['changed'][] = $crystalCode;
    $entries[] = RuleCommitEntry::put($crystalCode, new RuleVersionBody(
        'item',
        'Кристалл-хранилище',
        'Врождённый кристалл магии: Предельная мощь, Объём, записи заклинаний и мана.',
        crystalSpec(),
        [],
        [],
        'needs_work',
        $note,
    ));
}

if (!isset($present[$capacityCode])) {
    $stats['changed'][] = $capacityCode;
    $entries[] = RuleCommitEntry::put($capacityCode, new RuleVersionBody(
        'ability',
        'Врождённый кристалл',
        'Кристалл-хранилище магии: предел мощи, Объём и записи заклинаний. Даёт путь простейшего волшебства.',
        capacitySpec($capacityCode),
        $traitKeywords,
        [],
        'needs_work',
        $note,
    ));
}

if (!isset($present[$pathCode])) {
    $stats['changed'][] = $pathCode;
    $entries[] = RuleCommitEntry::put($pathCode, new RuleVersionBody(
        'magic_path',
        'Простейшее волшебство',
        'Каст из записей кристалла без Контроля магии.',
        pathSpec(),
        [],
        [],
        'needs_work',
        $note,
    ));
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
 * Спека предмета-кристалла.
 *
 * @return array<string, mixed> Спека.
 */
function crystalSpec(): array
{
    return [
        'category' => 'equipment',
        'cost_gm' => null,
        'weight' => null,
        'innate' => true,
        'special_rule_codes' => [],
        'storage' => [
            'power_parameter' => 'max_power',
            'volume_parameter' => 'volume',
            'recorded_parameter' => 'recorded_spells',
            'volume_base' => 20,
            'volume_increment' => 10,
            'min_volume_power_delta' => 3,
        ],
    ];
}

/**
 * Спека врождённого кристалла.
 *
 * @param string $code Код карточки.
 *
 * @return array<string, mixed> Спека.
 */
function capacitySpec(string $code): array
{
    return [
        'type' => 'trait',
        'zones' => ['os' => [
            'kind' => 'parameter_sum_tables',
            'tables' => [
                'x' => powerCosts(),
                'volume' => volumeCosts(),
            ],
            'max_level' => 205,
        ]],
        'grants' => [[
            'level' => 1,
            'grants' => [[
                'type' => 'item',
                'item_code' => 'magic-crystal',
                'quantity' => 1,
                'parameters' => ['max_power' => 'x'],
                'payload' => ['volume' => 'volume'],
            ], [
                'type' => 'magic_path',
                'path_code' => 'simple-magic',
            ]],
        ]],
        'parameters' => [[
            'code' => 'x',
            'label' => 'Предельная магическая мощь',
            'kind' => 'dimensional',
            'resolution' => 'purchase',
            'default' => ['base' => 3, 'size' => 0],
            'min' => ['base' => 3, 'size' => -1],
            'max' => ['base' => 5, 'size' => 1],
        ], [
            'code' => 'volume',
            'label' => 'Объём кристалла',
            'kind' => 'scalar',
            'resolution' => 'purchase',
            'default' => 20,
            'min' => 20,
            'max' => 200,
        ]],
        'requirements' => [],
        'parent_ability_code' => null,
    ];
}

/**
 * Цена Предельной мощи: ступени размерного значения.
 *
 * @return array<string, int> Таблица.
 */
function powerCosts(): array
{
    return ['3' => 4, '4' => 6, '5' => 8, '3↑' => 12, '3↓' => 1, '4↑' => 16, '4↓' => 2, '5↑' => 20, '5↓' => 3];
}

/**
 * Цена Объёма: 20 бесплатно, каждые 10 сверх — +1.
 *
 * @return array<string, int> Таблица.
 */
function volumeCosts(): array
{
    $costs = [];
    for ($volume = 20; $volume <= 200; $volume += 10) {
        $costs[(string) $volume] = (int) (($volume - 20) / 10);
    }

    return $costs;
}

/**
 * Спека пути простейшего волшебства.
 *
 * @return array<string, mixed> Спека.
 */
function pathSpec(): array
{
    return [
        'check_code' => 'check-spell-cast',
        'cast_check_code' => 'check-spell-cast',
        'power_characteristic_code' => 'magic-power',
        'control_characteristic_code' => null,
        'includes_path_codes' => [],
        'no_control' => true,
        'recorded_casting' => true,
    ];
}

/**
 * Новый снимок карточки с прежними полями.
 *
 * @param RuleVersionRecord $record Запись.
 * @param array<string, mixed> $spec Новая спека.
 * @param string $note Комментарий.
 * @param array{errors: array<int, string>} $stats Счётчики.
 *
 * @return RuleCommitEntry Запись.
 */
function put(RuleVersionRecord $record, array $spec, string $note, array &$stats): RuleCommitEntry
{
    $read = RuleSpecs::read($record->getType(), $spec);
    if ($read->isBroken()) {
        $stats['errors'][] = $record->getCode() . ': ' . $read->getShapeKey();
    }

    return RuleCommitEntry::put($record->getCode(), new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $record->getDescription(),
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        $note,
    ));
}
