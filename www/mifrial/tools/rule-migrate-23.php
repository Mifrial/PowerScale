<?php

declare(strict_types=1);

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-23.php [--space=13] [--from=23] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 23);
$to = (int) ($options['to'] ?? $from + 1);
$commit = isset($options['commit']);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$descriptions = [
    'simple-push' => '<p><strong>Толчок.</strong> 3 ОД. Есть у каждого персонажа автоматически. '
        . 'По умолчанию руками, можно щитом; оружие без щита не подходит.</p>'
        . '<p>Цель сопротивляется Силой. Сложность — вес цели минус один размер шкалы, '
        . 'то есть вес − 3 пункта.</p>'
        . '<p>База дробящего урона — сырые успехи Силы минус один размер шкалы '
        . '(−3 пункта). Повреждение считается как у удара: '
        . '(база − сопротивление) × РУ − смягчение уклона.</p>'
        . '<p>Цель получает Неустойчивость на РУ. Если РУ больше 6, цель отбрасывает '
        . 'на РУ − 6 ипари.</p>'
        . '<p><strong>Реакции:</strong></p>'
        . '<ul>'
        . '<li><strong>Игнор</strong> — Сила против сложности, без броска попадания.</li>'
        . '<li><strong>Блок</strong> — совместная проверка Силы. Защита блока входит '
        . 'в сопротивление, а успешный блок оставляет 1 РУ контакта.</li>'
        . '<li><strong>Уклонение</strong> — сначала проверка попадания. При промахе толчок сорван.</li>'
        . '</ul>',
    'tolkayuschiy-udar' => '<p><strong>Толкающий удар.</strong> 4 ОД. Тот же Толчок, но оружием: '
        . 'выбирается рубящий или дробящий урон.</p>'
        . '<p>Проверка идёт от урона оружия, а не от Силы. База урона — тоже урон оружия. '
        . 'Повреждение считается как у обычного удара: '
        . '(урон оружия − сопротивление) × РУ − смягчение уклона.</p>'
        . '<p>Неустойчивость и отброс считаются от рейтинга позы:</p>'
        . '<ul>'
        . '<li>дробящий удар — полный РУ;</li>'
        . '<li>рубящий удар — половина РУ, округлённая вниз.</li>'
        . '</ul>'
        . '<p>При блоке защита блока входит в сопротивление, а успешный блок оставляет '
        . '1 РУ контакта.</p>',
];

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'changed' => [],
    'errors' => [],
];

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    $description = $descriptions[$code] ?? null;
    if ($description === null || $record->getType() !== 'ability') {
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $spec = $record->getSpecDocument();
    $read = RuleSpecs::read($record->getType(), $spec);
    if ($read->isBroken()) {
        $stats['errors'][] = $code . ': ' . $read->getShapeKey();
    }

    $stats['changed'][] = $code;
    $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
        $record->getType(),
        $record->getName(),
        $description,
        $spec,
        $record->getKeywordIds(),
        $record->getMechanics(),
        $record->getContentStatus(),
        $record->getContentNote(),
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
