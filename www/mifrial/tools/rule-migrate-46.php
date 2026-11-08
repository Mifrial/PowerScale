<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-46.php [--space=13] [--from=45] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 45);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$study = [
    'careful-magic' => ['paths' => 'any'],
    'piercing-magic' => ['paths' => 'any'],
    'discharge' => ['paths' => 'any'],
    'lightning-generator' => ['paths' => 'any'],
    'lightning-strike' => ['paths' => 'any'],
    'magic-perception' => ['paths' => 'any'],
    'composite-magic-structures' => ['paths' => ['arcanist']],
    'magic-structure-construction' => ['paths' => ['arcanist']],
];

$note = 'C46: изучение в рамках пути. Грант magic_study получил paths вместо scope/path_code; '
    . 'карточка получила study.paths.';

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'changed' => [],
    'kept' => 0,
    'errors' => [],
];

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    if ($record->getType() !== 'ability') {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $spec = $record->getSpecDocument();
    $changed = false;

    if (isset($spec['grants']) && is_array($spec['grants'])) {
        $spec['grants'] = migrateBlocks($spec['grants'], $changed);
    }

    if (isset($study[$code])) {
        $spec['study'] = $study[$code];
        if (!is_string($spec['domain_ref'] ?? null) || $spec['domain_ref'] === '') {
            $spec['domain_ref'] = 'magic-path';
        }
        $changed = true;
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
    fwrite(STDERR, "Карточки изучения не найдены; публикация остановлена.\n");
    exit(3);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);

/**
 * Переписывает блоки грантов: magic_study получает paths и теряет scope/path_code.
 *
 * @param array<int, mixed> $blocks Блоки грантов.
 * @param bool $changed Признак изменения.
 *
 * @return array<int, mixed> Блоки.
 */
function migrateBlocks(array $blocks, bool &$changed): array
{
    foreach ($blocks as $blockIndex => $block) {
        if (!is_array($block) || !isset($block['grants']) || !is_array($block['grants'])) {
            continue;
        }

        $rows = [];
        $seen = [];
        foreach ($block['grants'] as $grant) {
            if (!is_array($grant) || ($grant['type'] ?? null) !== 'magic_study') {
                $rows[] = $grant;
                continue;
            }

            $pathCode = is_string($grant['path_code'] ?? null) ? $grant['path_code'] : '';
            if ($pathCode === '') {
                $rows[] = $grant;
                continue;
            }

            $paths = [$pathCode];
            $migrated = ['type' => 'magic_study', 'paths' => $paths, 'max_cost' => $grant['max_cost']];
            foreach (['max_instances', 'paid_cost', 'permanent'] as $key) {
                if (array_key_exists($key, $grant)) {
                    $migrated[$key] = $grant[$key];
                }
            }

            $key = json_encode($migrated);
            if (isset($seen[$key])) {
                $changed = true;
                continue;
            }

            $seen[$key] = true;
            $rows[] = $migrated;
            $changed = true;
        }

        $blocks[$blockIndex]['grants'] = $rows;
    }

    return $blocks;
}
