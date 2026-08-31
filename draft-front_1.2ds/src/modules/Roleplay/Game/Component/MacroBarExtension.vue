<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { UserMacro } from '@/modules/Roleplay/Game/Dto/UserMacro';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatToolbarContext } from '@/modules/Messages/Chat/Dto/ChatToolbarContext';
import { useMacrosStore } from '@/modules/Roleplay/Game/Store/macros';
import { rollService } from '@/modules/Roleplay/Game/Service/Instance/rollService';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { ROLL_ADV_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ADV_MAX';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';

const props = defineProps<ChatToolbarContext>();

const macrosStore = useMacrosStore();
const macros = computed(() => macrosStore.macros);

const advDialog = ref(false);
const advInput = ref(0);
const pendingMacro = ref<UserMacro | null>(null);

onMounted(() => {
  if (!props.disabled) macrosStore.fetchMacros();
});

function macroChipTitle(m: UserMacro): string {
  const parts: string[] = [];
  if (m.textTemplate) parts.push(m.textTemplate);
  for (const r of m.rolls) parts.push(rollService.formatRollSpecText(r));

  return parts.join(' | ');
}

function buildRollSpec(r: UserMacro['rolls'][number], adv: number): DiceRollSpec {
  const formula = rollService.parseRollFormula(r.rollFormula);

  return {
    diceCount: formula?.diceCount ?? 5,
    dieFaces: formula?.dieFaces ?? 6,
    dieSize: r.dieSize ?? 0,
    efficiency: r.efficiency,
    advantages: aggregateSourceDeltasService.advantageEntries(adv),
    label: r.rollLabel?.trim() || undefined,
  };
}

const pendingFlaggedCount = computed(() => pendingMacro.value?.rolls.filter((r) => r.variableAdvantages).length ?? 0);

function toAttachments(rolls: DiceRollSpec[]): ChatAttachment[] {
  return rolls.map((spec) => ({ type: ROLL_ATTACHMENT_TYPE, payload: spec }));
}

function sendMacro(m: UserMacro) {
  if (!m.rolls.length) {
    props.send(m.textTemplate ?? '', []);

    return;
  }
  const flagged = m.rolls.filter((r) => r.variableAdvantages);
  if (flagged.length) {
    pendingMacro.value = m;
    advInput.value = flagged[0].adv ?? 0;
    advDialog.value = true;

    return;
  }
  props.send(m.textTemplate ?? '', toAttachments(m.rolls.map((r) => buildRollSpec(r, r.adv ?? 0))));
}

function confirmAdv() {
  const m = pendingMacro.value;
  if (!m) return;
  const adv = Number.isFinite(advInput.value)
    ? Math.max(-ROLL_ADV_MAX, Math.min(ROLL_ADV_MAX, Math.round(advInput.value)))
    : 0;
  const rolls = m.rolls.map((r) => buildRollSpec(r, r.variableAdvantages ? adv : (r.adv ?? 0)));
  props.send(m.textTemplate ?? '', toAttachments(rolls));
  advDialog.value = false;
  pendingMacro.value = null;
}
</script>

<template>
  <div v-if="!disabled && macros.length" class="macro-bar">
    <v-icon icon="mdi-script-text" size="14" class="macro-bar-icon" />
    <div v-for="m in macros" :key="m.id" class="macro-chip" :title="macroChipTitle(m)" @click="sendMacro(m)">
      {{ m.name }}
    </div>

    <v-dialog v-model="advDialog" max-width="360">
      <v-card>
        <v-card-title>Преимущества</v-card-title>
        <v-card-subtitle v-if="pendingMacro" class="text-body-2">
          {{ pendingMacro.name }} · {{ pendingFlaggedCount }} броск{{ pendingFlaggedCount === 1 ? 'а' : 'ов' }}
        </v-card-subtitle>
        <v-card-text>
          <v-text-field
            v-model.number="advInput"
            label="Число преимуществ"
            type="number"
            :min="-ROLL_ADV_MAX"
            :max="ROLL_ADV_MAX"
            variant="outlined"
            hide-details
          />
          <div class="text-caption mt-1">отрицательное значение = помеха</div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="advDialog = false">Отмена</v-btn>
          <v-btn color="primary" @click="confirmAdv">Отправить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.macro-bar {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  padding: 4px 0;
}
.macro-bar-icon {
  opacity: 0.6;
}
.macro-chip {
  display: flex;
  align-items: center;
  gap: 4px;
  background: rgba(var(--v-theme-primary), 0.08);
  border: 1px solid rgba(var(--v-theme-primary), 0.25);
  border-radius: 14px;
  padding: 1px 10px;
  font-size: 12px;
  cursor: pointer;
  color: rgb(var(--v-theme-primary));
}
.macro-chip:hover {
  background: rgba(var(--v-theme-primary), 0.15);
}
</style>
