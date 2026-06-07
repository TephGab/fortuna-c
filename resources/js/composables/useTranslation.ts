import { usePage } from '@inertiajs/vue3';

export function useTranslation() {
    const page = usePage();

    const t = (key: string, params?: Record<string, any>): string => {
        const translations = (page.props.translations as Record<string, string>) || {};
        let text = translations[key] || key;

        if (params) {
            Object.entries(params).forEach(([param, value]) => {
                text = text.replace(`:${param}`, String(value));
            });
        }

        return text;
    };

    return { t };
}