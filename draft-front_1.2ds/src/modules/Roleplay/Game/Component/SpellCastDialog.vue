<script setup lang="ts">
import { computed, inject, nextTick, ref, watch } from 'vue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { ATTACK_CALC_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Attack/ATTACK_CALC_ATTACHMENT_TYPE';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { CommittedActionSession } from '@/modules/Roleplay/Game/Dto/CommittedActionSession';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';
import CombatEntitySelect from '@/modules/Roleplay/Game/Component/CombatEntitySelect.vue';
import ConcentrationTokenOption from '@/modules/Roleplay/Game/Component/ConcentrationTokenOption.vue';
import { CONCENTRATION_TOKEN_ASK_INJECT_KEY } from '@/modules/Roleplay/Game/Constant/CONCENTRATION_TOKEN_ASK_INJECT_KEY';
import { combatChatSendService } from '@/modules/Roleplay/Game/Service/Instance/combatChatSendService';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { CHECK_HIT_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { spellCastDifficultyService } from '@/modules/Roleplay/Game/Service/Instance/spellCastDifficultyService';
import { spellCastOptionsService } from '@/modules/Roleplay/Game/Service/Instance/spellCastOptionsService';
import { spellCastService } from '@/modules/Roleplay/Game/Service/Instance/spellCastService';
import { spellCastExecutionService } from '@/modules/Roleplay/Game/Service/Instance/spellCastExecutionService';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { woundInstanceService } from '@/modules/Roleplay/Game/Service/Instance/woundInstanceService';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';
import { concentrationTokenService } from '@/modules/Roleplay/Game/Service/Instance/concentrationTokenService';
import { exhaustionCheckService } from '@/modules/Roleplay/Game/Service/Instance/exhaustionCheckService';
import { injuryCheckService } from '@/modules/Roleplay/Game/Service/Instance/injuryCheckService';
import { injuryPackageService } from '@/modules/Roleplay/Game/Service/Instance/injuryPackageService';
import { CHARACTERISTIC_BASE_RANGE, characterOverviewService } from '@/modules/Roleplay/Character/init';
import { MAGIC_CONTROL_CODE } from '@/modules/Roleplay/Game/Constant/Spell/MAGIC_CONTROL_CODE';
import { MAGIC_POWER_CODE } from '@/modules/Roleplay/Game/Constant/Spell/MAGIC_POWER_CODE';
import { SIMPLE_TOUCH_CODE } from '@/modules/Roleplay/Game/Constant/Combat/SIMPLE_TOUCH_CODE';
import { actionOdCost, findRuleByRef, listAttackActions } from '@/modules/Roleplay/Game/Utils/combatActions';
import { useCombatChatThread } from '@/modules/Roleplay/Game/Composables/useCombatChatThread';
import { useKeywords } from '@/modules/Roleplay/Keyword/init';
import SpellCastSpellRequirementMarks from '@/modules/Roleplay/Game/Component/SpellCastSpellRequirementMarks.vue';
import SpellCastUpgradeList from '@/modules/Roleplay/Game/Component/SpellCastUpgradeList.vue';
import SpellChainHopDialog from '@/modules/Roleplay/Game/Component/SpellChainHopDialog.vue';
import SpellArcaneBurstDialog from '@/modules/Roleplay/Game/Component/SpellArcaneBurstDialog.vue';
import { spellCastUpgradeService } from '@/modules/Roleplay/Game/Service/Instance/spellCastUpgradeService';
import { spellChainHopService } from '@/modules/Roleplay/Game/Service/Instance/spellChainHopService';
import { spellDeviationService } from '@/modules/Roleplay/Game/Service/Instance/spellDeviationService';
import { electrochargeService } from '@/modules/Roleplay/Game/Service/Instance/electrochargeService';
import { activeSpellService } from '@/modules/Roleplay/Game/Service/Instance/activeSpellService';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import {
  buildAttackCalcPayload,
  formatSpellCastBeginMessage,
  formatSpellCastCheckMessage,
  formatSpellCastOutcomeMessage,
  formatChainBreakMessage,
  formatSpellEffectMessage,
  formatSustainBeginMessage,
} from '@/modules/Roleplay/Game/Utils/attackDamageMessage';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import type { ApplyAttackDamageResult } from '@/modules/Roleplay/Game/Dto/ApplyAttackDamageResult';
import type { CheckOffer } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { SpellCastLaunchContext } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastLaunchContext';
import type { SpellCastExecutionResult } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastExecutionResult';
import type { SpellBurstTarget } from '@/modules/Roleplay/Game/Dto/Spell/SpellBurstTarget';
import type { SpellDeviationOutcome } from '@/modules/Roleplay/Game/Dto/Spell/SpellDeviationOutcome';
import { ARCANE_DAMAGE_TYPE_CODE } from '@/modules/Roleplay/Game/Constant/Spell/ARCANE_DAMAGE_TYPE_CODE';
import {
  ACCUMULATED_DAMAGE_STATE_CODE,
  EXHAUSTION_STATE_CODE,
  SHOCK_STATE_CODE,
  STUNNED_STATE_CODE,
  WOUND_STATE_CODE,
} from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';

const props = defineProps<{
  open: boolean;
  casterKey?: CombatEntityKey | null;
  activeSpeakerKey: string | null;
  gameId: number;
  chatId: number | null;
  characters: GameCharacterMembership[];
  npcs: GameNpc[];
  rules: Rule[];
  mechanics: Mechanic[];
  canEdit: boolean;
  currentUserId: number | null;
  launchContext?: SpellCastLaunchContext | null;
  initiativeKeys?: string[];
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  'overlay-changed': [];
  settled: [];
}>();

const sendChat = combatChatSendService.sendCombatChat(props.gameId);
const askTokenSpend = inject(CONCENTRATION_TOKEN_ASK_INJECT_KEY, undefined);
const combatThread = useCombatChatThread(() => props.gameId);
const overlays = ref<GameCombatOverlay[]>([]);
const committedSessions = ref<Record<CombatEntityKey, CommittedActionSession>>({});
const spendConcentration = ref(0);
const spellCode = ref('');
const sourceKey = ref('');
const pathCode = ref('');
const appliedUpgradeCodes = ref<string[]>([]);
const activeSpells = ref<ActiveSpell[]>([]);
const hopOpen = ref(false);
const hopLastKey = ref<CombatEntityKey | null>(null);
let hopWait: ((value: { key: CombatEntityKey; distanceIpari: number } | null) => void) | null = null;
const burstOpen = ref(false);
let burstWait: ((value: SpellBurstTarget[]) => void) | null = null;
const usedPower = ref<DimensionalNumberValue>({ base: 3, size: 0 });
const hasTarget = ref(false);
const targetKey = ref<CombatEntityKey | null>(null);
const parameterPower = ref<DimensionalNumberValue>({ base: 3, size: 0 });
const busy = ref(false);
const error = ref<string | null>(null);
const lastSkip = ref(false);
const lastMilk = ref(false);
const lastAutoFail = ref(false);
const showCastOptions = ref(false);
const touchActionCode = ref(SIMPLE_TOUCH_CODE);
const touchTargetKey = ref<CombatEntityKey | ''>('');
const touchProfileItem = ref('');
const { keywords, fetchTags } = useKeywords();

const resolvedCasterKey = computed<CombatEntityKey | null>(() => {
  if (props.casterKey) {
    return props.casterKey;
  }
  const key = props.activeSpeakerKey;

  return key && key !== 'gm' ? (key as CombatEntityKey) : null;
});

const casterModel = computed(() => {
  if (!resolvedCasterKey.value) {
    return null;
  }

  return combatCardModelService.combatCardModel(
    resolvedCasterKey.value,
    props.characters,
    props.npcs,
    props.canEdit,
    props.currentUserId,
    overlays.value.find((item) => item.entityKey === resolvedCasterKey.value) ?? null,
  );
});

const casterVersion = computed(() => casterModel.value?.effectiveVersion ?? null);

const chargeSustain = computed(() => {
  const context = props.launchContext;
  if (context?.kind !== 'charge_spend') {
    return null;
  }

  return activeSpells.value.find((spell) => spell.id === context.sustainId) ?? null;
});

const chargeSpendCost = computed(() => {
  if (!chargeSustain.value) {
    return undefined;
  }
  const spec = electrochargeService.chargeSpec(chargeSustain.value.spellCode, props.rules);

  return spec?.spend.action_points;
});

const overview = computed(() => {
  const version = casterVersion.value;
  if (!version) {
    return null;
  }

  return characterOverviewService.build(version, props.rules);
});

const spells = computed(() => {
  const owned = spellCastOptionsService.listOwnedSpells(overview.value, props.rules);
  const sustain = chargeSustain.value;
  const spec = sustain ? electrochargeService.chargeSpec(sustain.spellCode, props.rules) : null;
  if (!sustain || !spec) {
    return owned;
  }

  return electrochargeService.filterCastOptions(
    owned,
    props.rules,
    keywords.value,
    electrochargeService.turnApMax(overview.value),
    spec,
  );
});
const sources = computed(() => spellCastOptionsService.listSources(casterVersion.value, props.rules));
const paths = computed(() => spellCastOptionsService.listPaths(casterVersion.value, props.rules));
const spellItems = computed(() =>
  spells.value.map((spell) => ({
    ...spell,
    powerLabel: spellCastDifficultyService.formatSpellRequirement(spell.requiredPower),
    controlLabel: spellCastDifficultyService.formatSpellRequirement(spell.requiredControl),
  })),
);
const sourceItems = computed(() =>
  sources.value.map((source) => ({
    value: source.key,
    title: source.name,
    disabled:
      resolvedCasterKey.value != null &&
      activeSpellService.isSourceOccupied(activeSpells.value, resolvedCasterKey.value, source.key),
  })),
);
const pathItems = computed(() => paths.value.map((path) => ({ value: path.pathCode, title: path.name })));
const selectedPath = computed(() => paths.value.find((path) => path.pathCode === pathCode.value) ?? null);
const selectedSpell = computed(() => props.rules.find((rule) => rule.code === spellCode.value));
const spellSpec = computed(() => spellCastDifficultyService.asSpellAbilitySpec(selectedSpell.value));
const needsTouchAttack = computed(() => spellSpec.value?.hit_resolution?.type === 'attack');
const needsSpellTarget = computed(() => spellSpec.value?.hit_resolution?.type === 'auto');
const touchActions = computed(() =>
  listAttackActions(props.rules, overview.value, 'strike').map((action) => ({
    value: action.code,
    title: `${action.name} (${action.odCost} ОД)`,
  })),
);
const strikeProfiles = computed(() =>
  (overview.value?.attacks ?? []).filter((attack) => attack.profileType === 'strike'),
);
const strikeProfileItems = computed(() =>
  strikeProfiles.value.map((attack, index) => ({
    value: `${attack.itemRuleCode}:${attack.profileIndex ?? 0}:${index}`,
    title: `${attack.itemName} · ${attack.accuracyLabel}`,
  })),
);
const selectedTouchProfile = computed(() => {
  const index = strikeProfileItems.value.findIndex((item) => item.value === touchProfileItem.value);

  return index >= 0 ? strikeProfiles.value[index] : (strikeProfiles.value[0] ?? null);
});
const actionPointCost = computed(() =>
  spellCastExecutionService.actionPointCost({
    spellCode: spellCode.value,
    touchActionCode: needsTouchAttack.value ? touchActionCode.value : null,
    rules: props.rules,
    casterAbilities: casterVersion.value?.abilities ?? [],
    pathCode: pathCode.value || null,
    appliedUpgradeCodes: appliedUpgradeCodes.value,
    chargeSpendCost: chargeSpendCost.value,
  }),
);
const upgradeOptions = computed(() =>
  spellCastUpgradeService.listApplicable(
    casterVersion.value?.abilities ?? [],
    pathCode.value || null,
    spellCode.value,
    props.rules,
  ),
);
const casterAp = computed(() => {
  if (!overview.value) {
    return { base: 0, size: 0 };
  }

  return attackDamageService.actionPointsResource(overview.value, props.rules)?.current ?? { base: 0, size: 0 };
});
const powerIsParameter = computed(() =>
  Boolean(spellSpec.value && 'type' in spellSpec.value.spell.power && spellSpec.value.spell.power.type === 'parameter'),
);

const targetVersion = computed(() => {
  if (!hasTarget.value || !targetKey.value) {
    return null;
  }
  const overlay = overlays.value.find((item) => item.entityKey === targetKey.value) ?? null;

  return (
    combatCardModelService.combatCardModel(
      targetKey.value,
      props.characters,
      props.npcs,
      props.canEdit,
      props.currentUserId,
      overlay,
    ).effectiveVersion ?? null
  );
});

const entityItems = computed(() => {
  const characters = props.characters
    .filter((membership) => membership.membershipStatus === 'active')
    .map((membership) => ({
      value: `character:${membership.characterId}` as CombatEntityKey,
      title: membership.characterName,
    }));
  const npcs = props.npcs
    .filter((npc) => npc.status === 'active')
    .map((npc) => ({ value: `npc:${npc.id}` as CombatEntityKey, title: npc.name }));

  return [...characters, ...npcs];
});

const availableControl = computed(() => spellCastOptionsService.defaultControl(overview.value));
const casterMaxPower = computed(() =>
  spellCastOptionsService.defaultUsedPower(overview.value, casterVersion.value?.states ?? [], sourceKey.value),
);
const powerStatName = computed(
  () => overview.value?.characteristics.find((entry) => entry.ruleCode === MAGIC_POWER_CODE)?.name ?? 'Мощь',
);
const controlStatName = computed(
  () => overview.value?.characteristics.find((entry) => entry.ruleCode === MAGIC_CONTROL_CODE)?.name ?? 'Контроль',
);
const usedPowerLabel = computed(() => DimensionalNumber.from(usedPower.value).toString());
const fixedSpellPower = computed(() => {
  if (!spellSpec.value || powerIsParameter.value) {
    return null;
  }

  return spellCastDifficultyService.resolveSpellValue(spellSpec.value.spell.power, {});
});
const fixedSpellPowerLabel = computed(() =>
  fixedSpellPower.value ? DimensionalNumber.from(fixedSpellPower.value).toString() : '—',
);

const parameterValues = computed((): Record<string, DimensionalNumberValue> => {
  if (!powerIsParameter.value || !spellSpec.value || !('type' in spellSpec.value.spell.power)) {
    return {};
  }

  return { [spellSpec.value.spell.power.parameter_code]: parameterPower.value };
});

const damageTypeCode = computed(() => spellSpec.value?.spell.damage?.damage_type_code ?? null);

const targetResistance = computed(() => {
  if (!hasTarget.value || !damageTypeCode.value) {
    return 0;
  }

  return spellCastOptionsService.targetResistanceAmount(
    targetVersion.value,
    props.rules,
    damageTypeCode.value,
    parameterValues.value,
  );
});

const preview = computed(() => {
  if (!spellCode.value) {
    return null;
  }

  return spellCastDifficultyService.computeForSpell(
    {
      spellCode: spellCode.value,
      usedPower: usedPower.value,
      availableControl: availableControl.value,
      parameterValues: parameterValues.value,
      hasTarget: hasTarget.value,
      targetResistanceAmount: targetResistance.value,
    },
    props.rules,
  );
});

const controlLabel = computed(() => DimensionalNumber.from(availableControl.value).toString());

const checkCharacteristicCode = computed(() =>
  spellCastService.characteristicCodeForCheck(selectedPath.value?.checkCode ?? null, props.rules),
);

const checkCharacteristic = computed(() => {
  const code = checkCharacteristicCode.value;
  if (!code || !overview.value) {
    return null;
  }

  return overview.value.characteristics.find((entry) => entry.ruleCode === code) ?? null;
});

const selectedSpellItem = computed(() => spellItems.value.find((spell) => spell.ruleCode === spellCode.value) ?? null);

const castCheckLine = computed(() => {
  if (!preview.value) {
    return '—';
  }

  return spellCastDifficultyService.formatCastCheckLine(
    preview.value.needsCheck,
    preview.value.difficulty,
    checkCharacteristic.value?.name ?? null,
    checkCharacteristic.value?.value ?? null,
  );
});

watch(
  () => props.open,
  async (open) => {
    if (!open) {
      return;
    }
    error.value = null;
    lastSkip.value = false;
    lastMilk.value = false;
    lastAutoFail.value = false;
    showCastOptions.value = false;
    if (keywords.value.length === 0) {
      void fetchTags();
    }
    overlays.value = await getGameApi().getCombatOverlays(props.gameId);
    committedSessions.value = await getGameApi()
      .getCommittedActionSessions(props.gameId)
      .catch(() => ({}));
    activeSpells.value = await getGameApi().getActiveSpells(props.gameId);
    appliedUpgradeCodes.value = [];
    await nextTick();
    const sustain = chargeSustain.value;
    if (sustain) {
      sourceKey.value = sustain.sourceKey;
      pathCode.value = sustain.pathCode ?? '';
    } else {
      sourceKey.value = sources.value[0]?.key ?? '';
      pathCode.value = paths.value[0]?.pathCode ?? '';
    }
    usedPower.value = spellCastOptionsService.defaultUsedPower(
      overview.value,
      casterVersion.value?.states ?? [],
      sourceKey.value,
    );
    if (sustain && DimensionalNumber.from(usedPower.value).compare(DimensionalNumber.from(sustain.sustainPower)) > 0) {
      usedPower.value = { ...sustain.sustainPower };
    }
    parameterPower.value = { ...usedPower.value };
    spellCode.value = spells.value[0]?.ruleCode ?? '';
    touchActionCode.value = SIMPLE_TOUCH_CODE;
    touchTargetKey.value = '';
    touchProfileItem.value = strikeProfileItems.value[0]?.value ?? '';
  },
);

watch(spellCode, () => {
  hasTarget.value = needsSpellTarget.value;
});

watch(sourceKey, () => {
  usedPower.value = spellCastDifficultyService.clampToAtMost(usedPower.value, casterMaxPower.value);
});

watch(upgradeOptions, (options) => {
  appliedUpgradeCodes.value = spellCastUpgradeService.pruneSelected(options, appliedUpgradeCodes.value);
});

function close(): void {
  emit('update:open', false);
}

function setUsedPower(value: DimensionalNumberValue | null): void {
  usedPower.value = spellCastDifficultyService.clampToAtMost(value ?? { base: 3, size: 0 }, casterMaxPower.value);
}

function toggleCastOptions(): void {
  showCastOptions.value = !showCastOptions.value;
}

function speaker(): ChatSpeaker {
  const model = casterModel.value;
  if (!model) {
    return { kind: 'gm' };
  }

  return model.kind === 'character'
    ? { kind: 'character', characterId: model.entityId, characterName: model.name }
    : { kind: 'npc', npcId: model.entityId, npcName: model.name };
}

function nameOf(key: CombatEntityKey): string {
  return entityItems.value.find((item) => item.value === key)?.title ?? key;
}

function versionOf(key: CombatEntityKey) {
  return combatCardModelService.combatCardModel(
    key,
    props.characters,
    props.npcs,
    props.canEdit,
    props.currentUserId,
    overlays.value.find((item) => item.entityKey === key) ?? null,
  ).effectiveVersion;
}

async function runCast(): Promise<void> {
  if (!props.canEdit || !preview.value || !spellCode.value || !overview.value || !resolvedCasterKey.value) {
    return;
  }
  busy.value = true;
  error.value = null;
  lastSkip.value = false;
  lastMilk.value = false;
  lastAutoFail.value = false;
  try {
    if (resolvedCasterKey.value && committedSessions.value[resolvedCasterKey.value]) {
      error.value = 'Сначала закончи или сорви текущее действие';

      return;
    }
    const characteristic = checkCharacteristic.value;
    const touchKey = touchTargetKey.value === '' ? null : touchTargetKey.value;
    if (needsTouchAttack.value && touchKey) {
      const profile = selectedTouchProfile.value;
      if (!profile) {
        error.value = 'Выберите оружие касания';

        return;
      }
      const cost = actionPointCost.value;
      if (cost > casterAp.value.base) {
        error.value = 'Недостаточно ОД для сотворения';

        return;
      }
      if (
        !chargeSustain.value &&
        resolvedCasterKey.value &&
        sourceKey.value &&
        activeSpellService.isSourceOccupied(activeSpells.value, resolvedCasterKey.value, sourceKey.value)
      ) {
        error.value = 'Источник занят поддержанием';

        return;
      }
      await offerTouchHit(resolvedCasterKey.value, touchKey, profile, cost, characteristic ?? undefined);
      await persistSpentAp(resolvedCasterKey.value, cost);
      emit('settled');
      close();

      return;
    }
    const effectKey = needsTouchAttack.value ? touchKey : hasTarget.value ? targetKey.value : null;
    if (
      !chargeSustain.value &&
      sourceKey.value &&
      activeSpellService.isSourceOccupied(activeSpells.value, resolvedCasterKey.value, sourceKey.value)
    ) {
      error.value = 'Источник занят поддержанием';

      return;
    }
    const extraCheckAdvantages: AdvantageModifier[] = [];
    if (spendConcentration.value > 0 && resolvedCasterKey.value && casterVersion.value) {
      const overlay = overlays.value.find((item) => item.entityKey === resolvedCasterKey.value) ?? null;
      const cap = concentrationTokenService.maxSpend(
        casterVersion.value,
        overlay,
        props.rules,
        selectedPath.value?.checkCode ?? '',
        checkCharacteristicCode.value,
      );
      const spent = Math.min(spendConcentration.value, cap);
      if (spent > 0) {
        const next = await concentrationTokenService.spendToken(
          getGameApi(),
          props.gameId,
          resolvedCasterKey.value,
          casterVersion.value,
          overlay,
          spent,
        );
        overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, next);
        extraCheckAdvantages.push(concentrationTokenService.tokenAdvantage(spent));
        spendConcentration.value = 0;
      }
    }
    const outcome = spellCastExecutionService.execute({
      spellCode: spellCode.value,
      casterKey: resolvedCasterKey.value,
      casterOverview: overview.value,
      casterAbilities: casterVersion.value?.abilities ?? [],
      currentActionPoints: casterAp.value,
      resolve: {
        spellCode: spellCode.value,
        usedPower: usedPower.value,
        availableControl: availableControl.value,
        parameterValues: parameterValues.value,
        hasTarget: hasTarget.value,
        targetResistanceAmount: targetResistance.value,
      },
      checkCode: selectedPath.value?.checkCode ?? null,
      characteristicValue: characteristic?.value ?? { base: 3, size: 0 },
      characteristicName: characteristic?.name ?? selectedPath.value?.name ?? 'Сотворение',
      parameterPower: parameterPower.value,
      keywords: keywords.value,
      rules: props.rules,
      mechanics: props.mechanics,
      rng: Math.random,
      touchActionCode: needsTouchAttack.value ? touchActionCode.value : null,
      touchProfile: selectedTouchProfile.value,
      touchTargetKey: touchKey,
      touchTargetOverview: touchKey ? overviewOf(touchKey) : null,
      effectTargetOverview: effectKey ? overviewOf(effectKey) : null,
      distanceIpari: 0,
      sourceKey: sourceKey.value,
      pathCode: pathCode.value || null,
      appliedUpgradeCodes: appliedUpgradeCodes.value,
      extraCheckAdvantages,
      chargeSpendCost: chargeSpendCost.value,
    });
    if (!outcome.started) {
      error.value = 'Недостаточно ОД для сотворения';

      return;
    }
    await persistSpentAp(resolvedCasterKey.value, outcome.spentAp);
    await persistChargeSpendIfNeeded();
    lastSkip.value = Boolean(outcome.cast && !outcome.cast.needsCheck);
    lastMilk.value = outcome.milk;
    lastAutoFail.value = outcome.autoFail;
    await resolveTargetedCast(outcome, effectKey);
    await persistGeneratorIfNeeded(outcome);
    emit('settled');
    close();
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось сотворить заклинание';
  } finally {
    busy.value = false;
  }
}

