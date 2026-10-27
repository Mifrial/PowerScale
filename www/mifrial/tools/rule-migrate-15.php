<?php

declare(strict_types=1);

use Mifrial\Roleplay\Game\Dto\GamePatch;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;

$options = getopt('', ['space::', 'from::', 'to::', 'game::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-15.php [--space=13] [--from=14] [--game=1] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 14);
$to = (int) ($options['to'] ?? $from + 1);
$gameId = isset($options['game']) ? (int) $options['game'] : null;
$commit = isset($options['commit']);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$rules = $locator->get(IRuleContainer::class)->get(IRules::class);
if (!$rules instanceof IRules) {
    fwrite(STDERR, "Не удалось получить сервис правил\n");
    exit(1);
}

$slice = $rules->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'dodge' => [],
    'errors' => [],
];

foreach ($slice->getItems() as $record) {
    $spec = $record->getSpecDocument();
    $changed = false;

    if ($record->getType() === 'ability' && ($spec['combat_action'] ?? null) === 'dodge') {
        $stats['dodge'][] = $record->getCode();
        if (($spec['dodge_efficiency'] ?? null) !== ['base' => 5, 'size' => -1]) {
            $spec['dodge_efficiency'] = ['base' => 5, 'size' => -1];
            $changed = true;
        }

        $penalty = [
            'profile_type' => 'shoot',
            'distance' => ['base' => 3, 'size' => 0],
            'delta' => -2,
        ];
        if (($spec['dodge_ranged_penalty'] ?? null) !== $penalty) {
            $spec['dodge_ranged_penalty'] = $penalty;
            $changed = true;
        }
    }

    if ($changed) {
        $read = RuleSpecs::read($record->getType(), $spec);
        if ($read->isBroken()) {
            $stats['errors'][] = $record->getCode() . ': ' . $read->getShapeKey();
        }
    }

    $entries[] = $changed
        ? RuleCommitEntry::put($record->getCode(), body($record, $spec))
        : RuleCommitEntry::keep($record->getVersionId());
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

$revision = $rules->commit($spaceId, $entries, false, $from);
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
    fwrite(STDERR, 'Ревизия опубликована, но игру не удалось перевести: ' . $exception->getMessage() . PHP_EOL);
    exit(4);
}

fwrite(STDOUT, 'Игра ' . $gameId . ' переведена на ревизию ' . $revision->getRevision() . PHP_EOL);

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
