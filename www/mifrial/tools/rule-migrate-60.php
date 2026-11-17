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
    fwrite(STDOUT, "php tools/rule-migrate-60.php [--space=13] [--from=59] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 59);
$to = $from + 1;
$commit = isset($options['commit']);

$note = 'C60: Ноломансия — черновая выгрузка и первая пачка 16 эффектов; '
    . 'эффект, длительность lingering и дистанция ещё не реализованы в runtime.';

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
    'spell' => 'Заклинание',
    'nolomancy' => 'Ноломансия',
    'nervous-system' => 'Нервная система',
    'vision' => 'Зрение',
    'hearing' => 'Слух',
    'mind' => 'Разум',
    'nausea' => 'Тошнота',
    'blocking' => 'Блокировка',
    'decay' => 'Разложение',
    'metabolism' => 'Обмен веществ',
];
$keywordIds = [];
$keywordPending = [];
$syntheticId = 1000000;
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
    'tearing' => [
        'name' => 'Слезоточивость',
        'cost' => 1,
        'creation' => 3,
        'power' => [
            'base' => 3,
            'size' => -1,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 1,
                'unit' => 'turn',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 1,
                'ability_code' => 'fiziologiya',
            ],
        ],
        'keywords' => ['vision'],
        'description' => '<p>Эффект работает только на живых.</p>

<p>У целевого существа из глаз текут слёзы - для него все существа имеют лёгкое укрытие. Это эффект от зрения.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime.',
    ],
    'runny-nose' => [
        'name' => 'Насморк',
        'cost' => 1,
        'creation' => 3,
        'power' => [
            'base' => 3,
            'size' => -1,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 1,
                'unit' => 'turn',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 1,
                'ability_code' => 'fiziologiya',
            ],
        ],
        'keywords' => [],
        'description' => '<p>Эффект работает только на живых.</p>

<p>У цели забиваются выделениями дыхательные каналы, это снижает Восприятие от обоняния на 3 размера. Также действие отдыха требует в два раза больше времени и для расчета времени получения истощения от нагрузок Выносливость цели считается на размер меньше. Пример: для бега. Это эффект от насморка.</p>

<p>После окончания действия заклинания эффект продолжает действовать, пока цель не прочистит дыхательные каналы действием за 3ОД.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime.',
    ],
    'living-itch' => [
        'name' => 'Чесотка живого',
        'cost' => 2,
        'creation' => 3,
        'power' => [
            'base' => 4,
            'size' => -1,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 1,
                'unit' => 'turn',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 1,
                'ability_code' => 'fiziologiya',
            ],
        ],
        'keywords' => ['nervous-system'],
        'description' => '<p>Эффект работает только на живых.</p>

<p>Всё тело цели сильно чешется. Она должна пройти проверку на силу воли со сложностью 2. В случае провала она получает одну помеху на всё от рассеянности. Если она проваливает проверку минимум с -2РУ, то она теряет концентрацию и не может сконцентрироваться на протяжении действия заклинания. При -4РУ она помимо этого пытается чесаться - обычно это сопутствующее действие рукой.</p>

<p>В конце каждого своего хода цель должна пройти проверку на силу воли и изменить действие эффекта в соответствии с новым результатом.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime.',
    ],
    'heightened-senses' => [
        'name' => 'Обострённые чувства живого',
        'cost' => 5,
        'creation' => 5,
        'power' => [
            'base' => 4,
            'size' => -1,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 5,
                'unit' => 'turn',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 2,
                'ability_code' => 'fiziologiya',
            ],
            [
                'type' => 'has_ability',
                'min_level' => 1,
                'ability_code' => 'living-itch',
            ],
        ],
        'keywords' => ['nervous-system'],
        'description' => '<p>Эффект работает только на живых.</p>

<p>Существо получает +3 к Реакции от улучшения проводимости нервной системы.</p>

<p>Сложность заклинания повышена на размер за каждый размер, на который существо больше среднего.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime. Требование «10 опыта ноломансии нервной системы» не смоделировано.',
    ],
    'living-pain' => [
        'name' => 'Боль живого',
        'cost' => 1,
        'creation' => 3,
        'power' => [
            'base' => 4,
            'size' => -1,
        ],
        'duration' => [
            'type' => 'instant',
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 1,
                'ability_code' => 'fiziologiya',
            ],
        ],
        'keywords' => ['nervous-system'],
        'description' => '<p>Эффект работает только на существ с телом гуманоида.</p>

