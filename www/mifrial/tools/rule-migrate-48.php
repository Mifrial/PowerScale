<?php

declare(strict_types=1);

use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-48.php [--space=13] [--from=47] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 47);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$deviationCode = 'spell-deviation';
$castCheckCode = 'check-spell-cast';
$stateCode = 'core-magic-deviation';
$damageTypeCode = 'arcane';
$mechanicCode = 'spell_deviation';
$mechanicVersion = '1.0.0';
$note = 'C48: карточка магического отклонения и ссылка с проверки сотворения.';

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$mechanics = $locator->get(IMechanicContainer::class)->get(IMechanics::class);
if (!$mechanics instanceof IMechanics) {
    fwrite(STDERR, "Не удалось получить каталог механик\n");
    exit(1);
}

$mechanicId = null;
foreach ($mechanics->getList() as $mechanic) {
    if ($mechanic->getCode() === $mechanicCode && $mechanic->getHandlerVersion() === $mechanicVersion) {
        $mechanicId = $mechanic->getId();
        break;
    }
}

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'mechanic' => $mechanicId === null ? 'create' : 'exists',
    'changed' => [],
    'kept' => 0,
    'errors' => [],
];

if ($mechanicId === null && !$commit) {
    $stats['mechanic'] = 'would create';
    fwrite(STDOUT, json_encode([
        'space' => $spaceId,
        'from' => $from,
        'to' => $to,
        'commit' => $commit,
        'stats' => $stats,
        'note' => 'Сначала будет создана механика; состав ревизии в dry-run не строится.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(0);
}

if ($mechanicId === null) {
    $mechanicId = $mechanics->add(
        $mechanicCode,
        'Магическое отклонение',
        'Маркер карточки отклонения: кубы, порог, состояние и взрыв при дубле.',
        $mechanicVersion,
    );
    $stats['mechanic'] = 'created';
}

$payload = [
    'type' => $mechanicCode,
    'data' => [
        'dice_count' => 2,
        'die_faces' => 6,
        'neutral_sum' => 7,
        'state_rule_code' => $stateCode,
        'burst' => [
            'damage_type_code' => $damageTypeCode,
            'success_rating' => 1,
        ],
    ],
];

$hasDeviation = false;
foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    if ($code === $deviationCode) {
        $hasDeviation = true;
        $stats['changed'][] = $code;
        $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
            'simple',
            'Магическое отклонение',
            'Отклонение после провала сотворения: 2d6, сила — сумма граней минус 7; '
            . 'сила снижает Магию источника, дубль вызывает взрыв.',
            ['type' => 'simple'],
            $record->getKeywordIds(),
            [['mechanic_id' => $mechanicId, 'mechanic_payload' => $payload]],
            $record->getContentStatus(),
            $note,
        ));
        continue;
    }

    $spec = $record->getSpecDocument();
    if ($record->getType() === 'check' && $code === $castCheckCode) {
        $spec['failure_rule_codes'] = [$deviationCode];
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
        continue;
    }

    ++$stats['kept'];
    $entries[] = RuleCommitEntry::keep($record->getVersionId());
}

if (!$hasDeviation) {
    $stats['changed'][] = $deviationCode;
    $entries[] = RuleCommitEntry::put($deviationCode, new RuleVersionBody(
        'simple',
        'Магическое отклонение',
        'Отклонение после провала сотворения: 2d6, сила — сумма граней минус 7; '
        . 'сила снижает Магию источника, дубль вызывает взрыв.',
        ['type' => 'simple'],
        [],
        [['mechanic_id' => $mechanicId, 'mechanic_payload' => $payload]],
        'needs_work',
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

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);
