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
    fwrite(STDOUT, "php tools/rule-migrate-13.php [--space=13] [--from=12] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 12);
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

$keywordIds = [];
foreach (['arrow' => 'Стрела', 'bolt' => 'Болт', 'bullet' => 'Пуля'] as $code => $name) {
    try {
        $keywordIds[$code] = $keywords->getByCode($code)->getId();
    } catch (Throwable) {
        $keywordIds[$code] = $keywords->add($code, $name, 'Боеприпас для стрельбы');
    }
}

$slice = $rules->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'items' => 0,
    'profiles' => 0,
    'ranged' => 0,
    'races' => 0,
    'ammo' => [],
    'errors' => [],
];
$sample = null;

foreach ($slice->getItems() as $record) {
    $spec = $record->getSpecDocument();
    $changed = false;
    $type = $record->getType();

    if ($type === 'item') {
        foreach (['weapon', 'shield'] as $part) {
            if (!isset($spec[$part]['weapon_profiles']) || !is_array($spec[$part]['weapon_profiles'])) {
                continue;
            }

            ++$stats['items'];
            foreach ($spec[$part]['weapon_profiles'] as $index => $profile) {
                if (!is_array($profile)) {
                    continue;
                }

                ++$stats['profiles'];
                $profile = migrateDistance($profile);
                $profile = migrateAmmunition($profile, $record->getCode());
                if (($profile['type'] ?? null) === 'shoot' || ($profile['type'] ?? null) === 'throw') {
                    ++$stats['ranged'];
                }

                $spec[$part]['weapon_profiles'][$index] = $profile;
                $changed = true;
                if ($sample === null && ($profile['type'] ?? null) === 'shoot') {
                    $sample = ['code' => $record->getCode(), 'profile' => $profile];
                }
            }

            unset($spec[$part]['ammunition_requirements']);
        }
    }

    if ($type === 'race') {
        ++$stats['races'];
        if (!isset($spec['movement_step'])) {
            $spec['movement_step'] = ['base' => 1, 'size' => 0];
            $changed = true;
        }
    }

    if ($changed) {
        $read = RuleSpecs::read($type, $spec);
        if ($read->isBroken()) {
            $stats['errors'][] = $record->getCode() . ': ' . $read->getShapeKey();
        }
    }

    $entries[] = $changed
        ? RuleCommitEntry::put(
            $record->getCode(),
            body($record, $spec),
        )
        : RuleCommitEntry::keep($record->getVersionId());
}

foreach (['arrow', 'bolt', 'bullet'] as $code) {
    $exists = false;
    foreach ($slice->getItems() as $record) {
        if ($record->getCode() === $code) {
            $exists = true;
            break;
        }
    }

    if ($exists) {
        continue;
    }

    $name = ['arrow' => 'Стрела', 'bolt' => 'Болт', 'bullet' => 'Пуля'][$code];
    $spec = [
        'category' => 'equipment',
        'cost_gm' => 1,
        'innate' => false,
        'weight' => ['base' => 0, 'size' => 0],
        'special_rule_codes' => [],
        'occupy_hands' => ['min' => 0, 'max' => 0],
    ];
    $read = RuleSpecs::read('item', $spec);
    if ($read->isBroken()) {
        $stats['errors'][] = $code . ': ' . $read->getShapeKey();
    }

    $stats['ammo'][] = $code;
    $entries[] = RuleCommitEntry::put(
        $code,
        new RuleVersionBody('item', $name, 'Боеприпас для стрельбы', $spec, [$keywordIds[$code]], [], 'needs_work'),
    );
}

fwrite(STDOUT, json_encode([
    'space' => $spaceId,
    'from' => $from,
    'to' => $to,
    'commit' => $commit,
    'stats' => $stats,
    'sample' => $sample,
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
 * Переводит старую дистанцию профиля в {min,max}.
 *
 * @param array<string, mixed> $profile Профиль.
 *
 * @return array<string, mixed> Профиль.
 */
function migrateDistance(array $profile): array
{
    $distance = $profile['distance'] ?? null;
    if (is_array($distance) && isset($distance['min'])) {
        return $profile;
    }

    $old = is_array($distance) ? $distance : ['type' => 'fixed', 'value' => 0];
    $range = $profile['range'] ?? null;
    $type = $profile['type'] ?? 'strike';
    if ($range !== null) {
        $min = $old;
        $max = $range;
    } elseif ($type === 'throw') {
        $min = $old;
        $max = null;
    } else {
        $min = ['type' => 'fixed', 'value' => 0];
        $max = $old;
    }

    $profile['distance'] = [
        'min' => wrapDistance($min),
        'max' => $max === null ? null : wrapDistance($max),
    ];
    unset($profile['range']);

    $falloff = $profile['falloff'] ?? null;
    if (is_array($falloff) && !isset($falloff['type'])) {
        $profile['falloff'] = [
            'type' => 'ipari',
            'value' => ['type' => 'literal', 'value' => $falloff],
        ];
    }

    return $profile;
}

/**
 * Дописывает ammunition профиля.
 *
 * @param array<string, mixed> $profile Профиль.
 * @param string $itemCode Код предмета.
 *
 * @return array<string, mixed> Профиль.
 */
function migrateAmmunition(array $profile, string $itemCode): array
{
    if (isset($profile['ammunition'])) {
        return $profile;
    }

    $type = $profile['type'] ?? 'strike';
    if ($type === 'strike') {
        $profile['ammunition'] = ['mode' => 'none', 'keyword_codes' => []];

        return $profile;
    }

    if ($type === 'throw') {
        $profile['ammunition'] = ['mode' => 'self', 'keyword_codes' => []];

        return $profile;
    }

    $code = str_contains($itemCode, 'arbalet')
        ? 'bolt'
        : (str_contains($itemCode, 'kulevrina') ? 'bullet' : 'arrow');
    $profile['ammunition'] = ['mode' => 'item', 'keyword_codes' => [$code]];

    return $profile;
}

/**
 * Оборачивает формулу в DistanceValue.
 *
 * @param array<string, mixed> $formula Формула.
 *
 * @return array<string, mixed> DistanceValue.
 */
function wrapDistance(array $formula): array
{
    if (!isset($formula['type']) && isset($formula['base'], $formula['size'])) {
        $formula = ['type' => 'dimensional', 'base' => $formula['base'], 'size' => $formula['size']];
    }

    return ['type' => 'ipari', 'value' => $formula];
}

/**
 * Собирает тело версии.
 *
 * @param object $record Запись.
 * @param array<string, mixed> $spec Спека.
 *
 * @return RuleVersionBody Тело.
 */
function body(object $record, array $spec): RuleVersionBody
{
    return new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $record->getDescription(),
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        $record->getContentNote(),
    );
}
