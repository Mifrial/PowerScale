import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterWound } from '@/modules/Roleplay/Character/Dto/CharacterWound';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { BandageOdArgs } from '@/modules/Roleplay/Game/Dto/BandageOdArgs';
import { BANDAGE_CONTRIBUTION_CAP } from '@/modules/Roleplay/Game/Constant/Wound/BANDAGE_CONTRIBUTION_CAP';
import { BANDAGE_CONTRIBUTION_STEP } from '@/modules/Roleplay/Game/Constant/Wound/BANDAGE_CONTRIBUTION_STEP';
import { WOUND_ACTION_OD } from '@/modules/Roleplay/Game/Constant/Wound/WOUND_ACTION_OD';
import { WOUND_HAND_SLOT_MAX } from '@/modules/Roleplay/Game/Constant/Wound/WOUND_HAND_SLOT_MAX';
import { FIRST_AID_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/FIRST_AID_ABILITY_CODE';
import { QUICK_BANDAGE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/QUICK_BANDAGE_ABILITY_CODE';
import { WOUND_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';

/**
 * Экземпляр раны в бою: сила, вклады закрытости, зажим, стоимость перевязки.
 */
export class WoundInstanceService {
  isWound(state: CharacterStateValue): boolean {
    return state.stateRuleCode === WOUND_STATE_CODE;
  }

  emptyWound(): CharacterWound {
    return { bandage: 0, clotting: 0, internal: false, aided: false, heldBy: null };
  }

  addWound(strength: number): CharacterStateValue | null {
    const value = Math.floor(strength);
    if (value <= 0) return null;

    return { stateRuleCode: WOUND_STATE_CODE, value, wound: this.emptyWound() };
  }

  migrate(state: CharacterStateValue): CharacterStateValue {
    if (!this.isWound(state)) return state;
    const value = Math.max(0, Math.floor(state.value ?? 0));
    const wound = { ...this.emptyWound(), ...(state.wound ?? {}) };

    return this.clampToStrength({ ...state, value, wound });
  }

  migrateStates(states: CharacterStateValue[]): CharacterStateValue[] {
    return states.map((state) => this.migrate(state));
  }

  stripHolds(states: CharacterStateValue[]): CharacterStateValue[] {
    return states.map((state) => {
      if (!this.isWound(state) || !state.wound?.heldBy) return state;

      const wound = this.migrate(state).wound ?? this.emptyWound();

      return this.migrate({ ...state, wound: { ...wound, heldBy: null } });
    });
  }

  strength(state: CharacterStateValue): number {
    return Math.max(0, Math.floor(this.migrate(state).value ?? 0));
  }

  payload(state: CharacterStateValue): CharacterWound {
    return this.migrate(state).wound ?? this.emptyWound();
  }

  isHeld(state: CharacterStateValue): boolean {
    return this.payload(state).heldBy != null;
  }

  closure(state: CharacterStateValue, heldActive = this.isHeld(state)): number {
    const migrated = this.migrate(state);
    const strength = this.strength(migrated);
    const wound = migrated.wound ?? this.emptyWound();
    const fromContrib = Math.min(strength, Math.max(0, wound.bandage) + Math.max(0, wound.clotting));
    if (!heldActive) return fromContrib;

    return Math.min(strength, Math.max(fromContrib, 1));
  }

  tick(state: CharacterStateValue, heldActive = this.isHeld(state)): number {
    return Math.max(0, this.strength(state) - this.closure(state, heldActive));
  }

  bleedTotal(states: CharacterStateValue[]): number {
    return states.filter((state) => this.isWound(state)).reduce((sum, state) => sum + this.tick(state), 0);
  }

  heldCount(actorKey: string, overlays: readonly GameCombatOverlay[]): number {
    let count = 0;
    for (const overlay of overlays) {
      for (const state of overlay.states) {
        if (this.isWound(state) && this.payload(state).heldBy === actorKey) count += 1;
      }
    }

    return count;
  }

  freeHands(actorKey: string, overlays: readonly GameCombatOverlay[]): number {
    return Math.max(0, WOUND_HAND_SLOT_MAX - this.heldCount(actorKey, overlays));
  }

  clampToStrength(state: CharacterStateValue): CharacterStateValue {
    if (!this.isWound(state)) return state;
    const strength = Math.max(0, Math.floor(state.value ?? 0));
    const wound = { ...this.emptyWound(), ...(state.wound ?? {}) };
    wound.bandage = Math.min(strength, Math.max(0, Math.floor(Number(wound.bandage) || 0)));
    wound.clotting = Math.min(strength, Math.max(0, Math.floor(Number(wound.clotting) || 0)));
    if (wound.heldBy === '') wound.heldBy = null;

    return { ...state, value: strength, wound };
  }

  setStrength(state: CharacterStateValue, strength: number): CharacterStateValue {
    return this.clampToStrength({ ...this.migrate(state), value: Math.max(1, Math.floor(strength)) });
  }

  setInternal(state: CharacterStateValue, internal: boolean): CharacterStateValue {
    const migrated = this.migrate(state);
    const wound = { ...this.payload(migrated), internal };
    if (internal) wound.heldBy = null;

    return { ...migrated, wound };
  }

  canSqueeze(state: CharacterStateValue): boolean {
    const migrated = this.migrate(state);

    return this.isWound(migrated) && !this.payload(migrated).internal && this.payload(migrated).heldBy == null;
  }

  applySqueeze(state: CharacterStateValue, actorKey: string): CharacterStateValue {
    const migrated = this.migrate(state);

    return { ...migrated, wound: { ...this.payload(migrated), heldBy: actorKey } };
  }

  releaseSqueeze(state: CharacterStateValue): CharacterStateValue {
    const migrated = this.migrate(state);

    return { ...migrated, wound: { ...this.payload(migrated), heldBy: null } };
  }

  bandageCap(state: CharacterStateValue, medicHasAid: boolean): number {
    const migrated = this.migrate(state);
    const wound = this.payload(migrated);
    if (wound.aided || medicHasAid) return this.strength(migrated);

    return Math.min(this.strength(migrated), BANDAGE_CONTRIBUTION_CAP);
  }

  canBandage(state: CharacterStateValue, medicHasAid: boolean): boolean {
    if (!this.isWound(state)) return false;
    const migrated = this.migrate(state);
    if (this.payload(migrated).internal) return false;

    return this.payload(this.applyBandage(migrated, medicHasAid)).bandage > this.payload(migrated).bandage;
  }

  bandageOd(args: BandageOdArgs): number {
    if (args.medicHasAid && args.medicHasQuick && args.targetBandagedOnce) return WOUND_ACTION_OD.bandageQuick;
    if (args.medicHasAid) return WOUND_ACTION_OD.bandageAid;

    return WOUND_ACTION_OD.bandage;
  }

  applyBandage(state: CharacterStateValue, medicHasAid: boolean): CharacterStateValue {
    const migrated = this.migrate(state);
    const wound = { ...this.payload(migrated) };
    if (wound.internal) return migrated;
    const cap = this.bandageCap(migrated, medicHasAid);
    wound.bandage = Math.min(cap, wound.bandage + BANDAGE_CONTRIBUTION_STEP);
    if (medicHasAid) wound.aided = true;

    return this.clampToStrength({ ...migrated, wound });
  }

  applyClotting(state: CharacterStateValue, rating: number): CharacterStateValue {
    const migrated = this.migrate(state);
    const wound = { ...this.payload(migrated) };
    const gain = Math.max(0, Math.floor(rating));
    if (gain <= 0) return migrated;
    wound.clotting = wound.clotting + gain;

    return this.clampToStrength({ ...migrated, wound });
  }

  hasAbility(abilities: readonly CharacterAbility[], code: string): boolean {
    return abilities.some((ability) => ability.ruleCode === code && ability.level >= 1);
  }

  medicHasAid(version: CharacterVersion | null | undefined): boolean {
    return this.hasAbility(version?.abilities ?? [], FIRST_AID_ABILITY_CODE);
  }

  medicHasQuick(version: CharacterVersion | null | undefined): boolean {
    return this.medicHasAid(version) && this.hasAbility(version?.abilities ?? [], QUICK_BANDAGE_ABILITY_CODE);
  }

  summaryLabel(state: CharacterStateValue): string {
    const migrated = this.migrate(state);
    const tick = this.tick(migrated);
    const bits = [`сила ${this.strength(migrated)}`, `тик ${tick}`];
    if (this.payload(migrated).internal) bits.push('внутр.');
    if (this.isHeld(migrated)) bits.push('заж.');

    return bits.join(' · ');
  }
}