async function offerTouchHit(
  casterKey: CombatEntityKey,
  opponent: CombatEntityKey,
  profile: NonNullable<typeof selectedTouchProfile.value>,
  cost: number,
  characteristic: { name: string; value: DimensionalNumberValue } | undefined,
): Promise<CheckOffer> {
  const action = listAttackActions(props.rules, overview.value, 'strike').find(
    (item) => item.code === touchActionCode.value,
  );

  return getGameApi().createCheckOffer(props.gameId, {
    checkCode: CHECK_HIT_CODE,
    initiator: casterKey,
    opponent,
    proposal: {
      initiatorCharacteristic: null,
      opponentCharacteristic: null,
      initiatorAdv: 0,
      opponentAdv: 0,
      attackAction: {
        initiator: casterKey,
        source: { kind: 'action', actionRuleCode: touchActionCode.value },
        strikes: [{ targetKey: opponent, profile }],
        reactionMode: 'simultaneous',
        totalOdCost: cost,
      },
      hit: {
        itemRuleCode: profile.itemRuleCode,
        itemName: profile.itemName,
        profileType: profile.profileType,
        profileIndex: profile.profileIndex,
        accuracy: profile.accuracy,
        reaction: null,
        damageTypeCode: profile.damageTypeCode,
        damage: profile.damage,
        penetration: profile.penetration,
        actionRuleCode: touchActionCode.value,
        actionName: action?.name ?? 'Касание',
        actionOd: cost,
        distanceIpari: null,
        reach: profile.reach,
        falloff: profile.falloff,
        flank: false,
        turn: false,
      },
      spellCast: {
        spellCode: spellCode.value,
        usedPower: usedPower.value,
        availableControl: availableControl.value,
        parameterValues: parameterValues.value,
        hasSpellTarget: hasTarget.value,
        spellTargetKey: hasTarget.value ? targetKey.value : null,
        targetResistanceAmount: targetResistance.value,
        parameterPower: parameterPower.value,
        checkCode: selectedPath.value?.checkCode ?? null,
        characteristicValue: characteristic?.value ?? { base: 3, size: 0 },
        characteristicName: characteristic?.name ?? selectedPath.value?.name ?? 'Сотворение',
        touchActionCode: touchActionCode.value,
        touchActionName: action?.name ?? 'Касание',
        spellOd: actionOdCost(spellSpec.value?.action_components),
        touchOd: action?.odCost ?? 0,
        spentAp: cost,
        sourceKey: sourceKey.value,
        pathCode: pathCode.value || null,
        appliedUpgradeCodes: appliedUpgradeCodes.value,
        chargeSustainId: chargeSustain.value?.id,
      },
    },
  });
}

