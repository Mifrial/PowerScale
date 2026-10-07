<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSessionParticipants;
use Mifrial\Roleplay\Game\Action\AddGameMemberAction;
use Mifrial\Roleplay\Game\Action\AnswerGameCheckAction;
use Mifrial\Roleplay\Game\Action\ApplyGameEconomyAction;
use Mifrial\Roleplay\Game\Action\ApproveGameCharacterAction;
use Mifrial\Roleplay\Game\Action\CreateGameAction;
use Mifrial\Roleplay\Game\Action\CreateGameChronicleEntryAction;
use Mifrial\Roleplay\Game\Action\CreateGameNpcAction;
use Mifrial\Roleplay\Game\Action\DeclareGameCheckAction;
use Mifrial\Roleplay\Game\Action\DeclareGameStrikeAction;
use Mifrial\Roleplay\Game\Action\DeclareGameWideStrikeAction;
use Mifrial\Roleplay\Game\Action\DeleteGameChronicleEntryAction;
use Mifrial\Roleplay\Game\Action\EndGameBattleAction;
use Mifrial\Roleplay\Game\Action\GetGameAction;
use Mifrial\Roleplay\Game\Action\GetGameBattleSheetsAction;
use Mifrial\Roleplay\Game\Action\GetGameCharacterAction;
use Mifrial\Roleplay\Game\Action\GetGameCharacterListAction;
use Mifrial\Roleplay\Game\Action\GetGameChronicleAction;
use Mifrial\Roleplay\Game\Action\GetGameChronicleEntriesAction;
use Mifrial\Roleplay\Game\Action\GetGameInvitationsAction;
use Mifrial\Roleplay\Game\Action\GetGameJoinRequestsAction;
use Mifrial\Roleplay\Game\Action\GetGameListAction;
use Mifrial\Roleplay\Game\Action\GetGameMemberListAction;
use Mifrial\Roleplay\Game\Action\GetGameNpcAction;
use Mifrial\Roleplay\Game\Action\GetGameRosterAction;
use Mifrial\Roleplay\Game\Action\GetGameSheetsAction;
use Mifrial\Roleplay\Game\Action\GetGameShopAction;
use Mifrial\Roleplay\Game\Action\GetGameWhitelistAction;
use Mifrial\Roleplay\Game\Action\GetMyGameInvitationsAction;
use Mifrial\Roleplay\Game\Action\InviteGameAction;
use Mifrial\Roleplay\Game\Action\JoinGameWhitelistAction;
use Mifrial\Roleplay\Game\Action\LeaveGameCharacterAction;
use Mifrial\Roleplay\Game\Action\ProposeGameCheckAction;
use Mifrial\Roleplay\Game\Action\RejectGameCharacterAction;
use Mifrial\Roleplay\Game\Action\RemoveGameMemberAction;
use Mifrial\Roleplay\Game\Action\RollGameInitiativeAction;
use Mifrial\Roleplay\Game\Action\ReplaceGameShopAction;
use Mifrial\Roleplay\Game\Action\RequestGameJoinAction;
use Mifrial\Roleplay\Game\Action\ResolveGameStrikeAction;
use Mifrial\Roleplay\Game\Action\ResolveGameWideStrikeAction;
use Mifrial\Roleplay\Game\Action\RespondGameInvitationAction;
use Mifrial\Roleplay\Game\Action\RespondGameJoinRequestAction;
use Mifrial\Roleplay\Game\Action\ReturnGameCharacterAction;
use Mifrial\Roleplay\Game\Action\SetGameBattleRosterAction;
use Mifrial\Roleplay\Game\Action\SetGameCharacterBonusAction;
use Mifrial\Roleplay\Game\Action\SetGameCharacterSectionVisibilityAction;
use Mifrial\Roleplay\Game\Action\SetGameWhitelistAction;
use Mifrial\Roleplay\Game\Action\StartGameBattleAction;
use Mifrial\Roleplay\Game\Action\StartGameSessionAction;
use Mifrial\Roleplay\Game\Action\StopGameSessionAction;
use Mifrial\Roleplay\Game\Action\SubmitGameCharacterAction;
use Mifrial\Roleplay\Game\Action\TranslateGameNpcAction;
use Mifrial\Roleplay\Game\Action\UpdateGameAction;
use Mifrial\Roleplay\Game\Action\UpdateGameChronicleEntryAction;
use Mifrial\Roleplay\Game\Action\UpdateGameMemberAction;
use Mifrial\Roleplay\Game\Action\UpdateGameNpcAction;
use Mifrial\Roleplay\Game\Container\GameContainer;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGameAdmissions;
use Mifrial\Roleplay\Game\Interface\Service\IGameBattles;
use Mifrial\Roleplay\Game\Interface\Service\IGameChecks;
use Mifrial\Roleplay\Game\Interface\Service\IGameChronicles;
use Mifrial\Roleplay\Game\Interface\Service\IGameEconomy;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;
use Mifrial\Roleplay\Game\Interface\Service\IGameProcesses;
use Mifrial\Roleplay\Game\Interface\Service\IGameProjections;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Interface\Service\IGameStrikes;
use Mifrial\Roleplay\Game\Interface\Service\IGameWideStrikes;
use Mifrial\Roleplay\Game\Service\GameBattlePortFactory;
use Mifrial\Roleplay\Game\Service\GameCheckPortFactory;
use Mifrial\Roleplay\Game\Service\GameChroniclePortFactory;
use Mifrial\Roleplay\Game\Service\GameEconomyPortFactory;
use Mifrial\Roleplay\Game\Service\GamePortFactory;
use Mifrial\Roleplay\Game\Service\GameProcessPortFactory;
use Mifrial\Roleplay\Game\Service\GameProjectionPortFactory;
use Mifrial\Roleplay\Game\Service\GameStrikePortFactory;
use Mifrial\Roleplay\Game\Service\GameWideStrikePortFactory;
use Mifrial\Roleplay\Game\Setup\GameModuleSetup;

