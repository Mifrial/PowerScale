<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Messages\Chat\Dto\MessageRecord;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Service\GameDeliveryPortFactory;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
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
        self::assertSame(1, $this->checkDeliveryCount($gameId));
        self::assertSame(['base' => 0, 'size' => 0], $solo['data']['difficulty']);
        self::assertSame(0, $solo['data']['rating']);
        self::assertNull($solo['data']['sheetVersion']);
        self::assertSame('character:' . $characterId, $solo['data']['actorKey']);
        self::assertArrayHasKey('faces', $solo['data']);
        self::assertArrayHasKey('advantages', $solo['data']);
        $messages = $this->allMessages($gameId);
        $checkMessages = $this->messagesOfThread($messages, 'check-1');
        self::assertCount(3, $checkMessages);
        self::assertCount(1, array_filter(
            $checkMessages,
            static fn (MessageRecord $message): bool => str_contains($message->getContent(), 'совершает проверку'),
        ));
        self::assertCount(1, array_filter(
            $checkMessages,
            static fn (MessageRecord $message): bool => str_contains($message->getContent(), 'преуспел в проверке')
                || str_contains($message->getContent(), 'провалил проверку'),
        ));
        self::assertCount(1, array_filter(
            $checkMessages,
            static fn (MessageRecord $message): bool => $message->getAttachments() !== []
                && array_filter(
                    $message->getAttachments(),
                    static fn (array $attachment): bool => ($attachment['type'] ?? null) === 'roll',
                ) !== [],
        ));
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
        self::assertSame(0, $this->countCommands('check'));
        self::assertSame(0, $this->countAll(GameSessionTable::class));
        self::assertGreaterThan(0, $this->countAll(GameProcessTable::class));
    }

    /**
     * Проверка характеристики берёт значение из листа, если закупок нет.
     *
     * @return void
     */
    public function testCharacteristicCheckUsesSheetValueWithoutPurchases(): void
    {
        $world = $this->worldWithCheck();
        $gameId = $this->addGame($world->getId());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $version = $this->characterFacade()->get($characterId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $rolled = $this->dispatch('game.declareCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'strength',
            'expectedSheetVersion' => $version,
            'check' => [
                'participant' => ['type' => 'character', 'id' => $characterId],
                'ruleCode' => 'strength-check',
                'askedDifficulty' => ['base' => 0, 'size' => 0],
            ],
        ]);
        self::assertTrue($rolled['success'], json_encode($rolled));
        self::assertIsInt($rolled['data']['rating'] ?? null);
    }

    /**
     * Joint-проверка не требует difficulty ask: сложность — успехи другой стороны.
     *
     * @return void
     */
    public function testJointOfferDoesNotRequireAskedDifficulty(): void
    {
        $world = $this->worldWithCheck();
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero');
        $rival = $this->admit($world->getId(), $gameId, 'Rival');
        $heroVersion = $this->characterFacade()->get($hero)->getActualVersion();
        $rivalVersion = $this->characterFacade()->get($rival)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);

        $offer = $this->dispatch('game.proposeCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'joint-ask',
            'expectedSheetVersion' => $heroVersion,
            'proposal' => [
                'checkCode' => 'strength-check',
                'initiator' => ['type' => 'character', 'id' => $hero],
                'opponent' => ['type' => 'character', 'id' => $rival],
            ],
        ]);
        self::assertTrue($offer['success'], json_encode($offer));
        self::assertSame('pending', $offer['data']['status']);
        $accepted = $this->dispatch('game.answerCheck', [
            'gameId' => $gameId,
            'processId' => $offer['data']['processId'],
            'idempotencyKey' => 'joint-ask-accept',
            'expectedSheetVersion' => $rivalVersion,
            'answer' => [
                'decision' => 'accept',
                'actor' => ['type' => 'character', 'id' => $rival],
            ],
        ]);
        self::assertTrue($accepted['success'], json_encode($accepted));
        self::assertSame('resolved', $accepted['data']['status']);
        self::assertArrayHasKey('initiator', $accepted['data']['rolls']);
        self::assertArrayHasKey('opponent', $accepted['data']['rolls']);
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
        self::assertSame('pending', $offer['data']['status']);
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
     * Двустороннее предложение: правка, отмена и совместный бросок.
     *
     * @return void
     */
    public function testJointOfferReviseCancelAndRoll(): void
    {
        $world = $this->worldWithCheck();
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero');
        $rival = $this->admit($world->getId(), $gameId, 'Rival');
        $heroVersion = $this->characterFacade()->get($hero)->getActualVersion();
        $rivalVersion = $this->characterFacade()->get($rival)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);

        $offer = $this->dispatch('game.proposeCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'joint-offer',
            'expectedSheetVersion' => $heroVersion,
            'proposal' => [
                'checkCode' => 'sneak',
                'initiator' => ['type' => 'character', 'id' => $hero],
                'opponent' => ['type' => 'character', 'id' => $rival],
                'initiatorFree' => ['diceCount' => 3, 'dieSize' => 6, 'efficiency' => 0],
                'opponentFree' => ['diceCount' => 3, 'dieSize' => 6, 'efficiency' => 0],
            ],
        ]);
        self::assertTrue($offer['success'], json_encode($offer));
        self::assertSame('pending', $offer['data']['status']);
        self::assertSame('opponent', $offer['data']['waitingOn']);
        self::assertSame(1, $this->checkDeliveryCount($gameId));
        $processId = $offer['data']['processId'];
        $all = $this->dispatch('game.getCheckOffers', ['gameId' => $gameId]);
        self::assertTrue($all['success'], json_encode($all));
        self::assertCount(1, $all['data']);
        $offers = $this->dispatch('game.getCheckOffers', [
            'gameId' => $gameId,
            'entity' => ['type' => 'character', 'id' => $rival],
        ]);
        self::assertTrue($offers['success'], json_encode($offers));
        self::assertCount(1, $offers['data']);
        self::assertSame($processId, $offers['data'][0]['id']);

        $revised = $this->dispatch('game.reviseCheck', [
            'gameId' => $gameId,
            'processId' => $processId,
            'idempotencyKey' => 'joint-revise',
            'expectedSheetVersion' => $rivalVersion,
            'actor' => ['type' => 'character', 'id' => $rival],
            'proposal' => [
                'opponentCharacteristic' => null,
                'opponentAdv' => 1,
                'opponentFree' => ['diceCount' => 3, 'dieSize' => 6, 'efficiency' => 0],
            ],
        ]);
        self::assertTrue($revised['success'], json_encode($revised));
        self::assertSame('initiator', $revised['data']['waitingOn']);
        self::assertSame(1, $revised['data']['proposal']['opponentAdv']);
        self::assertSame(2, $this->checkDeliveryCount($gameId));

        $cancelled = $this->dispatch('game.cancelCheck', [
            'gameId' => $gameId,
            'processId' => $processId,
            'idempotencyKey' => 'joint-cancel',
            'proposal' => ['actor' => ['type' => 'character', 'id' => $hero]],
        ]);
        self::assertTrue($cancelled['success'], json_encode($cancelled));
        self::assertSame('cancelled', $cancelled['data']['status']);
        self::assertSame(3, $this->checkDeliveryCount($gameId));
        self::assertCount(0, $this->dispatch('game.getCheckOffers', ['gameId' => $gameId])['data']);

        $second = $this->dispatch('game.proposeCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'joint-second',
            'expectedSheetVersion' => $heroVersion,
            'proposal' => [
                'checkCode' => 'sneak',
                'initiator' => ['type' => 'character', 'id' => $hero],
                'opponent' => ['type' => 'character', 'id' => $rival],
                'initiatorFree' => ['diceCount' => 3, 'dieSize' => 6, 'efficiency' => 0],
                'opponentFree' => ['diceCount' => 3, 'dieSize' => 6, 'efficiency' => 0],
            ],
        ]);
        self::assertTrue($second['success'], json_encode($second));
        $accepted = $this->dispatch('game.answerCheck', [
            'gameId' => $gameId,
            'processId' => $second['data']['processId'],
            'idempotencyKey' => 'joint-accept',
            'expectedSheetVersion' => $rivalVersion,
            'answer' => [
                'decision' => 'accept',
                'actor' => ['type' => 'character', 'id' => $rival],
                'proposal' => [
                    'opponentCharacteristic' => null,
                    'opponentAdv' => 1,
                    'opponentFree' => ['diceCount' => 3, 'dieSize' => 6, 'efficiency' => 0],
                ],
            ],
        ]);
        self::assertTrue($accepted['success'], json_encode($accepted));
        self::assertSame('resolved', $accepted['data']['status']);
        self::assertSame(1, $accepted['data']['proposal']['opponentAdv']);
        self::assertSame(5, $this->checkDeliveryCount($gameId));
        self::assertArrayHasKey('initiator', $accepted['data']['sheetVersions']);
        self::assertArrayHasKey('opponent', $accepted['data']['sheetVersions']);
        self::assertArrayHasKey('initiator', $accepted['data']['rolls']);
        self::assertArrayHasKey('opponent', $accepted['data']['rolls']);
        self::assertArrayHasKey('faces', $accepted['data']['rolls']['initiator']);
        self::assertArrayHasKey('faces', $accepted['data']['rolls']['opponent']);
        self::assertCount(0, $this->dispatch('game.getCheckOffers', ['gameId' => $gameId])['data']);
        $messages = $this->allMessages($gameId);
        $checkThreads = array_values(array_filter(
            $this->messageThreadIds($messages),
            static fn (string $id): bool => str_starts_with($id, 'check-'),
        ));
        self::assertSame(
            ['check-' . $offer['data']['processId'], 'check-' . $second['data']['processId']],
            $checkThreads,
        );
        $checkMessages = $this->messagesOfThread($messages, 'check-' . $second['data']['processId']);
        self::assertCount(3, $checkMessages);
        self::assertCount(1, array_filter(
            $checkMessages,
            static fn (MessageRecord $message): bool => str_contains($message->getContent(), 'преуспел в проверке')
                || str_contains($message->getContent(), 'закончилась ничьей')
                || str_contains($message->getContent(), 'Никто не преуспел'),
        ));
        self::assertCount(1, array_filter(
            $checkMessages,
            static fn (MessageRecord $message): bool => count(array_filter(
                $message->getAttachments(),
                static fn (array $attachment): bool => ($attachment['type'] ?? null) === 'roll',
            )) === 2,
        ));
    }

    /**
     * Проверка в бою попадает в тред текущего хода.
     *
     * @return void
     */
    public function testOfferThreadFollowsBattleTurn(): void
    {
        $world = $this->worldWithCheck();
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero');
        $rival = $this->admit($world->getId(), $gameId, 'Rival');
        $version = $this->characterFacade()->get($hero)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'battle-thread',
            'participants' => [
                ['type' => 'character', 'id' => $hero],
                ['type' => 'character', 'id' => $rival],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $offer = $this->dispatch('game.proposeCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'turn-thread',
            'expectedSheetVersion' => $version,
            'proposal' => [
                'checkCode' => 'notice',
                'initiator' => ['type' => 'character', 'id' => $hero],
                'opponent' => ['type' => 'character', 'id' => $rival],
            ],
        ]);
        self::assertTrue($offer['success'], json_encode($offer));
        $thread = null;
        foreach ($this->allMessages($gameId) as $message) {
            if (str_contains($message->getContent(), 'Совместная проверка')) {
                $thread = $this->threadOf($message);
            }
        }
        self::assertIsArray($thread);
        self::assertSame(
            'b' . $battle['data']['battleId'] . '-r1-t0',
            $thread['parentId'] ?? null,
        );
    }

    /**
     * Соло-проверка тратит жетон концентрации и получает преимущество.
     *
     * @return void
     */
    public function testSoloConcentrationToken(): void
    {
        $world = $this->worldWithCheck(true);
        $gameId = $this->addGame($world->getId());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero', [
            'abilityLevels' => ['peak' => 1],
            'resources' => [['ruleCode' => 'concentration', 'current' => 2]],
        ], [['ruleCode' => 'peak', 'level' => 1]]);
        $version = $this->characterFacade()->get($characterId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $check = $this->dispatch('game.declareCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'concentration',
            'expectedSheetVersion' => $version,
            'check' => [
                'participant' => ['type' => 'character', 'id' => $characterId],
                'ruleCode' => 'notice',
                'concentration' => 1,
            ],
        ]);
        self::assertTrue($check['success'], json_encode($check));
        self::assertContains(
            ['source_code' => 'concentration', 'source_label' => 'Concentration', 'delta' => 1],
            $check['data']['advantages'] ?? [],
        );
        $resources = $this->characterFacade()->get($characterId)->getSheet()['resources'] ?? [];
        $current = null;
        foreach ($resources as $row) {
            if (is_array($row) && ($row['ruleCode'] ?? null) === 'concentration') {
                $current = $row['current'] ?? null;
            }
        }
        self::assertSame(1, $current);
        $tooMany = $this->dispatch('game.declareCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'concentration-too-many',
            'expectedSheetVersion' => $this->characterFacade()->get($characterId)->getActualVersion(),
            'check' => [
                'participant' => ['type' => 'character', 'id' => $characterId],
                'ruleCode' => 'notice',
                'concentration' => 2,
            ],
        ]);
        self::assertFalse($tooMany['success']);
        self::assertSame('GAME_INVALID', $tooMany['error']['code']);
    }

    /**
     * Предельная концентрация 1 поднимает максимум траты до двух жетонов.
     *
     * @return void
     */
    public function testPeakConcentrationAllowsTwoTokens(): void
    {
        $world = $this->worldWithCheck(true);
        $gameId = $this->addGame($world->getId());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero', [
            'abilityLevels' => ['peak' => 1],
            'resources' => [['ruleCode' => 'concentration', 'current' => 3]],
        ], [['ruleCode' => 'peak', 'level' => 1]]);
        $version = $this->characterFacade()->get($characterId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $check = $this->dispatch('game.declareCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'peak-concentration',
            'expectedSheetVersion' => $version,
            'check' => [
                'participant' => ['type' => 'character', 'id' => $characterId],
                'ruleCode' => 'notice',
                'concentration' => 2,
            ],
        ]);
        self::assertTrue($check['success'], json_encode($check));
        self::assertContains(
            ['source_code' => 'concentration', 'source_label' => 'Concentration', 'delta' => 2],
            $check['data']['advantages'] ?? [],
        );
        self::assertSame(1, $this->resourceCurrent($characterId, 'concentration'));
    }

    /**
     * Совместная проверка тратит жетон своей стороны.
     *
     * @return void
     */
    public function testJointConcentrationToken(): void
    {
        $world = $this->worldWithCheck(true);
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero', [
            'abilityLevels' => ['peak' => 1],
            'resources' => [['ruleCode' => 'concentration', 'current' => 2]],
        ], [['ruleCode' => 'peak', 'level' => 1]]);
        $rival = $this->admit($world->getId(), $gameId, 'Rival', [
            'abilityLevels' => ['peak' => 1],
            'resources' => [['ruleCode' => 'concentration', 'current' => 2]],
        ], [['ruleCode' => 'peak', 'level' => 1]]);
        $heroVersion = $this->characterFacade()->get($hero)->getActualVersion();
        $rivalVersion = $this->characterFacade()->get($rival)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);

        $offer = $this->dispatch('game.proposeCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'joint-concentration',
            'expectedSheetVersion' => $heroVersion,
            'proposal' => [
                'checkCode' => 'notice',
                'initiator' => ['type' => 'character', 'id' => $hero],
                'opponent' => ['type' => 'character', 'id' => $rival],
                'initiatorFree' => ['diceCount' => 3, 'dieSize' => 6, 'efficiency' => 0],
                'opponentFree' => ['diceCount' => 3, 'dieSize' => 6, 'efficiency' => 0],
                'initiatorSpendConcentration' => 1,
            ],
        ]);
        self::assertTrue($offer['success'], json_encode($offer));
        $accepted = $this->dispatch('game.answerCheck', [
            'gameId' => $gameId,
            'processId' => $offer['data']['processId'],
            'idempotencyKey' => 'joint-concentration-accept',
            'expectedSheetVersion' => $rivalVersion,
            'answer' => [
                'decision' => 'accept',
                'actor' => ['type' => 'character', 'id' => $rival],
            ],
        ]);
        self::assertTrue($accepted['success'], json_encode($accepted));
        self::assertContains(
            ['source_code' => 'concentration', 'source_label' => 'Concentration', 'delta' => 1],
            $accepted['data']['rolls']['initiator']['advantages'] ?? [],
        );
        self::assertSame(1, $this->resourceCurrent($hero, 'concentration'));
        self::assertSame(2, $this->resourceCurrent($rival, 'concentration'));
    }

    /**
     * Недопустимая трата жетонов отклоняется при объявлении, а не у соперника.
     *
     * @return void
     */
    public function testProposalRejectsInvalidConcentration(): void
    {
        $world = $this->worldWithCheck(true);
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero', [
            'abilityLevels' => ['peak' => 1],
            'resources' => [['ruleCode' => 'concentration', 'current' => 3]],
        ], [['ruleCode' => 'peak', 'level' => 1]]);
        $rival = $this->admit($world->getId(), $gameId, 'Rival', [
            'resources' => [['ruleCode' => 'concentration', 'current' => 3]],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'concentration-battle',
            'participants' => [
                ['type' => 'character', 'id' => $hero],
                ['type' => 'character', 'id' => $rival],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));

        $offer = $this->dispatch('game.proposeCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'concentration-first',
            'expectedSheetVersion' => $this->characterFacade()->get($hero)->getActualVersion(),
            'proposal' => [
                'checkCode' => 'notice',
                'initiator' => ['type' => 'character', 'id' => $hero],
                'opponent' => ['type' => 'character', 'id' => $rival],
                'initiatorFree' => ['diceCount' => 3, 'dieSize' => 6, 'efficiency' => 0],
                'opponentFree' => ['diceCount' => 3, 'dieSize' => 6, 'efficiency' => 0],
                'initiatorSpendConcentration' => 2,
            ],
        ]);
        self::assertTrue($offer['success'], json_encode($offer));
        $accepted = $this->dispatch('game.answerCheck', [
            'gameId' => $gameId,
            'processId' => $offer['data']['processId'],
            'idempotencyKey' => 'concentration-first-accept',
            'expectedSheetVersion' => $this->characterFacade()->get($rival)->getActualVersion(),
            'answer' => [
                'decision' => 'accept',
                'actor' => ['type' => 'character', 'id' => $rival],
            ],
        ]);
        self::assertTrue($accepted['success'], json_encode($accepted));
        self::assertSame(1, $this->resourceCurrent($hero, 'concentration'));

        $second = $this->dispatch('game.proposeCheck', [
            'gameId' => $gameId,
            'idempotencyKey' => 'concentration-second',
            'expectedSheetVersion' => $this->characterFacade()->get($hero)->getActualVersion(),
            'proposal' => [
                'checkCode' => 'notice',
                'initiator' => ['type' => 'character', 'id' => $hero],
                'opponent' => ['type' => 'character', 'id' => $rival],
                'initiatorFree' => ['diceCount' => 3, 'dieSize' => 6, 'efficiency' => 0],
                'opponentFree' => ['diceCount' => 3, 'dieSize' => 6, 'efficiency' => 0],
                'initiatorSpendConcentration' => 2,
            ],
        ]);
        self::assertFalse($second['success'], json_encode($second));
        self::assertSame('GAME_INVALID', $second['error']['code']);
        self::assertSame('Game concentration amount is invalid', $second['error']['message']);
        self::assertSame(1, $this->resourceCurrent($hero, 'concentration'));
    }

    /**
     * Мир с проверкой и правилом броска.
     *
     * @return \Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord Мир.
     */
    private function worldWithCheck(bool $concentration = false): \Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord
    {
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $rollId = $mechanics->add('plain_roll', 'Бросок', '', '1.0.0');
        $world = $this->addWorldWithRevision('razrabotka');
        $check = [
            'allow_characteristic_override' => false,
            'allowed_modes' => 'both',
            'ordinary_root' => true,
            'default_efficiency' => 0,
            'concentration_token' => $concentration,
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
            ...($concentration ? [
                RuleCommitEntry::put('concentration', new RuleVersionBody('resource', 'Concentration', '', [
                    'is_dimensional' => false,
                    'auto_add' => false,
                    'check_token' => true,
                    'limit' => ['base' => 2, 'adjustments' => []],
                ], [], [], 'needs_work')),
                RuleCommitEntry::put('peak', new RuleVersionBody('ability', 'Peak', '', [
                    'type' => 'skill',
                    'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
                    'grants' => [],
                    'peak_concentration' => true,
                    'will_focus' => false,
                    'long_tension' => false,
                ], [], [], 'needs_work')),
            ] : []),
            RuleCommitEntry::put('strength-check', new RuleVersionBody('check', 'Strength check', '', [
                ...$check,
                'characteristic_code' => 'strength',
                'difficulty_input' => ['kind' => 'ask'],
            ], [], [], 'needs_work')),
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
    private function admit(int $spaceId, int $gameId, string $name, array $sheet = [], array $abilities = []): int
    {
        $characterId = $this->addCharacter($spaceId, $name);
        $this->characterFacade()->replaceMigrated(
            $characterId,
            $name,
            true,
            $this->storedChoices($name, $abilities),
            $sheet,
            2,
            1,
        );
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
    private function storedChoices(string $name, array $abilities = []): array
    {
        return [
            'name' => $name,
            'raceCode' => 'human',
            'abilities' => $abilities,
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
        foreach (
            $gateway->open(GameCheckTable::class)->records()->getList(new ListQuery(
                null,
                ['id' => 'ASC'],
                ListQuery::MAX_LIMIT,
                0,
                false,
                null,
            ))->rows() as $row
        ) {
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

    /**
     * Сколько событий check доставлено игре.
     *
     * @param int $gameId Игра.
     *
     * @return int Количество.
     */
    private function checkDeliveryCount(int $gameId): int
    {
        $delivery = (new GameDeliveryPortFactory())->create($this->gameApplication()->getLocator());
        $frame = $delivery->tail($gameId, $this->ownerUserId, true, 0);
        $count = 0;
        foreach ($frame['events'] as $event) {
            if (($event['source'] ?? null) === 'check') {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Текущее значение ресурса.
     *
     * @param int $characterId Персонаж.
     * @param string $code Ресурс.
     *
     * @return int|null Значение.
     */
    private function resourceCurrent(int $characterId, string $code): ?int
    {
        foreach ($this->characterFacade()->get($characterId)->getSheet()['resources'] ?? [] as $row) {
            if (is_array($row) && ($row['ruleCode'] ?? null) === $code) {
                return is_int($row['current'] ?? null) ? $row['current'] : null;
            }
        }

        return null;
    }

    /**
     * Уникальные id тредов сообщений в порядке появления.
     *
     * @param array<int, MessageRecord> $messages Сообщения.
     *
     * @return array<int, string> Id.
     */
    private function messageThreadIds(array $messages): array
    {
        $ids = [];
        foreach ($messages as $message) {
            $id = $this->threadIdOf($message);
            if ($id !== null && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Сообщения одного треда.
     *
     * @param array<int, MessageRecord> $messages Сообщения.
     * @param string $threadId Тред.
     *
     * @return array<int, MessageRecord> Сообщения треда.
     */
    private function messagesOfThread(array $messages, string $threadId): array
    {
        return array_values(array_filter(
            $messages,
            fn (MessageRecord $message): bool => $this->threadIdOf($message) === $threadId,
        ));
    }

    /**
     * Id треда сообщения.
     *
     * @param MessageRecord $message Сообщение.
     *
     * @return string|null Id или null.
     */
    private function threadIdOf(MessageRecord $message): ?string
    {
        $id = $this->threadOf($message)['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * Вложение треда сообщения.
     *
     * @param MessageRecord $message Сообщение.
     *
     * @return array<string, mixed>|null Тред или null.
     */
    private function threadOf(MessageRecord $message): ?array
    {
        foreach ($message->getAttachments() as $attachment) {
            if (($attachment['type'] ?? null) !== 'chat_system') {
                continue;
            }

            $payload = $attachment['payload'] ?? null;
            $thread = is_array($payload) ? ($payload['thread'] ?? null) : null;
            if (is_array($thread)) {
                return $thread;
            }
        }

        return null;
    }

    /**
     * Чат игры.
     *
     * @return IChats Сервис.
     */
    private function chats(): IChats
    {
        $chats = $this->gameApplication()->getLocator()->get(IChatContainer::class)->get(IChats::class);
        self::assertInstanceOf(IChats::class, $chats);

        return $chats;
    }

    /**
     * Все сообщения игрового чата в хронологическом порядке.
     *
     * @param int $gameId Игра.
     *
     * @return array<int, MessageRecord> Сообщения.
     */
    private function allMessages(int $gameId): array
    {
        $chatId = $this->gameFacade()->get($gameId)->getGameChatId();
        self::assertIsInt($chatId);

        return array_reverse($this->chats()->findMessagePage($chatId, $this->ownerUserId, 100, 0)->getItems());
    }
}
