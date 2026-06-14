<template>
    <!-- Modal Overlay -->
    <Teleport to="body">
        <div 
            v-if="isOpen" 
            class="fixed inset-0 z-50 overflow-y-auto"
            @click.self="closeModal"
        >
            <div class="flex min-h-screen items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
                
                <!-- Modal Content -->
                <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl dark:bg-gray-900">
                    <!-- Header -->
                    <div class="sticky top-0 z-10 flex items-center justify-between rounded-t-2xl border-b border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Create New Vault</h2>
                        <button 
                            @click="closeModal" 
                            class="rounded-lg p-1 transition-colors hover:bg-gray-100 dark:hover:bg-gray-800"
                        >
                            <X class="h-5 w-5 text-gray-500" />
                        </button>
                    </div>

                    <!-- Form Body -->
                    <div class="max-h-[calc(100vh-200px)] overflow-y-auto p-6">
                        <!-- Vault Name -->
                        <div class="mb-5">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Vault Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-xl border border-gray-300 p-3 focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                placeholder="e.g., Vacation Fund, Emergency Savings"
                            />
                            <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
                        </div>

                        <!-- Vault Type Selection -->
                        <div class="mb-5">
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Vault Type <span class="text-red-500">*</span>
                            </label>
                            <div class="space-y-3">
                                <button
                                    v-for="(config, type) in availableTypes"
                                    :key="type"
                                    type="button"
                                    @click="selectType(type)"
                                    class="w-full rounded-xl border p-4 text-left transition-all"
                                    :class="[
                                        selectedType === type
                                            ? config.selected_border_class
                                            : config.default_border_class
                                    ]"
                                >
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-2xl">{{ config.icon }}</span>
                                                <span class="font-semibold text-gray-900 dark:text-white">{{ config.name }}</span>
                                            </div>
                                            <p class="mt-1 text-sm text-gray-500">{{ config.description }}</p>
                                            <div class="mt-2 flex flex-wrap gap-4 text-xs">
                                                <span class="flex items-center gap-1 text-emerald-600">
                                                    <TrendingUp class="h-3 w-3" />
                                                    {{ config.interest_rate }}% APY
                                                </span>
                                                <span v-if="config.lock_days > 0" class="flex items-center gap-1 text-amber-600">
                                                    <Lock class="h-3 w-3" />
                                                    {{ config.lock_days }} day lock
                                                </span>
                                                <span v-else class="flex items-center gap-1 text-gray-500">
                                                    <Unlock class="h-3 w-3" />
                                                    No lock
                                                </span>
                                                <span v-if="config.penalty > 0" class="flex items-center gap-1 text-red-500">
                                                    <AlertCircle class="h-3 w-3" />
                                                    {{ config.penalty }}% penalty
                                                </span>
                                            </div>
                                        </div>
                                        <CheckCircle v-if="selectedType === type" class="ml-3 h-5 w-5 flex-shrink-0 text-emerald-500" />
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Source Wallet -->
                        <div class="mb-5">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Source Wallet <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.wallet_id"
                                class="w-full rounded-xl border border-gray-300 p-3 focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            >
                                <option value="">Select wallet</option>
                                <option v-for="wallet in wallets" :key="wallet.id" :value="wallet.id">
                                    {{ wallet.currency_code }} - {{ wallet.formatted_balance }}
                                </option>
                            </select>
                            <p v-if="errors.wallet_id" class="mt-1 text-sm text-red-600">{{ errors.wallet_id }}</p>
                        </div>

                        <!-- Initial Deposit -->
                        <div class="mb-5">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Initial Deposit (Optional)
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                                    {{ selectedWallet?.currency_symbol || '$' }}
                                </span>
                                <input
                                    v-model.number="form.initial_deposit"
                                    type="number"
                                    step="10"
                                    min="0"
                                    class="w-full rounded-xl border border-gray-300 p-3 pl-8 focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    placeholder="0.00"
                                />
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Leave 0 to create an empty vault</p>
                            <p v-if="errors.initial_deposit" class="mt-1 text-sm text-red-600">{{ errors.initial_deposit }}</p>
                        </div>

                        <!-- Description -->
                        <div class="mb-5">
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Description (Optional)
                            </label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-xl border border-gray-300 p-3 focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                placeholder="What is this vault for?"
                            ></textarea>
                        </div>

                        <!-- Summary Box - All data comes pre-formatted from backend -->
                        <div v-if="selectedType" class="mb-5 rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                            <p class="mb-2 text-sm font-medium text-gray-900 dark:text-white">Summary</p>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Vault Type</span>
                                    <span class="font-medium">{{ selectedTypeConfig?.name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Interest Rate</span>
                                    <span class="font-medium text-emerald-600">{{ selectedTypeConfig?.interest_rate }}% APY</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Lock Period</span>
                                    <span class="font-medium">{{ selectedTypeConfig?.lock_days }} days</span>
                                </div>
                                <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <span class="text-gray-600">Initial Deposit</span>
                                    <span class="font-semibold">{{ formattedDeposit }}</span>
                                </div>
                                <div v-if="projectedInterest" class="flex justify-between">
                                    <span class="text-gray-600">Projected Interest</span>
                                    <span class="font-medium text-emerald-600">{{ projectedInterest }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Warning - All text comes from backend config -->
                        <div v-if="selectedType !== 'flexible'" 
                             :class="selectedTypeConfig?.warning_box_class || 'rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20'">
                            <div class="flex items-start gap-2">
                                <AlertCircle class="h-5 w-5 flex-shrink-0 text-amber-600" />
                                <p class="text-sm text-amber-800 dark:text-amber-300">
                                    {{ selectedTypeConfig?.warning_message || `Funds will be locked for ${selectedTypeConfig?.lock_days} days. Early withdrawal incurs a ${selectedTypeConfig?.penalty}% penalty.` }}
                                </p>
                            </div>
                        </div>

                        <!-- Error Message -->
                        <div v-if="errors.general" class="rounded-xl border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                            <p class="text-sm text-red-600">{{ errors.general }}</p>
                        </div>
                    </div>

                    <!-- Footer Buttons -->
                    <div class="sticky bottom-0 flex gap-3 rounded-b-2xl border-t border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <button
                            type="button"
                            @click="closeModal"
                            class="flex-1 rounded-xl border border-gray-300 py-2.5 font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="submitForm"
                            :disabled="isSubmitting || !isFormValid"
                            class="flex-1 rounded-xl bg-gray-900 py-2.5 font-semibold text-white transition hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-700 dark:hover:bg-gray-600"
                        >
                            <Loader2 v-if="isSubmitting" class="mx-auto h-5 w-5 animate-spin" />
                            <span v-else>Create Vault</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { X, TrendingUp, Lock, Unlock, CheckCircle, AlertCircle, Loader2 } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

// ============================================================================
// PROPS - All data comes pre-formatted from backend
// ============================================================================

const props = defineProps<{
    isOpen: boolean;
    wallets: Array<{
        id: number;
        currency_code: string;
        currency_symbol: string;
        formatted_balance: string;
        balance_float: number;
    }>;
    availableTypes: Record<string, {
        name: string;
        lock_days: number;
        interest_rate: number;
        penalty: number;
        icon: string;
        color: string;
        description: string;
        selected_border_class?: string;
        default_border_class?: string;
        warning_box_class?: string;
        warning_message?: string;
    }>;
}>();

// ============================================================================
// EMITS - Only emit events, no logic
// ============================================================================

const emit = defineEmits(['close', 'created']);

// ============================================================================
// STATE - Only track user input, no calculations
// ============================================================================

const form = ref({
    name: '',
    wallet_id: '',
    initial_deposit: 0,
    description: '',
});

const selectedType = ref<string>('');
const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});

// ============================================================================
// COMPUTED - Simple data lookups, no formatting logic
// ============================================================================

const selectedTypeConfig = computed(() => {
    if (!selectedType.value) return null;
    return props.availableTypes[selectedType.value];
});

const selectedWallet = computed(() => {
    if (!form.value.wallet_id) return null;
    return props.wallets.find(w => w.id === Number(form.value.wallet_id));
});

const isFormValid = computed(() => {
    return selectedType.value && form.value.name && form.value.wallet_id;
});

// This uses backend data, no formatting logic
const formattedDeposit = computed(() => {
    if (!selectedWallet.value) return `$${form.value.initial_deposit || 0}`;
    return `${selectedWallet.value.currency_symbol} ${form.value.initial_deposit || 0}`;
});

// This comes from API call, not calculated in Vue
const projectedInterest = ref<string | null>(null);

// ============================================================================
// METHODS - Only emit events and call backend APIs
// ============================================================================

/**
 * Fetch projected interest from backend API
 * No calculations done in Vue
 */
const fetchProjectedInterest = async () => {
    if (!selectedType.value || !form.value.initial_deposit || form.value.initial_deposit <= 0) {
        projectedInterest.value = null;
        return;
    }
    
    try {
        const response = await fetch('/vaults/preview-interest', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                type: selectedType.value,
                amount: form.value.initial_deposit,
            }),
        });
        
        const data = await response.json();
        
        if (response.ok) {
            projectedInterest.value = data.formatted_interest;
        }
    } catch (err) {
        console.error('Failed to fetch interest preview:', err);
    }
};

