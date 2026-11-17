<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-65.php [--space=13] [--from=64] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 64);
$commit = isset($options['commit']);

$root = dirname(__DIR__);
$app = require $root . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$state = [
    'effects' => [[
        'type' => 'sense_status',
        'sense_code' => 'sense-vision',
        'status' => 'absent',
    ]],
    'icon_code' => 'mdi-eye-off-outline',
    'value_type' => 'flag',
    'aggregation' => 'independent',
];

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'updated' => [],
    'kept' => 0,
    'missing' => ['blindness'],
];
foreach ($slice->getItems() as $record) {
    if ($record->getCode() !== 'blindness') {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    $stats['missing'] = [];
    $stats['updated'][] = $record->getCode();
    $entries[] = RuleCommitEntry::put($record->getCode(), new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        'Потеря зрения: персонаж не видит, пока действует эффект.',
        $state,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        'Слепота лишает чувства зрения (M20.2).',
    ));
}

fwrite(STDOUT, json_encode([
    'space' => $spaceId,
    'from' => $from,
    'to' => $from + 1,
    'commit' => $commit,
    'stats' => $stats,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);

if ($stats['missing'] !== []) {
    fwrite(STDERR, "Не найдена карточка blindness; публикация остановлена.\n");
    exit(2);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);