<p>Существо испытывает боль, и должно пройти проверку на силу воли со сложностью 2 против боли.</p>

<p>В случае провала она теряет концентрацию и теряет количество ОД по значению результата провала.</p>

<p>В случае провала с 4РУ и больше цель теряет сознание и получает кубик на увечье от боли за каждую 1, на которую РУ превышает Стойкость.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18).',
    ],
    'living-numbness' => [
        'name' => 'Бесчувственность живого',
        'cost' => 3,
        'creation' => [
            'type' => 'turn',
            'count' => 1,
        ],
        'power' => [
            'base' => 4,
            'size' => -1,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 1,
                'unit' => 'minute',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 2,
                'ability_code' => 'fiziologiya',
            ],
        ],
        'keywords' => ['nervous-system'],
        'description' => '<p>Эффект работает только на живых.</p>

<p>У цели притупляется верхняя граница чувствительности нервных окончаний, что позволяет ему не чувствовать боли, но понимать, что с ним происходит.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime. Требование «10 опыта ноломансии нервной системы» не смоделировано.',
    ],
    'magic-absorbers' => [
        'name' => 'Поглотители магии',
        'cost' => 4,
        'creation' => 3,
        'power' => [
            'type' => 'parameter',
            'parameter_code' => 'x',
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 2,
                'unit' => 'turn',
            ],
        ],
        'requirements' => [],
        'keywords' => [],
        'description' => '<p>Если у цели есть сопротивление магии, то оно считается на х меньше для этого заклинания.</p>

<p>+х Поглощения магии и -х Сопротивления магии цели. Поглощение магии будет подобно сопротивлению магии защищать цель от магии, но и, вместе с этим, мешать ей творить волшебство.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Сырое «х↑» сведено к параметру x. Длительность lingering не поддержана runtime.',
        'parameters' => [
            [
                'code' => 'x',
                'kind' => 'dimensional',
                'label' => 'X',
                'min' => [
                    'base' => 3,
                    'size' => -1,
                ],
                'default' => [
                    'base' => 3,
                    'size' => 0,
                ],
                'resolution' => 'activation',
            ],
        ],
    ],
    'humanoid-nausea' => [
        'name' => 'Тошнота гуманоида',
        'cost' => 1,
        'creation' => [
            'type' => 'turn',
            'count' => 1,
        ],
        'power' => [
            'base' => 4,
            'size' => -1,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 1,
                'unit' => 'minute',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 1,
                'ability_code' => 'fiziologiya',
            ],
        ],
        'keywords' => ['nausea'],
        'description' => '<p>Эффект работает только на существ с телом гуманоида.</p>

<p>Сложность заклинания увеличена на размер за каждый размер цели выше среднего.</p>

<p>Существо должно пройти проверку на силу воли со сложностью 1, или получит помеху на все совершаемые действия от тошноты. Существо может пройти проверку на силу воли один раз в ход, чтобы подавить этот эффект от тошноты.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime.',
    ],
    'humanoid-distraction' => [
        'name' => 'Рассеянность гуманоида',
        'cost' => 3,
        'creation' => [
            'type' => 'turn',
            'count' => 1,
        ],
        'power' => [
            'base' => 4,
            'size' => -1,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 30,
                'unit' => 'minute',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 2,
                'ability_code' => 'fiziologiya',
            ],
        ],
        'keywords' => ['mind'],
        'description' => '<p>Эффект работает только на гуманоидов.</p>

<p>Цель получает увечье разума с силой 3 Разрушение разума: “-сила к Восприятию и Интеллекту от рассеянности. Если вы получаете ещё один эффект, то их эффект суммируется, вплоть до [Получаемая сила * 2]. Каждые 10 минут уменьшается сила на 1.”</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime.',
    ],
    'living-weakness' => [
        'name' => 'Слабость живого',
        'cost' => 2,
        'creation' => 3,
        'power' => [
            'base' => 4,
            'size' => -1,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 1,
                'unit' => 'turn',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 1,
                'ability_code' => 'fiziologiya',
            ],
        ],
        'keywords' => ['nervous-system', 'blocking'],
        'description' => '<p>Эффект работает только на живых.</p>