/**
 * Watch for changes to fetch new interest preview
 */
watch([selectedType, () => form.value.initial_deposit], () => {
    fetchProjectedInterest();
});

/**
 * Select a vault type - just sets the value, no validation
 */
const selectType = (type: string) => {
    selectedType.value = type;
};

/**
 * Reset form when modal opens - all reset logic here, no calculations
 */
watch(() => props.isOpen, (open) => {
    if (open) {
        form.value = {
            name: '',
            wallet_id: '',
            initial_deposit: 0,
            description: '',
        };
        selectedType.value = '';
        errors.value = {};
        projectedInterest.value = null;
    }
});

/**
 * Submit form using Inertia (not manual fetch)
 * Backend handles all validation and returns errors
 */
const submitForm = async () => {
    isSubmitting.value = true;
    errors.value = {};
    
    // Use Inertia post instead of manual fetch
    router.post('/vaults', {
        name: form.value.name,
        type: selectedType.value,
        wallet_id: form.value.wallet_id,
        initial_deposit: form.value.initial_deposit,
        description: form.value.description,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            emit('created');
            closeModal();
        },
        onError: (backendErrors) => {
            // Backend returns all validation errors
            errors.value = backendErrors;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

/**
 * Close modal - just emit event, parent handles everything
 */
const closeModal = () => {
    emit('close');
};
</script>