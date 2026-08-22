import { createVuetify } from 'vuetify';
import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import 'roboto-fontface/css/roboto/roboto-fontface.css';
import { ru } from 'vuetify/locale';

// Компоненты/директивы НЕ регистрируем глобально — их подтягивает
// vite-plugin-vuetify ({ autoImport: true }) по использованию в SFC (tree-shaking).
const vuetify = createVuetify({
  locale: {
    locale: 'ru',
    messages: { ru },
  },
  icons: { defaultSet: 'mdi' },
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        dark: false,
        colors: {
          primary: '#1976D2',
          primaryLight: '#EFF5FC',
          primaryDark: '#0D47A1',
          scrollbar: '#D3E6F8',
          divider: '#E0E0E0',
          scrim: '#000000',
          secondary: '#757575',
          accent: '#82B1FF',
          error: '#FF5252',
          info: '#2196F3',
          success: '#4CAF50',
          warning: '#FFC107',
          background: '#FAFAFA',
          surface: '#FFFFFF',
          'surface-variant': '#E7E5E4',
          'on-surface': '#000000',
          'on-surface-variant': '#49454F',
        },
      },
    },
  },
  defaults: {
    VBtn: {
      rounded: 'lg',
      variant: 'flat',
    },
    VTextField: {
      variant: 'outlined',
      density: 'comfortable',
      hideDetails: 'auto',
    },
    VCard: {
      rounded: 'lg',
      elevation: 0,
    },
  },
});

export default vuetify;
