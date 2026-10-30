<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-20.php [--space=13] [--from=20] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 20);
$to = (int) ($options['to'] ?? $from + 1);
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
    'changed' => [],
    'added' => [],
    'errors' => [],
];
$codes = [];

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    $codes[$code] = true;
    $spec = $record->getSpecDocument();
    $changed = false;

    if ($code === 'unstable' && $record->getType() === 'state') {
        $spec['increase_check_code'] = 'check-unstable';
        $changed = true;
    }

    if ($code === 'lying' && $record->getType() === 'state') {
        $spec['suppressed_state_codes'] = ['unstable'];
        $changed = true;
    }

    if ($code === 'check-dexterity' && $record->getType() === 'check') {
        unset($spec['unstable_check']);
        $changed = true;
    }

    if ($changed) {
        $stats['changed'][] = $code;
        $read = RuleSpecs::read($record->getType(), $spec);
        if ($read->isBroken()) {
            $stats['errors'][] = $code . ': ' . $read->getShapeKey();
        }
    }

    $entries[] = $changed
        ? RuleCommitEntry::put($code, body($record, $spec))
        : RuleCommitEntry::keep($record->getVersionId());
}

$check = unstableCheck();
$read = RuleSpecs::read('check', $check);
if ($read->isBroken()) {
    $stats['errors'][] = 'check-unstable: ' . $read->getShapeKey();
}

if (!isset($codes['check-unstable'])) {
    $stats['added'][] = 'check-unstable';
    $entries[] = RuleCommitEntry::put('check-unstable', new RuleVersionBody(
        'check',
        'Проверка Ловкости (неустойчивость)',
        '',
        $check,
        [],
        [],
        'needs_work',
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

/**
 * Проверка прироста Неустойчивости: сложность из значения состояния.
 *
 * @return array<string, mixed> Спека проверки.
 */
function unstableCheck(): array
{
    return [
        'type' => 'check',
        'parent_check_code' => 'check-dexterity',
        'allowed_modes' => 'solo',
        'unstable_check' => true,
        'difficulty_input' => ['kind' => 'from_state', 'state_code' => 'unstable'],
        'outcomes' => [
            ['max_rating' => 0, 'state_rule_code' => 'lying'],
        ],
    ];
}

/**
 * Собирает тело версии.
 *
 * @param object $record Запись.
 * @param array<string, mixed> $spec Спека.
 *
 * @return RuleVersionBody Тело.
 */
function body(object $record, array $spec): RuleVersionBody
{
    return new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $record->getDescription(),
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        $record->getContentNote(),
    );
}
