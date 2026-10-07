<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Table\GameCheckCommandTable;
use Mifrial\Roleplay\Game\Table\GameCheckTable;
use Mifrial\Roleplay\Game\Table\GameProcessTable;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use PHPUnit\Framework\TestCase;

final class GameCheckMysqlTest extends TestCase
{
    use GameMysqlFixture;

    private ?IRequestContext $requestContext = null;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->connectGameMysql();
        $requestContext = $this->gameApplication()->getLocator()->get(IKernelContainer::class)->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $this->requestContext = $requestContext;
    }

    /**
     * Снимает таблицы.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropGameTables();
    }

    /**
     * Соло закрывает process и не пишет лист. Pairwise ждёт согласия.
     *
     * @return void
     */
    public function testSoloAndPairwise(): void
    {
        $world = $this->worldWithCheck();
        $gameId = $this->addGame($world->getId());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $version = $this->characterFacade()->get($characterId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $solo = $this->dispatch('game.declareCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'solo',
            'expectedSheetVersion' => $version,
            'check' => [
                'participant' => ['type' => 'character', 'id' => $characterId],
                'ruleCode' => 'notice',
            ],
        ]);
        self::assertTrue($solo['success'], json_encode($solo));
        self::assertSame('resolved', $solo['data']['status']);
        self::assertSame(['base' => 0, 'size' => 0], $solo['data']['difficulty']);
        self::assertNull($solo['data']['sheetVersion']);
        self::assertSame($version, $this->characterFacade()->get($characterId)->getActualVersion());
        $again = $this->dispatch('game.declareCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'solo',
            'expectedSheetVersion' => $version,
            'check' => [
                'participant' => ['type' => 'character', 'id' => $characterId],
                'ruleCode' => 'notice',
            ],
        ]);
        self::assertSame($solo['data']['processId'], $again['data']['processId']);
        self::assertSame($solo['data']['success'], $again['data']['success']);
        self::assertSame(1, $this->countAll(GameProcessTable::class));
        self::assertSame('resolved', $this->processStatus());
        $damage = $this->dispatch('game.declareCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'bad',
            'expectedSheetVersion' => $version,
            'check' => [
                'participant' => ['type' => 'character', 'id' => $characterId],
                'ruleCode' => 'notice',
                'damage' => 1,
            ],
        ]);
        self::assertSame('INVALID_PARAMS', $damage['error']['code']);
        $stale = $this->dispatch('game.declareCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'stale',
            'expectedSheetVersion' => $version + 1,
            'check' => [
                'participant' => ['type' => 'character', 'id' => $characterId],
                'ruleCode' => 'notice',
            ],
        ]);
        self::assertSame('GAME_CONFLICT', $stale['error']['code']);
        $joint = $this->dispatch('game.declareCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'joint-solo',
            'expectedSheetVersion' => $version,
            'check' => [
                'participant' => ['type' => 'character', 'id' => $characterId],
                'ruleCode' => 'sneak',
            ],
        ]);
        self::assertSame('GAME_INVALID', $joint['error']['code']);
        $offer = $this->dispatch('game.proposeCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'offer',
            'expectedSheetVersion' => $version,
            'proposal' => [
                'participant' => ['type' => 'character', 'id' => $characterId],
                'target' => ['type' => 'character', 'id' => $characterId],
                'ruleCode' => 'notice',
            ],
        ]);
        self::assertSame('GAME_INVALID', $offer['error']['code']);
        self::assertTrue($this->dispatch('game.stopSession', ['gameId' => $gameId])['success']);
        self::assertSame(0, $this->countAll(GameCheckTable::class));
        self::assertSame(0, $this->countAll(GameCheckCommandTable::class));
        self::assertSame(0, $this->countAll(GameSessionTable::class));
        self::assertGreaterThan(0, $this->countAll(GameProcessTable::class));
    }

    /**
     * Pairwise заполняет строку после согласия. Бой гасит только своё предложение.
     *
     * @return void
     */
    public function testPairwiseAnswerAndBattle(): void
    {
        $world = $this->worldWithCheck();
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero');
        $rival = $this->admit($world->getId(), $gameId, 'Rival');
        $version = $this->characterFacade()->get($hero)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $offer = $this->dispatch('game.proposeCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'offer',
            'expectedSheetVersion' => $version,
            'proposal' => [
                'participant' => ['type' => 'character', 'id' => $hero],
                'target' => ['type' => 'character', 'id' => $rival],
                'ruleCode' => 'sneak',
            ],
        ]);
        self::assertTrue($offer['success'], json_encode($offer));
        self::assertSame('open', $offer['data']['status']);
        self::assertNull($offer['data']['roll']);
        self::assertSame('pending', $this->checkOffer($offer['data']['processId']));
        $accepted = $this->dispatch('game.answerCheck', [
            'gameId' => $gameId,
            'processId' => $offer['data']['processId'],
            'idempotencyKey' => 'yes',
            'expectedSheetVersion' => $version,
            'answer' => ['decision' => 'accept'],
        ]);
        self::assertTrue($accepted['success'], json_encode($accepted));
        self::assertSame('resolved', $accepted['data']['status']);
        self::assertSame(['base' => 0, 'size' => 0], $accepted['data']['difficulty']);
        self::assertSame('accepted', $this->checkOffer($offer['data']['processId']));
        self::assertSame(0, $this->checkField($offer['data']['processId'], 'difficulty_base'));
        $second = $this->dispatch('game.proposeCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'offer-2',
            'expectedSheetVersion' => $version,
            'proposal' => [
                'participant' => ['type' => 'character', 'id' => $hero],
                'target' => ['type' => 'character', 'id' => $rival],
                'ruleCode' => 'notice',
            ],
        ]);
        self::assertTrue($second['success']);
        $declined = $this->dispatch('game.answerCheck', [
            'gameId' => $gameId,
            'processId' => $second['data']['processId'],
            'idempotencyKey' => 'no',
            'expectedSheetVersion' => $version,
            'answer' => ['decision' => 'decline'],
        ]);
        self::assertSame('cancelled', $declined['data']['status']);
        self::assertSame('declined', $this->checkOffer($second['data']['processId']));
        self::assertSame('resolved', $this->processStatus());
        $clash = $this->dispatch('game.proposeCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'offer-2',
            'expectedSheetVersion' => $version,
            'proposal' => [
                'participant' => ['type' => 'character', 'id' => $hero],
                'target' => ['type' => 'character', 'id' => $rival],
                'ruleCode' => 'sneak',
            ],
        ]);
        self::assertSame('GAME_CONFLICT', $clash['error']['code']);
        $hit = $this->dispatch('game.declareCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'hit',
            'expectedSheetVersion' => $version,
            'check' => [
                'participant' => ['type' => 'character', 'id' => $hero],
                'ruleCode' => 'strike',
            ],
        ]);
        self::assertSame('GAME_INVALID', $hit['error']['code']);
        $state = $this->dispatch('game.declareCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'dot',
            'expectedSheetVersion' => $version,
            'check' => [
                'participant' => ['type' => 'character', 'id' => $hero],
                'ruleCode' => 'poison',
            ],
        ]);
        self::assertSame('GAME_INVALID', $state['error']['code']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b1',
            'participants' => [
                ['type' => 'character', 'id' => $hero],
                ['type' => 'character', 'id' => $rival],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $fight = $this->dispatch('game.proposeCheck', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'fight',
            'expectedSheetVersion' => $version,
            'proposal' => [
                'participant' => ['type' => 'character', 'id' => $hero],
                'target' => ['type' => 'character', 'id' => $rival],
                'ruleCode' => 'sneak',
            ],
        ]);
        self::assertTrue($fight['success'], json_encode($fight));
        self::assertTrue($this->dispatch('game.endBattle', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'end',
            'expectedVersion' => 1,
        ])['success']);
        self::assertSame(1, $this->countAll(GameSessionTable::class));
        self::assertSame('cancelled', $this->processStatusOf($fight['data']['processId']));
        self::assertSame('resolved', $this->processStatus());
    }

    /**
     * Мир с проверкой и правилом броска.
     *
     * @return \Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord Мир.
     */
    private function worldWithCheck(): \Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord
    {
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $rollId = $mechanics->add('roll', 'Бросок', '', '1.0.0');
        $world = $this->addWorldWithRevision('razrabotka');
        $check = [
            'allow_characteristic_override' => false,
            'allowed_modes' => 'both',
            'ordinary_root' => true,
            'concentration_token' => false,
            'willpower' => false,
            'unstable_check' => false,
            'hit_check' => false,
            'difficulty_input' => ['kind' => 'none', 'state_code' => ''],
        ];
        $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('race', 'Human', '', [
                'characteristics' => [[
                    'characteristic_code' => 'strength',
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('roll', new RuleVersionBody('simple', 'Roll', '', [], [], [[
                'mechanic_id' => $rollId,
                'mechanic_payload' => ['type' => 'roll', 'data' => ['diceCount' => 1, 'dieFaces' => 6, 'efficiency' => 3]],
            ]], 'needs_work')),
            RuleCommitEntry::put('notice', new RuleVersionBody('check', 'Notice', '', $check, [], [], 'needs_work')),
            RuleCommitEntry::put('sneak', new RuleVersionBody('check', 'Sneak', '', [
                ...$check,
                'allowed_modes' => 'joint',
                'ordinary_root' => false,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('strike', new RuleVersionBody('check', 'Strike', '', [
                ...$check,
                'hit_check' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('poison', new RuleVersionBody('check', 'Poison', '', [
                ...$check,
                'difficulty_input' => ['kind' => 'from_state', 'state_code' => 'bleed'],
            ], [], [], 'needs_work')),
        ]);

        return $world;
    }

    /**
     * Допущенный active.
     *
     * @param int $spaceId Мир.
     * @param int $gameId Игра.
     * @param string $name Имя.
     *
     * @return int Персонаж.
     */
    private function admit(int $spaceId, int $gameId, string $name): int
    {
        $characterId = $this->addCharacter($spaceId, $name);
        $this->characterFacade()->replaceMigrated($characterId, $name, true, $this->storedChoices($name), [], 2, 1);
        $this->membershipFacade()->submit($gameId, $characterId, $this->ownerUserId);
        $this->membershipFacade()->approve($gameId, $characterId, 2, 1);

        return $characterId;
    }

    /**
     * Черновик.
     *
     * @param int $spaceId Мир.
     *
     * @return int Игра.
     */
    private function addGame(int $spaceId): int
    {
        return $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Tale',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $spaceId,
            'rulesRevision' => 2,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ]));
    }

    /**
     * Персонаж.
     *
     * @param int $spaceId Мир.
     * @param string $name Имя.
     *
     * @return int Id.
     */
    private function addCharacter(int $spaceId, string $name): int
    {
        return $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'name' => $name,
            'choices' => ['race' => 'human'],
            'sheet' => ['hp' => 1],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
    }

    /**
     * Выборы листа.
     *
     * @param string $name Имя.
     *
     * @return array<string, mixed> choices.
     */
    private function storedChoices(string $name): array
    {
        return [
            'name' => $name,
            'raceCode' => 'human',
            'abilities' => [],
            'inventory' => [],
            'characteristicPurchases' => [['characteristicCode' => 'strength', 'cost' => 2]],
            'customRules' => [],
            'active' => true,
        ];
    }

    /**
     * Актор HTTP.
     *
     * @param int $userId Учётка.
     * @param array<int, string> $permissionKeys Ключи.
     *
     * @return void
     */
    private function setActor(int $userId, array $permissionKeys): void
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);
        $this->requestContext->setActor(new RequestActor($userId, $permissionKeys, false));
    }

    /**
     * Action.
     *
     * @param string $action Код.
     * @param mixed $payload Тело.
     *
     * @return array<string, mixed> Конверт.
     */
    private function dispatch(string $action, mixed $payload): array
    {
        return $this->gameApplication()->dispatch($action, $payload)->toArray();
    }

    /**
     * Число строк.
     *
     * @param class-string $table Класс.
     *
     * @return int Число.
     */
    private function countAll(string $table): int
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);

        return count($gateway->open($table)->records()->getList(new ListQuery(
            null,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ))->rows());
    }

    /**
     * Статус единственной строки process.
     *
     * @return string Статус.
     */
    private function processStatus(): string
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $rows = $gateway->open(GameProcessTable::class)->records()->getList(new ListQuery(
            null,
            ['id' => 'ASC'],
            1,
            0,
            false,
            null,
        ))->rows();
        $status = $rows[0]['status'] ?? null;
        self::assertIsString($status);

        return $status;
    }

    /**
     * Статус process по id.
     *
     * @param int $processId Process.
     *
     * @return string Статус.
     */
    private function processStatusOf(int $processId): string
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $row = $gateway->open(GameProcessTable::class)->records()->getList(new ListQuery(
            null,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ))->rows();
        foreach ($row as $item) {
            if (is_array($item) && ($item['id'] ?? null) === $processId) {
                self::assertIsString($item['status']);

                return $item['status'];
            }
        }

        self::fail('process missing');
    }

    /**
     * Поле строки проверки.
     *
     * @param int $processId Process.
     * @param string $field Колонка.
     *
     * @return mixed Значение.
     */
    private function checkField(int $processId, string $field): mixed
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        foreach ($gateway->open(GameCheckTable::class)->records()->getList(new ListQuery(
            null,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ))->rows() as $row) {
            if (is_array($row) && ($row['process_id'] ?? null) === $processId) {
                return $row[$field] ?? null;
            }
        }

        self::fail('check missing');
    }

    /**
     * Предложение строки.
     *
     * @param int $processId Process.
     *
     * @return string offer.
     */
    private function checkOffer(int $processId): string
    {
        $offer = $this->checkField($processId, 'offer');
        self::assertIsString($offer);

        return $offer;
    }
}
