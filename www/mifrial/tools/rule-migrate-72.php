<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-72.php [--space=13] [--from=71] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 71);
$to = $from + 1;
$commit = isset($options['commit']);

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
    'errors' => [],
];
foreach ($slice->getItems() as $record) {
    if ($record->getCode() !== 'magic-structure-construction') {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    $spec = $record->getSpecDocument();
    $changed = false;
    if (is_array($spec['grants'] ?? null)) {
        foreach ($spec['grants'] as $blockIndex => $block) {
            if (!is_array($block['grants'] ?? null)) {
                continue;
            }

            foreach ($block['grants'] as $grantIndex => $grant) {
                if (($grant['type'] ?? null) !== 'magic_study') {
                    continue;
                }

                $spec['grants'][$blockIndex]['grants'][$grantIndex]['max_cost'] = [
                    'type' => 'to_scalar',
                    'value' => [
                        'type' => 'characteristic',
                        'characteristic_code' => 'intellect',
                        'modifier' => 0,
                    ],
                ];
                $changed = true;
            }
        }
    }

    if (!$changed) {
        $stats['errors'][] = $record->getCode() . ': нет грантов magic_study';
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    $body = new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $record->getDescription(),
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        'C72: потолок изучения Конструирования — to_scalar от Интеллекта. '
            . 'to_scalar сворачивает размерное значение в целое: при Интеллекте {4|2} '
            . 'открывает стоимость 16.',
    );
    $check = RuleSpecs::read($body->getType(), $body->getSpec());
    if ($check->isBroken()) {
        $stats['errors'][] = $record->getCode() . ': новая форма битая (' . $check->getShapeKey() . ')';
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    $stats['updated'][] = $record->getCode();
    $entries[] = RuleCommitEntry::put($record->getCode(), $body);
}

fwrite(STDOUT, json_encode([
    'space' => $spaceId,
    'from' => $from,
    'to' => $to,
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
