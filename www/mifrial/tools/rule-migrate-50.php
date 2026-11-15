<?php

declare(strict_types=1);

use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-50.php [--space=13] [--from=49] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 49);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$coreCode = 'magic-core';
$capacityCode = 'magic-core-capacity';
$flowCode = 'core-flow-optimization';
$note = 'C50: мощь магического ядра живёт у экземпляра предмета; «Оптимизация потоков» даёт бонус к мощи источника.';

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

$skillId = keywordId($keywords, 'skill', 'Навык');
$magicId = keywordId($keywords, 'magic', 'Волшебство');

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'changed' => [],
    'kept' => 0,
    'errors' => [],
];

$hasFlow = false;

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    $spec = $record->getSpecDocument();

    if ($code === $coreCode) {
        $spec['grants'] = [[
            'type' => 'characteristic_parameter',
            'characteristic_code' => 'magic-power',
            'parameter_code' => 'power',
            'per_unit' => 1,
            'permanent' => true,
        ]
        ];
        $stats['changed'][] = $code;
        $entries[] = put($record, $spec, $note, $stats);
        continue;
    }

    if ($code === $capacityCode) {
        $spec['grants'] = capacityGrants($spec);
        $stats['changed'][] = $code;
        $entries[] = put($record, $spec, $note, $stats);
        continue;
    }

    if ($code === $flowCode) {
        $hasFlow = true;
        $stats['changed'][] = $code;
        $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
            'ability',
            'Оптимизация потоков',
            'Вы получаете +х к магии ядра от мастерства при сотворении волшебства с помощью пути. '
            . 'Требует наличие магического ядра.',
            flowSpec($flowCode),
            [$skillId, $magicId],
            [],
            'needs_work',
            $note,
        ));
        continue;
    }

    ++$stats['kept'];
    $entries[] = RuleCommitEntry::keep($record->getVersionId());
}

if (!$hasFlow) {
    $stats['changed'][] = $flowCode;
    $entries[] = RuleCommitEntry::put($flowCode, new RuleVersionBody(
        'ability',
        'Оптимизация потоков',
        'Вы получаете +х к магии ядра от мастерства при сотворении волшебства с помощью пути. '
        . 'Требует наличие магического ядра.',
        flowSpec($flowCode),
        [$skillId, $magicId],
        [],
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

/**
 * Id признака по коду; отсутствующий создаётся.
 *
 * @param IKeywords $keywords Каталог.
 * @param string $code Код.
 * @param string $name Подпись.
 *
 * @return int Id.
 */
function keywordId(IKeywords $keywords, string $code, string $name): int
{
    try {
        return $keywords->getByCode($code)->getId();
    } catch (KeywordNotFoundException) {
        return $keywords->add($code, $name);
    }
}

/**
 * Гранты «Врождённого магического ядра»: предмет с привязкой мощности.
 *
 * @param array<string, mixed> $spec Спека карточки.
 *
 * @return array<int, array<string, mixed>> Блоки грантов.
 */
function capacityGrants(array $spec): array
{
    $blocks = is_array($spec['grants'] ?? null) ? $spec['grants'] : [];
    $result = [];
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        $rows = [];
        foreach (is_array($block['grants'] ?? null) ? $block['grants'] : [] as $grant) {
            if (!is_array($grant)) {
                continue;
            }

            if (
                ($grant['type'] ?? null) === 'characteristic_parameter'
                && ($grant['characteristic_code'] ?? null) === 'magic-power'
            ) {
                continue;
            }

            if (($grant['type'] ?? null) === 'item' && ($grant['item_code'] ?? null) === 'magic-core') {
                $grant['parameters'] = ['power' => 'x'];
            }

            $rows[] = $grant;
        }

        $block['grants'] = $rows;
        $result[] = $block;
    }

    return $result;
}

/**
 * Спека «Оптимизации потоков».
 *
 * @param string $code Код карточки.
 *
 * @return array<string, mixed> Спека.
 */
function flowSpec(string $code): array
{
    return [
        'type' => 'skill',
        'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [0, 0, 0]]],
        'grants' => [],
        'requirements' => [[
            'level' => 1,
            'requirements' => [['type' => 'has_ability', 'ability_code' => 'magic-core-capacity']],
        ]
        ],
        'derived_level' => ['source_keyword' => 'magic', 'thresholds' => [15, 30, 50]],
        'spell_source_power' => [
            'scope' => ['kind' => 'item', 'item_code' => 'magic-core'],
            'path_scoped' => true,
            'amount' => ['type' => 'ability_level', 'ability_code' => $code, 'multiplier' => 1],
        ],
        'parent_ability_code' => null,
    ];
}

/**
 * Новый снимок карточки с прежними полями.
 *
 * @param \Mifrial\Roleplay\Rule\Dto\RuleVersionRecord $record Запись.
 * @param array<string, mixed> $spec Новая спека.
 * @param string $note Комментарий.
 * @param array{errors: array<int, string>} $stats Счётчики.
 *
 * @return RuleCommitEntry Запись.
 */
function put(RuleVersionRecord $record, array $spec, string $note, array &$stats): RuleCommitEntry
{
    $read = RuleSpecs::read($record->getType(), $spec);
    if ($read->isBroken()) {
        $stats['errors'][] = $record->getCode() . ': ' . $read->getShapeKey();
    }

    return RuleCommitEntry::put($record->getCode(), new RuleVersionBody(
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
