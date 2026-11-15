<?php

declare(strict_types=1);

use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-54.php [--space=13] [--from=53] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 53);
$to = $from + 1;
$commit = isset($options['commit']);

$note = 'C54: кристалл принимает только заклинания электромансии и не сохраняется без записей.';

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
    $keywords->getByCode('characteristic')->getId(),
    $keywords->getByCode('gift')->getId(),
    $keywords->getByCode('innate')->getId(),
    $keywords->getByCode('trait')->getId(),
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

    if ($code === 'magic-crystal') {
        $stats['changed'][] = $code;
        $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
            'item',
            'Кристалл-хранилище',
            'Кристалл-хранилище магии: Предельная мощь, Объём, записи заклинаний и мана. Принимает только заклинания электромансии.',
            crystalSpec(),
            [],
            [],
            'needs_work',
            $note,
        ));
        continue;
    }

    if ($code === 'magic-crystal-capacity') {
        $stats['changed'][] = $code;
        $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
            'ability',
            'Врождённый кристалл',
            'Кристалл-хранилище магии: предел мощи, Объём и записи заклинаний электромансии. Даёт путь простейшего волшебства.',
            crystalCapacitySpec(),
            $traitKeywords,
            [],
            'needs_work',
            $note,
        ));
        continue;
    }

    ++$stats['kept'];
    $entries[] = RuleCommitEntry::keep($record->getVersionId());
}

foreach (['magic-crystal', 'magic-crystal-capacity'] as $code) {
    if (!isset($present[$code])) {
        $stats['errors'][] = $code . ': rule is missing in the source revision';
    }
}

$read = RuleSpecs::read('item', crystalSpec());
if ($read->isBroken()) {
    $stats['errors'][] = 'magic-crystal: ' . $read->getShapeKey();
}

$read = RuleSpecs::read('ability', crystalCapacitySpec());
if ($read->isBroken()) {
    $stats['errors'][] = 'magic-crystal-capacity: ' . $read->getShapeKey();
}

fwrite(STDOUT, json_encode([
    'space' => $spaceId,
    'from' => $from,
    'to' => $to,
    'commit' => $commit,
    'stats' => $stats,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);

if ($stats['errors'] !== []) {
    fwrite(STDERR, "Есть ошибки; публикация остановлена.\n");
    exit(2);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);

/**
 * Спека предмета-кристалла с ограничением записей электромансией.
 *
 * @return array<string, mixed> Спека.
 */
function crystalSpec(): array
{
    return [
        'innate' => true,
        'weight' => null,
        'cost_gm' => null,
        'storage' => [
            'volume_base' => 20,
            'power_parameter' => 'max_power',
            'volume_increment' => 10,
            'volume_parameter' => 'volume',
            'recorded_parameter' => 'recorded_spells',
            'min_volume_power_delta' => 3,
            'recorded_keywords' => ['electromancy'],
        ],
        'category' => 'equipment',
        'special_rule_codes' => [],
    ];
}

/**
 * Спека трейта «Врождённый кристалл».
 *
 * @return array<string, mixed> Спека.
 */
function crystalCapacitySpec(): array
{
    return [
        'type' => 'trait',
        'zones' => [
            'os' => [
                'kind' => 'parameter_sum_tables',
                'tables' => [
                    'x' => [
                        '3' => 4,
                        '4' => 6,
                        '5' => 8,
                        '3↑' => 12,
                        '3↓' => 1,
                        '4↑' => 16,
                        '4↓' => 2,
                        '5↑' => 20,
                        '5↓' => 3,
                    ],
                    'volume' => [
                        '20' => 0,
                        '30' => 1,
                        '40' => 2,
                        '50' => 3,
                        '60' => 4,
                        '70' => 5,
                        '80' => 6,
                        '90' => 7,
                        '100' => 8,
                        '110' => 9,
                        '120' => 10,
                        '130' => 11,
                        '140' => 12,
                        '150' => 13,
                        '160' => 14,
                        '170' => 15,
                        '180' => 16,
                        '190' => 17,
                        '200' => 18,
                    ],
                ],
                'max_level' => 205,
            ],
        ],
        'grants' => [
            [
                'level' => 1,
                'grants' => [
                    [
                        'type' => 'item',
                        'payload' => ['volume' => 'volume'],
                        'quantity' => 1,
                        'item_code' => 'magic-crystal',
                        'parameters' => ['max_power' => 'x'],
                    ],
                    [
                        'type' => 'magic_path',
                        'path_code' => 'simple-magic',
                    ],
                ],
            ],
        ],
        'parameters' => [
            [
                'max' => ['base' => 5, 'size' => 1],
                'min' => ['base' => 3, 'size' => -1],
                'code' => 'x',
                'kind' => 'dimensional',
                'label' => 'Предельная магическая мощь',
                'default' => ['base' => 3, 'size' => 0],
                'resolution' => 'purchase',
            ],
            [
                'max' => 200,
                'min' => 20,
                'code' => 'volume',
                'kind' => 'scalar',
                'label' => 'Объём кристалла',
                'default' => 20,
                'resolution' => 'purchase',
            ],
        ],
        'requirements' => [],
        'parent_ability_code' => null,
    ];
}
