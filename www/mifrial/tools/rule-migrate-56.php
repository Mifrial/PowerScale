<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-56.php [--space=13] [--from=55] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 55);
$to = $from + 1;
$commit = isset($options['commit']);

$note = 'C56: Цепная молния изучается в пути: study.paths = any и домен пути.';

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
    'kept' => 0,
    'errors' => [],
];

$patched = [];
$present = [];
foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    $present[$code] = true;
    $spec = $record->getSpecDocument();
    $next = match ($code) {
        'chain-lightning' => chainLightningSpec($spec),
        default => null,
    };
    if ($next === null) {
        ++$stats['kept'];
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $stats['changed'][] = $code;
    $patched[$code] = $next;
    $entries[] = RuleCommitEntry::put($code, bodyOf($record, $next, $note));
}

if (!isset($present['chain-lightning'])) {
    $stats['errors'][] = 'chain-lightning: rule is missing in the source revision';
}

foreach ($patched as $code => $spec) {
    $read = RuleSpecs::read('ability', $spec);
    if ($read->isBroken()) {
        $stats['errors'][] = $code . ': ' . $read->getShapeKey();
    }
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
 * Тело новой версии из текущей карточки.
 *
 * @param RuleVersionRecord $record Текущая версия.
 * @param array<string, mixed> $spec Новая спека.
 * @param string $note Комментарий ревизии.
 *
 * @return RuleVersionBody Тело.
 */
function bodyOf(RuleVersionRecord $record, array $spec, string $note): RuleVersionBody
{
    return new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $record->getDescription(),
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        $note,
    );
}

/**
 * Цепная молния: карточка усиления заклинания изучается в пути.
 *
 * @param array<string|int, mixed> $spec Текущая спека.
 *
 * @return array<string, mixed> Спека.
 */
function chainLightningSpec(array $spec): array
{
    $spec['study'] = ['paths' => 'any'];
    $spec['domain_ref'] = 'magic-path';

    return $spec;
}
