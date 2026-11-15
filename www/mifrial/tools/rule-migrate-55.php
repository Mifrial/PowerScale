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
    fwrite(STDOUT, "php tools/rule-migrate-55.php [--space=13] [--from=54] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 54);
$to = $from + 1;
$commit = isset($options['commit']);

$note = 'C55: Псионик держит одно активное волшебство, Духовность считается '
    . 'от Общения/Воли/Внимательности, потолок изучения Шамана — половина Духовности.';

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
        'psionic' => psionicSpec($spec),
        'spirituality' => spiritualitySpec($spec),
        'otherworldly-contact' => otherworldlyContactSpec($spec, $stats['errors']),
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

foreach (['psionic', 'spirituality', 'otherworldly-contact'] as $code) {
    if (!isset($present[$code])) {
        $stats['errors'][] = $code . ': rule is missing in the source revision';
    }
}

foreach ($patched as $code => $spec) {
    $type = $code === 'psionic' ? 'magic_path' : ($code === 'spirituality' ? 'characteristic' : 'ability');
    $read = RuleSpecs::read($type, $spec);
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
 * Псионик: одно активное волшебство.
 *
 * @param array<string|int, mixed> $spec Текущая спека.
 *
 * @return array<string, mixed> Спека.
 */
function psionicSpec(array $spec): array
{
    $spec['active_spell_limit'] = 1;

    return $spec;
}

/**
 * Духовность: агрегат трёх вкладов относительно {5|0}.
 *
 * @param array<string|int, mixed> $spec Текущая спека.
 *
 * @return array<string, mixed> Спека.
 */
function spiritualitySpec(array $spec): array
{
    $spec['aggregate'] = [
        'base' => ['base' => 3, 'size' => 1],
        'sources' => ['communication', 'willpower', 'attention'],
        'reference' => ['base' => 5, 'size' => 0],
        'points_per_step' => 2,
        'step' => 1,
    ];

    return $spec;
}

/**
 * Потусторонний контакт: потолок изучения — половина Духовности.
 *
 * @param array<string|int, mixed> $spec Текущая спека.
 * @param array<int, string> $errors Накопитель ошибок.
 *
 * @return array<string, mixed>|null Спека или null, если гранта нет.
 */
function otherworldlyContactSpec(array $spec, array &$errors): ?array
{
    $levels = is_array($spec['grants'] ?? null) ? $spec['grants'] : [];
    foreach ($levels as $levelIndex => $level) {
        $grants = is_array($level['grants'] ?? null) ? $level['grants'] : [];
        foreach ($grants as $grantIndex => $grant) {
            if (!is_array($grant) || ($grant['type'] ?? null) !== 'magic_study') {
                continue;
            }

            $levels[$levelIndex]['grants'][$grantIndex]['max_cost'] = [
                'type' => 'characteristic_floor_div',
                'characteristic_code' => 'spirituality',
                'divisor' => 2,
            ];
            $spec['grants'] = $levels;

            return $spec;
        }
    }

    $errors[] = 'otherworldly-contact: magic_study grant is missing';

    return null;
}
