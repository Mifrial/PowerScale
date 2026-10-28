<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-16.php [--space=13] [--from=16] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 16);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$bows = ['blochnyy-luk', 'dlinnyy-luk', 'korotkiy-luk', 'rekursivnyy-luk'];
$halfSword = 'polutornyy-mech';

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
    $spec = $record->getSpecDocument();
    $code = $record->getCode();
    $changed = false;

    if (in_array($code, $bows, true)) {
        $hands = ['min' => 1, 'max' => 1];
        if (($spec['occupy_hands'] ?? null) !== $hands) {
            $spec['occupy_hands'] = $hands;
            $changed = true;
        }

        $profiles = $spec['weapon']['weapon_profiles'] ?? [];
        if (is_array($profiles)) {
            foreach ($profiles as $index => $profile) {
                if (
                    is_array($profile)
                    && ($profile['type'] ?? null) === 'shoot'
                    && ($profile['extra_hands'] ?? null) !== 1
                ) {
                    $spec['weapon']['weapon_profiles'][$index]['extra_hands'] = 1;
                    $changed = true;
                }
            }
        }
    }

    if ($code === $halfSword) {
        $hands = $spec['occupy_hands'] ?? null;
        if (is_array($hands) && array_key_exists('action', $hands)) {
            unset($spec['occupy_hands']['action']);
            $changed = true;
        }
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

$revision = $spaces->commit($spaceId, $entries, null, $from);
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
