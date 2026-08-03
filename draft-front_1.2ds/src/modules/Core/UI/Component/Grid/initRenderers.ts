import { registerRenderer } from '@/modules/Core/UI/Component/Grid/cells/registry';
import StringCell from '@/modules/Core/UI/Component/Grid/cells/StringCell.vue';
import NumberCell from '@/modules/Core/UI/Component/Grid/cells/NumberCell.vue';
import DateCell from '@/modules/Core/UI/Component/Grid/cells/DateCell.vue';
import BooleanCell from '@/modules/Core/UI/Component/Grid/cells/BooleanCell.vue';

export function initBaseRenderers() {
  registerRenderer('string', StringCell);
  registerRenderer('number', NumberCell);
  registerRenderer('date', DateCell);
  registerRenderer('boolean', BooleanCell);
}
