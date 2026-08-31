import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import { BLOOD_LOSS_STATE_CODE, EXHAUSTION_STATE_CODE, WOUND_STATE_CODE } from '@/modules/Roleplay/Rule/init';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { injuryCheckService } from '@/modules/Roleplay/Game/Service/Instance/injuryCheckService';
import { exhaustionCheckService } from '@/modules/Roleplay/Game/Service/Instance/exhaustionCheckService';

import { injuryRollService } from '@/modules/Roleplay/Game/Service/Instance/injuryRollService';

import { resolveInjuryProcedure } from '@/modules/Roleplay/Game/Utils/resolveInjuryProcedure';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';

import { formatBloodLossTickMessage } from '@/modules/Roleplay/Game/Utils/bloodLossMessage';
import { setNumericState } from '@/modules/Roleplay/Game/Utils/combatStateWrite';
import { endOfTurnDotsService } from '@/modules/Roleplay/Game/Service/Instance/endOfTurnDotsService';

import { applyBloodLossGain, bloodLossInjuryDifficulty } from '@/modules/Roleplay/Game/Utils/bloodLossMath';

import type { ApplyBloodLossArgs } from '@/modules/Roleplay/Game/Dto/ApplyBloodLossArgs';

export class BloodLossService {
  constructor(private readonly resolveGameApi: () => IGameApi) {}

  async applyBloodLossTick(args: ApplyBloodLossArgs): Promise<GameCombatOverlay | null> {
    if (args.delta <= 0) return null;
    let version = args.version;
    const oldBlood = injuryCheckService.overlayStateTotal(version, args.rules, BLOOD_LOSS_STATE_CODE);
    const oldExh = injuryCheckService.overlayStateTotal(version, args.rules, EXHAUSTION_STATE_CODE);
    const next = applyBloodLossGain(oldBlood, args.delta, oldExh);
    let overlay = await setNumericState(
      this.resolveGameApi(),
      args.gameId,
      args.targetKey,
      version,
      args.rules,
      BLOOD_LOSS_STATE_CODE,
      next.bloodLoss,
    );
    if (overlay) version = combatOverlayService.mergeCombatOverlay(version, overlay);
    if (args.chatId !== null) {
      const sent = await args.sendMessage(
        formatBloodLossTickMessage(args.targetName, args.delta, next.bloodLoss, args.targetKey),
        [],
        args.chatId,
        args.speaker,
      );
      if (!sent) throw new Error('Не удалось отправить сообщение о кровопотере');
    }
    if (next.exhaustion !== oldExh) {
      overlay =
        (await setNumericState(
          this.resolveGameApi(),
          args.gameId,
          args.targetKey,
          version,
          args.rules,
          EXHAUSTION_STATE_CODE,
          next.exhaustion,
        )) ?? overlay;
      if (overlay) version = combatOverlayService.mergeCombatOverlay(version, overlay);
    }
    if (next.addedExhaustion > 0) {
      const checked = await exhaustionCheckService.applyExhaustionCheck({
        version,
        rng: args.rng,
        rules: args.rules,
        mechanics: args.mechanics,
        gameId: args.gameId,
        targetKey: args.targetKey,
        targetName: args.targetName,
        chatId: args.chatId,
        speaker: args.speaker,
        change: 'increase',
        sendMessage: args.sendMessage,
      });
      if (checked.overlay) {
        overlay = checked.overlay;
        version = combatOverlayService.mergeCombatOverlay(version, checked.overlay);
      }
    }
    const bloodDc = bloodLossInjuryDifficulty(next.reserved);
    const procedure = resolveInjuryProcedure(args.rules, args.mechanics);
    const normalDc = injuryRollService.injuryDifficulty(
      {
        leftoverDamage: 0,
        woundStrength: 0,
        endurance: Math.max(1, args.endurance),
        exhaustion: next.exhaustion,
        attackSr: 0,
      },
      procedure,
      0,
    );
    const useBlood = bloodDc != null;
    const difficulty = useBlood ? bloodDc : normalDc;
    if (difficulty > 0 && (useBlood || next.addedExhaustion > 0)) {
      const applied = await injuryCheckService.applyInjuryCheck({
        input: {
          leftoverDamage: 0,
          woundStrength: 0,
          difficulty,
          endurance: Math.max(1, args.endurance),
          exhaustion: next.exhaustion,
          attackSr: 0,
          actorKey: args.targetKey,
          label: useBlood ? 'Проверка на увечье (кровопотеря)' : undefined,
        },
        rng: args.rng,
        rules: args.rules,
        mechanics: args.mechanics,
        gameId: args.gameId,
        targetKey: args.targetKey,
        targetName: args.targetName,
        chatId: args.chatId,
        speaker: args.speaker,
        skipIfNoRoll: !useBlood,
        chatPrefix: useBlood ? 'Увечье от кровопотери.' : undefined,
        targetVersion: version,
        sendMessage: args.sendMessage,
      });
      if (applied.overlay) overlay = applied.overlay;
    }

    return overlay;
  }

  async applyTurnWoundBleed(args: Omit<ApplyBloodLossArgs, 'delta'>): Promise<GameCombatOverlay | null> {
    const delta = injuryCheckService.overlayStateTotal(args.version, args.rules, WOUND_STATE_CODE);
    const blood = await this.applyBloodLossTick({ ...args, delta });
    const version = blood ? combatOverlayService.mergeCombatOverlay(args.version, blood) : args.version;
    const dots = await endOfTurnDotsService.applyEndOfTurnDots({ ...args, version });

    return dots ?? blood;
  }
}