function overviewOf(key: CombatEntityKey) {
  const overlay = overlays.value.find((item) => item.entityKey === key) ?? null;
  const version = combatCardModelService.combatCardModel(
    key,
    props.characters,
    props.npcs,
    props.canEdit,
    props.currentUserId,
    overlay,
  ).effectiveVersion;
  if (!version) {
    return null;
  }

  return characterOverviewService.build(version, props.rules);
}

async function persistSpentAp(key: CombatEntityKey, cost: number): Promise<void> {
  if (cost <= 0 || !overview.value) {
    return;
  }
  const resource = attackDamageService.actionPointsResource(overview.value, props.rules);
  if (!resource) {
    return;
  }
  const next = attackDamageService.spendActionPoints(resource.current, cost);
  const overlay = await getGameApi().setCombatResource(props.gameId, key, resource.ruleCode, next);
  overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, overlay);
  const pending = (await getGameApi().getPendingActionEffects(props.gameId))[key] ?? [];
  const nextEffects = actionEffectService.afterDeclaredAction(pending, cost, {
    isAttack: false,
    component: 'strike',
    baseCost: cost,
  });
  await getGameApi().setCombatActionEffects(props.gameId, key, nextEffects);
  emit('overlay-changed');
}

async function persistChargeSpendIfNeeded(): Promise<void> {
  const sustain = chargeSustain.value;
  const casterKey = resolvedCasterKey.value;
  if (!sustain || !casterKey) {
    return;
  }
  const spec = electrochargeService.chargeSpec(sustain.spellCode, props.rules);
  const states = casterVersion.value?.states ?? [];
  if (!spec) {
    return;
  }
  const next = electrochargeService.spend(states, sustain.id, spec);
  const index = electrochargeService.boundIndex(states, spec.state_code, sustain.id);
  if (!next || index < 0) {
    return;
  }
  const overlay = await getGameApi().replaceCombatState(props.gameId, casterKey, index, next);
  overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, overlay);
  emit('overlay-changed');
}

