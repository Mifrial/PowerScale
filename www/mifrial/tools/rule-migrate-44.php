<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-44.php [--space=13] [--from=43] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 43);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$maneuver = [
    'min_success' => 3,
    'min_weapon_mastery' => 3,
    'effects' => [['kind' => 'state', 'state' => 'unstable', 'amount' => 1]],
];

$boost = [
    'maneuvers' => ['zastavit-otkrytsya', 'razbit-zaschitu'],
    'min_success' => 4,
    'min_weapon_mastery' => 5,
    'replaces' => [['kind' => 'state', 'state' => 'unstable']],
    'effects' => [['kind' => 'state', 'state' => 'unstable', 'amount' => 2]],
];

$requirements = [
    'razbit-zaschitu' => [[
        'level' => 1,
        'requirements' => [[
            'type' => 'min_weapon_mastery',
            'keyword_code' => 'weapon',
            'min_level' => 3,
        ]
        ],
    ]
    ],
    'vskryt-slabost' => [[
        'level' => 1,
        'requirements' => [[
            'type' => 'and',
            'children' => [
                [
                    'type' => 'min_weapon_mastery',
                    'keyword_code' => 'weapon',
                    'min_level' => 5,
                ],
                [
                    'type' => 'or',
                    'children' => [
                        ['type' => 'has_ability', 'ability_code' => 'zastavit-otkrytsya'],
                        ['type' => 'has_ability', 'ability_code' => 'razbit-zaschitu'],
                    ],
                ],
            ],
        ]
        ],
    ]
    ],
];

$descriptions = [
    'razbit-zaschitu' => 'Вы можете применить этот манёвр при ударе 3РУ оружием, '
        . 'для которого мастерство боя с оружием не ниже 3.'
        . "\n" . 'Цель атаки получает Неустойчивость 1.',
    'vskryt-slabost' => 'При 4РУ манёвр Заставить открыться и Разбить защиту дают цели '
        . 'Неустойчивость 2, если удар нанесён оружием с мастерством боя с оружием не ниже 5. '
        . 'При этом Заставить открыться позволяет провести атаку за 4ОД.',
];

$notes = [
    'razbit-zaschitu' => 'S1: манёвр — действие сразу после удара. Эффект описывает состояние '
        . 'данными, мастерство оружия удара проверяется исполнением.',
    'vskryt-slabost' => 'S1: усиление подменяет эффект манёвра. Требования покупки — '
        . 'мастерство оружия 5 и один из манёвров.',
];

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'changed' => [],
    'kept' => 0,
    'errors' => [],
];

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    if (!isset($notes[$code])) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $spec = $record->getSpecDocument();
    if ($code === 'razbit-zaschitu') {
        unset($spec['strike_maneuver']);
        $spec['strike_maneuver'] = $maneuver;
    } else {
        unset($spec['maneuver_boost']);
        $spec['maneuver_boost'] = $boost;
    }

    $spec['requirements'] = $requirements[$code];

    $read = RuleSpecs::read($record->getType(), $spec);
    if ($read->isBroken()) {
        $stats['errors'][] = $code . ': ' . $read->getShapeKey();
    }

    $stats['changed'][] = $code;
    $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $descriptions[$code],
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        $notes[$code],
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

if ($stats['changed'] === []) {
    fwrite(STDERR, "Карточки манёвров не найдены; публикация остановлена.\n");
    exit(3);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);
