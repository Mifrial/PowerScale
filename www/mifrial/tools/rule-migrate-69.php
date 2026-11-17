<?php

declare(strict_types=1);

use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogSection;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use Mifrial\Roleplay\RuleSpace\Repository\RuleSpaceCatalogRepository;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogItemTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogSectionTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceRevisionCatalogTable;

$options = getopt('', ['space::', 'from::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-69.php [--space=13] [--from=68] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 68);
$to = $from + 1;
$commit = isset($options['commit']);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

/**
 * Текущая карточка «Проклятие взглядом»: мощь +N пунктов, а не размеров.
 *
 * @return array{description: string, note: string, range: array<string, int>}
 */
function gazeCurse(): array
{
    return [
        'description' => 'Улучшение любого касательного заклинания ноломансии: вместо касания цель '
            . 'выбирается на расстоянии до N ипари. Требуемая мощь растёт на N пунктов, Требуемый '
            . 'контроль — на 2.',
        'note' => 'Дальность и расстояние до цели вводятся вручную: батлмапа нет, расстояние заявки '
            . 'сверяется с пределом N. Попадание не бросается — эффект ложится после проверки сотворения.',
        'range' => ['power_per_step' => 1, 'control_base' => 2],
    ];
}

/**
 * Комментарии остальных навыков школы.
 *
 * @return array<string, string> Код => комментарий.
 */
function otherNotes(): array
{
    return [
        'experienced-nolomant' => 'Преимущество от мастерства: с «Контролем нервов» действует '
            . 'сильнейшее, а не сумма.',
        'nerve-control' => 'Требование «Опытный электромант» не выгружено (электромансия): '
            . 'в карточке не выставлено.',
        'nerve-decay' => 'Постоянное состояние: строка эффекта без срока. Требование «заклинание '
            . 'ноломансии нервной системы» выражено двумя отдельными признаками — они могут быть '
            . 'у разных карточек.',
        'nerve-transformation' => 'Скидка 2 при «Разложении нервов» не смоделирована. Намеренное '
            . 'снятие постоянного состояния и задержка «через ход» пока не реализованы.',
    ];
}

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'kept' => 0,
    'updated' => [],
    'errors' => [],
];
$targets = ['gaze-curse' => gazeCurse()] + array_map(
    static fn (string $note): array => ['note' => $note],
    otherNotes(),
);
$present = [];
foreach ($slice->getItems() as $record) {
    $present[$record->getCode()] = true;
    if (isset($targets[$record->getCode()])) {
        continue;
    }

    ++$stats['kept'];
    $entries[] = RuleCommitEntry::keep($record->getVersionId());
}

$body = static function (RuleVersionRecord $record, string $description, string $note, ?array $range): RuleVersionBody {
    $spec = $record->getSpecDocument();
    if ($range !== null) {
        $spec['spell_upgrade']['range'] = $range;
        unset($spec['spell_upgrade']['power_size_per_step']);
    }

    return new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $description,
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        $note,
    );
};

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    if (!isset($targets[$code])) {
        continue;
    }

    $target = $targets[$code];
    if (!isset($present[$code])) {
        $stats['errors'][] = $code . ': карточки нет в ревизии';
        continue;
    }

    $newBody = $body(
        $record,
        $target['description'] ?? $record->getDescription(),
        $target['note'],
        $target['range'] ?? null,
    );
    $check = RuleSpecs::read($newBody->getType(), $newBody->getSpec());
    if ($check->isBroken()) {
        $stats['errors'][] = $code . ': новая форма битая (' . $check->getShapeKey() . ')';
        continue;
    }

    $stats['updated'][] = $code;
    $entries[] = RuleCommitEntry::put($code, $newBody);
}

$catalog = latestCatalog($locator, $spaceId, $present, $stats);
if ($catalog instanceof RuleSpaceCatalog && $catalog->getPlacements() === []) {
    $stats['errors'][] = 'catalog: пустой каталог';
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
 * Последний каталог с размещениями только из новой ревизии.
 *
 * @param mixed $locator Локатор.
 * @param int $spaceId Мир.
 * @param array<string, true> $ruleCodes Состав ревизии.
 * @param array<string, mixed> $stats Статистика.
 *
 * @return RuleSpaceCatalog|null Снимок или null.
 */
function latestCatalog(mixed $locator, int $spaceId, array $ruleCodes, array &$stats): ?RuleSpaceCatalog
{
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
    $sections = [];
    foreach ($latest->getSections() as $section) {
        $sections[] = new RuleSpaceCatalogSection(
            $section->getCode(),
            $section->getName(),
            $section->getParentCode(),
            $section->getSortOrder(),
            $section->getCatalogRootFor(),
        );
    }
    $placements = [];
    foreach ($latest->getPlacements() as $placement) {
        if (!isset($ruleCodes[$placement->getRuleCode()])) {
            $stats['catalog_dropped'][] = $placement->getRuleCode();
            continue;
        }

        $placements[] = $placement;
    }

    try {
        return RuleSpaceCatalog::fromParts($sections, $placements);
    } catch (Throwable $exception) {
        $stats['errors'][] = 'catalog: ' . $exception->getMessage();

        return null;
    }
}