async function persistChargeGrant(casterKey: CombatEntityKey, sustain: ActiveSpell): Promise<void> {
  const spec = electrochargeService.chargeSpec(sustain.spellCode, props.rules);
  if (!spec) {
    return;
  }
  const states = versionOf(casterKey)?.states ?? [];
  const cap = electrochargeService.cap(
    sustain.spellCode,
    versionOf(casterKey)?.abilities ?? [],
    props.rules,
    keywords.value,
  );
  const next = electrochargeService.grant(states, sustain.id, spec, cap);
  const index = electrochargeService.boundIndex(states, spec.state_code, sustain.id);
  const overlay =
    index >= 0
      ? await getGameApi().replaceCombatState(props.gameId, casterKey, index, next)
      : await getGameApi().addCombatState(props.gameId, casterKey, next);
  overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, overlay);
  emit('overlay-changed');
}

async function persistCoreDeviation(
  casterKey: CombatEntityKey | null,
  sourceKeyValue: string,
  strength: number,
): Promise<void> {
  if (!casterKey) {
    return;
  }
  const states = versionOf(casterKey)?.states ?? [];
  const next = spellDeviationService.grant(states, sourceKeyValue, strength);
  if (!next) {
    return;
  }
  const index = spellDeviationService.boundIndex(states, sourceKeyValue);
  const overlay =
    index >= 0
      ? await getGameApi().replaceCombatState(props.gameId, casterKey, index, next)
      : await getGameApi().addCombatState(props.gameId, casterKey, next);
  overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, overlay);
  emit('overlay-changed');
}

