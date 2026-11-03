<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-28.php [--space=13] [--from=27] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 27);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$comboDescription = 'Процесс из трёх шагов по одной цели. '
    . 'Начало (3 ОД): удар с одной помехой на попадание от действия; попадание даёт Комбо 1, '
    . 'промах заканчивает процесс. '
    . 'Набор (2 ОД): помех от действия столько, сколько текущее Комбо; попадание увеличивает '
    . 'Комбо на 1, промах заканчивает процесс. '
    . 'Завершение (2 ОД, при Комбо не меньше 2): +[Комбо] преимуществ от действия (потолок 3) '
    . 'и +⌊Комбо/2⌋ успехов размера броска (потолок 3) поверх правила 6 и 1; процесс '
    . 'заканчивается и при попадании, и при промахе. '
    . 'Комбо живёт только в этом процессе и только против выбранной цели.';

$openingDescription = 'При Комбо не меньше 2 вместо Завершения комбо можно объявить другое своё '
    . 'атакующее действие по той же цели. На всю атаку (все попадания мультиудара) садится пакет '
    . 'Завершения: преимущества и доп. успехи размера броска. Стоимость — ОД той атаки. Процесс '
    . 'заканчивается при попадании и при промахе. Собственные эффекты атаки не вычёркиваются, '
    . 'но пакет идёт с источником «действие»: одноимённая дельта группы не складывается.';

$comboStrike = [
    'advantage' => ['type' => 'counter', 'counter_code' => 'combo', 'max_value' => 3],
    'extra_successes' => [
        'type' => 'counter',
        'counter_code' => 'combo',
        'divisor' => 2,
        'max_value' => 3,
    ],
];

$specs = [
    'kombinatsiya-udarov' => [
        'description' => $comboDescription,
        'contentNote' => 'Game: три шага Комбо на сессии, пакет закрытия от действия.',
        'spec' => [
            'type' => 'process',
            'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
            'requirements' => [
                [
                    'level' => 1,
                    'requirements' => [
                        [
                            'type' => 'and',
                            'children' => [
                                ['type' => 'has_ability', 'ability_code' => 'bystryy-udar', 'min_level' => 1],
                                ['type' => 'has_ability', 'ability_code' => 'tochnyy-udar', 'min_level' => 1],
                            ],
                        ],
                    ],
                ],
            ],
            'grants' => [],
            'parent_ability_code' => null,
            'process' => [
                'start_step_code' => 'combo-start',
                'exit_step_codes' => ['combo-finish'],
                'counters' => [['counter_code' => 'combo', 'initial' => 0]],
                'target_mode' => 'single',
                'transition' => [
                    'mode' => 'custom',
                    'edges' => [
                        ['from' => 'combo-start', 'to' => 'combo-build'],
                        ['from' => 'combo-build', 'to' => 'combo-build'],
                        ['from' => 'combo-build', 'to' => 'combo-finish'],
                    ],
                ],
                'failure' => 'end_action',
                'steps' => [
                    [
                        'code' => 'combo-start',
                        'name' => 'Начало комбо',
                        'description' => 'Совершите удар с одной помехой на попадание от действия. '
                            . 'При попадании Комбо становится 1. При промахе процесс заканчивается.',
                        'interruption' => ['mode' => 'normal'],
                        'costs' => [['resource_code' => 'action-points', 'amount' => 3]],
                        'execution' => ['type' => 'strike'],
                        'strike' => ['advantage' => ['type' => 'fixed', 'value' => -1]],
                        'counter_on_success' => [['counter_code' => 'combo', 'delta' => 1]],
                    ],
                    [
                        'code' => 'combo-build',
                        'name' => 'Набор комбо',
                        'description' => 'Совершите удар с числом помех на попадание от действия, '
                            . 'равным текущему Комбо. При попадании Комбо увеличивается на 1. '
                            . 'При промахе процесс заканчивается.',
                        'interruption' => ['mode' => 'normal'],
                        'costs' => [['resource_code' => 'action-points', 'amount' => 2]],
                        'execution' => ['type' => 'strike'],
                        'strike' => [
                            'advantage' => [
                                'type' => 'counter',
                                'counter_code' => 'combo',
                                'factor' => -1,
                            ],
                        ],
                        'counter_on_success' => [['counter_code' => 'combo', 'delta' => 1]],
                    ],
                    [
                        'code' => 'combo-finish',
                        'name' => 'Завершение комбо',
                        'description' => 'Доступно при Комбо не меньше 2. Удар получает +[Комбо] '
                            . 'преимуществ от действия (потолок 3) и +⌊Комбо/2⌋ успехов размера броска '
                            . '(потолок 3) поверх правила 6 и 1. Процесс заканчивается.',
                        'interruption' => ['mode' => 'normal'],
                        'costs' => [['resource_code' => 'action-points', 'amount' => 2]],
                        'execution' => ['type' => 'strike'],
                        'requires_counters' => [['counter_code' => 'combo', 'min' => 2]],
                        'strike' => $comboStrike,
                        'counter_on_success' => [['counter_code' => 'combo', 'set' => 0]],
                    ],
                ],
            ],
        ],
    ],
    'raskrytie' => [
        'description' => $openingDescription,
        'contentNote' => 'Game: закрытие Комбо чужой атакой с пакетом Завершения на всю атаку.',
        'spec' => [
            'type' => 'skill',
            'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
            'requirements' => [],
            'grants' => [],
            'parent_ability_code' => 'kombinatsiya-udarov',
            'closes_parent_process' => [
                'requires_counters' => [['counter_code' => 'combo', 'min' => 2]],
                'strike' => $comboStrike,
            ],
        ],
    ],
];

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'changed' => [],
    'kept' => 0,
    'errors' => [],
];
$codes = [];

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    $codes[$code] = true;
    $patch = $specs[$code] ?? null;
    if ($patch === null) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $read = RuleSpecs::read($record->getType(), $patch['spec']);
    if ($read->isBroken()) {
        $stats['errors'][] = $code . ': ' . $read->getShapeKey();
    }

    $stats['changed'][] = $code;
    $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $patch['description'],
        $patch['spec'],
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        $patch['contentNote'],
    ));
}

foreach (array_keys($specs) as $code) {
    if (!isset($codes[$code])) {
        $stats['errors'][] = $code . ': карточка не найдена в ревизии ' . $from;
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
