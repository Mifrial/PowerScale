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
    fwrite(STDOUT, "php tools/rule-migrate-25.php [--space=13] [--from=26] [--commit]\n");
    exit(0);
}

$spaceId = (int) ($options['space'] ?? 13);
$from = (int) ($options['from'] ?? 26);
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
    'check-push' => '<p>Совместная проверка '
        . '<a data-rule-code="melee-combat">Мастерства ближнего боя</a> против уклонения '
        . 'или блока. Кубы даёт навык, эффективность — точность профиля. '
        . 'На боевом минимуме — молоко.</p>',
    'push-procedure' => '<p>Вы толкаете цель. Проверка на Толчок — совместная проверка '
        . '<a data-rule-code="melee-combat">Мастерства ближнего боя</a>: кубы даёт навык, '
        . 'эффективность — точность профиля. На боевом минимуме толчок уходит в молоко.</p>'
        . '<p><strong>Защитные реакции</strong></p>'
        . '<ul>'
        . '<li><strong>Игнорирование.</strong> Устойчивость защитника равна минимуму — '
        . '<a data-rule-code="weight">Вес</a> минус один размер.</li>'
        . '<li><strong>Уклонение.</strong> Успех защитника срывает толчок. Провал — '
        . 'Устойчивость равна минимуму, но цель получает '
        . '<a data-rule-code="dodge">Смягчение от уклонения</a>.</li>'
        . '<li><strong>Блокирование.</strong> Провал защитника снижает Устойчивость на РУ '
        . 'проверки, но не ниже минимума. Успех повышает Устойчивость на РУ проверки, '
        . 'но не больше чем на 6, и оставляет 1 РУ контакта; защита профиля входит '
        . 'в сопротивление.</li>'
        . '</ul>'
        . '<p><strong>Результат</strong></p>'
        . '<ol>'
        . '<li>Урон: урон профиля минус один размер, умноженный на РУ (на успешном блоке — '
        . 'на 1), минус сопротивление и <a data-rule-code="dodge">смягчение</a>.</li>'
        . '<li><a data-rule-code="unstable">Неустойчивость</a> равна разнице Сил атакующего '
        . 'и Устойчивости защитника, если она положительна.</li>'
        . '<li>Если Неустойчивость 4 или больше, цель отбрасывается на '
        . '⌊Неустойчивость / 4⌋ ипари.</li>'
        . '</ol>'
        . '<p><span class="description-hint">Устойчивость — эффективная Сила защитника '
        . 'для толчка. Её минимум — вес минус один размер.</span></p>',
    'simple-push' => '<p>Совершите <a data-rule-code="push-procedure">Толчок</a> по цели. '
        . 'Проверка — <a data-rule-code="check-push">Проверка на Толчок</a>. Урон — '
        . 'дробящий, урон профиля рук минус один размер; множитель — РУ проверки. '
        . 'По умолчанию толчок выполняется руками, можно щитом; оружие без щита '
        . 'не подходит. Блокировать можно руками или щитом.</p>',
    'tolkayuschiy-udar' => '<p>Совершите <a data-rule-code="push-procedure">Толчок</a> по цели, '
        . 'используя вместо рук дробящий или рубящий профиль оружия. Проверка — '
        . '<a data-rule-code="check-push">Проверка на Толчок</a>. Урон — урон оружия минус 1; '
        . 'блокировать можно дробящим или рубящим профилем.</p>'
        . '<p>Если удар рубящий, Неустойчивость и отброс делятся на 2 (округление вниз); '
        . 'урон считается полностью.</p>',
];

$checkSpec = [
    'type' => 'check',
    'allowed_modes' => 'both',
    'difficulty_input' => ['kind' => 'ask'],
    'parent_check_code' => 'check-simple',
    'push_check' => true,
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
    if ($description === null) {
        $entries[] = RuleCommitEntry::keep($record->getVersionId());
        continue;
    }

    $spec = $record->getSpecDocument();
    if ($record->getType() === 'ability') {
        $push = is_array($spec['push'] ?? null) ? $spec['push'] : [];
        unset($push['pool'], $push['damage']);
        $push['check'] = 'check-push';
        $push['damage_modifier'] = $code === 'tolkayuschiy-udar' ? -1 : -3;
        $push['stability_bonus_cap'] = 6;
        $spec['push'] = $push;
    }

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
        $record->getType() === 'ability'
            ? [$keywordIds['skill'], $keywordIds['action'], $keywordIds['attack'], $keywordIds['push']]
            : [$keywordIds['push']],
        $record->getMechanics(),
        $record->getContentStatus(),
        $record->getContentNote(),
    ));
}

if (!isset($codes['check-push'])) {
    $stats['added'][] = 'check-push';
    $entries[] = RuleCommitEntry::put('check-push', new RuleVersionBody(
        'check',
        'Проверка на Толчок',
        $descriptions['check-push'],
        $checkSpec,
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
