import type { Component } from 'vue';

/**
 * Расширение карточки персонажа (паттерн плагинов): модуль-донор (например Game)
 * регистрирует компонент через `registerCharacterCardExtension`; страница персонажа
 * рендерит его, передавая `character-id`. Хост не знает семантику расширения.
 */
export interface CharacterCardExtension {
  id: string;
  component: Component;
}
