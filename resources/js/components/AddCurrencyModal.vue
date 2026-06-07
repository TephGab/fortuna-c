<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Add new currency') }}</h2>
                <button @click="close" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-800">
                    <X class="h-5 w-5 text-gray-500" />
                </button>
            </div>

            <div class="space-y-4">
                <!-- Currency selector with flags -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Select currency') }}
                    </label>
                    <select
                        v-model="selectedCurrency"
                        class="w-full rounded-xl border border-gray-300 bg-white p-3 focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        :disabled="loading"
                    >
                        <option value="">{{ t('-- Choose a currency --') }}</option>
                        <option
                            v-for="curr in availableCurrencies"
                            :key="curr.code"
                            :value="curr.code"
                        >
                            {{ getFlagEmoji(curr.code) }} {{ curr.code }} – {{ curr.name }} ({{ curr.symbol }})
                        </option>
                    </select>
                </div>

                <!-- Info box -->
                <div class="rounded-xl bg-gray-50 p-3 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    <Info class="mr-1 inline h-4 w-4" />
                    {{ t('Opening a new currency wallet is free and takes seconds.') }}
                </div>

                <!-- Error message -->
                <div v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400">
                    {{ error }}
                </div>

                <!-- Actions -->
                <div class="flex gap-3 pt-2">
                    <button
                        @click="close"
                        class="flex-1 rounded-xl border border-gray-300 py-2 font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        {{ t('Cancel') }}
                    </button>
                    <button
                        @click="submit"
                        :disabled="!selectedCurrency || loading"
                        class="flex-1 rounded-xl bg-gray-900 py-2 font-semibold text-white transition hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-700 dark:hover:bg-gray-600"
                    >
                        <Loader2 v-if="loading" class="mx-auto h-5 w-5 animate-spin" />
                        <span v-else>{{ t('Add currency') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { X, Info, Loader2 } from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits(['close', 'added']);

const selectedCurrency = ref('');
const availableCurrencies = ref<any[]>([]);
const loading = ref(false);
const error = ref('');

// Helper to get flag emoji from currency code (same mapping as dashboard)
function getFlagEmoji(currencyCode: string): string {
    const flags: Record<string, string> = {
        USD: '🇺🇸', EUR: '🇪🇺', GBP: '🇬🇧', JPY: '🇯🇵',
        BRL: '🇧🇷', CAD: '🇨🇦', AUD: '🇦🇺', CHF: '🇨🇭',
        CNY: '🇨🇳', INR: '🇮🇳', MXN: '🇲🇽', DOP: '🇩🇴',
        HTG: '🇭🇹', KRW: '🇰🇷', SGD: '🇸🇬', HKD: '🇭🇰',
        NZD: '🇳🇿', THB: '🇹🇭', VND: '🇻🇳', MYR: '🇲🇾',
    };
    return flags[currencyCode] || '🌐';
}

// Fetch available currencies when modal opens
watch(() => props.isOpen, async (open) => {
    if (open) {
        await fetchAvailableCurrencies();
        selectedCurrency.value = '';
        error.value = '';
    }
});

async function fetchAvailableCurrencies() {
    loading.value = true;
    try {
        const res = await fetch('/wallets/available-currencies');
        if (!res.ok) throw new Error('Failed to load');
        availableCurrencies.value = await res.json();
    } catch (err) {
        console.error(err);
        error.value = t('Could not load currencies. Please refresh.');
    } finally {
        loading.value = false;
    }
}

async function submit() {
    if (!selectedCurrency.value) return;
    loading.value = true;
    error.value = '';
    try {
        const response = await fetch('/wallets/add-currency', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ currency_code: selectedCurrency.value }),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || t('Failed to add currency'));
        emit('added', data.wallet);
        close();
    } catch (err: any) {
        error.value = err.message;
    } finally {
        loading.value = false;
    }
}

function close() {
    emit('close');
}
</script>