async function persistDamage(key: CombatEntityKey, result: ApplyAttackDamageResult): Promise<void> {
  await writeAccumulatedDamage(key, result.remainingHpDamage);
  await applyCombatState(key, EXHAUSTION_STATE_CODE, result.exhaustion);
  await applyCombatState(key, WOUND_STATE_CODE, (result.wound ?? 0) + (result.cuttingWound ?? 0));
  await applyCombatState(key, STUNNED_STATE_CODE, result.stun ?? 0);
  await applyCombatState(key, SHOCK_STATE_CODE, result.shock ?? 0);
  emit('overlay-changed');
}

async function applyCombatState(key: CombatEntityKey, code: string, amount: number): Promise<void> {
  if (amount <= 0) {
    return;
  }
  const rule = props.rules.find((item) => item.code === code && item.type === 'state');
  if (!rule) {
    return;
  }
  const independent = (rule.spec as StateSpec | undefined)?.aggregation === 'independent';
  const version = combatCardModelService.combatCardModel(
    key,
    props.characters,
    props.npcs,
    props.canEdit,
    props.currentUserId,
    overlays.value.find((item) => item.entityKey === key) ?? null,
  ).effectiveVersion;
  const states = version?.states ?? [];
  const index = states.findIndex((state) => state.stateRuleCode === rule.code);
  const addedWound = code === WOUND_STATE_CODE ? woundInstanceService.addWound(amount) : null;
  if (code === WOUND_STATE_CODE && !addedWound) return;
  const overlay = addedWound
    ? await getGameApi().addCombatState(props.gameId, key, addedWound)
    : !independent && index >= 0
      ? await getGameApi().setCombatStateValue(props.gameId, key, index, (states[index]?.value ?? 0) + amount)
      : await getGameApi().addCombatState(props.gameId, key, {
          stateRuleCode: rule.code,
          value: amount,
        } as CharacterStateValue);
  overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, overlay);
}

async function writeAccumulatedDamage(key: CombatEntityKey, amount: number): Promise<void> {
  const rule = props.rules.find((item) => item.code === ACCUMULATED_DAMAGE_STATE_CODE && item.type === 'state');
  if (!rule) {
    return;
  }
  const version = combatCardModelService.combatCardModel(
    key,
    props.characters,
    props.npcs,
    props.canEdit,
    props.currentUserId,
    overlays.value.find((item) => item.entityKey === key) ?? null,
  ).effectiveVersion;
  if (!version) {
    return;
  }
  const index = version.states.findIndex((state) => state.stateRuleCode === rule.code);
  if (amount <= 0) {
    if (index >= 0) {
      const overlay = await getGameApi().removeCombatState(props.gameId, key, index);
      overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, overlay);
    }

    return;
  }
  const state: CharacterStateValue = { stateRuleCode: rule.code, dimensionalValue: { base: amount, size: 0 } };
  const overlay =
    index >= 0
      ? await getGameApi().replaceCombatState(props.gameId, key, index, state)
      : await getGameApi().addCombatState(props.gameId, key, state);
  overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, overlay);
}

async function resolveTargetedCast(
  outcome: SpellCastExecutionResult,
  effectKey: CombatEntityKey | null,
): Promise<void> {
  const chatId = props.chatId;
  if (chatId !== null) {
    combatThread.beginAttack();
  }
  try {
    if (chatId !== null) {
      await announceTargetedCast(outcome, effectKey, chatId);
    }
    await runDeviationAftermath(outcome, chatId);
    if (effectKey && outcome.spellApply) {
      await persistDamage(effectKey, outcome.spellApply);
      if (chatId !== null) {
        await announceSpellApply(outcome, effectKey, chatId);
      }
      await runChainHops(outcome, effectKey);
    }
  } finally {
    if (chatId !== null) {
      combatThread.endAttack();
    }
  }
}

