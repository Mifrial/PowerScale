<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-61.php [--space=13] [--from=60] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 60);
$commit = isset($options['commit']);

$codes = [
    'tearing',
    'runny-nose',
    'living-itch',
    'heightened-senses',
    'living-numbness',
    'magic-absorbers',
    'humanoid-nausea',
    'humanoid-distraction',
    'living-weakness',
    'living-clumsiness',
    'living-blindness',
    'living-deafness',
    'living-paralysis',
    'living-decay',
    'accelerated-metabolism',
];
$drop = ' Длительность lingering не поддержана runtime.';

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
    'updated' => [],
    'kept' => 0,
    'missing' => $codes,
    'errors' => [],
];
$present = [];
foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    $present[$code] = true;
    $note = $record->getContentNote();
    if (!in_array($code, $codes, true)) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    if (!str_contains($note, $drop)) {
        $stats['errors'][] = $code . ': старая оговорка о длительности не найдена';
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    $updated = str_replace($drop, '', $note);
    $stats['updated'][] = $code;
    $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $record->getDescription(),
        $record->getSpecDocument(),
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        $updated,
    ));
}

foreach ($codes as $code) {
    if (isset($present[$code])) {
        $stats['missing'] = array_values(array_diff($stats['missing'], [$code]));
    }
}

fwrite(STDOUT, json_encode([
    'space' => $spaceId,
    'from' => $from,
    'to' => $from + 1,
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