return [
    'container' => GameContainer::class,
    'locator' => IGameContainer::class,
    'setup' => GameModuleSetup::class,
    'ports' => [
        IGames::class => static function (IServiceLocator $serviceLocator): IGames {
            return (new GamePortFactory())->create($serviceLocator);
        },
        IGameMemberships::class => static function (IServiceLocator $serviceLocator): IGameMemberships {
            return (new GamePortFactory())->createMemberships($serviceLocator);
        },
        IGameAdmissions::class => static function (IServiceLocator $serviceLocator): IGameAdmissions {
            return (new GamePortFactory())->createAdmissions($serviceLocator);
        },
        IGameChronicles::class => static function (IServiceLocator $serviceLocator): IGameChronicles {
            return (new GameChroniclePortFactory())->create($serviceLocator);
        },
        IGameEconomy::class => static function (IServiceLocator $serviceLocator): IGameEconomy {
            return (new GameEconomyPortFactory())->create($serviceLocator);
        },
        IGameBattles::class => static function (IServiceLocator $serviceLocator): IGameBattles {
            return (new GameBattlePortFactory())->create($serviceLocator);
        },
        IGameProcesses::class => static function (IServiceLocator $serviceLocator): IGameProcesses {
            return (new GameProcessPortFactory())->create($serviceLocator);
        },
        IGameChecks::class => static function (IServiceLocator $serviceLocator): IGameChecks {
            return (new GameCheckPortFactory())->create($serviceLocator);
        },
        IGameStrikes::class => static function (IServiceLocator $serviceLocator): IGameStrikes {
            return (new GameStrikePortFactory())->create($serviceLocator);
        },
        IGameWideStrikes::class => static function (IServiceLocator $serviceLocator): IGameWideStrikes {
            return (new GameWideStrikePortFactory())->create($serviceLocator);
        },
        IGameProjections::class => static function (IServiceLocator $serviceLocator): IGameProjections {
            return (new GameProjectionPortFactory())->create($serviceLocator);
        },
        GetGameRosterAction::class => static function (IServiceLocator $serviceLocator): GetGameRosterAction {
            return new GetGameRosterAction((new GameProjectionPortFactory())->createHttp($serviceLocator));
        },
        GetGameSheetsAction::class => static function (IServiceLocator $serviceLocator): GetGameSheetsAction {
            return new GetGameSheetsAction((new GameProjectionPortFactory())->createHttp($serviceLocator));
        },
        GetGameBattleSheetsAction::class => static function (IServiceLocator $serviceLocator): GetGameBattleSheetsAction {
            return new GetGameBattleSheetsAction((new GameProjectionPortFactory())->createHttp($serviceLocator));
        },
        DeclareGameCheckAction::class => static function (IServiceLocator $serviceLocator): DeclareGameCheckAction {
            return new DeclareGameCheckAction((new GameCheckPortFactory())->createHttp($serviceLocator));
        },
        ProposeGameCheckAction::class => static function (IServiceLocator $serviceLocator): ProposeGameCheckAction {
            return new ProposeGameCheckAction((new GameCheckPortFactory())->createHttp($serviceLocator));
        },
        AnswerGameCheckAction::class => static function (IServiceLocator $serviceLocator): AnswerGameCheckAction {
            return new AnswerGameCheckAction((new GameCheckPortFactory())->createHttp($serviceLocator));
        },
        DeclareGameStrikeAction::class => static function (IServiceLocator $serviceLocator): DeclareGameStrikeAction {
            return new DeclareGameStrikeAction((new GameStrikePortFactory())->createHttp($serviceLocator));
        },
        ResolveGameStrikeAction::class => static function (IServiceLocator $serviceLocator): ResolveGameStrikeAction {
            return new ResolveGameStrikeAction((new GameStrikePortFactory())->createHttp($serviceLocator));
        },
        DeclareGameWideStrikeAction::class => static function (IServiceLocator $serviceLocator): DeclareGameWideStrikeAction {
            return new DeclareGameWideStrikeAction((new GameWideStrikePortFactory())->createHttp($serviceLocator));
        },
        ResolveGameWideStrikeAction::class => static function (IServiceLocator $serviceLocator): ResolveGameWideStrikeAction {
            return new ResolveGameWideStrikeAction((new GameWideStrikePortFactory())->createHttp($serviceLocator));
        },
        StartGameBattleAction::class => static function (IServiceLocator $serviceLocator): StartGameBattleAction {
            return new StartGameBattleAction((new GameBattlePortFactory())->createHttp($serviceLocator));
        },
        SetGameBattleRosterAction::class => static function (IServiceLocator $serviceLocator): SetGameBattleRosterAction {
            return new SetGameBattleRosterAction((new GameBattlePortFactory())->createHttp($serviceLocator));
        },
        EndGameBattleAction::class => static function (IServiceLocator $serviceLocator): EndGameBattleAction {
            return new EndGameBattleAction((new GameBattlePortFactory())->createHttp($serviceLocator));
        },
        RollGameInitiativeAction::class => static function (IServiceLocator $serviceLocator): RollGameInitiativeAction {
            return new RollGameInitiativeAction((new GameBattlePortFactory())->createInitiativeHttp($serviceLocator));
        },
        ApplyGameEconomyAction::class => static function (IServiceLocator $serviceLocator): ApplyGameEconomyAction {
            return new ApplyGameEconomyAction((new GameEconomyPortFactory())->createHttp($serviceLocator));
        },
        GetGameShopAction::class => static function (IServiceLocator $serviceLocator): GetGameShopAction {
            return new GetGameShopAction((new GameEconomyPortFactory())->createHttp($serviceLocator));
        },
        ReplaceGameShopAction::class => static function (IServiceLocator $serviceLocator): ReplaceGameShopAction {
            return new ReplaceGameShopAction((new GameEconomyPortFactory())->createHttp($serviceLocator));
        },
        GetGameChronicleAction::class => static function (IServiceLocator $serviceLocator): GetGameChronicleAction {
            return new GetGameChronicleAction((new GameChroniclePortFactory())->createHttp($serviceLocator));
        },
        GetGameChronicleEntriesAction::class => static function (
            IServiceLocator $serviceLocator,
        ): GetGameChronicleEntriesAction {
            return new GetGameChronicleEntriesAction((new GameChroniclePortFactory())->createHttp($serviceLocator));
        },
        CreateGameChronicleEntryAction::class => static function (
            IServiceLocator $serviceLocator,
        ): CreateGameChronicleEntryAction {
            return new CreateGameChronicleEntryAction((new GameChroniclePortFactory())->createHttp($serviceLocator));
        },
        UpdateGameChronicleEntryAction::class => static function (
            IServiceLocator $serviceLocator,
        ): UpdateGameChronicleEntryAction {
            return new UpdateGameChronicleEntryAction((new GameChroniclePortFactory())->createHttp($serviceLocator));
        },
        DeleteGameChronicleEntryAction::class => static function (
            IServiceLocator $serviceLocator,
        ): DeleteGameChronicleEntryAction {
            return new DeleteGameChronicleEntryAction((new GameChroniclePortFactory())->createHttp($serviceLocator));
        },
        InviteGameAction::class => static function (IServiceLocator $serviceLocator): InviteGameAction {
            return new InviteGameAction((new GamePortFactory())->createAdmissionHttp($serviceLocator));
        },
        GetGameInvitationsAction::class => static function (IServiceLocator $serviceLocator): GetGameInvitationsAction {
            return new GetGameInvitationsAction((new GamePortFactory())->createAdmissionHttp($serviceLocator));
        },
        GetMyGameInvitationsAction::class => static function (IServiceLocator $serviceLocator): GetMyGameInvitationsAction {
            return new GetMyGameInvitationsAction((new GamePortFactory())->createAdmissionHttp($serviceLocator));
        },
        RespondGameInvitationAction::class => static function (IServiceLocator $serviceLocator): RespondGameInvitationAction {
            return new RespondGameInvitationAction((new GamePortFactory())->createAdmissionHttp($serviceLocator));
        },
        RequestGameJoinAction::class => static function (IServiceLocator $serviceLocator): RequestGameJoinAction {
            return new RequestGameJoinAction((new GamePortFactory())->createAdmissionHttp($serviceLocator));
        },
        GetGameJoinRequestsAction::class => static function (IServiceLocator $serviceLocator): GetGameJoinRequestsAction {
            return new GetGameJoinRequestsAction((new GamePortFactory())->createAdmissionHttp($serviceLocator));
        },
        RespondGameJoinRequestAction::class => static function (IServiceLocator $serviceLocator): RespondGameJoinRequestAction {
            return new RespondGameJoinRequestAction((new GamePortFactory())->createAdmissionHttp($serviceLocator));
        },
        GetGameWhitelistAction::class => static function (IServiceLocator $serviceLocator): GetGameWhitelistAction {
            return new GetGameWhitelistAction((new GamePortFactory())->createAdmissionHttp($serviceLocator));
        },
        SetGameWhitelistAction::class => static function (IServiceLocator $serviceLocator): SetGameWhitelistAction {
            return new SetGameWhitelistAction((new GamePortFactory())->createAdmissionHttp($serviceLocator));
        },
        JoinGameWhitelistAction::class => static function (IServiceLocator $serviceLocator): JoinGameWhitelistAction {
            return new JoinGameWhitelistAction((new GamePortFactory())->createAdmissionHttp($serviceLocator));
        },
        CreateGameAction::class => static function (IServiceLocator $serviceLocator): CreateGameAction {
            return new CreateGameAction((new GamePortFactory())->createHttp($serviceLocator));
        },
        GetGameAction::class => static function (IServiceLocator $serviceLocator): GetGameAction {
            return new GetGameAction((new GamePortFactory())->createHttp($serviceLocator));
        },
        GetGameListAction::class => static function (IServiceLocator $serviceLocator): GetGameListAction {
            return new GetGameListAction((new GamePortFactory())->createHttp($serviceLocator));
        },
        UpdateGameAction::class => static function (IServiceLocator $serviceLocator): UpdateGameAction {
            return new UpdateGameAction((new GamePortFactory())->createHttp($serviceLocator));
        },
        AddGameMemberAction::class => static function (IServiceLocator $serviceLocator): AddGameMemberAction {
            return new AddGameMemberAction((new GamePortFactory())->createHttp($serviceLocator));
        },
        UpdateGameMemberAction::class => static function (IServiceLocator $serviceLocator): UpdateGameMemberAction {
            return new UpdateGameMemberAction((new GamePortFactory())->createHttp($serviceLocator));
        },
        RemoveGameMemberAction::class => static function (IServiceLocator $serviceLocator): RemoveGameMemberAction {
            return new RemoveGameMemberAction((new GamePortFactory())->createHttp($serviceLocator));
        },
        GetGameMemberListAction::class => static function (IServiceLocator $serviceLocator): GetGameMemberListAction {
            return new GetGameMemberListAction((new GamePortFactory())->createHttp($serviceLocator));
        },
        SubmitGameCharacterAction::class => static function (IServiceLocator $serviceLocator): SubmitGameCharacterAction {
            return new SubmitGameCharacterAction((new GamePortFactory())->createCharacterHttp($serviceLocator));
        },
        ApproveGameCharacterAction::class => static function (IServiceLocator $serviceLocator): ApproveGameCharacterAction {
            return new ApproveGameCharacterAction((new GamePortFactory())->createCharacterHttp($serviceLocator));
        },
        ReturnGameCharacterAction::class => static function (IServiceLocator $serviceLocator): ReturnGameCharacterAction {
            return new ReturnGameCharacterAction((new GamePortFactory())->createCharacterHttp($serviceLocator));
        },
        RejectGameCharacterAction::class => static function (IServiceLocator $serviceLocator): RejectGameCharacterAction {
            return new RejectGameCharacterAction((new GamePortFactory())->createCharacterHttp($serviceLocator));
        },
        LeaveGameCharacterAction::class => static function (IServiceLocator $serviceLocator): LeaveGameCharacterAction {
            return new LeaveGameCharacterAction((new GamePortFactory())->createCharacterHttp($serviceLocator));
        },
        SetGameCharacterBonusAction::class => static function (IServiceLocator $serviceLocator): SetGameCharacterBonusAction {
            return new SetGameCharacterBonusAction((new GamePortFactory())->createCharacterHttp($serviceLocator));
        },
        SetGameCharacterSectionVisibilityAction::class => static function (IServiceLocator $serviceLocator): SetGameCharacterSectionVisibilityAction {
            return new SetGameCharacterSectionVisibilityAction((new GamePortFactory())->createCharacterSectionHttp($serviceLocator));
        },
        GetGameCharacterAction::class => static function (IServiceLocator $serviceLocator): GetGameCharacterAction {
            return new GetGameCharacterAction((new GamePortFactory())->createCharacterHttp($serviceLocator));
        },
        GetGameCharacterListAction::class => static function (IServiceLocator $serviceLocator): GetGameCharacterListAction {
            return new GetGameCharacterListAction((new GamePortFactory())->createCharacterHttp($serviceLocator));
        },
        ICharacterSessionParticipants::class => static function (IServiceLocator $serviceLocator): ICharacterSessionParticipants {
            return (new GamePortFactory())->createSessionParticipants($serviceLocator);
        },
        StartGameSessionAction::class => static function (IServiceLocator $serviceLocator): StartGameSessionAction {
            return new StartGameSessionAction((new GamePortFactory())->createSessionHttp($serviceLocator));
        },
        StopGameSessionAction::class => static function (IServiceLocator $serviceLocator): StopGameSessionAction {
            return new StopGameSessionAction((new GamePortFactory())->createSessionHttp($serviceLocator));
        },
        CreateGameNpcAction::class => static function (IServiceLocator $serviceLocator): CreateGameNpcAction {
            return new CreateGameNpcAction((new GamePortFactory())->createNpcHttp($serviceLocator));
        },
        GetGameNpcAction::class => static function (IServiceLocator $serviceLocator): GetGameNpcAction {
            return new GetGameNpcAction((new GamePortFactory())->createNpcHttp($serviceLocator));
        },
        UpdateGameNpcAction::class => static function (IServiceLocator $serviceLocator): UpdateGameNpcAction {
            return new UpdateGameNpcAction((new GamePortFactory())->createNpcHttp($serviceLocator));
        },
        TranslateGameNpcAction::class => static function (IServiceLocator $serviceLocator): TranslateGameNpcAction {
            return new TranslateGameNpcAction((new GamePortFactory())->createNpcHttp($serviceLocator));
        },
    ],
    'routes' => [
        'game.create' => [
            'handler' => CreateGameAction::class,
            'csrf' => true,
        ],
        'game.get' => [
            'handler' => GetGameAction::class,
            'csrf' => true,
        ],
        'game.getList' => [
            'handler' => GetGameListAction::class,
            'csrf' => true,
        ],
        'game.update' => [
            'handler' => UpdateGameAction::class,
            'csrf' => true,
        ],
        'game.addMember' => [
            'handler' => AddGameMemberAction::class,
            'csrf' => true,
        ],
        'game.updateMember' => [
            'handler' => UpdateGameMemberAction::class,
            'csrf' => true,
        ],
        'game.removeMember' => [
            'handler' => RemoveGameMemberAction::class,
            'csrf' => true,
        ],
        'game.getMemberList' => [
            'handler' => GetGameMemberListAction::class,
            'csrf' => true,
        ],
        'game.submitCharacter' => [
            'handler' => SubmitGameCharacterAction::class,
            'csrf' => true,
        ],
        'game.approveCharacter' => [
            'handler' => ApproveGameCharacterAction::class,
            'csrf' => true,
        ],
        'game.returnCharacter' => [
            'handler' => ReturnGameCharacterAction::class,
            'csrf' => true,
        ],
        'game.rejectCharacter' => [
            'handler' => RejectGameCharacterAction::class,
            'csrf' => true,
        ],
        'game.leaveCharacter' => [
            'handler' => LeaveGameCharacterAction::class,
            'csrf' => true,
        ],
        'game.setCharacterBonus' => [
            'handler' => SetGameCharacterBonusAction::class,
            'csrf' => true,
        ],
        'game.setCharacterSectionVisibility' => [
            'handler' => SetGameCharacterSectionVisibilityAction::class,
            'csrf' => true,
        ],
        'game.getCharacter' => [
            'handler' => GetGameCharacterAction::class,
            'csrf' => true,
        ],
        'game.getCharacterList' => [
            'handler' => GetGameCharacterListAction::class,
            'csrf' => true,
        ],
        'game.startSession' => [
            'handler' => StartGameSessionAction::class,
            'csrf' => true,
        ],
        'game.stopSession' => [
            'handler' => StopGameSessionAction::class,
            'csrf' => true,
        ],
        'game.createNpc' => [
            'handler' => CreateGameNpcAction::class,
            'csrf' => true,
        ],
        'game.getNpc' => [
            'handler' => GetGameNpcAction::class,
            'csrf' => true,
        ],
        'game.getRoster' => [
            'handler' => GetGameRosterAction::class,
            'csrf' => true,
        ],
        'game.getSheets' => [
            'handler' => GetGameSheetsAction::class,
            'csrf' => true,
        ],
        'game.getBattleSheets' => [
            'handler' => GetGameBattleSheetsAction::class,
            'csrf' => true,
        ],
        'game.updateNpc' => [
            'handler' => UpdateGameNpcAction::class,
            'csrf' => true,
        ],
        'game.translateNpc' => [
            'handler' => TranslateGameNpcAction::class,
            'csrf' => true,
        ],
        'game.invite' => [
            'handler' => InviteGameAction::class,
            'csrf' => true,
        ],
        'game.getInvitations' => [
            'handler' => GetGameInvitationsAction::class,
            'csrf' => true,
        ],
        'game.getMyInvitations' => [
            'handler' => GetMyGameInvitationsAction::class,
            'csrf' => true,
        ],
        'game.respondInvitation' => [
            'handler' => RespondGameInvitationAction::class,
            'csrf' => true,
        ],
        'game.requestJoin' => [
            'handler' => RequestGameJoinAction::class,
            'csrf' => true,
        ],
        'game.getJoinRequests' => [
            'handler' => GetGameJoinRequestsAction::class,
            'csrf' => true,
        ],
        'game.respondJoinRequest' => [
            'handler' => RespondGameJoinRequestAction::class,
            'csrf' => true,
        ],
        'game.getWhitelist' => [
            'handler' => GetGameWhitelistAction::class,
            'csrf' => true,
        ],
        'game.setWhitelist' => [
            'handler' => SetGameWhitelistAction::class,
            'csrf' => true,
        ],
        'game.joinWhitelist' => [
            'handler' => JoinGameWhitelistAction::class,
            'csrf' => true,
        ],
        'game.getChronicle' => [
            'handler' => GetGameChronicleAction::class,
            'csrf' => true,
        ],
        'game.getChronicleEntries' => [
            'handler' => GetGameChronicleEntriesAction::class,
            'csrf' => true,
        ],
        'game.createChronicleEntry' => [
            'handler' => CreateGameChronicleEntryAction::class,
            'csrf' => true,
        ],
        'game.updateChronicleEntry' => [
            'handler' => UpdateGameChronicleEntryAction::class,
            'csrf' => true,
        ],
        'game.deleteChronicleEntry' => [
            'handler' => DeleteGameChronicleEntryAction::class,
            'csrf' => true,
        ],
        'game.startBattle' => [
            'handler' => StartGameBattleAction::class,
            'csrf' => true,
        ],
        'game.setBattleRoster' => [
            'handler' => SetGameBattleRosterAction::class,
            'csrf' => true,
        ],
        'game.endBattle' => [
            'handler' => EndGameBattleAction::class,
            'csrf' => true,
        ],
        'game.rollInitiative' => [
            'handler' => RollGameInitiativeAction::class,
            'csrf' => true,
        ],
        'game.declareCheck' => [
            'handler' => DeclareGameCheckAction::class,
            'csrf' => true,
        ],
        'game.proposeCheck' => [
            'handler' => ProposeGameCheckAction::class,
            'csrf' => true,
        ],
        'game.answerCheck' => [
            'handler' => AnswerGameCheckAction::class,
            'csrf' => true,
        ],
        'game.declareStrike' => [
            'handler' => DeclareGameStrikeAction::class,
            'csrf' => true,
        ],
        'game.resolveStrike' => [
            'handler' => ResolveGameStrikeAction::class,
            'csrf' => true,
        ],
        'game.declareWideStrike' => [
            'handler' => DeclareGameWideStrikeAction::class,
            'csrf' => true,
        ],
        'game.resolveWideStrike' => [
            'handler' => ResolveGameWideStrikeAction::class,
            'csrf' => true,
        ],
        'game.applyEconomy' => [
            'handler' => ApplyGameEconomyAction::class,
            'csrf' => true,
        ],
        'game.getShop' => [
            'handler' => GetGameShopAction::class,
            'csrf' => true,
        ],
        'game.replaceShop' => [
            'handler' => ReplaceGameShopAction::class,
            'csrf' => true,
        ],
    ],
    'events' => [],
];