async function announceTargetedCast(
  outcome: SpellCastExecutionResult,
  effectKey: CombatEntityKey | null,
  chatId: number,
): Promise<void> {
  const casterKey = resolvedCasterKey.value;
  const casterName = casterModel.value?.name ?? '';
  if (!casterKey) {
    return;
  }
  const spellRule = findRuleByRef(props.rules, spellCode.value);
  const speakerRef = speaker();
  await sendChat(
    formatSpellCastBeginMessage({
      casterKey,
      casterName,
      spellRuleCode: spellCode.value,
      spellName: spellRule?.name ?? spellCode.value,
      spellOd: actionOdCost(spellSpec.value?.action_components),
      touchActionCode: null,
      touchActionName: '',
      touchOd: 0,
      spentOd: outcome.spentAp,
      targetKey: effectKey,
      targetName: effectKey ? nameOf(effectKey) : null,
      appliedUpgradeCodes: appliedUpgradeCodes.value,
      rules: props.rules,
    }),
    [],
    chatId,
    speakerRef,
  );
  const castFailed = outcome.autoFail || Boolean(outcome.cast?.needsCheck && !outcome.cast.roll?.check?.passed);
  if (outcome.cast?.needsCheck) {
    await sendChat(
      formatSpellCastCheckMessage({
        casterKey,
        casterName,
        characteristicName: checkCharacteristic.value?.name ?? selectedPath.value?.name ?? 'Сотворение',
      }),
      [],
      chatId,
      speakerRef,
    );
  }
  if (outcome.cast?.roll) {
    await sendChat('', [{ type: ROLL_ATTACHMENT_TYPE, payload: outcome.cast.roll }], chatId, speakerRef);
  }
  const outcomeText = formatSpellCastOutcomeMessage({
    milk: outcome.milk,
    castFailed,
    casterKey,
    casterName,
    spellRuleCode: spellCode.value,
    spellName: spellRule?.name ?? spellCode.value,
    spentOd: outcome.spentAp,
    rules: props.rules,
  });
  await sendChat(outcomeText, [], chatId, speakerRef);
}

async function announceSpellApply(
  outcome: SpellCastExecutionResult,
  effectKey: CombatEntityKey,
  chatId: number,
  effectRuleCode?: string,
): Promise<void> {
  if (!outcome.spellApply) {
    return;
  }
  const ruleCode = effectRuleCode ?? spellCode.value;
  const spellRule = findRuleByRef(props.rules, ruleCode);
  const typeCode =
    effectRuleCode === ARCANE_DAMAGE_TYPE_CODE
      ? ARCANE_DAMAGE_TYPE_CODE
      : (spellSpec.value?.spell.damage?.damage_type_code ?? null);
  const defenderOverview = overviewOf(effectKey);
  const speakerRef = speaker();
  await sendChat(
    formatSpellEffectMessage({
      spellRuleCode: ruleCode,
      spellName: spellRule?.name ?? ruleCode,
      defenderKey: effectKey,
      defenderName: nameOf(effectKey),
      exhaustion: outcome.spellApply.exhaustion,
      wound: (outcome.spellApply.wound ?? 0) + (outcome.spellApply.cuttingWound ?? 0),
      raw: outcome.spellApply.raw,
      rules: props.rules,
    }),
    [
      {
        type: ATTACK_CALC_ATTACHMENT_TYPE,
        payload: buildAttackCalcPayload({
          weaponDamage: outcome.spellDamage ?? { base: outcome.spellApply.raw, size: 0 },
          damageTypeCode: typeCode,
          rules: props.rules,
          sr: outcome.spellSr ?? 0,
          endurance: defenderOverview
            ? attackDamageService.enduranceValueOf(defenderOverview, props.rules)
            : { base: 1, size: 0 },
          result: outcome.spellApply,
          defenseIgnored: false,
          heading: spellRule?.name ?? 'Заклинание',
        }),
      },
    ],
    chatId,
    speakerRef,
  );
  const totalExhaustion = outcome.spellApply.exhaustion;
  if (totalExhaustion > 0) {
    const afterHit = versionOf(effectKey);
    if (afterHit) {
      const exhaustion = await exhaustionCheckService.applyExhaustionCheck({
        version: afterHit,
        overlay: overlays.value.find((item) => item.entityKey === effectKey) ?? null,
        rules: props.rules,
        mechanics: props.mechanics,
        gameId: props.gameId,
        targetKey: effectKey,
        targetName: nameOf(effectKey),
        chatId,
        speaker: speaker(),
        change: 'increase',
        sendMessage: (content, attachments, nextChatId, nextSpeaker) =>
          sendChat(content, attachments, nextChatId, nextSpeaker),
        askTokenSpend,
      });
      if (exhaustion.overlay) {
        overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, exhaustion.overlay);
        emit('overlay-changed');
      }
    }
  }
  const planned = injuryPackageService.planFromLayers(
    [
      {
        hpDamage: outcome.spellApply.hpDamage,
        remainingSr: outcome.spellApply.appliedSr,
        damageTypeCode: typeCode,
        cuttingWound: outcome.spellApply.cuttingWound,
        woundFromHit: outcome.spellApply.wound,
      },
    ],
    defenderOverview ? attackDamageService.enduranceOf(defenderOverview, props.rules) : 1,
    injuryCheckService.overlayStateTotal(versionOf(effectKey), props.rules, EXHAUSTION_STATE_CODE),
    effectKey,
  );
  const injuryPreview = injuryPackageService.describePlan(planned, props.rules);
  if (injuryPreview) {
    await sendChat(injuryPreview, [], chatId, speakerRef);
  }
  for (const input of planned) {
    const applied = await injuryCheckService.applyInjuryCheck({
      input,
      rules: props.rules,
      mechanics: props.mechanics,
      gameId: props.gameId,
      targetKey: effectKey,
      targetName: nameOf(effectKey),
      chatId,
      speaker: speaker(),
      skipIfNoRoll: true,
      targetVersion: versionOf(effectKey) ?? undefined,
      sendMessage: (content, attachments, nextChatId, nextSpeaker) =>
        sendChat(content, attachments, nextChatId, nextSpeaker),
    });
    if (applied.overlay) {
      overlays.value = combatOverlayService.replaceCombatOverlay(overlays.value, applied.overlay);
      emit('overlay-changed');
    }
  }
}

