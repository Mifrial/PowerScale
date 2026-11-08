<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-41.php [--space=13] [--from=40] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 40);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$maneuver = [
    'trigger' => ['on' => 'hit', 'min_success' => 3],
    'min_mastery' => ['base' => 3, 'size' => 0],
    'effects' => [['type' => 'unstable', 'amount' => 1]],
];

$boost = [
    'applies_to' => ['zastavit-otkrytsya', 'razbit-zaschitu'],
    'min_success' => 4,
    'min_mastery' => ['base' => 5, 'size' => 0],
    'requires_any' => ['zastavit-otkrytsya', 'razbit-zaschitu'],
    'effects' => [['type' => 'unstable', 'amount' => 2]],
];

$notes = [
    'razbit-zaschitu' => 'S1: манёвр при ударе и Неустойчивость исполняются Game. '
        . 'Защитные манёвры и немедленная атака — обязательная доработка.',
    'vskryt-slabost' => 'S1: усиление «Разбить защиту» исполняется Game. '
        . 'Усиление защитных манёвров — обязательная доработка.',
    'poymat-moment' => 'Обязательная боевая доработка: реакция после уклонения и немедленная атака '
        . 'пока не исполняются Game. Цена исправлена на 0 ОД по каталогу.',
    'zastavit-otkrytsya' => 'Обязательная боевая доработка: защитный манёвр и немедленная атака '
        . 'пока не исполняются Game.',
    'obezoruzhit-protivnika' => 'Обязательная боевая доработка: выбитое оружие требует позиции '
        . 'предмета в мире и действия «поднять».',
    'smenit-pozitsiyu' => 'Обязательная боевая доработка: манёвр требует движения/сцены, '
        . 'которых в Game пока нет.',
    'opyt-koordinatsii-atak' => 'Обязательная боевая доработка: усиление ждёт Совместной атаки.',
];

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'changed' => [],
    'kept' => 0,
    'errors' => [],
];

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    if (!isset($notes[$code])) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $spec = $record->getSpecDocument();
    if ($code === 'razbit-zaschitu') {
        $spec['strike_maneuver'] = $maneuver;
    }

    if ($code === 'vskryt-slabost') {
        $spec['parent_ability_code'] = null;
        $spec['maneuver_boost'] = $boost;
    }

    if ($code === 'poymat-moment') {
        $components = is_array($spec['action_components'] ?? null) ? $spec['action_components'] : [];
        foreach ($components as $index => $component) {
            if (is_array($component) && ($component['resource_code'] ?? null) === 'action-points') {
                $components[$index]['amount'] = 0;
            }
        }

        $spec['action_components'] = $components;
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
        $notes[$code],
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
    fwrite(STDERR, "Карточки манёвров не найдены; публикация остановлена.\n");
    exit(3);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);
