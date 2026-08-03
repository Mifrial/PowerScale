<script setup lang="ts">
import { ref, computed } from 'vue';
import type { UserMacro } from '@/modules/Roleplay/Game/Dto/UserMacro';
import type { MacroRollSpec } from '@/modules/Roleplay/Game/Dto/MacroRollSpec';
import type { RollForm } from '@/modules/Roleplay/Game/Dto/RollForm';
import type { CreateMacroData } from '@/modules/Roleplay/Game/Interface/IMacroApi';
import { useMacrosStore } from '@/modules/Roleplay/Game/Store/macros';
import { rollService } from '@/modules/Roleplay/Game/Service/RollService';
import MacroRollEditor from '@/modules/Roleplay/Game/Component/Macros/MacroRollEditor.vue';

const props = defineProps<{ macro: UserMacro | null }>();
const emit = defineEmits<{ close: [] }>();

const store = useMacrosStore();

const id = ref<number | undefined>(props.macro?.id);
const name = ref(props.macro?.name ?? '');
const textTemplate = ref(props.macro?.textTemplate ?? '');
const rolls = ref<RollForm[]>(props.macro ? props.macro.rolls.map(toRollForm) : [createEmptyRoll()]);
const saving = ref(false);

const formValid = computed(() => {
  if (!name.value.trim()) return false;
  const hasContent = textTemplate.value.trim().length > 0 || rolls.value.length > 0;
  if (!hasContent) return false;

  return rolls.value.every((roll) => rollService.validateRollSpec(roll));
});

function createEmptyRoll(): RollForm {
  return { diceCount: 5, dieFaces: 6, efficiency: 3, dieSize: 0, adv: 0, rollLabel: '', variableAdvantages: false };
}

function toRollForm(spec: MacroRollSpec): RollForm {
  const parsed = spec.rollFormula ? rollService.parseRollFormula(spec.rollFormula) : null;

  return {
    diceCount: parsed?.diceCount ?? 5,
    dieFaces: parsed?.dieFaces ?? 6,
    efficiency: spec.efficiency,
    dieSize: spec.dieSize ?? 0,
    adv: spec.adv ?? 0,
    rollLabel: spec.rollLabel ?? '',
    variableAdvantages: spec.variableAdvantages ?? false,
  };
}

function toMacroRollSpec(roll: RollForm): MacroRollSpec {
  return {
    rollFormula: `${roll.diceCount}d${roll.dieFaces}`,
    efficiency: Number(roll.efficiency),
    adv: Number(roll.adv),
    dieSize: Number(roll.dieSize),
    rollLabel: roll.rollLabel.trim() || undefined,
    variableAdvantages: roll.variableAdvantages,
  };
}

function addRoll() {
  rolls.value.push(createEmptyRoll());
}

function removeRoll(index: number) {
  rolls.value.splice(index, 1);
}

function close() {
  emit('close');
}

async function save() {
  if (!formValid.value) return;
  saving.value = true;
  try {
    const data: CreateMacroData = {
      name: name.value.trim(),
      textTemplate: textTemplate.value.trim(),
      rolls: rolls.value.map(toMacroRollSpec),
    };
    if (id.value != null) {
      await store.updateMacro(id.value, data);
    } else {
      await store.createMacro(data);
    }
    close();
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="macro-form mt-2">
    <v-text-field v-model="name" label="Название" density="compact" variant="outlined" hide-details class="mb-2" />
    <v-text-field
      v-model="textTemplate"
      label="Текст сообщения (опционально)"
      density="compact"
      variant="outlined"
      hide-details
      class="mb-2"
    />

    <div class="text-caption font-weight-medium mb-1">Броски</div>
    <MacroRollEditor v-for="(roll, ri) in rolls" :key="ri" v-model="rolls[ri]" :index="ri" @remove="removeRoll(ri)" />

    <v-btn variant="tonal" size="small" class="mb-2" @click="addRoll">
      <v-icon start size="small">mdi-plus</v-icon>Добавить бросок
    </v-btn>

    <div class="macro-preview mb-2">
      <span v-if="textTemplate.trim()" class="macro-preview-text">{{ textTemplate.trim() }}</span>
      <template v-for="(roll, ri) in rolls" :key="ri">
        <span class="macro-sep">+</span>
        <v-icon icon="mdi-dice-d6" size="14" />
        <span>{{ rollService.formatRollFormText(roll) }}</span>
      </template>
      <span v-if="!textTemplate.trim() && !rolls.length" class="text-caption text-medium-emphasis"
        >макрос пуст — задайте текст или добавьте бросок</span
      >
    </div>

    <div class="d-flex ga-2">
      <v-btn variant="tonal" color="primary" size="small" :loading="saving" :disabled="!formValid" @click="save">
        <v-icon start size="small">mdi-check</v-icon>Сохранить
      </v-btn>
      <v-btn variant="text" size="small" @click="close">Отмена</v-btn>
    </div>
  </div>
</template>

<style scoped>
.macro-sep {
  margin: 0 4px;
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
}
.macro-preview {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
  background: rgba(var(--v-theme-primaryLight), 0.5);
  border-radius: 16px;
  padding: 3px 12px;
  font-size: 12px;
}
.macro-preview-text {
  max-width: 240px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
