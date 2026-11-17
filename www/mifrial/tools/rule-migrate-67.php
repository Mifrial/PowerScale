<?php

declare(strict_types=1);

use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-67.php [--space=13] [--from=66] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 66);
$commit = isset($options['commit']);

$root = dirname(__DIR__);
$app = require $root . '/init.php';
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

$keywordIds = [];
foreach ([
    'hearing' => 'Слух',
    'nervous-system' => 'Нервная система',
    'movement' => 'Движение',
] as $code => $name) {
    try {
        $keywordIds[$code] = $keywords->getByCode($code)->getId();
    } catch (KeywordNotFoundException) {
        if (!$commit) {
            fwrite(STDERR, 'Нет признака ' . $code . '; для проверки нужен --commit' . PHP_EOL);
            exit(1);
        }

        $keywordIds[$code] = $keywords->add($code, $name);
    }
}

$states = [
    'deafness' => [
        'name' => 'Глухота',
        'description' => 'Потеря слуха: персонаж не слышит, пока действует эффект.',
        'spec' => [
            'effects' => [[
                'type' => 'sense_status',
                'sense_code' => 'sense-hearing',
                'status' => 'absent',
            ]],
            'icon_code' => 'mdi-ear-hearing-off',
            'value_type' => 'flag',
            'aggregation' => 'independent',
        ],
        'keywordIds' => [$keywordIds['hearing'], $keywordIds['nervous-system']],
        'note' => 'Состояние для заклинания «Глухота живого» (M20.2).',
    ],
    'clumsiness' => [
        'name' => 'Неуклюжесть',
        'description' => 'Нарушение координации: −3 к Восприятию и помеха проверкам Ловкости, Реакции и броскам попадания.',
        'spec' => [
            'effects' => [
                [
                    'type' => 'characteristic_modify',
                    'amount' => -3,
                    'characteristic_code' => 'perception',
                ],
                [
                    'type' => 'check_advantage',
                    'amount' => -1,
                    'includes_hit' => true,
                    'characteristic_codes' => ['dexterity', 'reaction'],
                ],
            ],
            'icon_code' => 'mdi-human-handsdown',
            'value_type' => 'flag',
            'aggregation' => 'independent',
        ],
        'keywordIds' => [$keywordIds['nervous-system'], $keywordIds['movement']],
        'note' => 'Состояние для заклинания «Неуклюжесть живого» (M20.2).',
    ],
];

$spells = [
    'living-deafness' => 'deafness',
    'living-clumsiness' => 'clumsiness',
];

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'updated' => [],
    'added' => [],
    'kept' => 0,
    'missing' => array_keys($spells),
];
foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    if (!isset($spells[$code])) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    $spec = $record->getSpecDocument();
    if (!is_array($spec['spell'] ?? null)) {
        fwrite(STDERR, $code . ": spell не объект\n");
        exit(2);
    }

    $stats['missing'] = array_values(array_diff($stats['missing'], [$code]));
    $spec['spell']['effects'] = [['type' => 'state', 'state_code' => $spells[$code]]];
    $stats['updated'][] = $code;
    $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $record->getDescription(),
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        'Состояние ' . $spells[$code] . ' на срок lingering (M20.2).',
    ));
}

foreach ($states as $code => $state) {
    $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
        'state',
        $state['name'],
        $state['description'],
        $state['spec'],
        $state['keywordIds'],
        [],
        'needs_work',
        $state['note'],
    ));
    $stats['added'][] = $code;
}

fwrite(STDOUT, json_encode([
    'space' => $spaceId,
    'from' => $from,
    'to' => $from + 1,
    'commit' => $commit,
    'stats' => $stats,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);

if ($stats['missing'] !== []) {
    fwrite(STDERR, 'Не найдены карточки: ' . implode(', ', $stats['missing'])
        . '; публикация остановлена.' . PHP_EOL);
    exit(2);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);
