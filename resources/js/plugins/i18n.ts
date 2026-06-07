import { createI18n } from 'vue-i18n'
import { usePage } from '@inertiajs/vue3'

export function setupI18n() {
  const page = usePage()
  const locale = (page.props.locale as string) ?? 'en'
  const translations = (page.props.translations as Record<string, string>) ?? {}

  return createI18n({
    legacy: false,
    locale,
    fallbackLocale: 'en',
    messages: {
      [locale]: translations,
    },
  })
}