<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-62.php [--space=13] [--from=61] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 61);
$commit = isset($options['commit']);

$codes = [
    'tearing',
    'runny-nose',
    'living-itch',
    'heightened-senses',
    'living-pain',
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
    if (!in_array($code, $codes, true)) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    $spec = $record->getSpecDocument();
    $requirements = $spec['requirements'] ?? [];
    if (!is_array($requirements)) {
        $stats['errors'][] = $code . ': requirements не список';
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    if ($requirements === []) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    $alreadyGrouped = true;
    foreach ($requirements as $row) {
        if (!is_array($row) || !array_key_exists('level', $row) || !array_key_exists('requirements', $row)) {
            $alreadyGrouped = false;
            break;
        }
    }

    if ($alreadyGrouped) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    $flat = [];
    foreach ($requirements as $row) {
        if (!is_array($row) || !isset($row['type'])) {
            $stats['errors'][] = $code . ': строка требования без type';
            continue 2;
        }

        $flat[] = $row;
    }

    $spec['requirements'] = [['level' => 1, 'requirements' => $flat]];
    $stats['updated'][] = $code;
    $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $record->getDescription(),
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        $record->getContentNote(),
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
