<?php

declare(strict_types=1);

use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogPlacement;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogSection;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use Mifrial\Roleplay\RuleSpace\Repository\RuleSpaceCatalogRepository;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogItemTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogSectionTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceRevisionCatalogTable;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-68.php [--space=13] [--from=67] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 67);
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

$keywordDefs = [
    'skill' => 'Навык',
    'magic' => 'Волшебство',
    'nolomancy' => 'Ноломансия',
    'nervous-system' => 'Нервная система',
    'blocking' => 'Блокировка',
    'decay' => 'Разложение',
    'distance' => 'Дистанция',
    'transformation' => 'Преобразование',
];
$keywordIds = [];
$keywordPending = [];
$syntheticId = 2000000;
foreach ($keywordDefs as $code => $name) {
    try {
        $keywordIds[$code] = $keywords->getByCode($code)->getId();
    } catch (KeywordNotFoundException) {
        if ($commit) {
            $keywordIds[$code] = $keywords->add($code, $name);
            continue;
        }

        $keywordPending[] = $code;
        $keywordIds[$code] = $syntheticId++;
    }
}

$cards = [
    'experienced-nolomant' => [
        'name' => 'Опытный ноломант',
        'description' => 'Применяя это улучшение к заклинанию ноломансии, вы получаете преимущество от мастерства '
            . 'для проверки на его сотворение.',
        'cost' => 1,
        'keywords' => [],
        'requirements' => [[
            'type' => 'keyword_experience',
            'keyword_codes' => ['nolomancy'],
            'min' => 15,
        ]],
        'upgrade' => [
            'action_point_delta' => 0,
            'check_advantage' => 1,
            'check_advantage_source' => 'mastery',
            'keyword_codes' => ['nolomancy'],
        ],
        'note' => 'Преимущество от мастерства: один источник с «Контролем нервов» (сильнейшее, не сумма).',
    ],
    'nerve-control' => [
        'name' => 'Контроль нервов',
        'description' => 'Применяя это улучшение к заклинанию ноломансии нервной системы, вы получаете два преимущества '
            . 'от мастерства для проверки на его сотворение.',
        'cost' => 1,
        'keywords' => ['nervous-system'],
        'requirements' => [[
            'type' => 'has_ability',
            'ability_code' => 'experienced-nolomant',
        ], [
            'type' => 'has_ability_keyword',
            'keyword_code' => 'nervous-system',
            'min_count' => 1,
        ]],
        'upgrade' => [
            'action_point_delta' => 0,
            'check_advantage' => 2,
            'check_advantage_source' => 'mastery',
            'keyword_codes' => ['nolomancy', 'nervous-system'],
        ],
        'note' => 'Требование «Опытный электромант» не выгружено (электромансия): в карточке не выставлено.',
    ],
    'gaze-curse' => [
        'name' => 'Проклятие взглядом',
        'description' => 'Улучшение любого заклинания ноломансии: вместо касания цель выбирается на расстоянии до N ипари. '
            . 'Требуемая мощь растёт на N размеров, Требуемый контроль — на 2.',
        'cost' => 1,
        'keywords' => ['distance'],
        'requirements' => [],
        'upgrade' => [
            'action_point_delta' => 0,
            'keyword_codes' => ['nolomancy'],
            'range' => [
                'power_size_per_step' => 1,
                'control_base' => 2,
            ],
        ],
        'note' => 'Заменяет компонент касания: каст становится целевым на расстоянии N ипари.',
    ],
    'nerve-decay' => [
        'name' => 'Разложение нервов',
        'description' => 'Штрафы ноломансии блокировки нервной системы становятся постоянными: эффект заклинания '
            . 'проходит, а состояние остаётся.',
        'cost' => 4,
        'keywords' => ['nervous-system', 'blocking', 'decay'],
        'requirements' => [[
            'type' => 'and',
            'children' => [[
                'type' => 'has_ability_keyword',
                'keyword_code' => 'nolomancy',
                'min_count' => 1,
            ], [
                'type' => 'has_ability_keyword',
                'keyword_code' => 'nervous-system',
                'min_count' => 1,
            ]],
        ]],
        'upgrade' => [
            'action_point_delta' => 0,
            'keyword_codes' => ['nolomancy', 'nervous-system', 'blocking'],
            'state_permanence' => true,
        ],
        'note' => 'Постоянное состояние: строка эффекта без срока. Требование «заклинание ноломансии нервной системы» '
            . 'выражено двумя отдельными признаками — они могут быть у разных карточек.',
    ],
    'nerve-transformation' => [
        'name' => 'Преобразование нервов',
        'description' => 'Штрафы ноломансии блокировки нервной системы становятся постоянными, как при «Разложении '
            . 'нервов»; эффект применяется через ход и может быть снят намеренно.',
        'cost' => 6,
        'keywords' => ['nervous-system', 'blocking', 'transformation'],
        'requirements' => [[
            'type' => 'keyword_experience',
            'keyword_codes' => ['nolomancy', 'nervous-system'],
            'min' => 10,
        ], [
            'type' => 'has_knowledge',
            'field_code' => 'physiology',
            'min_level' => 2,
        ]],
        'upgrade' => [
            'action_point_delta' => 0,
            'keyword_codes' => ['nolomancy', 'nervous-system', 'blocking'],
            'state_permanence' => true,
        ],
        'note' => 'Скидка 2 при «Разложении нервов» не смоделирована. Намеренное снятие постоянного состояния '
            . 'и задержка «через ход» пока не реализованы.',
    ],
];

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'added' => [],
    'kept' => 0,
    'keyword_pending' => $keywordPending,
    'errors' => [],
];
$present = [];
foreach ($slice->getItems() as $record) {
    $present[$record->getCode()] = true;
    ++$stats['kept'];
    $entries[] = RuleCommitEntry::keep($record->getVersionId());
}

