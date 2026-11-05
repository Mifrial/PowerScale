<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-37.php [--space=13] [--from=36] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 36);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$note = 'Обманный манёвр: компьютерная редакция S4. Навык выбирается при '
    . 'объявлении атаки как улучшение, скрыт до реакций; совместная проверка '
    . 'после ответов, строгая победа, авто-провал атаки и метка advantage -2.';

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'changed' => [],
    'kept' => 0,
    'errors' => [],
];

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    if ($code !== 'obmannyy-manevr') {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $spec = $record->getSpecDocument();
    unset($spec['reaction'], $spec['support'], $spec['cover_ally']);
    $spec['maneuver'] = [
        'trigger' => 'strike',
        'window' => 'after_defense_before_check',
        'attack' => 'auto_fail',
        'check' => [
            'attacker' => [
                ['check' => 'deception', 'characteristic' => 'communication'],
            ],
            'target' => [
                ['check' => 'check-attention', 'characteristic' => 'attention'],
                ['check' => 'check-melee-combat', 'characteristic' => 'melee-combat'],
            ],
        ],
        'outcome' => [
            'on_target_passed' => 'may_ignore',
            'on_target_failed' => [
                [
                    'kind' => 'advantage',
                    'value' => -2,
                    'source_code' => 'circumstances',
                    'scope' => 'defense_against_attacker',
                    'consumes' => 'first_defense',
                    'expires' => 'attacker_turn',
                ],
            ],
        ],
    ];

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

if ($stats['changed'] === []) {
    fwrite(STDERR, "Карточка obmannyy-manevr не найдена; публикация остановлена.\n");
    exit(3);
}

if ($stats['errors'] !== []) {
    fwrite(STDERR, "Есть ошибки разбора; публикация остановлена.\n");
    exit(2);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);
