<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-49.php [--space=13] [--from=48] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 48);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$note = 'C49: у силовых параметров заклинаний снят верхний предел: потолок задаёт характеристика кастера, а не карточка.';

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'changed' => [],
    'kept' => 0,
    'errors' => [],
];

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    $spec = $record->getSpecDocument();
    if ($record->getType() !== 'ability' || ($spec['type'] ?? null) !== 'spell') {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $changed = false;
    $parameters = is_array($spec['parameters'] ?? null) ? $spec['parameters'] : [];
    foreach ($parameters as $index => $parameter) {
        if (
            !is_array($parameter)
            || ($parameter['resolution'] ?? null) !== 'activation'
            || ($parameter['kind'] ?? null) !== 'dimensional'
            || !array_key_exists('max', $parameter)
        ) {
            continue;
        }

        unset($parameters[$index]['max']);
        $changed = true;
    }

    if (!$changed) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $spec['parameters'] = $parameters;
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

if ($stats['errors'] !== []) {
    fwrite(STDERR, "Есть ошибки разбора; публикация остановлена.\n");
    exit(2);
}

if ($stats['changed'] === []) {
    fwrite(STDERR, "Силовые параметры заклинаний уже без верхнего предела.\n");
    exit(3);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);
