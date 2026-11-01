<?php

declare(strict_types=1);

use Mifrial\Roleplay\Game\Dto\GamePatch;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'game::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-22.php [--space=13] [--from=22] [--game=1] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 22);
$to = (int) ($options['to'] ?? $from + 1);
$gameId = isset($options['game']) ? (int) $options['game'] : null;
$commit = isset($options['commit']);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$spaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
if (!$spaces instanceof IRuleSpaces) {
    fwrite(STDERR, "Не удалось получить сервис миров\n");
    exit(1);
}

$descriptions = [
    'simple-push' => 'Базовый толчок. 3 ОД. Есть у каждого персонажа автоматически. '
        . 'По умолчанию руками, можно щитом. Цель сопротивляется Силой против пола: '
        . 'вес цели минус один размер. Успех наносит дробящий урон: '
        . '(сырые успехи Силы − 3) × РУ. Цель получает Неустойчивость на РУ; '
        . 'при РУ больше 6 её отбрасывает на РУ − 6 ипари. Уклонение: сначала проверка '
        . 'попадания, при промахе толчок сорван. При блоке защита блока входит '
        . 'в сопротивление, а успешный блок оставляет 1 РУ контакта.',
    'tolkayuschiy-udar' => 'Совершите Толчок по цели своим оружием, выбрав рубящий '
        . 'или дробящий урон. 4 ОД. Проверка толчка идёт от урона оружия, не от Силы: '
        . 'пул — урон оружия, база урона — тоже урон оружия. Урон считается как '
        . 'у обычного удара: (урон оружия − сопротивление) × РУ. Для отброса '
        . 'и Неустойчивости рубящий удар берёт половину РУ, дробящий — полный РУ. '
        . 'При блоке защита блока входит в сопротивление.',
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
    'game' => $gameId,
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

if ($gameId === null) {
    exit(0);
}

$games = $locator->get(IGameContainer::class)->get(IGames::class);
if (!$games instanceof IGames) {
    fwrite(STDERR, "Не удалось получить сервис игр\n");
    exit(1);
}

$game = $games->get($gameId);
if ($game->getSpaceId() !== $spaceId) {
    fwrite(STDERR, 'Игра ' . $gameId . ' не в мире ' . $spaceId . PHP_EOL);
    exit(3);
}

if ($game->getRulesRevision() === $revision->getRevision()) {
    fwrite(STDOUT, 'Игра ' . $gameId . ' уже на ревизии ' . $revision->getRevision() . PHP_EOL);
    exit(0);
}

try {
    $games->update($gameId, GamePatch::fromNormalized([
        'name' => $game->getName(),
        'shortDescription' => $game->getShortDescription(),
        'description' => $game->getDescription(),
        'status' => $game->getStatus(),
        'visibility' => $game->getVisibility(),
        'joinPolicy' => $game->getJoinPolicy(),
        'osPointsLimit' => $game->getOsPointsLimit(),
        'olPointsLimit' => $game->getOlPointsLimit(),
        'orPointsLimit' => $game->getOrPointsLimit(),
        'moneyLimit' => $game->getMoneyLimit(),
        'rulesRevision' => $revision->getRevision(),
    ]));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Ревизия опубликована, но игру не удалось перевести: '
        . $exception->getMessage() . PHP_EOL);
    exit(4);
}

fwrite(STDOUT, 'Игра ' . $gameId . ' переведена на ревизию ' . $revision->getRevision() . PHP_EOL);
