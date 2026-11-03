<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-29.php [--space=13] [--from=28] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 28);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$descriptions = [
    'kombinatsiya-udarov' => 'Серия из трёх ударов по одной цели. Каждый шаг — отдельное действие; '
        . 'удары получают помехи или преимущества от источника «действие». '
        . 'Попадание накапливает Комбо, промах заканчивает процесс. '
        . 'Комбо живёт только в этом процессе и только против выбранной цели.',
    'raskrytie' => 'При Комбо не меньше 2 вместо шага «Завершение комбо» можно объявить другое своё '
        . 'атакующее действие по той же цели. На всю атаку (все попадания) ложится пакет Завершения: '
        . 'преимущество на попадание от действия +Комбо (не более +3) и +⌊Комбо/2⌋ успехов того же '
        . 'размера, что и бросок (не более +3; каждый даёт те же РУ, что обычный успех проверки), '
        . 'после правила 6 и 1. Стоимость — ОД этой атаки. '
        . 'Процесс заканчивается при попадании и при промахе. Пакет идёт с источником «действие»: '
        . 'одноимённая дельта не складывается.',
];

$stepDescriptions = [
    'combo-start' => 'Удар с одной помехой на попадание от действия. '
        . 'При попадании Комбо становится 1. При промахе процесс заканчивается.',
    'combo-build' => 'Удар с помехой на попадание от действия, равной текущему Комбо. '
        . 'При попадании Комбо увеличивается на 1. При промахе процесс заканчивается.',
    'combo-finish' => 'Доступно при Комбо не меньше 2. Удар получает преимущество на попадание '
        . 'от действия +Комбо (не более +3). К итогу попадания добавляется ⌊Комбо/2⌋ успехов того же '
        . 'размера, что и бросок (не более +3); каждый такой успех даёт те же РУ, что обычный успех '
        . 'проверки. Это происходит после правила 6 и 1, поэтому сами кубы не пересчитываются. '
        . 'Процесс заканчивается при попадании и при промахе.',
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
    $description = $descriptions[$code] ?? null;
    if ($description === null) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $spec = $record->getSpecDocument();
    if ($code === 'kombinatsiya-udarov' && is_array($spec['process'] ?? null)) {
        $steps = is_array($spec['process']['steps'] ?? null) ? $spec['process']['steps'] : [];
        foreach ($steps as $index => $step) {
            if (!is_array($step) || !is_string($step['code'] ?? null)) {
                continue;
            }

            $stepDescription = $stepDescriptions[$step['code']] ?? null;
            if ($stepDescription !== null) {
                $steps[$index]['description'] = $stepDescription;
            }
        }

        $spec['process']['steps'] = $steps;
    }

    $read = RuleSpecs::read($record->getType(), $spec);
    if ($read->isBroken()) {
        $stats['errors'][] = $code . ': ' . $read->getShapeKey();
    }

    $stats['changed'][] = $code;
    $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $description,
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        $record->getContentNote(),
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
