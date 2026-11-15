<?php

declare(strict_types=1);

use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-53.php [--space=13] [--from=52] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 52);
$to = $from + 1;
$commit = isset($options['commit']);

$raceCode = 'yappi';
$note = 'C53: Яппи — врождённый кристалл 4↓ даётся расой автоматически, покупка до 5.';

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$keywords = $locator->get(IKeywordContainer::class)->get(IKeywords::class);
if (!$keywords instanceof IKeywords) {
    fwrite(STDERR, "Не удалось получить сервис признаков\n");
    exit(1);
}

$humanoidKeyword = $keywords->getByCode('humanoid')->getId();
$speciesKeyword = $keywords->getByCode('ptitselyud')->getId();
$raceKeyword = $keywords->getByCode('yappi')->getId();

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'changed' => [],
    'kept' => 0,
    'errors' => [],
];

$present = [];
foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    $present[$code] = true;

    if ($code === $raceCode) {
        $stats['changed'][] = $code;
        $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
            'race',
            'Яппи',
            'Небольшие молниевые птички: крошечное тело, врождённый магический кристалл и сопротивление электричеству.',
            raceSpec(),
            [$humanoidKeyword, $speciesKeyword, $raceKeyword],
            [],
            'needs_work',
            $note,
        ));
        continue;
    }

    ++$stats['kept'];
    $entries[] = RuleCommitEntry::keep($record->getVersionId());
}

if (!isset($present[$raceCode])) {
    $stats['errors'][] = $raceCode . ': race is missing in the source revision';
}

$read = RuleSpecs::read('race', raceSpec());
if ($read->isBroken()) {
    $stats['errors'][] = $raceCode . ': ' . $read->getShapeKey();
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

/**
 * Спека расы Яппи.
 *
 * @return array<string, mixed> Спека.
 */
function raceSpec(): array
{
    return [
        'parent_race_code' => 'ptitselyud',
        'cost_os' => 0,
        'characteristics' => [
            ['mode' => 'fixed', 'characteristic_code' => 'strength', 'base' => ['base' => 5, 'size' => -2]],
            ['mode' => 'fixed', 'characteristic_code' => 'endurance', 'base' => ['base' => 3, 'size' => -1]],
            ['mode' => 'fixed', 'characteristic_code' => 'dexterity', 'base' => ['base' => 4, 'size' => -1]],
            ['mode' => 'fixed', 'characteristic_code' => 'reaction', 'base' => ['base' => 3, 'size' => 1]],
            ['mode' => 'fixed', 'characteristic_code' => 'attention', 'base' => ['base' => 3, 'size' => 1]],
            ['mode' => 'fixed', 'characteristic_code' => 'reasoning', 'base' => ['base' => 4, 'size' => 0]],
            ['mode' => 'fixed', 'characteristic_code' => 'memory', 'base' => ['base' => 3, 'size' => -1]],
        ],
        'abilities' => [
            ['automatic' => true, 'ability_code' => 'small-step'],
            ['automatic' => true, 'ability_code' => 'magic-resistance', 'parameters' => [
                'x' => ['base' => 3, 'size' => 0],
            ]],
            ['automatic' => false, 'ability_code' => 'magic-resistance', 'parameters' => [
                'x' => ['base' => 6, 'size' => 0],
            ]],
            ['automatic' => true, 'ability_code' => 'magic-crystal-capacity', 'parameters' => [
                'x' => ['base' => 4, 'size' => -1],
            ]],
            ['automatic' => false, 'ability_code' => 'magic-crystal-capacity', 'parameters' => [
                'x' => ['base' => 5, 'size' => 0],
            ]],
            ['automatic' => true, 'ability_code' => 'electricity-resistance', 'parameters' => [
                'x' => ['base' => 4, 'size' => 0],
            ]],
        ],
        'movement_step' => ['base' => 1, 'size' => 0],
    ];
}
