import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { MechanicBinding } from '@/modules/Roleplay/Mechanic/Dto/MechanicBinding';
import { mechanicEngine, registerMechanicHandler } from '@/modules/Roleplay/Mechanic/init';
import type { MovementStateMechanicContext } from '@/modules/Roleplay/Game/Dto/MovementStateMechanicContext';
import { MovementStateMechanic } from '@/modules/Roleplay/Game/Service/Handler/MovementStateMechanic';
import type { CurrentSpeed } from '@/modules/Roleplay/Game/Dto/CurrentSpeed';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import type { ActionOperationRequest } from '@/modules/Roleplay/Game/Dto/ActionOperationRequest';
import type { ActionOperation } from '@/modules/Roleplay/Rule/Dto/Ability/ActionOperation';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ISpatialResolver } from '@/modules/Roleplay/Game/Interface/ISpatialResolver';
import { ActionOperationResolutionService } from '@/modules/Roleplay/Game/Service/ActionOperationResolutionService';
import { MovementStateService } from '@/modules/Roleplay/Game/Service/MovementStateService';
import { postureStateService } from '@/modules/Roleplay/Game/Service/Instance/postureStateService';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';
import { characterOverviewService } from '@/modules/Roleplay/Character/init';
import { LYING_STATE_CODE, UNSTABLE_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { addFlagState, removeStatesByCodes } from '@/modules/Roleplay/Game/Utils/combatStateWrite';
import { movementContextService } from '@/modules/Roleplay/Character/init';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { formatAttackActionMessage } from '@/modules/Roleplay/Game/Utils/attackDamageMessage';

registerMechanicHandler(new MovementStateMechanic());

export class ActionExecutionService {
  constructor(
    private readonly resolveGameApi: () => IGameApi,
    private readonly operationResolutionService = new ActionOperationResolutionService(),
    private readonly movementStateService = new MovementStateService(),
  ) {}

  async execute(input: {
    gameId: number;
    entityKey: CombatEntityKey;
    version: CharacterVersion;
    rule: Rule;
    action: CombatActionOption;
    rules: Rule[];
    pendingEffects: PendingActionEffect[];
    actionPointCost: number;
    attackerName: string;
    chatId: number | null;
    speaker: ChatSpeaker;
    sendChat: (
      content: string,
      attachments: ChatAttachment[],
      chatId: number,
      speaker: ChatSpeaker,
    ) => Promise<unknown>;
    operations?: ActionOperation[];
    operationRequests?: ActionOperationRequest[];
    currentMovementStep?: DimensionalNumberValue;
    characteristicValues?: ReadonlyMap<string, DimensionalNumberValue>;
    spatialResolver?: ISpatialResolver;
    mechanics?: Mechanic[];
  }): Promise<{
    overlay: GameCombatOverlay;
    effects: PendingActionEffect[];
    spent: number;
    resolution: ReturnType<ActionOperationResolutionService['resolve']>;
  }> {
    const overview = characterOverviewService.build(input.version, input.rules);
    const resource = attackDamageService.actionPointsResource(overview, input.rules);
    if (!resource) throw new Error('ОД не найдено');
    if (input.actionPointCost <= 0) throw new Error('Укажите количество ОД');
    if (input.actionPointCost > resource.current.base) throw new Error('Недостаточно ОД для действия');
    const movementStep = movementContextService.resolveMovementStep(
      input.version,
      input.rules,
      input.currentMovementStep,
    );
    new ActionOperationResolutionService().resolve(input.operations ?? [], input.operationRequests ?? [], {
      currentMovementStep: movementStep,
      characteristicValues: input.characteristicValues ?? new Map(),
      spentActionPoints: input.actionPointCost,
    });

    const resolved = actionEffectService.resolveForNextAction(input.pendingEffects, {
      isAttack: false,
      component: 'strike',
      baseCost: input.actionPointCost,
    });
    const nextResource = attackDamageService.spendActionPoints(resource.current, input.actionPointCost);
    let overlay = await this.resolveGameApi().setCombatResource(
      input.gameId,
      input.entityKey,
      resource.ruleCode,
      nextResource,
    );
    const effects = [
      ...actionEffectService.consumeResource(resolved.remainingEffects, 'action-points', input.actionPointCost),
      ...actionEffectService.effectsAfterAction(input.rule),
    ];
    await this.resolveGameApi().setCombatActionEffects(input.gameId, input.entityKey, effects);
    const operationResolutionService = input.spatialResolver
      ? new ActionOperationResolutionService(undefined, input.spatialResolver)
      : this.operationResolutionService;
    const resolution = operationResolutionService.resolve(input.operations ?? [], input.operationRequests ?? [], {
      currentMovementStep: input.currentMovementStep ?? { base: 1, size: 0 },
      characteristicValues: input.characteristicValues ?? new Map(),
      spentActionPoints: input.actionPointCost,
    });
    let nextSpeed = this.movementStateService.fromResolution(resolution, movementStep);
    if (input.mechanics) {
      const mechanicContext: MovementStateMechanicContext = {
        resolution,
        currentMovementStep: movementStep,
        currentSpeed: nextSpeed,
        resolveCurrentSpeed: (resolved, step) =>
          this.movementStateService.fromResolution(
            resolved as ReturnType<ActionOperationResolutionService['resolve']>,
            step,
          ),
      };
      const bindings: MechanicBinding[] = input.rules.map((rule) => ({
        ruleCode: rule.code,
        mechanicId: rule.mechanicId ?? null,
        mechanicPayload: rule.mechanicPayload ?? null,
      }));
      const activeMechanics = mechanicEngine.resolveActive(bindings, input.mechanics, {
        extraRuleCodes: ['movement-state'],
      });
      mechanicEngine.runEvent('action_resolved', mechanicContext, activeMechanics);
      nextSpeed = mechanicContext.currentSpeed as CurrentSpeed;
    }
    await this.resolveGameApi().setCurrentSpeed(input.gameId, input.entityKey, nextSpeed);
    overlay = await this.applyPostureAndStability(input, overlay);
    if (input.chatId !== null) {
      await input.sendChat(
        formatAttackActionMessage({
          attackerKey: input.entityKey,
          attackerName: input.attackerName,
          action: input.action,
          attackerAp: input.actionPointCost,
          rules: input.rules,
        }),
        [],
        input.chatId,
        input.speaker,
      );
    }

    return { overlay, effects, spent: input.actionPointCost, resolution };
  }

  private async applyPostureAndStability(
    input: {
      gameId: number;
      entityKey: CombatEntityKey;
      version: CharacterVersion;
      rule: Rule;
      action: CombatActionOption;
      rules: Rule[];
      operations?: ActionOperation[];
    },
    overlay: GameCombatOverlay,
  ): Promise<GameCombatOverlay> {
    let version = combatOverlayService.mergeCombatOverlay(input.version, overlay);
    let nextOverlay = overlay;
    if (postureStateService.shouldToggleLying(input.operations ?? input.action.operations)) {
      const next = postureStateService.nextAfterStandUp(version);
      const removed = await removeStatesByCodes(
        this.resolveGameApi(),
        input.gameId,
        input.entityKey,
        version,
        input.rules,
        next.removeCodes,
      );
      if (removed) {
        nextOverlay = removed;
        version = combatOverlayService.mergeCombatOverlay(version, removed);
      }
      if (next.addLying) {
        const added = await addFlagState(
          this.resolveGameApi(),
          input.gameId,
          input.entityKey,
          input.rules,
          LYING_STATE_CODE,
        );
        if (added) {
          nextOverlay = added;
          version = combatOverlayService.mergeCombatOverlay(version, added);
        }
      }
    }
    if (postureStateService.isRecoverStability(input.rule.code)) {
      const removed = await removeStatesByCodes(
        this.resolveGameApi(),
        input.gameId,
        input.entityKey,
        version,
        input.rules,
        [UNSTABLE_STATE_CODE],
      );
      if (removed) nextOverlay = removed;
    }

    return nextOverlay;
  }
}
