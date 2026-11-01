<?php

declare(strict_types=1);

use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Spec\RuleSpecs;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

$options = getopt('', ['space::', 'from::', 'to::', 'commit', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/rule-migrate-24.php [--space=13] [--from=25] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 25);
$to = (int) ($options['to'] ?? $from + 1);
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

$keywordIds = [
    'skill' => keywordId($keywords, 'skill', 'Навык'),
    'action' => keywordId($keywords, 'action', 'Действие'),
    'attack' => keywordId($keywords, 'attack', 'Атака'),
    'push' => keywordId($keywords, 'push', 'Толчок'),
];

$descriptions = [
    'push-procedure' => '<p>Вы толкаете цель. Пул толчка и урон толчка задаёт способность, '
        . 'которой выполняется толчок. Цель либо игнорирует толчок, либо выбирает доступную '
        . 'ей защитную реакцию.</p>'
        . '<p><strong>Защитные реакции</strong></p>'
        . '<ul>'
        . '<li><strong>Игнорирование.</strong> Атакующий совершает проверку на толчок: '
        . 'пул толчка против минимальной сложности — <a data-rule-code="weight">Вес</a> цели '
        . 'минус один размер.</li>'
        . '<li><strong>Уклонение.</strong> Атакующий совершает '
        . '<a data-rule-code="check-hit">проверку на попадание</a>. При попадании толчок '
        . 'продолжается как при игнорировании, но цель получает '
        . '<a data-rule-code="dodge">Смягчение от уклонения</a>. При промахе толчок сорван '
        . 'и не имеет последствий.</li>'
        . '<li><strong>Блокирование.</strong> Атакующий и защитник совершают совместную '
        . 'проверку на толчок: атакующий — своим пулом, защитник — Силой. Минимальная '
        . 'сложность — <a data-rule-code="weight">Вес</a> минус один размер. Защитник '
        . 'выбирает доступный профиль блокирования; защита этого профиля прибавляется '
        . 'к сопротивлению. РУ попадания при блокировании равен 1.</li>'
        . '</ul>'
        . '<p><strong>Результат толчка</strong></p>'
        . '<ol>'
        . '<li>Толчок наносит урон как любое попадание: (урон толчка − сопротивление) × '
        . 'РУ попадания − Смягчение от уклонения.</li>'
        . '<li>Цель получает <a data-rule-code="unstable">Неустойчивость</a> со значением '
        . 'РУ толчка.</li>'
        . '<li>Если РУ толчка 4 или больше, цель отбрасывается на ⌊РУ толчка / 4⌋ ипари.</li>'
        . '</ol>'
        . '<p><span class="description-hint">РУ проверки на попадание — это РУ попадания; '
        . 'РУ проверки на толчок — это РУ толчка. Не путайте их.</span></p>',
    'simple-push' => '<p>Совершите <a data-rule-code="push-procedure">Толчок</a> по цели. '
        . 'Пул — <a data-rule-code="strength">Сила</a>, урон — дробящий и равен сырым '
        . 'успехам проверки на толчок, уменьшенным на один размер шкалы. По умолчанию '
        . 'толчок выполняется руками, можно щитом; оружие без щита не подходит. '
        . 'Блокировать толчок можно руками или щитом.</p>',
    'tolkayuschiy-udar' => '<p>Совершите <a data-rule-code="push-procedure">Толчок</a> по цели, '
        . 'используя вместо рук дробящий или рубящий профиль оружия. Пул — урон оружия, '
        . 'урон — урон оружия при обычном ударе. Блокировать толчок можно дробящим '
        . 'или рубящим профилем оружия.</p>'
        . '<p>Если удар рубящий, то РУ толчка для Неустойчивости и отброса делится на 2 '
        . '(округление вниз); урон считается от полного РУ.</p>',
];

$slice = $spaces->getRevision($spaceId, $from);
$entries = [];
$stats = [
    'changed' => [],
    'added' => [],
    'errors' => [],
];
$codes = [];

foreach ($slice->getItems() as $record) {
    $code = $record->getCode();
    $codes[$code] = true;
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

    $name = $code === 'simple-push' ? 'Простой толчок' : $record->getName();
    $stats['changed'][] = $code;
    $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
        $record->getType(),
        $name,
        $description,
        $spec,
        [$keywordIds['skill'], $keywordIds['action'], $keywordIds['attack'], $keywordIds['push']],
        $record->getMechanics(),
        $record->getContentStatus(),
        $record->getContentNote(),
    ));
}

if (!isset($codes['push-procedure'])) {
    $stats['added'][] = 'push-procedure';
    $entries[] = RuleCommitEntry::put('push-procedure', new RuleVersionBody(
        'simple',
        'Толчок',
        $descriptions['push-procedure'],
        [],
        [$keywordIds['push']],
        [],
        'needs_work',
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

/**
 * Id признака по коду; отсутствующий создаётся.
 *
 * @param IKeywords $keywords Сервис признаков.
 * @param string $code Код.
 * @param string $name Подпись нового признака.
 *
 * @return int Id.
 */
function keywordId(IKeywords $keywords, string $code, string $name): int
{
    try {
        return $keywords->getByCode($code)->getId();
    } catch (KeywordNotFoundException) {
        return $keywords->add($code, $name);
    }
}
