<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                    {{ showConfirmation ? t('Confirm Deposit') : t('Deposit to') + ' ' + vault.name }}
                </h2>
                <button @click="close" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-800">
                    <X class="h-5 w-5 text-gray-500" />
                </button>
            </div>

            <!-- Step 1: Deposit Form -->
            <form v-if="!showConfirmation" @submit.prevent="goToConfirmation" class="space-y-5">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Deposit Amount') }}
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                            {{ vaultCurrencySymbol }}
                        </span>
                        <input
                            v-model="amount"
                            type="text"
                            inputmode="decimal"
                            class="w-full rounded-xl border border-gray-300 p-3 pl-8 text-lg font-semibold focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            placeholder="0.00"
                            @input="validateAmount"
                        />
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="suggested in [10, 25, 50, 100, 250, 500]"
                            :key="suggested"
                            type="button"
                            @click="setAmount(suggested)"
                            class="rounded-lg border border-gray-200 px-3 py-1 text-xs transition-all hover:border-emerald-500 dark:border-gray-700"
                        >
                            {{ vaultCurrencySymbol }}{{ suggested }}
                        </button>
                    </div>
                    <p v-if="amountError" class="mt-1 text-xs text-red-500">
                        {{ amountError }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Source Wallet') }}
                    </label>
                    <select
                        v-model="selectedWalletId"
                        class="w-full rounded-xl border border-gray-300 p-3 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option v-for="wallet in wallets" :key="wallet.id" :value="wallet.id">
                            {{ wallet.currency_code }} - {{ wallet.formatted_balance }}
                        </option>
                    </select>
                    <p v-if="wallets.length === 0" class="mt-1 text-xs text-amber-600">
                        {{ t('No wallets available. Please add a wallet first.') }}
                    </p>
                </div>

                <!-- Show warning if currencies don't match -->
                <div v-if="currencyMismatch && selectedWallet" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
                    ⚠️ {{ t('This wallet uses') }} {{ selectedWalletCurrency }} 
                    {{ t('but the vault uses') }} {{ vaultCurrencyCode }}.
                    {{ t('Please select a wallet with') }} {{ vaultCurrencyCode }}.
                </div>

                <!-- Preview Box - Using pre-formatted values from backend -->
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ t('Current vault balance') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ vault.formatted_balance }}
                        </span>
                    </div>
                    <div class="mt-2 flex justify-between text-sm">
                        <span class="text-gray-600">{{ t('Amount to deposit') }}</span>
                        <span class="font-medium text-emerald-600">
                            {{ vaultCurrencySymbol }}{{ displayAmount }}
                        </span>
                    </div>
                    <div class="mt-2 flex justify-between border-t border-gray-200 pt-2 dark:border-gray-700">
                        <span class="font-medium text-gray-900 dark:text-white">{{ t('New vault balance') }}</span>
                        <span class="font-bold text-emerald-600">
                            {{ newBalanceFormatted }}
                        </span>
                    </div>
                </div>

                <!-- Error message -->
                <div v-if="errorMessage" class="rounded-xl bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400">
                    {{ errorMessage }}
                </div>

                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        @click="close"
                        class="flex-1 rounded-xl border border-gray-300 py-2.5 font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        {{ t('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="!isFormValid"
                        class="flex-1 rounded-xl bg-emerald-600 py-2.5 font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ t('Continue') }}
                    </button>
                </div>
            </form>

            <!-- Step 2: Confirmation Screen -->
            <div v-else class="space-y-5">
                <div class="rounded-xl bg-emerald-50 p-4 text-center dark:bg-emerald-900/20">
                    <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                        <Wallet class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <p class="text-sm text-emerald-700 dark:text-emerald-300">{{ t('Please review your deposit details') }}</p>
                </div>

                <!-- Confirmation Details - Using pre-formatted values from backend -->
                <div class="space-y-3 rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                    <div class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                        <span class="text-gray-600">{{ t('Vault') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ vault.name }}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                        <span class="text-gray-600">{{ t('Current Balance') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ vault.formatted_balance }}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                        <span class="text-gray-600">{{ t('Amount to deposit') }}</span>
                        <span class="font-bold text-emerald-600">{{ vaultCurrencySymbol }}{{ displayAmount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ t('New balance after deposit') }}</span>
                        <span class="font-bold text-emerald-600">{{ newBalanceFormatted }}</span>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        @click="showConfirmation = false"
                        class="flex-1 rounded-xl border border-gray-300 py-2.5 font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        {{ t('Back') }}
                    </button>
                    <button
                        type="button"
                        @click="submit"
                        :disabled="isSubmitting"
                        class="flex-1 rounded-xl bg-emerald-600 py-2.5 font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <Loader2 v-if="isSubmitting" class="mx-auto h-5 w-5 animate-spin" />
                        <span v-else>{{ t('Confirm Deposit') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { X, Loader2, Wallet } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const props = defineProps<{
    isOpen: boolean;
    vault: {
        id: number;
        name: string;
        formatted_balance: string;
        balance: number; // Raw integer in smallest unit (cents)
        currency_code?: string;
        currency_symbol?: string;
    };
    wallets: Array<{
        id: number;
        currency_code: string;
        currency_symbol: string;
        formatted_balance: string;
        balance_float: number;
        is_default?: boolean;
    }>;
}>();

const emit = defineEmits(['close', 'deposited']);

// ==================== STATE ====================
const amount = ref<string>('');
const selectedWalletId = ref<number | null>(null);
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);
const amountError = ref<string | null>(null);
const showConfirmation = ref(false);

// ==================== COMPUTED ====================
// Get vault currency info
const vaultCurrencyCode = computed(() => {
    return props.vault.currency_code || 'USD';
});

const vaultCurrencySymbol = computed(() => {
    return props.vault.currency_symbol || '$';
});

// Get selected wallet
const selectedWallet = computed(() => {
    if (!selectedWalletId.value) return null;
    return props.wallets.find(w => w.id === selectedWalletId.value);
});

const selectedWalletCurrency = computed(() => {
    return selectedWallet.value?.currency_code || null;
});

// Check if currencies match
const currencyMismatch = computed(() => {
    if (!selectedWallet.value) return false;
    return selectedWallet.value.currency_code !== vaultCurrencyCode.value;
});

// Parse amount as number (in dollars)
const amountNumber = computed(() => {
    const parsed = parseFloat(amount.value);
    return isNaN(parsed) ? 0 : parsed;
});

// Convert amount to smallest unit (cents) for calculation
const amountInSmallestUnit = computed(() => {
    return Math.round(amountNumber.value * 100);
});

// Display amount (for UI - just showing the input value)
const displayAmount = computed(() => {
    if (!amount.value || amountNumber.value === 0) return '0.00';
    return amountNumber.value.toFixed(2);
});

// Calculate new balance in smallest unit (cents)
const newBalanceInSmallestUnit = computed(() => {
    const currentBalance = props.vault.balance || 0;
    const depositAmount = amountInSmallestUnit.value;
    return currentBalance + depositAmount;
});

// Get formatted new balance using the same format as backend
// We simulate the format here for preview only - the actual deposit uses backend
const newBalanceFormatted = computed(() => {
    const newBalance = newBalanceInSmallestUnit.value;
    const dollars = (newBalance / 100).toFixed(2);
    return `${vaultCurrencySymbol.value} ${dollars}`;
});

// Check if form is valid
const isFormValid = computed(() => {
    if (amountNumber.value <= 0) return false;
    if (!selectedWalletId.value) return false;
    if (currencyMismatch.value) return false;
    if (amountError.value) return false;
    return true;
});

// ==================== METHODS ====================
const validateAmount = () => {
    const value = amount.value;
    
    if (!value) {
        amountError.value = null;
        return;
    }
    
    const regex = /^\d*\.?\d{0,2}$/;
    if (!regex.test(value)) {
        amountError.value = t('Please enter a valid amount');
        return;
    }
    
    const num = parseFloat(value);
    if (isNaN(num)) {
        amountError.value = t('Please enter a valid amount');
        return;
    }
    
    if (num < 1) {
        amountError.value = t('Minimum deposit amount is $1');
        return;
    }
    
    if (num > 50000) {
        amountError.value = t('Maximum deposit amount is $50,000');
        return;
    }
    
    amountError.value = null;
};

const setAmount = (value: number) => {
    amount.value = value.toString();
    validateAmount();
};

const goToConfirmation = () => {
    if (!isFormValid.value) return;
    showConfirmation.value = true;
};

const resetForm = () => {
    amount.value = '';
    selectedWalletId.value = null;
    errorMessage.value = null;
    amountError.value = null;
    showConfirmation.value = false;
    isSubmitting.value = false;
};

// ==================== WATCHERS ====================
watch(() => props.isOpen, (open) => {
    if (open) {
        resetForm();
        
        if (props.wallets.length > 0) {
            const mainWallet = props.wallets.find(w => w.is_default === true);
            const matchingWallet = props.wallets.find(w => w.currency_code === vaultCurrencyCode.value);
            const defaultWallet = props.wallets[0];
            
            if (mainWallet) {
                selectedWalletId.value = mainWallet.id;
            } else if (matchingWallet) {
                selectedWalletId.value = matchingWallet.id;
            } else if (defaultWallet) {
                selectedWalletId.value = defaultWallet.id;
            }
        }
    }
});

// ==================== SUBMIT ====================
const submit = async () => {
    if (amountNumber.value <= 0) {
        amountError.value = t('Please enter an amount');
        return;
    }
    
    if (amountError.value) return;
    
    if (!selectedWalletId.value) {
        errorMessage.value = t('Please select a source wallet');
        return;
    }
    
    if (currencyMismatch.value) {
        errorMessage.value = t('Please select a wallet with the same currency as the vault');
        return;
    }
    
    isSubmitting.value = true;
    errorMessage.value = null;
    
    try {
        await router.post(`/vaults/${props.vault.id}/deposit`, {
            amount: amountNumber.value,
            wallet_id: selectedWalletId.value,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                emit('deposited');
                close();
            },
            onError: (errors) => {
                console.error('Deposit errors:', errors);
                if (errors.error) {
                    errorMessage.value = errors.error;
                } else if (errors.amount) {
                    errorMessage.value = errors.amount;
                } else {
                    errorMessage.value = t('Deposit failed. Please try again.');
                }
            }
        });
    } catch (err) {
        console.error('Deposit failed:', err);
        errorMessage.value = t('Deposit failed. Please try again.');
    } finally {
        isSubmitting.value = false;
    }
};

const close = () => {
    resetForm();
    emit('close');
};
</script>