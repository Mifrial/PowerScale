<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-36.php [--space=13] [--from=35] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 35);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$note = 'Прикрытие: defense_advantages с source_code вместо circumstance_delta; '
    . 'заявка реакции третьей стороны до ответа цели; оплата при старте, '
    . 'а не при объявлении.';

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'changed' => [],
    'kept' => 0,
    'errors' => [],
];

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    if ($code !== 'prikrytie') {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $spec = $record->getSpecDocument();
    $spec['reaction'] = [
        'trigger' => 'strike',
        'window' => 'before_defense',
        'performer' => 'third_party',
    ];
    $spec['cover_ally'] = [
        'defense_advantages' => [
            ['delta' => -1, 'source_code' => 'circumstances'],
        ],
        'reactions' => ['block'],
        'resolution' => [
            'success' => 'highest_rating',
            'failure' => 'attacker_choice',
            'becomes' => 'actual_target',
        ],
        'distance' => 'declared',
    ];

    $read = RuleSpecs::read($record->getType(), $spec);
    if ($read->isBroken()) {
        $stats['errors'][] = $code . ': ' . $read->getShapeKey();
    }

    $stats['changed'][] = $code;
    $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
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

fwrite(STDOUT, json_encode([
    'space' => $spaceId,
    'from' => $from,
    'to' => $to,
    'commit' => $commit,
    'stats' => $stats,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);

if ($stats['changed'] === []) {
    fwrite(STDERR, "Карточка prikrytie не найдена; публикация остановлена.\n");
    exit(3);
}

if ($stats['errors'] !== []) {
    fwrite(STDERR, "Есть ошибки разбора; публикация остановлена.\n");
    exit(2);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);
