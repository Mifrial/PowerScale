<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-47.php [--space=13] [--from=46] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 46);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$targets = [
    'basic-element-properties',
    'magic-structure-interaction',
];

$note = 'C47: у грантов check_efficiency явно задан permanent: true.';

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
    if ($record->getType() !== 'ability' || !in_array($code, $targets, true)) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $spec = $record->getSpecDocument();
    $changed = false;
    if (isset($spec['grants']) && is_array($spec['grants'])) {
        $spec['grants'] = markPermanent($spec['grants'], $changed);
    }

    if (!$changed) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

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
    fwrite(STDERR, "Гранты check_efficiency не найдены; публикация остановлена.\n");
    exit(3);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);

/**
 * Добавляет permanent: true грантам check_efficiency.
 *
 * @param array<int, mixed> $blocks Блоки грантов.
 * @param bool $changed Признак изменения.
 *
 * @return array<int, mixed> Блоки.
 */
function markPermanent(array $blocks, bool &$changed): array
{
    foreach ($blocks as $blockIndex => $block) {
        if (!is_array($block) || !isset($block['grants']) || !is_array($block['grants'])) {
            continue;
        }

        foreach ($block['grants'] as $grantIndex => $grant) {
            if (!is_array($grant) || ($grant['type'] ?? null) !== 'check_efficiency') {
                continue;
            }

            if (array_key_exists('permanent', $grant)) {
                continue;
            }

            $grant['permanent'] = true;
            $blocks[$blockIndex]['grants'][$grantIndex] = $grant;
            $changed = true;
        }
    }

    return $blocks;
}
