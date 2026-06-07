<template>
    <div class="relative">
        <button
            @click="isOpen = !isOpen"
            class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-all hover:border-gray-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
        >
            <Globe class="h-4 w-4" />
            <span>{{ currentLanguageName }}</span>
            <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': isOpen }" />
        </button>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-48 rounded-xl border border-gray-200 bg-white py-2 shadow-lg dark:border-gray-700 dark:bg-gray-800"
            >
                <button
                    v-for="(name, code) in availableLocales"
                    :key="code"
                    @click="switchLanguage(code)"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-sm transition-colors hover:bg-gray-50 dark:hover:bg-gray-700"
                    :class="{ 'bg-gray-50 dark:bg-gray-700': currentLocale === code }"
                >
                    <span class="text-lg">{{ getFlag(code) }}</span>
                    <span>{{ name }}</span>
                    <CheckCircle v-if="currentLocale === code" class="ml-auto h-4 w-4 text-emerald-500" />
                </button>
            </div>
        </Transition>
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { Globe, ChevronDown, CheckCircle } from 'lucide-vue-next';

const isOpen = ref(false);
const page = usePage();

const currentLocale = computed(() => (page.props.locale as string) || 'en');
const availableLocales = computed(() => (page.props.availableLocales as Record<string, string>) || {});
const currentLanguageName = computed(() => availableLocales.value[currentLocale.value] || 'English');

const getFlag = (code: string): string => {
    const flags: Record<string, string> = {
        en: '🇬🇧',
        fr: '🇫🇷',
        ht: '🇭🇹',
    };

    return flags[code] || '🌐';
};

const switchLanguage = (locale: string) => {
    if (locale === currentLocale.value){
        return;
    }

    router.post(
        '/locale/switch',
        { locale },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                isOpen.value = false;
            },
        }
    );
};
</script>