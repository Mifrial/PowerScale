<?php

declare(strict_types=1);

use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-70.php [--space=13] [--from=69] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 69);
$to = $from + 1;
$commit = isset($options['commit']);

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

$keywordIds = [];
foreach (['nervous-system' => 'Нервная система'] as $code => $name) {
    try {
        $keywordIds[$code] = $keywords->getByCode($code)->getId();
    } catch (KeywordNotFoundException) {
        if (!$commit) {
            fwrite(STDERR, 'Нет признака ' . $code . '; для проверки нужен --commit' . PHP_EOL);
            exit(1);
        }

        $keywordIds[$code] = $keywords->add($code, $name);
    }
}

/**
 * Требования карточки с добавленным опытом признаков.
 *
 * @param mixed $requirements Текущие требования.
 * @param array<int, string> $codes Признаки.
 *
 * @return array<int, array<string, mixed>> Требования.
 */
function withExperience(mixed $requirements, array $codes): array
{
    $node = ['type' => 'keyword_experience', 'keyword_codes' => $codes, 'min' => 10];
    if (!is_array($requirements) || $requirements === []) {
        return [$node];
    }

    $first = $requirements[0] ?? null;
    if (is_array($first) && is_array($first['requirements'] ?? null)) {
        $first['requirements'][] = $node;
        $requirements[0] = $first;

        return array_values($requirements);
    }

    $requirements[] = $node;

    return array_values($requirements);
}

$cards = [
    'living-weakness' => [
        'state' => 'weakness',
        'description' => 'Движения цели заторможены: лимит Очков Действий снижен на 1, пока действует '
            . 'заклинание. Ослабленная версия: в источнике — снижение силы на размер.',
        'note' => 'Состояние «Слабость» из базовых правил (лимит ОД −1). Его видит конец хода: пул '
            . 'восстанавливается до уменьшенного лимита.',
    ],
    'heightened-senses' => [
        'state' => 'sharpened-senses',
        'experience' => ['nolomancy', 'nervous-system'],
        'note' => 'Состояние «Обострённые чувства» (+3 Реакции) на 5 ходов. Требование «10 опыта '
            . 'ноломансии нервной системы» выражено keyword_experience.',
    ],
    'living-numbness' => [
        'experience' => ['nolomancy', 'nervous-system'],
        'note' => 'Требование «10 опыта ноломансии нервной системы» выражено keyword_experience. '
            . 'Притупление боли не реализовано: подавлять пока нечего.',
    ],
    'accelerated-metabolism' => [
        'experience' => ['nolomancy'],
        'note' => 'Требование «10 опыта ноломансии» выражено keyword_experience. Ускоренный отдых '
            . 'и пятикратный расход еды не реализованы.',
    ],
];

$state = [
    'effects' => [[
        'type' => 'characteristic_modify',
        'amount' => 3,
        'characteristic_code' => 'reaction',
    ]],
    'icon_code' => 'mdi-brain',
    'value_type' => 'flag',
    'aggregation' => 'independent',
];

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'added' => [],
    'updated' => [],
    'kept' => 0,
    'missing' => array_keys($cards),
];
foreach ($slice->getItems() as $record) {
    if (!isset($cards[$record->getCode()])) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    $stats['missing'] = array_values(array_diff($stats['missing'], [$record->getCode()]));
    $target = $cards[$record->getCode()];
    $spec = $record->getSpecDocument();
    if (isset($target['state'])) {
        $spell = is_array($spec['spell'] ?? null) ? $spec['spell'] : [];
        $spell['effects'] = [['type' => 'state', 'state_code' => $target['state']]];
        $spec['spell'] = $spell;
    }

    if (isset($target['experience'])) {
        $spec['requirements'] = withExperience($spec['requirements'] ?? [], $target['experience']);
    }

    $body = new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $target['description'] ?? $record->getDescription(),
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        $target['note'],
    );
    $check = RuleSpecs::read($body->getType(), $body->getSpec());
    if ($check->isBroken()) {
        $stats['errors'][] = $record->getCode() . ': новая форма битая (' . $check->getShapeKey() . ')';
        continue;
    }

    $stats['updated'][] = $record->getCode();
    $entries[] = RuleCommitEntry::put($record->getCode(), $body);
}

$stateBody = new RuleVersionBody(
    'state',
    'Обострённые чувства',
    'Проводимость нервной системы улучшена: +3 к Реакции, пока действует эффект.',
    $state,
    [$keywordIds['nervous-system']],
    [],
    'needs_work',
    'Состояние для заклинания «Обострённые чувства живого» (M23).',
);
$stateCheck = RuleSpecs::read($stateBody->getType(), $stateBody->getSpec());
if ($stateCheck->isBroken()) {
    $stats['errors'][] = 'sharpened-senses: новая форма битая (' . $stateCheck->getShapeKey() . ')';
} else {
    $entries[] = RuleCommitEntry::put('sharpened-senses', $stateBody);
    $stats['added'][] = 'sharpened-senses';
}

fwrite(STDOUT, json_encode([
    'space' => $spaceId,
    'from' => $from,
    'to' => $to,
    'commit' => $commit,
    'stats' => $stats,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);

if ($stats['missing'] !== []) {
    fwrite(STDERR, 'Не найдены карточки: ' . implode(', ', $stats['missing'])
        . '; публикация остановлена.' . PHP_EOL);
    exit(2);
}

if (($stats['errors'] ?? []) !== []) {
    fwrite(STDERR, "Есть ошибки; публикация остановлена.\n");
    exit(2);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);
