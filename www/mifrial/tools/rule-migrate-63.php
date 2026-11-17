<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-63.php [--space=13] [--from=62] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 62);
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

    $changed = false;
    $rewritten = [];
    foreach ($requirements as $block) {
        if (!is_array($block) || !is_array($block['requirements'] ?? null)) {
            $rewritten[] = $block;

            continue;
        }

        $items = [];
        foreach ($block['requirements'] as $requirement) {
            if (!is_array($requirement)) {
                $items[] = $requirement;

                continue;
            }

            $replacement = physiologyKnowledge($requirement);
            if ($replacement !== null) {
                $items[] = $replacement;
                $changed = true;

                continue;
            }

            $items[] = $requirement;
        }
        $block['requirements'] = $items;
        $rewritten[] = $block;
    }

    if (!$changed) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    $spec['requirements'] = $rewritten;
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

/**
 * Заменяет требование старого навыка «Физиология» на знание типа physiology.
 *
 * @param array<string, mixed> $requirement Требование.
 *
 * @return array<string, mixed>|null Новое требование или null, если не подходит.
 */
function physiologyKnowledge(array $requirement): ?array
{
    if (($requirement['type'] ?? null) !== 'has_ability') {
        return null;
    }

    if (($requirement['ability_code'] ?? null) !== 'fiziologiya') {
        return null;
    }

    return [
        'type' => 'has_knowledge',
        'field_code' => 'physiology',
        'min_level' => $requirement['min_level'] ?? 1,
    ];
}
