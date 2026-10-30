<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceSpecRejectedException;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-21.php [--space=13] [--from=21] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 21);
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
    'errors' => [],
];

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    $spec = $record->getSpecDocument();
    $changed = false;

    if ($code === 'exhaustion' && $record->getType() === 'state') {
        $spec['increase_check_code'] = 'check-exhaustion';
        unset($spec['check_code']);
        $changed = true;
    }

    if ($code === 'simple-push' && $record->getType() === 'ability') {
        $push = is_array($spec['push'] ?? null) ? $spec['push'] : [];
        $push['block_profiles'] = 'hands_or_shield';
        $spec['push'] = $push;
        $changed = true;
    }

    if ($code === 'tolkayuschiy-udar' && $record->getType() === 'ability') {
        $push = is_array($spec['push'] ?? null) ? $spec['push'] : [];
        $push['block_profiles'] = 'slashing_or_blunt_strike';
        $spec['push'] = $push;
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

try {
    $revision = $spaces->commit($spaceId, $entries, null, $from);
} catch (RuleSpaceSpecRejectedException $exception) {
    fwrite(STDERR, json_encode($exception->getErrorDetails(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(3);
}

fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);

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