<p>Движения цели становятся заторможенными - для всех действий ей сила снижена на размер.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime.',
    ],
    'living-clumsiness' => [
        'name' => 'Неуклюжесть живого',
        'cost' => 2,
        'creation' => 3,
        'power' => [
            'base' => 4,
            'size' => -1,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 1,
                'unit' => 'turn',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 1,
                'ability_code' => 'fiziologiya',
            ],
        ],
        'keywords' => ['nervous-system', 'blocking'],
        'description' => '<p>Эффект работает только на живых.</p>

<p>Существо получает -3 к Восприятию и помеху на все совершаемые им проверки от нарушений нервной системы.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime.',
    ],
    'living-blindness' => [
        'name' => 'Слепота живого',
        'cost' => 2,
        'creation' => 3,
        'power' => [
            'base' => 4,
            'size' => -1,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 1,
                'unit' => 'turn',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 2,
                'ability_code' => 'fiziologiya',
            ],
            [
                'type' => 'has_ability',
                'min_level' => 1,
                'ability_code' => 'living-clumsiness',
            ],
        ],
        'keywords' => ['nervous-system', 'blocking', 'vision'],
        'description' => '<p>Эффект работает только на живых.</p>

<p>Существо теряет зрение на время действия заклинания.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime.',
    ],
    'living-deafness' => [
        'name' => 'Глухота живого',
        'cost' => 2,
        'creation' => 3,
        'power' => [
            'base' => 4,
            'size' => -1,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 1,
                'unit' => 'turn',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 2,
                'ability_code' => 'fiziologiya',
            ],
            [
                'type' => 'has_ability',
                'min_level' => 1,
                'ability_code' => 'living-clumsiness',
            ],
        ],
        'keywords' => ['nervous-system', 'blocking', 'hearing'],
        'description' => '<p>Эффект работает только на живых.</p>

<p>Существо теряет слух на время действия заклинания.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime.',
    ],
    'living-paralysis' => [
        'name' => 'Паралич живого',
        'cost' => 2,
        'creation' => 3,
        'power' => [
            'base' => 3,
            'size' => 1,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 1,
                'unit' => 'turn',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 2,
                'ability_code' => 'fiziologiya',
            ],
            [
                'type' => 'has_ability',
                'min_level' => 1,
                'ability_code' => 'living-clumsiness',
            ],
        ],
        'keywords' => ['nervous-system', 'blocking'],
        'description' => '<p>Эффект работает только на живых.</p>

<p>Существо теряет способность управлять своим телом и падает на месте. За каждый размер, на который существо больше среднего, сложность заклинания увеличивается на размер.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime.',
    ],
    'living-decay' => [
        'name' => 'Разложение живого',
        'cost' => 3,
        'creation' => 5,
        'power' => [
            'base' => 3,
            'size' => 0,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 2,
                'unit' => 'turn',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 2,
                'ability_code' => 'fiziologiya',
            ],
        ],
        'keywords' => ['decay'],
        'description' => '<p>Эффект работает только на живых.</p>

<p>Тело цели начинает буквально разлагаться - он в конце каждого своего хода совершает проверку на получение увечья с силой 1. В том числе и перед окончанием действия заклинания.</p>

<p>За каждый размер тела цели выше среднего сложность заклинания повышается на размер.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime. Требование «10 опыта ноломансии» не смоделировано.',
    ],
    'accelerated-metabolism' => [
        'name' => 'Ускоренный обмен веществ',
        'cost' => 3,
        'creation' => [
            'type' => 'turn',
            'count' => 10,
        ],
        'power' => [
            'base' => 3,
            'size' => 0,
        ],
        'duration' => [
            'type' => 'lingering',
            'limit' => [
                'value' => 24,
                'unit' => 'hour',
            ],
        ],
        'requirements' => [
            [
                'type' => 'has_ability',
                'min_level' => 2,
                'ability_code' => 'fiziologiya',
            ],
        ],
        'keywords' => ['metabolism'],
        'description' => '<p>Эффект работает только на живых.</p>

<p>Обмен веществ у цели существенно ускоряется. Раны, увечья не относящиеся к разуму и истощение проходят от отдыха в пять раз быстрее от ускорения обмена веществ, нежели обычно. Существу необходимо есть и пить в пять раз больше, нежели обычно. Эффект не избавляет от усталости разума и цель по прежнему нуждается в нормальном количестве сна.</p>

<p>Сложность повышена на размер за каждый класс размера, на который цель больше среднего.</p>',
        'note' => 'Эффект заклинания не реализован в runtime (M18). Длительность lingering не поддержана runtime. Требование «10 опыта ноломансии» не смоделировано.',
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
    $spec = spellSpec($card, $keywordIds);
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
        abilityKeywords($card['keywords'], $keywordIds),
        [],
        'needs_work',
        $card['note'],
    ));
}

