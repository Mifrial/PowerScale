import { computed, onMounted, ref, type ComputedRef, type Ref } from 'vue';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
import { characterPatchService } from '@/modules/Roleplay/Character/Service/Instance/characterPatchService';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';
import { getCharacterApi } from '@/modules/Roleplay/Character/init';
import { characterSheetValidationService } from '@/modules/Roleplay/Character/Service/Instance/characterSheetValidationService';
import { getMechanicApi } from '@/modules/Roleplay/Mechanic/init';
import { useKeywords } from '@/modules/Roleplay/Keyword/init';
import { CharacterApiError } from '@/modules/Roleplay/Character/Service/CharacterApiError';

/**
 * Черновик и быстрое сохранение с карточки персонажа: экип (и дальше другие правки)
 * идут в тот же `character:${id}`, что standalone-редактор.
 */
export function useCharacterCardDraft(
  detail: ComputedRef<CharacterDetail | null>,
  rules: Ref<Rule[]>,
  canEdit: ComputedRef<boolean>,
  signal: ComputedRef<AbortSignal>,
) {
  const draftStore = useCharacterDraftStore();
  const characterStore = useCharacterStore();
  const { keywords, error: keywordsError, fetchTags } = useKeywords();
  const mechanics = ref<Mechanic[]>([]);
  const saving = ref(false);
  const saveError = ref<string | null>(null);
  const saveConflict = ref(false);
  const catalogError = ref<string | null>(null);
  const pendingPatch = ref<CharacterPatch | null>(null);
  const pendingPatchCharacterId = ref<number | null>(null);
  const pendingPatchDraftKey = ref<string | null>(null);

  const draftKey = computed(() => {
    const id = detail.value?.character.id;
    if (id == null) return null;

    return `character:${id}`;
  });

  const draft = computed(() => draftStore.draftOf(draftKey.value));

  const config = computed(() => {
    if (draft.value) return draft.value.config;
    const version = detail.value?.version;
    if (!version) return { osTotal: null, orTotal: null, moneyBudget: null };

    return {
      osTotal: version.budgets?.osTotal ?? null,
      orTotal: version.points.orTotal ?? null,
      moneyBudget: version.budgets?.moneyBudget ?? null,
    };
  });

  const build = computed(() => {
    const current = detail.value;
    if (draft.value) return draft.value.build;
    if (!current || rules.value.length === 0) return null;

    return characterBuildService.fromVersion(current.version, current.character.spaceId, rules.value);
  });

  const model = computed(() => {
    if (!build.value || rules.value.length === 0) return null;

    return characterEditorService.build(build.value, rules.value, config.value, keywords.value, mechanics.value);
  });

  const displayVersion = computed<CharacterVersion | null>(() => {
    const current = detail.value;
    if (!current) return null;
    if (!draft.value?.dirty || !build.value || rules.value.length === 0) return current.version;

    return characterEditorService.toVersion(build.value, rules.value, config.value, keywords.value, mechanics.value);
  });

  const validationIssues = computed(() =>
    characterSheetValidationService.characterSheetValidationIssues(draft.value?.build, model.value, true),
  );

  function ensureDraft(): void {
    if (!canEdit.value) return;
    const current = detail.value;
    const key = draftKey.value;
    if (!current || key === null || draftStore.draftOf(key)) return;
    const version = current.version;
    const fromRules = rules.value;
    const nextBuild = characterBuildService.fromVersion(version, current.character.spaceId, fromRules);
    const baseline = {
      inventory: nextBuild.inventory.map((item) => ({ ...item })),
      money: nextBuild.money,
    };
    draftStore.initDraft(
      key,
      nextBuild,
      {
        osTotal: version.budgets?.osTotal ?? null,
        orTotal: version.points.orTotal ?? null,
        moneyBudget: version.budgets?.moneyBudget ?? null,
      },
      baseline,
    );
  }

  async function executePatch(characterId: number, patch: CharacterPatch, key: string): Promise<void> {
    const validation = await getCharacterApi().validateCharacter({ mode: 'update', characterId, patch }, signal.value);
    if (!validation.valid) {
      throw new CharacterApiError('CHARACTER_VALIDATION_FAILED', 'Персонаж не прошёл проверку', {
        kind: 'validation',
        problems: validation.problems,
      });
    }
    const updated = await getCharacterApi().updateCharacter(characterId, { patch }, signal.value);
    characterStore.applyDetail(updated);
    draftStore.discard(key);
    pendingPatch.value = null;
    pendingPatchCharacterId.value = null;
    pendingPatchDraftKey.value = null;
  }

  async function save(): Promise<void> {
    const current = detail.value;
    const key = draftKey.value;
    const entry = draftStore.draftOf(key);
    if (!current || key === null || !entry || !model.value || saving.value) return;
    saveError.value = null;
    saveConflict.value = false;
    if (catalogError.value) {
      saveError.value = catalogError.value;

      return;
    }
    const issues = characterSheetValidationService.characterSheetValidationIssues(entry.build, model.value, true);
    if (issues.length > 0) {
      saveError.value = `Нельзя сохранить: ${issues.join('; ')}`;

      return;
    }
    const version = characterEditorService.toVersion(
      entry.build,
      rules.value,
      entry.config,
      keywords.value,
      mechanics.value,
    );
    const patch = characterPatchService.createPatch(
      current.version,
      version,
      crypto.randomUUID(),
      current.actualVersion,
    );
    pendingPatch.value = patch;
    pendingPatchCharacterId.value = current.character.id;
    pendingPatchDraftKey.value = key;
    saving.value = true;
    try {
      await executePatch(current.character.id, patch, key);
    } catch (e) {
      saveError.value = e instanceof Error ? e.message : 'Не удалось сохранить персонажа';
      saveConflict.value = e instanceof CharacterApiError && e.code === 'CHARACTER_ACTUAL_CONFLICT';
    } finally {
      saving.value = false;
    }
  }

  async function retrySave(): Promise<void> {
    const current = detail.value;
    const key = draftKey.value;
    const patch = pendingPatch.value;
    if (
      !current ||
      key === null ||
      !patch ||
      saving.value ||
      pendingPatchCharacterId.value !== current.character.id ||
      pendingPatchDraftKey.value !== key ||
      saveConflict.value
    ) {
      return;
    }
    saveError.value = null;
    saving.value = true;
    try {
      await executePatch(current.character.id, patch, key);
    } catch (e) {
      saveError.value = e instanceof Error ? e.message : 'Не удалось сохранить персонажа';
      saveConflict.value = e instanceof CharacterApiError && e.code === 'CHARACTER_ACTUAL_CONFLICT';
    } finally {
      saving.value = false;
    }
  }

  async function reloadAfterConflict(): Promise<void> {
    const current = detail.value;
    const key = draftKey.value;
    if (!current || key === null || saving.value) return;
    saveError.value = null;
    saveConflict.value = false;
    pendingPatch.value = null;
    pendingPatchCharacterId.value = null;
    pendingPatchDraftKey.value = null;
    draftStore.discard(key);
    const refreshed = await characterStore.fetchCharacter(current.character.id, signal.value);
    if (!refreshed) saveError.value = 'Не удалось загрузить актуальное состояние персонажа';
  }

  async function loadCatalog(): Promise<void> {
    catalogError.value = null;
    try {
      const pending: Promise<void>[] = [];
      if (keywords.value.length === 0 || keywordsError.value) {
        pending.push(fetchTags(signal.value));
      }
      pending.push(
        getMechanicApi()
          .getMechanics(signal.value)
          .then((list) => {
            mechanics.value = list;
          }),
      );
      await Promise.all(pending);
      if (keywordsError.value) catalogError.value = keywordsError.value;
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      mechanics.value = [];
      catalogError.value = e instanceof Error ? e.message : 'Не удалось загрузить справочники редактора';
    }
  }

  onMounted(() => {
    void loadCatalog();
  });

  return {
    draftKey,
    draft,
    build,
    model,
    displayVersion,
    validationIssues,
    saving,
    saveError,
    saveConflict,
    catalogError,
    keywords,
    ensureDraft,
    save,
    retrySave,
    reloadAfterConflict,
    retryCatalog: loadCatalog,
  };
}
