<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-66.php [--space=13] [--from=65] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 65);
$commit = isset($options['commit']);

$root = dirname(__DIR__);
$app = require $root . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$nolomancy = [
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
$nolomancyFound = [];

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'updated' => [],
    'targeted' => [],
    'kept' => 0,
];
foreach ($slice->getItems() as $record) {
    $spec = $record->getSpecDocument();
    if (($spec['type'] ?? null) !== 'spell' || !is_array($spec['spell'] ?? null)) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    $isNolomancy = in_array($record->getCode(), $nolomancy, true);
    $targeted = ($spec['spell']['targeting'] ?? null) === 'entity';
    if (!$isNolomancy && !$targeted) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    if ($isNolomancy) {
        $nolomancyFound[$record->getCode()] = true;
    }

    if ($targeted) {
        $stats['targeted'][] = $record->getCode();
    }

    $spec['spell']['resisted_by_magic'] = true;
    $stats['updated'][] = $record->getCode();
    $entries[] = RuleCommitEntry::put($record->getCode(), new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $record->getDescription(),
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        trim($record->getContentNote() . ' Срез 66: сопротивление магии цели повышает Сложность (M21).'),
    ));
}

$missing = array_values(array_diff($nolomancy, array_keys($nolomancyFound)));

fwrite(STDOUT, json_encode([
    'space' => $spaceId,
    'from' => $from,
    'to' => $from + 1,
    'commit' => $commit,
    'stats' => $stats,
    'missing' => $missing,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);

if ($missing !== []) {
    fwrite(STDERR, "Не найдены карточки Ноломансии; публикация остановлена.\n");
    exit(2);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);
