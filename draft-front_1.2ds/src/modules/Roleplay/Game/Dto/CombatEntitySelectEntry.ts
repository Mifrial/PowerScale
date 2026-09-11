/** Строка выпадающего списка персонажей/НПС (пункт Vuetify или разделитель). */
export interface CombatEntitySelectEntry {
  title: string;
  value?: string;
  type?: 'divider' | 'subheader';
  disabled?: boolean;
}
