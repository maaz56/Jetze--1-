import { createI18n } from 'vue-i18n';
import en from '../locales/en.json';
import ar from '../locales/ar.json';

const messages = {
  en,
  ar
};

// Get the stored locale from localStorage or default to 'en'
const storedLocale = localStorage.getItem('locale') || 'en';

const i18n = createI18n({
  legacy: false,
  locale: storedLocale,
  messages,
});

export default i18n;
