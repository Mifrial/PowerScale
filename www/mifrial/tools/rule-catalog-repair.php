<?php

declare(strict_types=1);

use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\RuleSpace\Repository\RuleSpaceCatalogRepository;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogItemTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogSectionTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceRevisionCatalogTable;

$options = getopt('', ['space::', 'revision::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-catalog-repair.php --revision=17 [--space=13] [--commit]\n");
    fwrite(STDOUT, "Возвращает указатель каталога ревизии на latest-снимок секций.\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$revision = (int) ($options['revision'] ?? 0);
$commit = isset($options['commit']);
if ($revision < 1) {
    fwrite(STDERR, "Укажите --revision\n");
    exit(1);
}

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$gateway = $locator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
if (!$gateway instanceof ISmartTableGateway) {
    fwrite(STDERR, "Не удалось получить SmartTable gateway\n");
    exit(1);
}

$repository = new RuleSpaceCatalogRepository(
    $gateway->open(RuleSpaceCatalogSectionTable::class)->records(),
    $gateway->open(RuleSpaceCatalogItemTable::class)->records(),
    $gateway->open(RuleSpaceRevisionCatalogTable::class)->records(),
);

$catalog = $repository->getByRevision($spaceId, $revision);
if ($catalog->getSections() !== []) {
    $count = count($catalog->getSections());
    fwrite(STDOUT, 'Ревизия ' . $revision . ' уже ссылается на каталог (' . $count . " секций)\n");
    exit(0);
}

$latest = $repository->findLatestSectionVersion($spaceId);
if ($latest === null) {
    fwrite(STDERR, 'Нет ни одного снимка секций для мира ' . $spaceId . "\n");
    exit(2);
}

fwrite(STDOUT, 'Ревизия ' . $revision . ' без каталога; latest-снимок: ' . $latest . PHP_EOL);
if (!$commit) {
    fwrite(STDOUT, "Dry-run. Запустите с --commit.\n");
    exit(0);
}

$repository->insertPointer($spaceId, $revision, $latest);
fwrite(STDOUT, 'Указатель ревизии ' . $revision . ' → снимок ' . $latest . " записан\n");