$catalogCodes = $present;
foreach (array_keys($cards) as $code) {
    $catalogCodes[$code] = true;
}
$catalog = catalogWithNolomancy($locator, $spaceId, array_keys($cards), $catalogCodes, $stats);

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
 * Признаки карточки: базовые школы и тематические.
 *
 * @param array<int, string> $extra Тематические коды.
 * @param array<string, int> $ids Справочник id.
 *
 * @return array<int, int> Id.
 */
function abilityKeywords(array $extra, array $ids): array
{
    $result = [$ids['skill'], $ids['magic'], $ids['spell'], $ids['nolomancy']];
    foreach ($extra as $code) {
        if (isset($ids[$code])) {
            $result[] = $ids[$code];
        }
    }

    return array_values(array_unique($result));
}

/**
 * Требования карточки: Физиология — знание, а не отдельный навык.
 *
 * @param array<int, array<string, mixed>> $requirements Требования.
 *
 * @return array<int, array<string, mixed>> Требования спеки.
 */
function nolomancyRequirements(array $requirements): array
{
    $result = [];
    foreach ($requirements as $requirement) {
        if (($requirement['type'] ?? null) === 'has_ability' && ($requirement['ability_code'] ?? null) === 'fiziologiya') {
            $result[] = [
                'type' => 'has_knowledge',
                'field_code' => 'physiology',
                'min_level' => $requirement['min_level'] ?? 1,
            ];

            continue;
        }

        $result[] = $requirement;
    }

    return $result;
}

/**
 * Спека заклинания Ноломансии: касание и создание по умолчанию.
 *
 * @param array<string, mixed> $card Карточка.
 * @param array<string, int> $ids Признаки.
 *
 * @return array<string, mixed> Спека.
 */
function spellSpec(array $card, array $ids): array
{
    $spec = [
        'type' => 'spell',
        'spell' => [
            'power' => $card['power'],
            'control' => $card['power'],
            'duration' => $card['duration'],
            'targeting' => 'none',
        ],
        'study' => ['paths' => 'any'],
        'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [$card['cost']]]],
        'grants' => [],
        'multiple' => true,
        'domain_ref' => 'magic-path',
        'requirements' => $card['requirements'] === []
            ? []
            : [['level' => 1, 'requirements' => nolomancyRequirements($card['requirements'])]],
        'hit_resolution' => ['type' => 'attack'],
        'action_components' => [
            [
                'type' => 'resource',
                'label' => 'Сотворение',
                'amount' => $card['creation'],
                'resource_code' => 'action-points',
            ],
            [
                'type' => 'somatic',
                'note' => 'Касание цели',
            ],
        ],
        'parent_ability_code' => null,
    ];
    if (isset($card['parameters'])) {
        $spec['parameters'] = $card['parameters'];
    }

    return $spec;
}

/**
 * Последний каталог с секцией Ноломансии и размещениями карточек.
 *
 * @param mixed $locator Локатор.
 * @param int $spaceId Мир.
 * @param array<int, string> $codes Коды карточек.
 * @param array<string, true> $ruleCodes Состав ревизии.
 * @param array<string, mixed> $stats Статистика.
 *
 * @return RuleSpaceCatalog|null Снимок или null.
 */
function catalogWithNolomancy(
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

    $sectionCode = 'abilities-acquired-magic-spells-nolomancy';
    if (!isset($existingSections[$sectionCode])) {
        $sections[] = new RuleSpaceCatalogSection(
            $sectionCode,
            'Ноломансия',
            'abilities-acquired-magic-spells',
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