async function persistGeneratorIfNeeded(outcome: SpellCastExecutionResult): Promise<void> {
  const spec = spellSpec.value?.spell;
  const casterKey = resolvedCasterKey.value;
  const castOk = !outcome.autoFail && (!outcome.cast?.needsCheck || Boolean(outcome.cast.roll?.check?.passed));
  if (!spec || spec.duration.type !== 'sustained' || !castOk || !casterKey || !sourceKey.value) {
    return;
  }
  const initiative = await getGameApi().getInitiative(props.gameId);
  const created = activeSpellService.createSustained({
    gameId: props.gameId,
    casterKey,
    spellCode: spellCode.value,
    sourceKey: sourceKey.value,
    pathCode: pathCode.value || null,
    usedPower: usedPower.value,
    requiredPower: spellCastDifficultyService.resolveSpellValue(spec.power, parameterValues.value),
    parameterValues: parameterValues.value,
    appliedUpgradeCodes: appliedUpgradeCodes.value,
    startedRound: initiative.round,
    startedParticipantId: casterKey,
  });
  await getGameApi().upsertActiveSpell(props.gameId, created);
  activeSpells.value = await getGameApi().getActiveSpells(props.gameId);
  await persistChargeGrant(casterKey, created);
  if (props.chatId === null) {
    return;
  }
  const spellRule = findRuleByRef(props.rules, spellCode.value);
  await sendChat(
    formatSustainBeginMessage({
      casterKey,
      casterName: casterModel.value?.name ?? '',
      spellRuleCode: spellCode.value,
      spellName: spellRule?.name ?? spellCode.value,
      rules: props.rules,
    }),
    [],
    props.chatId,
    speaker(),
  );
}

function waitBurstTargets(): Promise<SpellBurstTarget[]> {
  burstOpen.value = true;

  return new Promise((resolve) => {
    burstWait = resolve;
  });
}

function resolveBurst(targets: SpellBurstTarget[]): void {
  burstOpen.value = false;
  burstWait?.(targets);
  burstWait = null;
}

async function runDeviationAftermath(outcome: SpellCastExecutionResult, chatId: number | null): Promise<void> {
  if (!spellDeviationService.isFailedCastCheck(outcome.cast)) {
    return;
  }
  const deviation = spellDeviationService.roll(Math.random, resolvedCasterKey.value ?? undefined);
  if (chatId !== null) {
    await sendChat('', [{ type: ROLL_ATTACHMENT_TYPE, payload: deviation.roll }], chatId, speaker());
    await sendChat(spellDeviationService.formatOutcomeMessage(deviation), [], chatId, speaker());
  }
  if (deviation.hasEffect) {
    await persistCoreDeviation(resolvedCasterKey.value, sourceKey.value, deviation.strength);
  }
  if (!deviation.isDoubles) {
    return;
  }
  if (chatId !== null) {
    const burstText = spellDeviationService.formatBurstBeginMessage(deviation);
    if (burstText) {
      await sendChat(burstText, [], chatId, speaker());
    }
  }
  const targets = await waitBurstTargets();
  for (const target of targets) {
    await applyArcaneBurst(outcome, target, deviation, chatId);
  }
}

async function applyArcaneBurst(
  outcome: SpellCastExecutionResult,
  target: SpellBurstTarget,
  deviation: SpellDeviationOutcome,
  chatId: number | null,
): Promise<void> {
  const version = versionOf(target.key);
  const grant = version
    ? spellCastOptionsService.targetResistanceAmount(version, props.rules, ARCANE_DAMAGE_TYPE_CODE, {})
    : 0;
  const amount = spellDeviationService.explosionAmount(
    usedPower.value,
    target.distanceIpari,
    deviation.dieDigit,
    grant,
  );
  const weapon = spellDeviationService.explosionWeaponDamage(amount);
  const applied = spellCastExecutionService.applySpellDamage(
    weapon,
    1,
    ARCANE_DAMAGE_TYPE_CODE,
    overviewOf(target.key),
    props.rules,
    props.mechanics,
  );
  await persistDamage(target.key, applied);
  if (chatId === null) {
    return;
  }
  await announceSpellApply(
    { ...outcome, spellApply: applied, spellDamage: weapon },
    target.key,
    chatId,
    ARCANE_DAMAGE_TYPE_CODE,
  );
}

function waitHopChoice(): Promise<{ key: CombatEntityKey; distanceIpari: number } | null> {
  hopOpen.value = true;

  return new Promise((resolve) => {
    hopWait = resolve;
  });
}

function resolveHop(key: CombatEntityKey, distanceIpari: number): void {
  hopOpen.value = false;
  hopWait?.({ key, distanceIpari });
  hopWait = null;
}

function stopHop(): void {
  hopOpen.value = false;
  hopWait?.(null);
  hopWait = null;
}

async function runChainHops(outcome: SpellCastExecutionResult, firstKey: CombatEntityKey): Promise<void> {
  const selected = spellCastUpgradeService.selectedOf(upgradeOptions.value, appliedUpgradeCodes.value);
  const chainOption = selected.find((option) => option.upgrade.chain);
  const chain = chainOption?.upgrade.chain;
  if (!chain || !chainOption || !outcome.spellApply || outcome.spellApply.raw <= 0 || !outcome.spellDamage) {
    return;
  }
  const spec = spellSpec.value;
  const typeCode = spec?.spell.damage?.damage_type_code ?? null;
  const sr = outcome.spellSr ?? 0;
  const hopRuleCode = chainOption.ruleCode;
  let lastKey = firstKey;
  hopLastKey.value = lastKey;
  const alreadyHit = [firstKey];
  let lastAmount = outcome.spellDamage;
  let lastRaw = outcome.spellApply.raw;
  while (true) {
    const choice = await waitHopChoice();
    if (!choice) {
      return;
    }
    if (
      !spellChainHopService.canHop({
        raw: lastRaw,
        nextKey: choice.key,
        lastKey,
        alreadyHit,
        sameTarget: chain.same_target,
      })
    ) {
      hopOpen.value = true;
      continue;
    }
    const nextAmount = spellChainHopService.nextAmount(
      lastAmount,
      chain,
      choice.distanceIpari,
      spec?.spell.damage?.falloff,
    );
    if (!nextAmount) {
      if (props.chatId !== null) {
        await sendChat(
          formatChainBreakMessage({
            spellRuleCode: hopRuleCode,
            spellName: findRuleByRef(props.rules, hopRuleCode)?.name ?? hopRuleCode,
            rules: props.rules,
          }),
          [],
          props.chatId,
          speaker(),
        );
      }

      return;
    }
    const applied = spellCastExecutionService.applySpellDamage(
      nextAmount,
      sr,
      typeCode,
      overviewOf(choice.key),
      props.rules,
      props.mechanics,
    );
    await persistDamage(choice.key, applied);
    if (props.chatId !== null) {
      await announceSpellApply(
        { ...outcome, spellApply: applied, spellDamage: nextAmount },
        choice.key,
        props.chatId,
        hopRuleCode,
      );
    }
    if (applied.raw <= 0) {
      if (props.chatId !== null) {
        await sendChat(
          formatChainBreakMessage({
            spellRuleCode: hopRuleCode,
            spellName: findRuleByRef(props.rules, hopRuleCode)?.name ?? hopRuleCode,
            rules: props.rules,
          }),
          [],
          props.chatId,
          speaker(),
        );
      }

      return;
    }
    lastKey = choice.key;
    hopLastKey.value = lastKey;
    alreadyHit.push(choice.key);
    lastAmount = nextAmount;
    lastRaw = applied.raw;
  }
}
</script>

