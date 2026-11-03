<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-32.php [--space=13] [--from=31] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 31);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$supportNote = 'Поддержка объявляется только в открытом одиночном ближнем ударе до ответа цели. '
    . 'Даёт преимущество +2 от обстоятельств выбранной стороне. Нужно выбрать своё надетое '
    . 'оружие ближнего боя с мастерством не ниже 4|0. Дистанция до цели не проверяется: '
    . 'battleground нет, ограничение «в пределах дистанции оружия» не реализовано.';

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'changed' => [],
    'dropped' => [],
    'kept' => 0,
    'errors' => [],
];

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    if ($code === 'koordinatsiya') {
        $stats['dropped'][] = $code;
        continue;
    }

    if ($code !== 'podderzhka-v-boyu') {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $spec = $record->getSpecDocument();
    $spec['support'] = [
        'sides' => ['attacker', 'defender'],
        'advantage' => 2,
        'profile' => 'strike',
        'min_mastery' => ['base' => 4, 'size' => 0],
        'source' => 'circumstances',
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
        $supportNote,
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
