/** Узел выбора в дереве (`TreeSelect`): значение, подпись и родитель. */
export interface TreeSelectItem {
  value: string | number;
  label: string;
  parentValue?: string | number | null;
}
