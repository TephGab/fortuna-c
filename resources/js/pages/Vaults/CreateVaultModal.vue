<template>
    <Teleport to="body">
        <div 
            v-if="isOpen" 
            class="fixed inset-0 z-50 overflow-y-auto"
            @click.self="closeModal"
        >
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
                
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

                    <!-- Step 1: Form Body -->
                    <div v-if="!showConfirmation" class="max-h-[calc(100vh-200px)] overflow-y-auto p-6">
                        
                        <!-- ====== QUICK PLANS ====== -->
                        <div class="mb-6">
                            <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Quick start – choose a goal') }}
                            </p>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3">
                                <button
                                    v-for="plan in quickPlans"
                                    :key="plan.id"
                                    type="button"
                                    @click="applyPlan(plan)"
                                    class="flex flex-col items-center gap-1 rounded-xl border border-gray-200 p-3 text-center transition hover:border-gray-400 hover:shadow-sm dark:border-gray-700 dark:hover:border-gray-500"
                                >
                                    <span class="text-2xl sm:text-3xl">{{ plan.icon }}</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ plan.label }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 leading-tight">{{ plan.description }}</span>
                                </button>
                            </div>

                            <!-- Note: user can create custom -->
                            <p class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400">
                                {{ t('Don\'t see a plan that fits? You can create a custom vault below.') }}
                            </p>
                        </div>

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

                        <!-- Preview Box -->
                        <div v-if="selectedType" class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">{{ t('Vault Type') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ selectedTypeConfig?.name }}</span>
                            </div>
                            <div class="mt-2 flex justify-between text-sm">
                                <span class="text-gray-600">{{ t('Interest Rate') }}</span>
                                <span class="font-medium text-emerald-600">{{ selectedTypeConfig?.interest_rate }}% APY</span>
                            </div>
                            <div class="mt-2 flex justify-between text-sm">
                                <span class="text-gray-600">{{ t('Lock Period') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ selectedTypeConfig?.lock_days }} {{ t('days') }}</span>
                            </div>
                            <div class="mt-2 flex justify-between border-t border-gray-200 pt-2 dark:border-gray-700">
                                <span class="font-medium text-gray-900 dark:text-white">{{ t('Initial Deposit') }}</span>
                                <span class="font-bold text-emerald-600">{{ formattedDeposit }}</span>
                            </div>
                            <div v-if="projectedInterest && form.initial_deposit > 0" class="mt-2 flex justify-between">
                                <span class="font-medium text-gray-900 dark:text-white">{{ t('Projected Interest') }}</span>
                                <span class="font-bold text-emerald-600">{{ projectedInterest }}</span>
                            </div>
                        </div>

                        <!-- Warning -->
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

                        <!-- Footer Buttons -->
                        <div class="sticky bottom-0 mt-6 flex gap-3 rounded-b-2xl border-t border-gray-200 bg-white pt-4 dark:border-gray-800 dark:bg-gray-900">
                            <button
                                type="button"
                                @click="closeModal"
                                class="flex-1 rounded-xl border border-gray-300 py-2.5 font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="goToConfirmation"
                                :disabled="!isFormValid"
                                class="flex-1 rounded-xl bg-emerald-600 py-2.5 font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Continue
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Confirmation Screen -->
                    <div v-else class="max-h-[calc(100vh-200px)] overflow-y-auto p-6">
                        <div class="rounded-xl bg-emerald-50 p-4 text-center dark:bg-emerald-900/20">
                            <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                                <Wallet class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <p class="text-sm text-emerald-700 dark:text-emerald-300">{{ t('Please review your vault details') }}</p>
                        </div>

                        <div class="mt-4 space-y-3 rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                            <div class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                                <span class="text-gray-600">{{ t('Vault Name') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ form.name }}</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                                <span class="text-gray-600">{{ t('Vault Type') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ selectedTypeConfig?.name }}</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                                <span class="text-gray-600">{{ t('Interest Rate') }}</span>
                                <span class="font-bold text-emerald-600">{{ selectedTypeConfig?.interest_rate }}% APY</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                                <span class="text-gray-600">{{ t('Lock Period') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ selectedTypeConfig?.lock_days }} {{ t('days') }}</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                                <span class="text-gray-600">{{ t('Source Wallet') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ selectedWallet?.currency_code }} - {{ selectedWallet?.formatted_balance }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ t('Initial Deposit') }}</span>
                                <span class="font-bold text-emerald-600">{{ formattedDeposit }}</span>
                            </div>
                            <div v-if="projectedInterest && form.initial_deposit > 0" class="flex justify-between">
                                <span class="text-gray-600">{{ t('Projected Interest') }}</span>
                                <span class="font-bold text-emerald-600">{{ projectedInterest }}</span>
                            </div>
                        </div>

                        <div v-if="selectedType !== 'flexible'" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                            <div class="flex items-start gap-2">
                                <AlertCircle class="h-5 w-5 flex-shrink-0 text-amber-600" />
                                <p class="text-sm text-amber-800 dark:text-amber-300">
                                    ⚠️ {{ t('Funds will be locked for') }} {{ selectedTypeConfig?.lock_days }} {{ t('days') }}. 
                                    {{ t('Early withdrawal incurs a') }} {{ selectedTypeConfig?.penalty }}% {{ t('penalty') }}.
                                </p>
                            </div>
                        </div>

                        <div class="sticky bottom-0 mt-6 flex gap-3 rounded-b-2xl border-t border-gray-200 bg-white pt-4 dark:border-gray-800 dark:bg-gray-900">
                            <button
                                type="button"
                                @click="showConfirmation = false"
                                class="flex-1 rounded-xl border border-gray-300 py-2.5 font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                            >
                                {{ t('Back') }}
                            </button>
                            <button
                                type="button"
                                @click="submitForm"
                                :disabled="isSubmitting"
                                class="flex-1 rounded-xl bg-emerald-600 py-2.5 font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                            >
                                <Loader2 v-if="isSubmitting" class="mx-auto h-5 w-5 animate-spin" />
                                <span v-else>{{ t('Confirm Creation') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { X, TrendingUp, Lock, Unlock, CheckCircle, AlertCircle, Loader2, Wallet } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

// ============================================================================
// PROPS
// ============================================================================

const props = defineProps<{
    isOpen: boolean;
    wallets: Array<{
        id: number;
        currency_code: string;
        currency_symbol: string;
        formatted_balance: string;
        balance_raw: number;
    }>;
    availableTypes?: Record<string, any>;   // optional – fallback to defaults
}>();

const emit = defineEmits(['close', 'created']);

// ============================================================================
// DEFAULT VAULT TYPES (built‑in)
// ============================================================================

const defaultTypes: Record<string, any> = {
  flexible: {
    name: 'Flexible',
    lock_days: 0,
    interest_rate: 2.0,
    penalty: 0,
    icon: '🔓',
    color: 'emerald',
    description: 'No lock‑in, withdraw anytime',
    selected_border_class: 'border-2 border-emerald-500',
    default_border_class: 'border border-gray-200',
    warning_box_class: 'rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20',
    warning_message: 'Funds are not locked – you can withdraw anytime without penalty.',
  },
  locked_30: {
    name: '30‑Day Lock',
    lock_days: 30,
    interest_rate: 3.5,
    penalty: 5,
    icon: '🔒',
    color: 'amber',
    description: 'Lock funds for 30 days, earn 3.5% APY',
    selected_border_class: 'border-2 border-amber-500',
    default_border_class: 'border border-gray-200',
    warning_box_class: 'rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20',
    warning_message: 'Funds will be locked for 30 days. Early withdrawal incurs a 5% penalty.',
  },
  locked_90: {
    name: '90‑Day Lock',
    lock_days: 90,
    interest_rate: 5.0,
    penalty: 10,
    icon: '🔒',
    color: 'amber',
    description: 'Lock funds for 90 days, earn 5% APY',
    selected_border_class: 'border-2 border-amber-500',
    default_border_class: 'border border-gray-200',
    warning_box_class: 'rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20',
    warning_message: 'Funds will be locked for 90 days. Early withdrawal incurs a 10% penalty.',
  },
  locked_180: {
    name: '180‑Day Lock',
    lock_days: 180,
    interest_rate: 6.5,
    penalty: 15,
    icon: '🔐',
    color: 'blue',
    description: 'Lock funds for 180 days, earn 6.5% APY',
    selected_border_class: 'border-2 border-blue-500',
    default_border_class: 'border border-gray-200',
    warning_box_class: 'rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20',
    warning_message: 'Funds will be locked for 180 days. Early withdrawal incurs a 15% penalty.',
  },
  locked_365: {
    name: '365‑Day Lock (Best Rate)',
    lock_days: 365,
    interest_rate: 8.0,
    penalty: 20,
    icon: '🏦',
    color: 'purple',
    description: 'Lock funds for 365 days, earn 8% APY – highest return',
    selected_border_class: 'border-2 border-purple-500',
    default_border_class: 'border border-gray-200',
    warning_box_class: 'rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20',
    warning_message: 'Funds will be locked for 365 days. Early withdrawal incurs a 20% penalty.',
  },
};

// ============================================================================
// QUICK PLANS with clear descriptions
// ============================================================================

const quickPlans = [
  {
    id: 'vacation',
    label: 'Vacation',
    icon: '🏖️',
    description: 'No lock, earn 2% APY – perfect for short-term savings',
    vaultType: 'flexible',
    suggestedName: 'Vacation Fund',
  },
  {
    id: 'emergency',
    label: 'Emergency',
    icon: '🆘',
    description: 'No lock, earn 2% APY – access funds anytime',
    vaultType: 'flexible',
    suggestedName: 'Emergency Savings',
  },
  {
    id: 'car',
    label: 'Car',
    icon: '🚗',
    description: '30-day lock, earn 3.5% APY – grow your down payment',
    vaultType: 'locked_30',
    suggestedName: 'Car Purchase',
  },
  {
    id: 'house',
    label: 'House',
    icon: '🏠',
    description: '90-day lock, earn 5% APY – save for a home',
    vaultType: 'locked_90',
    suggestedName: 'House Down Payment',
  },
  {
    id: 'invest',
    label: 'Invest',
    icon: '📈',
    description: '180-day lock, earn 6.5% APY – longer-term growth',
    vaultType: 'locked_180',
    suggestedName: 'Investment Fund',
  },
  // You can add more, e.g.:
  // {
  //   id: 'retire',
  //   label: 'Retirement',
  //   icon: '🏦',
  //   description: '365-day lock, earn 8% APY – best return',
  //   vaultType: 'locked_365',
  //   suggestedName: 'Retirement Fund',
  // },
];

// ============================================================================
// COMPUTED
// ============================================================================

const availableTypes = computed(() => {
    return props.availableTypes && Object.keys(props.availableTypes).length > 0
        ? props.availableTypes
        : defaultTypes;
});

// ============================================================================
// STATE
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
const showConfirmation = ref(false);

const selectedTypeConfig = computed(() => {
    if (!selectedType.value) return null;
    return availableTypes.value[selectedType.value];
});

const selectedWallet = computed(() => {
    if (!form.value.wallet_id) return null;
    return props.wallets.find(w => w.id === Number(form.value.wallet_id));
});

const isFormValid = computed(() => {
    return selectedType.value && form.value.name && form.value.wallet_id;
});

const formattedDeposit = computed(() => {
    if (!selectedWallet.value) {
        return `$${form.value.initial_deposit || 0}`;
    }
    return `${selectedWallet.value.currency_symbol} ${form.value.initial_deposit || 0}`;
});

const projectedInterest = ref<string | null>(null);

// ============================================================================
// METHODS
// ============================================================================

const applyPlan = (plan: any) => {
    form.value.name = plan.suggestedName || '';
    if (plan.vaultType) {
        selectType(plan.vaultType);
    }
};

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
                wallet_id: form.value.wallet_id,
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

watch([selectedType, () => form.value.initial_deposit, () => form.value.wallet_id], () => {
    fetchProjectedInterest();
});

const selectType = (type: string) => {
    selectedType.value = type;
};

const goToConfirmation = () => {
    if (!isFormValid.value) return;
    showConfirmation.value = true;
};

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
        showConfirmation.value = false;
    }
});

const submitForm = async () => {
    isSubmitting.value = true;
    errors.value = {};
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
            errors.value = backendErrors;
            showConfirmation.value = false;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const closeModal = () => {
    showConfirmation.value = false;
    emit('close');
};
</script>