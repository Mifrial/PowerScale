<?php

declare(strict_types=1);

use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-14.php [--space=13] [--from=13] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 13);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$rules = $locator->get(IRuleContainer::class)->get(IRules::class);
$keywords = $locator->get(IKeywordContainer::class)->get(IKeywords::class);
if (!$rules instanceof IRules || !$keywords instanceof IKeywords) {
    fwrite(STDERR, "Не удалось получить сервисы правил/признаков\n");
    exit(1);
}

try {
    $projectileKeywordId = $keywords->getByCode('projectile')->getId();
} catch (Throwable) {
    $projectileKeywordId = $keywords->add('projectile', 'Снаряд', 'Общий признак боеприпаса');
}

$ammoCodes = ['arrow' => true, 'bolt' => true, 'bullet' => true];
$slice = $rules->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'ammo_items' => [],
    'ammo_profiles' => 0,
    'errors' => [],
];

foreach ($slice->getItems() as $record) {
    $spec = $record->getSpecDocument();
    $changed = false;
    $isAmmo = false;
    $code = $record->getCode();

    if ($record->getType() === 'item') {
        $isAmmo = isset($ammoCodes[$code]) || ($spec['projectile'] ?? false) === true;
        if ($isAmmo) {
            $stats['ammo_items'][] = $code;
            if (($spec['projectile'] ?? false) !== true) {
                $spec['projectile'] = true;
                $changed = true;
            }
        }

        foreach (['weapon', 'shield'] as $part) {
            if (!isset($spec[$part]['weapon_profiles']) || !is_array($spec[$part]['weapon_profiles'])) {
                continue;
            }

            foreach ($spec[$part]['weapon_profiles'] as $index => $profile) {
                if (!is_array($profile) || ($profile['type'] ?? null) !== 'shoot') {
                    continue;
                }

                $ammunition = $profile['ammunition'] ?? null;
                if (!is_array($ammunition) || ($ammunition['mode'] ?? null) !== 'item') {
                    continue;
                }

                $codes = $ammunition['keyword_codes'] ?? [];
                if (!is_array($codes)) {
                    continue;
                }

                if (!in_array('projectile', $codes, true)) {
                    array_unshift($codes, 'projectile');
                    $spec[$part]['weapon_profiles'][$index]['ammunition']['keyword_codes'] = $codes;
                    ++$stats['ammo_profiles'];
                    $changed = true;
                }
            }
        }
    }

    $keywordIds = $record->getKeywordIds();
    if ($isAmmo && !in_array($projectileKeywordId, $keywordIds, true)) {
        $keywordIds[] = $projectileKeywordId;
        $changed = true;
    }

    if ($changed) {
        $read = RuleSpecs::read($record->getType(), $spec);
        if ($read->isBroken()) {
            $stats['errors'][] = $record->getCode() . ': ' . $read->getShapeKey();
        }
    }

    $entries[] = $changed
        ? RuleCommitEntry::put($code, body($record, $spec, $keywordIds))
        : RuleCommitEntry::keep($record->getVersionId());
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

$revision = $rules->commit($spaceId, $entries, false, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);

/**
 * Собирает тело версии.
 *
 * @param object $record Запись.
 * @param array<string, mixed> $spec Спека.
 * @param array<int, int> $keywordIds Признаки.
 *
 * @return RuleVersionBody Тело.
 */
function body(object $record, array $spec, array $keywordIds): RuleVersionBody
{
    return new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $record->getDescription(),
        $spec,
        $keywordIds,
        $record->getMechanics(),
        $record->getContentStatus(),
        $record->getContentNote(),
    );
}
