<?php

declare(strict_types=1);

use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Service\CharacterRuleSlicePortFactory;
use Mifrial\Roleplay\Character\Service\CharacterSheetPortFactory;
use Mifrial\Roleplay\Character\Service\Runtime\Character;
use Mifrial\Roleplay\Character\Service\Save\CharacterItemInstances;

$options = getopt('', ['commit', 'limit::', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "php tools/character-repair-50.php [--commit] [--limit=100]\n");
    exit(0);
}

$commit = isset($options['commit']);
$limit = (int) ($options['limit'] ?? 0);

$app = require dirname(__DIR__) . '/init.php';
$locator = $app->getLocator();
$characters = $locator->get(ICharacterContainer::class)->get(ICharacters::class);
if (!$characters instanceof ICharacters) {
    fwrite(STDERR, "Не удалось получить сервис персонажей\n");
    exit(1);
}

$slices = (new CharacterRuleSlicePortFactory())->create($locator);
$sheets = (new CharacterSheetPortFactory())->create($locator);
$instances = new CharacterItemInstances();

$config = require dirname(__DIR__) . '/config/local.php';
$db = $config['db'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['database'], $db['charset']),
    $db['username'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$sql = 'SELECT id FROM `character` ORDER BY id';
if ($limit > 0) {
    $sql .= ' LIMIT ' . $limit;
}

$stats = ['checked' => 0, 'fixed' => 0, 'skipped' => 0, 'errors' => []];
foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $id) {
    ++$stats['checked'];
    try {
        $record = $characters->get((int) $id);
        $choices = $record->getChoices();
        $abilities = is_array($choices['abilities'] ?? null) ? $choices['abilities'] : [];
        $core = null;
        foreach ($abilities as $ability) {
            if (
                is_array($ability) && ($ability['ruleCode'] ?? null) === 'magic-core-capacity'
                && is_int($ability['level'] ?? null) && $ability['level'] >= 1
            ) {
                $core = $ability;
                break;
            }
        }

        if ($core === null) {
            ++$stats['skipped'];
            continue;
        }

        $slice = $slices->get($record->getSpaceId(), $record->getRulesRevision());
        $normalized = $instances->normalize($slice, $choices);
        $inventory = is_array($normalized['choices']['inventory'] ?? null) ? $normalized['choices']['inventory'] : [];
        $hasBound = false;
        foreach ($inventory as $row) {
            if (
                is_array($row) && ($row['grantedBy']['ruleCode'] ?? null) === 'magic-core-capacity'
                && is_array($row['parameters']['power'] ?? null)
            ) {
                $hasBound = true;
                break;
            }
        }

        if (!$hasBound) {
            $value = $core['parameters']['x'] ?? null;
            if (!is_array($value) || !is_int($value['base'] ?? null) || !is_int($value['size'] ?? null)) {
                $value = ['base' => 3, 'size' => 0];
            }

            $adopted = false;
            foreach ($inventory as $index => $row) {
                if (!is_array($row) || ($row['ruleCode'] ?? null) !== 'magic-core' || isset($row['grantedBy'])) {
                    continue;
                }

                $inventory[$index]['equipped'] = true;
                $inventory[$index]['parameters'] = ['power' => $value];
                $inventory[$index]['grantedBy'] = ['ruleCode' => 'magic-core-capacity'];
                $adopted = true;
                break;
            }

            if (!$adopted) {
                $nextId = 0;
                foreach ($inventory as $row) {
                    $nextId = max($nextId, is_array($row) && is_int($row['id'] ?? null) ? $row['id'] : 0);
                }

                $inventory[] = [
                    'id' => $nextId + 1,
                    'ruleCode' => 'magic-core',
                    'quantity' => 1,
                    'equipped' => true,
                    'parameters' => ['power' => $value],
                    'grantedBy' => ['ruleCode' => 'magic-core-capacity'],
                ];
            }

            $normalized['choices']['inventory'] = $inventory;
            $normalized = $instances->normalize($slice, $normalized['choices']);
        }

        if ($normalized['problems'] !== []) {
            $codes = array_map(static fn ($problem): string => $problem->getCode(), $normalized['problems']);
            $stats['errors'][] = $id . ': ' . implode(',', $codes);
            continue;
        }

        $sheet = Character::fromDocument(
            $slice,
            $normalized['choices'],
            $record->getSheet(),
            $sheets,
        )->getSheet();

        if ($commit) {
            $characters->replaceMigrated(
                $record->getId(),
                $record->getName(),
                $record->isActive(),
                $normalized['choices'],
                $sheet,
                $record->getRulesRevision(),
                $record->getActualVersion(),
            );
        }

        ++$stats['fixed'];
    } catch (Throwable $exception) {
        $stats['errors'][] = $id . ': ' . $exception->getMessage();
    }
}

fwrite(STDOUT, json_encode([
    'commit' => $commit,
    'stats' => $stats,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);

exit($stats['errors'] === [] ? 0 : 2);
