<script setup lang="ts">
import { computed } from 'vue'
import type { RollForm } from '@/modules/Roleplay/Game/Dto/RollForm'
import { rollService } from '@/modules/Roleplay/Game/Service/RollService'
import {
  ROLL_ADV_MAX,
  ROLL_DICE_COUNT_MAX,
  ROLL_DICE_COUNT_MIN,
  ROLL_DIE_FACES_MAX,
  ROLL_DIE_FACES_MIN,
  ROLL_DIE_SIZE_MAX,
  ROLL_EFFICIENCY_MAX,
  ROLL_EFFICIENCY_MIN,
} from '@/modules/Roleplay/Game/Constant/rollLimits'

defineProps<{ index: number }>()
const roll = defineModel<RollForm>({ required: true })
const emit = defineEmits<{ remove: [] }>()

const previewText = computed(() => rollService.formatRollFormText(roll.value))
</script>

<template>
  <div class="roll-editor mb-2">
    <div class="roll-editor-header">
      <span class="text-caption font-weight-medium">Бросок {{ index + 1 }}</span>
      <v-btn icon variant="text" size="x-small" aria-label="Удалить бросок" @click="emit('remove')">
        <v-icon size="16">mdi-close</v-icon>
      </v-btn>
    </div>

    <div class="d-flex align-center ga-2 mb-2">
      <v-text-field
        v-model.number="roll.diceCount"
        label="Кубы"
        type="number"
        :min="ROLL_DICE_COUNT_MIN"
        :max="ROLL_DICE_COUNT_MAX"
        density="compact"
        variant="outlined"
        hide-details
        class="macro-num"
      />
      <span class="text-caption">d</span>
      <v-text-field
        v-model.number="roll.dieFaces"
        label="Грань"
        type="number"
        :min="ROLL_DIE_FACES_MIN"
        :max="ROLL_DIE_FACES_MAX"
        density="compact"
        variant="outlined"
        hide-details
        class="macro-num"
      />
      <span class="text-caption">при</span>
      <v-text-field
        v-model.number="roll.efficiency"
        label="Эффективность"
        type="number"
        :min="ROLL_EFFICIENCY_MIN"
        :max="ROLL_EFFICIENCY_MAX"
        density="compact"
        variant="outlined"
        hide-details
        class="macro-num"
      />
    </div>

    <div class="d-flex align-center ga-2 mb-2">
      <v-text-field
        v-model.number="roll.adv"
        label="Преимущества"
        type="number"
        :min="-ROLL_ADV_MAX"
        :max="ROLL_ADV_MAX"
        density="compact"
        variant="outlined"
        hide-details
        class="macro-num"
      />
      <span class="text-caption">отрицательное = помеха</span>
      <v-text-field
        v-model.number="roll.dieSize"
        label="Размерность"
        type="number"
        :min="-ROLL_DIE_SIZE_MAX"
        :max="ROLL_DIE_SIZE_MAX"
        density="compact"
        variant="outlined"
        hide-details
        class="macro-num"
      />
    </div>

    <v-text-field
      v-model="roll.rollLabel"
      label="Подпись броска (опционально)"
      placeholder="например: 1 удар, уклонение"
      density="compact"
      variant="outlined"
      hide-details
      class="mb-1"
    />

    <v-checkbox
      v-model="roll.variableAdvantages"
      label="Переменные преимущества — спрашивать число при отправке"
      density="compact"
      hide-details
    />

    <div class="roll-preview">
      <v-icon icon="mdi-dice-d6" size="14" />
      <span>{{ previewText }}</span>
    </div>
  </div>
</template>

<style scoped>
.roll-editor {
  border: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
  border-radius: 8px;
  padding: 6px 8px;
}
.roll-editor-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.macro-num {
  max-width: 100px;
}
.roll-preview {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
  background: rgba(var(--v-theme-primaryLight), 0.5);
  border-radius: 16px;
  padding: 3px 12px;
  font-size: 12px;
  margin-top: 4px;
}
</style>