foreach ($cards as $code => $card) {
    $spec = skillSpec($card, $keywordIds);
    $read = RuleSpecs::read('ability', $spec);
    if ($read->isBroken()) {
        $stats['errors'][] = $code . ': ' . $read->getShapeKey();
        continue;
    }

    $stats['added'][] = isset($present[$code]) ? $code . ' (replace)' : $code;
    $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
        'ability',
        $card['name'],
        $card['description'],
        $spec,
        cardKeywords($card['keywords'], $keywordIds),
        [],
        'needs_work',
        $card['note'],
    ));
}

$catalogCodes = $present;
foreach (array_keys($cards) as $code) {
    $catalogCodes[$code] = true;
}
$catalog = catalogWithNolomancySkills($locator, $spaceId, array_keys($cards), $catalogCodes, $stats);

if ($catalog instanceof RuleSpaceCatalog) {
    $placed = [];
    foreach ($catalog->getPlacements() as $placement) {
        $placed[$placement->getRuleCode()] = true;
    }

    foreach (array_keys($cards) as $code) {
        if (!isset($placed[$code])) {
            $stats['errors'][] = 'catalog: ' . $code . ' is not placed';
        }
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

$revision = $spaces->commit($spaceId, $entries, $catalog, $from);
fwrite(STDOUT, 'Опубликована ревизия ' . $revision->getRevision() . PHP_EOL);

/**
 * Признаки карточки: навык школы плюс тематические.
 *
 * @param array<int, string> $extra Тематические коды.
 * @param array<string, int> $ids Справочник id.
 *
 * @return array<int, int> Id.
 */
function cardKeywords(array $extra, array $ids): array
{
    $result = [$ids['skill'], $ids['magic'], $ids['nolomancy']];
    foreach ($extra as $code) {
        if (isset($ids[$code])) {
            $result[] = $ids[$code];
        }
    }

    return array_values(array_unique($result));
}

/**
 * Спека навыка Ноломансии: изучение по пути, цена в ОР, улучшение каста.
 *
 * @param array<string, mixed> $card Карточка.
 * @param array<string, int> $ids Признаки.
 *
 * @return array<string, mixed> Спека.
 */
function skillSpec(array $card, array $ids): array
{
    return [
        'type' => 'skill',
        'study' => ['paths' => 'any'],
        'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [$card['cost']]]],
        'grants' => [],
        'multiple' => true,
        'domain_ref' => 'magic-path',
        'requirements' => $card['requirements'] === []
            ? []
            : [['level' => 1, 'requirements' => $card['requirements']]],
        'spell_upgrade' => $card['upgrade'],
        'parent_ability_code' => null,
    ];
}

/**
 * Последний каталог с секцией навыков Ноломансии и размещениями карточек.
 *
 * @param mixed $locator Локатор.
 * @param int $spaceId Мир.
 * @param array<int, string> $codes Коды карточек.
 * @param array<string, true> $ruleCodes Состав ревизии.
 * @param array<string, mixed> $stats Статистика.
 *
 * @return RuleSpaceCatalog|null Снимок или null.
 */
function catalogWithNolomancySkills(
    mixed $locator,
    int $spaceId,
    array $codes,
    array $ruleCodes,
    array &$stats,
): ?RuleSpaceCatalog {
    $container = $locator->get(ISmartTableContainer::class);
    $gateway = $container->get(ISmartTableGateway::class);
    if (!$gateway instanceof ISmartTableGateway) {
        $stats['errors'][] = 'catalog: SmartTable gateway is unavailable';

        return null;
    }

    $repository = new RuleSpaceCatalogRepository(
        $gateway->open(RuleSpaceCatalogSectionTable::class)->records(),
        $gateway->open(RuleSpaceCatalogItemTable::class)->records(),
        $gateway->open(RuleSpaceRevisionCatalogTable::class)->records(),
    );
    $version = $repository->findLatestSectionVersion($spaceId);
    if ($version === null) {
        $stats['errors'][] = 'catalog: no latest section snapshot';

        return null;
    }

    $latest = $repository->getBySectionVersion($spaceId, $version);
    $sections = array_values($latest->getSections());
    $placements = [];
    foreach ($latest->getPlacements() as $placement) {
        if (!isset($ruleCodes[$placement->getRuleCode()])) {
            $stats['catalog_dropped'][] = $placement->getRuleCode();
            continue;
        }

        $placements[] = $placement;
    }
    $existingSections = [];
    foreach ($sections as $section) {
        $existingSections[$section->getCode()] = true;
    }

    $sectionCode = 'abilities-acquired-magic-nolomancy';
    if (!isset($existingSections[$sectionCode])) {
        $sections[] = new RuleSpaceCatalogSection(
            $sectionCode,
            'Ноломансия',
            'abilities-acquired-magic',
            30,
            null,
        );
    }

    $placed = [];
    foreach ($placements as $placement) {
        $placed[$placement->getRuleCode()] = true;
    }

    $sort = 10;
    foreach ($codes as $code) {
        if (!isset($placed[$code])) {
            $placements[] = new RuleSpaceCatalogPlacement($code, $sectionCode, $sort);
        }

        $sort += 10;
    }

    try {
        return RuleSpaceCatalog::fromParts($sections, $placements);
    } catch (Throwable $exception) {
        $stats['errors'][] = 'catalog: ' . $exception->getMessage();

        return null;
    }
}