<template>
  <v-dialog :model-value="open" max-width="520" @update:model-value="emit('update:open', $event)">
    <v-card>
      <v-card-title class="text-body-1 d-flex align-center justify-space-between ga-2">
        <span>Сотворение</span>
        <v-btn
          :variant="showCastOptions ? 'tonal' : 'text'"
          size="small"
          class="text-none"
          :title="showCastOptions ? 'Только нужные поля' : 'Показать все поля'"
          @click="toggleCastOptions"
        >
          <v-icon size="18" start>mdi-flash</v-icon>
          {{ powerStatName }}
          <span class="text-medium-emphasis ms-1">{{ usedPowerLabel }}</span>
          <v-icon size="18" class="ms-3">mdi-head-cog-outline</v-icon>
          {{ controlStatName }}
          <span class="text-medium-emphasis ms-1">{{ controlLabel }}</span>
        </v-btn>
      </v-card-title>
      <v-card-text>
        <v-alert v-if="chargeSustain" type="info" variant="tonal" density="compact" class="mb-3">
          Каст через электрозаряд
        </v-alert>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
        <SpellCastUpgradeList v-model="appliedUpgradeCodes" :options="upgradeOptions" class="mb-3">
          <v-autocomplete
            v-model="spellCode"
            :items="spellItems"
            item-title="name"
            item-value="ruleCode"
            label="Заклинание"
            density="compact"
            hide-details
            single-line
            auto-select-first
          >
            <template #item="{ props: itemProps, item }">
              <v-list-item v-bind="itemProps">
                <template #append>
                  <SpellCastSpellRequirementMarks
                    :power-label="item.raw.powerLabel"
                    :control-label="item.raw.controlLabel"
                  />
                </template>
              </v-list-item>
            </template>
            <template #append-inner>
              <SpellCastSpellRequirementMarks
                v-if="selectedSpellItem"
                :power-label="selectedSpellItem.powerLabel"
                :control-label="selectedSpellItem.controlLabel"
              />
            </template>
          </v-autocomplete>
        </SpellCastUpgradeList>
        <div v-if="showCastOptions" class="d-flex ga-2 mb-3">
          <v-select
            v-model="sourceKey"
            :items="sourceItems"
            label="Источник"
            density="compact"
            hide-details
            class="flex-grow-1"
            :disabled="Boolean(chargeSustain)"
          />
          <v-select
            v-model="pathCode"
            :items="pathItems"
            label="Путь"
            density="compact"
            hide-details
            class="flex-grow-1"
            :disabled="Boolean(chargeSustain)"
          />
        </div>
        <DimensionalNumberInput
          v-if="showCastOptions"
          :model-value="usedPower"
          label="Используемая мощь"
          :min="CHARACTERISTIC_BASE_RANGE.min"
          :max="CHARACTERISTIC_BASE_RANGE.max"
          class="mb-3"
          @update:model-value="setUsedPower"
        />
        <DimensionalNumberInput
          v-if="powerIsParameter"
          v-model="parameterPower"
          label="Мощь заклинания"
          :min="CHARACTERISTIC_BASE_RANGE.min"
          :max="CHARACTERISTIC_BASE_RANGE.max"
          class="mb-3"
        />
        <div v-else-if="fixedSpellPower" class="text-caption text-medium-emphasis mb-3">
          Мощь заклинания: {{ fixedSpellPowerLabel }}
        </div>
        <v-checkbox
          v-if="showCastOptions"
          v-model="hasTarget"
          label="Направлен на цель"
          hide-details
          density="compact"
        />
        <CombatEntitySelect
          v-if="hasTarget"
          v-model="targetKey"
          label="Цель заклинания"
          :characters="characters"
          :npcs="npcs"
          :initiative-keys="initiativeKeys"
          :exclude="resolvedCasterKey ? [resolvedCasterKey] : []"
          class="mt-2"
        />
        <template v-if="needsTouchAttack">
          <v-select
            v-model="touchActionCode"
            :items="touchActions"
            label="Атака касания"
            density="compact"
            hide-details
            class="mt-3"
          />
          <v-select
            v-model="touchProfileItem"
            :items="strikeProfileItems"
            label="Оружие касания"
            density="compact"
            hide-details
            class="mt-2"
          />
          <CombatEntitySelect
            v-model="touchTargetKey"
            label="Цель касания"
            :characters="characters"
            :npcs="npcs"
            :initiative-keys="initiativeKeys"
            :exclude="resolvedCasterKey ? [resolvedCasterKey] : []"
            :leading="[{ title: 'Воздух', value: '' }]"
            class="mt-2"
          />
        </template>
        <div class="text-caption text-medium-emphasis mt-2">Стоимость: {{ actionPointCost }} ОД</div>
        <div v-if="needsSpellTarget && !hasTarget" class="text-caption text-warning mt-1">
          Без цели заклинания сотворение автоматически провалится
        </div>
        <div class="text-body-2 mt-4">{{ castCheckLine }}</div>
        <ConcentrationTokenOption
          v-model="spendConcentration"
          :version="casterVersion"
          :overlay="overlays.find((item) => item.entityKey === resolvedCasterKey) ?? null"
          :rules="rules"
          :check-code="selectedPath?.checkCode ?? ''"
          :characteristic-code="checkCharacteristicCode"
        />
        <div v-if="lastSkip" class="text-caption mt-1">Бросок не выполнялся</div>
        <div v-if="lastMilk" class="text-caption mt-1">Эффект в молоко</div>
        <div v-if="lastAutoFail" class="text-caption mt-1">Автопровал сотворения</div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="close">Закрыть</v-btn>
        <v-btn color="primary" :loading="busy" :disabled="!canEdit || !spellCode || !preview" @click="runCast">
          Сотворить
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <SpellChainHopDialog
    :open="hopOpen"
    :characters="characters"
    :npcs="npcs"
    :initiative-keys="initiativeKeys"
    :last-key="hopLastKey"
    :exclude="resolvedCasterKey ? [resolvedCasterKey] : []"
    @update:open="hopOpen = $event"
    @hop="resolveHop"
    @stop="stopHop"
  />
  <SpellArcaneBurstDialog
    :open="burstOpen"
    :characters="characters"
    :npcs="npcs"
    :initiative-keys="initiativeKeys"
    :caster-key="resolvedCasterKey"
    :caster-name="casterModel?.name ?? ''"
    @update:open="burstOpen = $event"
    @confirm="resolveBurst"
  />
</template>
