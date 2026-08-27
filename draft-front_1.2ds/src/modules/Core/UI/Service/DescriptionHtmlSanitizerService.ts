import DOMPurify from 'dompurify';
import { DESCRIPTION_HTML_CONFIG } from '@/modules/Core/UI/Constant/Description/DESCRIPTION_HTML_CONFIG';

export class DescriptionHtmlSanitizerService {
  sanitize(description: string): string {
    return DOMPurify.sanitize(description, DESCRIPTION_HTML_CONFIG);
  }
}
