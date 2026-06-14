<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                    {{ showConfirmation ? t('Confirm Withdrawal') : t('Withdraw from') + ' ' + vault.name }}
                </h2>
                <button @click="close" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-800">
                    <X class="h-5 w-5 text-gray-500" />
                </button>
            </div>

            <!-- Step 1: Withdraw Form -->
            <div v-if="!showConfirmation">
                <div class="mb-4 rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ t('Available balance') }}</span>
                        <span class="font-semibold">{{ vault.formatted_balance || '$0.00' }}</span>
                    </div>
                    <div class="mt-2 flex justify-between text-sm">
                        <span class="text-gray-600">{{ t('Interest earned') }}</span>
                        <span class="font-semibold text-emerald-600">{{ vault.formatted_interest_earned || '$0.00' }}</span>
                    </div>
                    <div v-if="vault.is_locked && !vault.is_matured" class="mt-2 flex justify-between text-sm">
                        <span class="text-gray-600">{{ t('Early withdrawal penalty') }}</span>
                        <span class="font-semibold text-amber-600">{{ vault.type_config?.penalty || 0 }}%</span>
                    </div>
                </div>

                <form @submit.prevent="goToConfirmation" class="space-y-5">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Withdraw Amount') }}
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                                {{ vaultCurrencySymbol }}
                            </span>
                            <input
                                v-model="amount"
                                type="text"
                                inputmode="decimal"
                                class="w-full rounded-xl border border-gray-300 p-3 pl-8 text-lg font-semibold focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                placeholder="0.00"
                                @input="validateAmount"
                            />
                        </div>
                        <button
                            type="button"
                            @click="setMaxAmount"
                            class="mt-2 text-xs text-amber-600 hover:underline"
                        >
                            {{ t('Withdraw all') }} ({{ vault.formatted_balance || '$0.00' }})
                        </button>
                        <p v-if="amountError" class="mt-1 text-xs text-red-500">
                            {{ amountError }}
                        </p>
                    </div>

                    <!-- Penalty Warning -->
                    <div v-if="isEarlyWithdrawal && amountNumber > 0 && !amountError" class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                        <div class="flex items-start gap-2">
                            <AlertCircle class="h-5 w-5 text-amber-600" />
                            <div>
                                <p class="text-sm font-medium text-amber-800 dark:text-amber-300">{{ t('Early Withdrawal Penalty') }}</p>
                                <p class="text-xs text-amber-700 dark:text-amber-400">
                                    {{ t('Withdrawing before the lock period ends will incur a') }} {{ vault.type_config?.penalty || 0 }}% {{ t('penalty') }}.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Preview Box -->
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ t('Amount to withdraw') }}</span>
                            <span class="font-medium">{{ vaultCurrencySymbol }}{{ displayAmount }}</span>
                        </div>
                        <div v-if="penaltyAmountFormatted && isEarlyWithdrawal && amountNumber > 0" class="mt-2 flex justify-between text-sm">
                            <span class="text-gray-600">{{ t('Penalty') }} ({{ vault.type_config?.penalty || 0 }}%)</span>
                            <span class="font-medium text-red-600">-{{ penaltyAmountFormatted }}</span>
                        </div>
                        <div class="mt-3 border-t border-gray-200 pt-2 flex justify-between font-semibold dark:border-gray-700">
                            <span class="text-gray-900">{{ t('You will receive') }}</span>
                            <span class="text-emerald-600">{{ estimatedAmountFormatted }}</span>
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
                            class="flex-1 rounded-xl bg-amber-600 py-2.5 font-semibold text-white transition hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ t('Continue') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Step 2: Confirmation Screen -->
            <div v-else class="space-y-5">
                <div class="rounded-xl bg-amber-50 p-4 text-center dark:bg-amber-900/20">
                    <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                        <AlertCircle class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                    </div>
                    <p class="text-sm text-amber-700 dark:text-amber-300">{{ t('Please review your withdrawal details') }}</p>
                </div>

                <!-- Confirmation Details -->
                <div class="space-y-3 rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                    <div class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                        <span class="text-gray-600">{{ t('Vault') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ vault.name }}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                        <span class="text-gray-600">{{ t('Current balance') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ vault.formatted_balance || '$0.00' }}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                        <span class="text-gray-600">{{ t('Withdraw amount') }}</span>
                        <span class="font-bold text-amber-600">{{ vaultCurrencySymbol }}{{ displayAmount }}</span>
                    </div>
                    <div v-if="penaltyAmountFormatted && isEarlyWithdrawal && amountNumber > 0" class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                        <span class="text-gray-600">{{ t('Penalty') }} ({{ vault.type_config?.penalty || 0 }}%)</span>
                        <span class="font-medium text-red-600">-{{ penaltyAmountFormatted }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">{{ t('You will receive') }}</span>
                        <span class="font-bold text-emerald-600">{{ estimatedAmountFormatted }}</span>
                    </div>
                </div>

                <!-- Warning in confirmation -->
                <div v-if="isEarlyWithdrawal" class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                    <div class="flex items-start gap-2">
                        <AlertCircle class="h-5 w-5 flex-shrink-0 text-amber-600" />
                        <p class="text-sm text-amber-800 dark:text-amber-300">
                            ⚠️ {{ t('This is an early withdrawal. A penalty of') }} {{ vault.type_config?.penalty || 0 }}% {{ t('will be applied.') }}
                        </p>
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
                        class="flex-1 rounded-xl bg-amber-600 py-2.5 font-semibold text-white transition hover:bg-amber-700 disabled:opacity-50"
                    >
                        <Loader2 v-if="isSubmitting" class="mx-auto h-5 w-5 animate-spin" />
                        <span v-else>{{ t('Confirm Withdrawal') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { X, AlertCircle, Loader2 } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const props = defineProps<{
    isOpen: boolean;
    vault: {
        id: number;
        name: string;
        formatted_balance: string;
        formatted_interest_earned: string;
        balance: number;
        interest_earned: number;
        type: string;
        status: string;
        is_locked: boolean;
        is_matured: boolean;
        type_config?: {
            penalty: number;
            lock_days: number;
            name: string;
        };
        currency_symbol?: string;
        currency_code?: string;
    };
}>();

const emit = defineEmits(['close', 'withdrawn']);

// ==================== STATE ====================
const amount = ref<string>('');
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);
const amountError = ref<string | null>(null);
const showConfirmation = ref(false);

// ==================== COMPUTED ====================
// Get vault currency symbol
const vaultCurrencySymbol = computed(() => {
    return props.vault.currency_symbol || '$';
});

// Parse amount as number (in dollars)
const amountNumber = computed(() => {
    const parsed = parseFloat(amount.value);
    return isNaN(parsed) ? 0 : parsed;
});

// Display amount (for UI)
const displayAmount = computed(() => {
    if (!amount.value || amountNumber.value === 0) return '0.00';
    return amountNumber.value.toFixed(2);
});

// Check if early withdrawal applies
const isEarlyWithdrawal = computed(() => {
    return props.vault.type !== 'flexible' && props.vault.status === 'locked' && !props.vault.is_matured;
});

// Calculate penalty amount in dollars
const penaltyAmount = computed(() => {
    if (!isEarlyWithdrawal.value || amountNumber.value <= 0) return 0;
    const penaltyPercent = props.vault.type_config?.penalty || 0;
    return amountNumber.value * (penaltyPercent / 100);
});

// Format penalty amount for display
const penaltyAmountFormatted = computed(() => {
    const penalty = penaltyAmount.value;
    if (penalty <= 0) return null;
    return `${vaultCurrencySymbol.value} ${penalty.toFixed(2)}`;
});

// Calculate estimated amount after penalty
const estimatedAmount = computed(() => {
    return amountNumber.value - penaltyAmount.value;
});

// Format estimated amount for display
const estimatedAmountFormatted = computed(() => {
    const estimated = estimatedAmount.value;
    if (estimated <= 0) return `${vaultCurrencySymbol.value} 0.00`;
    return `${vaultCurrencySymbol.value} ${estimated.toFixed(2)}`;
});

// Maximum withdraw amount - FIXED to properly extract from formatted_balance
const maxWithdrawFloat = computed(() => {
    // Try to get from formatted_balance first
    if (props.vault.formatted_balance) {
        // Extract number from formatted balance (e.g., "$35.00" -> 35, "€ 50,00" -> 50)
        const match = props.vault.formatted_balance.match(/(\d+(?:[.,]\d+)?)/);
        if (match) {
            // Convert comma to dot if needed (e.g., "50,00" -> "50.00")
            const numStr = match[1].replace(',', '.');
            const num = parseFloat(numStr);
            if (!isNaN(num)) {
                return num;
            }
        }
    }
    
    // Fallback: use balance from props (assuming it's in cents)
    if (props.vault.balance !== undefined && props.vault.balance !== null) {
        return props.vault.balance / 100;
    }
    
    // Ultimate fallback
    return 0;
});

// Check if form is valid
const isFormValid = computed(() => {
    if (amountNumber.value <= 0) return false;
    if (amountNumber.value > maxWithdrawFloat.value) return false;
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
    
    // Allow only numbers and decimal point
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
        amountError.value = t('Minimum withdrawal amount is $1');
        return;
    }
    
    if (num > maxWithdrawFloat.value) {
        amountError.value = t('Amount exceeds available balance');
        return;
    }
    
    amountError.value = null;
};

const setMaxAmount = () => {
    amount.value = maxWithdrawFloat.value.toString();
    validateAmount();
};

const goToConfirmation = () => {
    if (!isFormValid.value) return;
    showConfirmation.value = true;
};

const resetForm = () => {
    amount.value = '';
    errorMessage.value = null;
    amountError.value = null;
    showConfirmation.value = false;
    isSubmitting.value = false;
};

// ==================== WATCHERS ====================
watch(() => props.isOpen, (open) => {
    if (open) {
        resetForm();
    }
});

// ==================== SUBMIT ====================
const submit = async () => {
    // Validate amount
    if (amountNumber.value <= 0) {
        amountError.value = t('Please enter an amount');
        return;
    }
    
    if (amountError.value) {
        showConfirmation.value = false;
        return;
    }
    
    if (amountNumber.value > maxWithdrawFloat.value) {
        amountError.value = t('Amount exceeds available balance');
        showConfirmation.value = false;
        return;
    }
    
    isSubmitting.value = true;
    errorMessage.value = null;
    
    try {
        await router.post(`/vaults/${props.vault.id}/withdraw`, {
            amount: amountNumber.value,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                emit('withdrawn');
                close();
            },
            onError: (errors) => {
                console.error('Withdrawal errors:', errors);
                if (errors.error) {
                    errorMessage.value = errors.error;
                } else if (errors.amount) {
                    errorMessage.value = errors.amount;
                } else {
                    errorMessage.value = t('Withdrawal failed. Please try again.');
                }
                showConfirmation.value = false;
            }
        });
    } catch (err) {
        console.error('Withdrawal failed:', err);
        errorMessage.value = t('Withdrawal failed. Please try again.');
        showConfirmation.value = false;
    } finally {
        isSubmitting.value = false;
    }
};

const close = () => {
    resetForm();
    emit('close');
};
</script>