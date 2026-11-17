<?php

declare(strict_types=1);

use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-64.php [--space=13] [--from=63] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 63);
$commit = isset($options['commit']);

$root = dirname(__DIR__);
$app = require $root . '/init.php';
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
foreach (['vision' => 'Зрение', 'nervous-system' => 'Нервная система'] as $code => $name) {
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

$state = [
    'effects' => [[
        'type' => 'check_advantage',
        'amount' => -1,
        'per_unit' => true,
        'includes_hit' => true,
        'characteristic_codes' => ['perception'],
        'source_code' => 'blindness',
    ]],
    'icon_code' => 'mdi-eye-off-outline',
    'value_type' => 'number',
    'aggregation' => 'max',
];

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'added' => [],
    'updated' => [],
    'kept' => 0,
    'missing' => ['living-blindness'],
];
foreach ($slice->getItems() as $record) {
    if ($record->getCode() !== 'living-blindness') {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());

        continue;
    }

    $stats['missing'] = [];
    $spec = $record->getSpecDocument();
    $spell = $spec['spell'] ?? [];
    if (!is_array($spell)) {
        fwrite(STDERR, "living-blindness: spell не объект\n");
        exit(2);
    }

    $spell['effects'] = [['type' => 'state', 'state_code' => 'blindness']];
    $spec['spell'] = $spell;
    $stats['updated'][] = $record->getCode();
    $entries[] = RuleCommitEntry::put($record->getCode(), new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $record->getDescription(),
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        'Состояние blindness на срок lingering (M20).',
    ));
}

$entries[] = RuleCommitEntry::put('blindness', new RuleVersionBody(
    'state',
    'Слепота',
    'Потеря зрения: помехи проверкам Восприятия и броскам попадания на единицу силы эффекта.',
    $state,
    [$keywordIds['vision'], $keywordIds['nervous-system']],
    [],
    'needs_work',
    'Состояние для заклинания «Слепота живого» (M20).',
));
$stats['added'][] = 'blindness';

fwrite(STDOUT, json_encode([
    'space' => $spaceId,
    'from' => $from,
    'to' => $from + 1,
    'commit' => $commit,
    'stats' => $stats,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);

if ($stats['missing'] !== []) {
    fwrite(STDERR, "Не найдена карточка living-blindness; публикация остановлена.\n");
    exit(2);
}

if (!$commit) {
    exit(0);
}

$revision = $spaces->commit($spaceId, $entries, null, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);
