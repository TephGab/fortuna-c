<script setup lang="ts">
/**
 * Send Money Component
 * 
 * Features:
 * - 3-step guided transfer flow
 * - Real-time exchange rate calculation
 * - Quote system with rate lock (60 seconds)
 * - Multi-currency wallet selection
 * - Recipient details shown on all steps
 * - Recent recipients for quick access
 * - Full error handling with rollback
 */

import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { 
    ArrowLeft, 
    Send, 
    User, 
    Mail, 
    ArrowRight,
    RefreshCw,
    CheckCircle,
    AlertCircle,
    Loader2,
    ChevronRight,
    Wallet,
    Clock,
    Shield,
    Building2,
    Smartphone,
    Sparkles,
    Search,
    X,
    CircleDollarSign,
    TrendingUp,
    Landmark,
    CreditCard,
    Info,
    DollarSign
} from 'lucide-vue-next';

// ==================== PROPS ====================
const props = defineProps<{
    wallets: Array<{
        id: number;
        currency_code: string;
        currency_symbol: string;
        balance: number;
        formatted_balance: string;
        is_default: boolean;
        decimal_places: number;
    }>;
    recentRecipients: Array<{
        id: number;
        name: string;
        email: string;
        avatar: string;
    }>;
    currencies: Array<{
        code: string;
        symbol: string;
        name: string;
    }>;
    fee_percentage: number;
    min_fee: number;
}>();

// ==================== STATE ====================
const step = ref(1);
const isLoading = ref(false);
const error = ref<string | null>(null);
const quoteId = ref<string | null>(null);
const showRecent = ref(true);
const showScanner = ref(false);
const quoteExpiresIn = ref(0);
let quoteTimer: NodeJS.Timeout | null = null;

// Form data
const recipientEmail = ref('');
const recipientData = ref<any>(null);
const selectedSourceWallet = ref<any>(null);
const selectedTargetWallet = ref<any>(null);
const amount = ref<number | null>(null);
const transferDetails = ref<any>(null);

// ==================== COMPUTED ====================
const sourceWallets = computed(() => props.wallets);
const recipientWallets = computed(() => recipientData.value?.wallets || []);

const canProceed = computed(() => {
    if (step.value === 1) return recipientData.value !== null;
    if (step.value === 2) return amount.value && amount.value > 0 && selectedSourceWallet.value && selectedTargetWallet.value && transferDetails.value;
    return true;
});

const formattedFee = computed(() => {
    if (!transferDetails.value) return '';
    return `${transferDetails.value.source_symbol} ${transferDetails.value.fee.toFixed(2)}`;
});

const formattedTotal = computed(() => {
    if (!transferDetails.value) return '';
    return `${transferDetails.value.source_symbol} ${transferDetails.value.total.toFixed(2)}`;
});

const formattedConvertedAmount = computed(() => {
    if (!transferDetails.value || !transferDetails.value.is_cross_currency) return '';
    return `${transferDetails.value.target_symbol} ${transferDetails.value.converted_amount.toFixed(2)}`;
});

const recipientInitials = computed(() => {
    if (!recipientData.value) return '';
    return recipientData.value.name
        .split(' ')
        .map((n: string) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
});

// ==================== METHODS ====================

/**
 * Navigate back - either to previous step or dashboard
 */
const goBack = () => {
    if (step.value > 1) {
        step.value--;
    } else {
        router.visit('/dashboard');
    }
};

/**
 * Find recipient by email address
 */
const findRecipient = async () => {
    if (!recipientEmail.value) {
        error.value = 'Please enter an email address';
        return;
    }
    
    isLoading.value = true;
    error.value = null;
    
    try {
        const response = await fetch('/transfers/get-recipient', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ email: recipientEmail.value }),
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Recipient not found');
        }
        
        recipientData.value = data;
        // Auto-select the recipient's default wallet
        selectedTargetWallet.value = data.default_currency;
        step.value = 2;
        
    } catch (err: any) {
        error.value = err.message;
    } finally {
        isLoading.value = false;
    }
};

/**
 * Select a recent recipient
 */
const selectRecentRecipient = (recip: any) => {
    recipientEmail.value = recip.email;
    findRecipient();
};

/**
 * Calculate transfer details (exchange rate, fees, etc.)
 */
const calculateTransfer = async () => {
    if (!selectedSourceWallet.value || !amount.value || !selectedTargetWallet.value) return;
    
    isLoading.value = true;
    error.value = null;
    
    try {
        const response = await fetch('/transfers/calculate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                amount: amount.value,
                source_wallet_id: selectedSourceWallet.value.id,
                target_currency: selectedTargetWallet.value.currency_code,
            }),
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Failed to calculate transfer');
        }
        
        transferDetails.value = data;
        
    } catch (err: any) {
        error.value = err.message;
    } finally {
        isLoading.value = false;
    }
};

/**
 * Create a rate-locked quote for the transfer
 */
const createQuote = async () => {
    if (!selectedSourceWallet.value || !amount.value || !recipientData.value || !selectedTargetWallet.value) return;
    
    isLoading.value = true;
    error.value = null;
    
    try {
        const response = await fetch('/transfers/create-quote', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                recipient_id: recipientData.value.id,
                source_wallet_id: selectedSourceWallet.value.id,
                recipient_wallet_id: selectedTargetWallet.value.id,
                amount: amount.value,
            }),
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Failed to create quote');
        }
        
        quoteId.value = data.quote_id;
        quoteExpiresIn.value = data.expires_in;
        
        // Start countdown timer
        if (quoteTimer) clearInterval(quoteTimer);
        quoteTimer = setInterval(() => {
            if (quoteExpiresIn.value > 0) {
                quoteExpiresIn.value--;
            } else {
                if (quoteTimer) clearInterval(quoteTimer);
            }
        }, 1000);
        
        step.value = 3;
        
    } catch (err: any) {
        error.value = err.message;
    } finally {
        isLoading.value = false;
    }
};

/**
 * Execute the transfer
 */
const executeTransfer = async () => {
    if (!quoteId.value) return;
    
    isLoading.value = true;
    error.value = null;
    
    try {
        const response = await fetch('/transfers/execute', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ quote_id: quoteId.value }),
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Transfer failed');
        }
        
        // Redirect to success page
        if (data.redirect_url) {
            window.location.href = data.redirect_url;
        } else {
            router.visit('/dashboard');
        }
        
    } catch (err: any) {
        error.value = err.message;
        isLoading.value = false;
    }
};

// Clean up timer on unmount
onUnmounted(() => {
    if (quoteTimer) clearInterval(quoteTimer);
});

// Watch for changes to recalculate transfer details
watch([selectedSourceWallet, amount, selectedTargetWallet], () => {
    if (selectedSourceWallet.value && amount.value && amount.value >= 1 && selectedTargetWallet.value) {
        calculateTransfer();
    }
});

// Auto-select default wallet on mount
onMounted(() => {
    const defaultWallet = props.wallets.find(w => w.is_default);
    if (defaultWallet) {
        selectedSourceWallet.value = defaultWallet;
    }
});
</script>

<template>
    <Head title="Send Money" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
        <div class="mx-auto max-w-4xl px-4 py-4 sm:px-6 sm:py-6">
            
            <!-- Header -->
            <div class="mb-6 flex items-center gap-3">
                <button 
                    @click="goBack"
                    class="rounded-xl p-2 text-gray-500 transition-all hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800"
                >
                    <ArrowLeft class="h-5 w-5" />
                </button>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">
                        Send Money
                    </h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Fast and secure transfers
                    </p>
                </div>
                
                <!-- Scan to Pay Button (Future Feature) -->
                <button 
                    v-if="step === 1"
                    @click="showScanner = true"
                    class="ml-auto rounded-xl border border-gray-200 px-4 py-2 text-sm text-gray-600 transition-all hover:bg-gray-100 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800"
                >
                    <Smartphone class="mr-2 inline h-4 w-4" />
                    Scan to Pay
                </button>
            </div>

            <!-- Step Progress -->
            <div class="mb-6 flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-1 items-center">
                    <div :class="[
                        'flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold',
                        step >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-800'
                    ]">1</div>
                    <div class="ml-2 text-xs font-medium text-gray-600 dark:text-gray-400">Recipient</div>
                </div>
                <div class="h-px flex-1 mx-2 bg-gray-200 dark:bg-gray-800"></div>
                <div class="flex flex-1 items-center">
                    <div :class="[
                        'flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold',
                        step >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-800'
                    ]">2</div>
                    <div class="ml-2 text-xs font-medium text-gray-600 dark:text-gray-400">Amount</div>
                </div>
                <div class="h-px flex-1 mx-2 bg-gray-200 dark:bg-gray-800"></div>
                <div class="flex flex-1 items-center">
                    <div :class="[
                        'flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold',
                        step >= 3 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-800'
                    ]">3</div>
                    <div class="ml-2 text-xs font-medium text-gray-600 dark:text-gray-400">Confirm</div>
                </div>
            </div>

            <!-- ==================== STEP 1: Recipient ==================== -->
            <div v-show="step === 1" class="space-y-4">
                <!-- Search Input -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Recipient's Email
                    </label>
                    <div class="relative">
                        <Mail class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input 
                            v-model="recipientEmail"
                            type="email"
                            class="w-full rounded-xl border border-gray-200 py-3 pl-10 pr-4 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            placeholder="friend@example.com"
                            @keyup.enter="findRecipient"
                        />
                    </div>
                    <button 
                        @click="findRecipient"
                        :disabled="isLoading || !recipientEmail"
                        class="mt-4 w-full rounded-xl bg-gray-900 py-3 font-medium text-white transition-all hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-700 dark:hover:bg-gray-600"
                    >
                        <Loader2 v-if="isLoading" class="mx-auto h-5 w-5 animate-spin" />
                        <span v-else>Continue</span>
                    </button>
                </div>

                <!-- Recent Recipients -->
                <div v-if="recentRecipients.length > 0" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Recent Recipients</h3>
                        <button @click="showRecent = !showRecent" class="text-xs text-blue-600">
                            {{ showRecent ? 'Hide' : 'Show' }}
                        </button>
                    </div>
                    <div v-show="showRecent" class="space-y-2">
                        <button
                            v-for="recip in recentRecipients"
                            :key="recip.id"
                            @click="selectRecentRecipient(recip)"
                            class="flex w-full items-center gap-3 rounded-xl p-3 transition-all hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-600 text-sm font-medium text-white">
                                {{ recip.avatar }}
                            </div>
                            <div class="flex-1 text-left">
                                <p class="font-medium text-gray-900 dark:text-white">{{ recip.name }}</p>
                                <p class="text-xs text-gray-500">{{ recip.email }}</p>
                            </div>
                            <ChevronRight class="h-4 w-4 text-gray-400" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- ==================== STEP 2: Amount ==================== -->
            <div v-show="step === 2" class="space-y-4">
                
                <!-- RECIPIENT SUMMARY CARD (New - shows recipient details clearly) -->
                <div class="rounded-2xl bg-gradient-to-r from-blue-50 to-indigo-50 p-5 dark:from-blue-950/30 dark:to-indigo-950/30">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-lg">
                            <User class="h-7 w-7" />
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-blue-600 dark:text-blue-400">Sending to</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ recipientData?.name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ recipientData?.email }}</p>
                        </div>
                        <button 
                            @click="step = 1" 
                            class="rounded-lg px-3 py-1.5 text-xs font-medium text-blue-600 transition-all hover:bg-blue-100 dark:text-blue-400 dark:hover:bg-blue-900/30"
                        >
                            Change
                        </button>
                    </div>
                    <div class="mt-3 flex items-center gap-2 border-t border-blue-200 pt-3 dark:border-blue-800">
                        <DollarSign class="h-4 w-4 text-blue-500" />
                        <span class="text-sm text-gray-600 dark:text-gray-300">Will receive in <strong>{{ selectedTargetWallet?.currency_code }}</strong></span>
                    </div>
                </div>

                <!-- Amount Input -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Amount to send
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xl font-semibold text-gray-500">
                            {{ selectedSourceWallet?.currency_symbol }}
                        </span>
                        <input 
                            v-model.number="amount"
                            type="number"
                            step="0.01"
                            min="1"
                            class="w-full rounded-xl border border-gray-200 py-3 pl-12 pr-4 text-xl font-semibold focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            placeholder="0.00"
                        />
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button 
                            v-for="suggested in [10, 25, 50, 100, 250]"
                            :key="suggested"
                            @click="amount = suggested"
                            class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs transition-all hover:border-blue-500 hover:bg-blue-50 dark:border-gray-700 dark:hover:bg-blue-900/20"
                        >
                            {{ selectedSourceWallet?.currency_symbol }}{{ suggested }}
                        </button>
                    </div>
                </div>

                <!-- From Wallet -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="mb-2 text-xs text-gray-500">From</p>
                    <div class="space-y-2">
                        <button
                            v-for="wallet in sourceWallets"
                            :key="wallet.id"
                            @click="selectedSourceWallet = wallet"
                            class="flex w-full items-center justify-between rounded-xl p-3 transition-all"
                            :class="selectedSourceWallet?.id === wallet.id 
                                ? 'bg-blue-50 dark:bg-blue-900/20 ring-1 ring-blue-500' 
                                : 'hover:bg-gray-50 dark:hover:bg-gray-800'"
                        >
                            <div class="flex items-center gap-3">
                                <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-800">
                                    <CircleDollarSign class="h-5 w-5 text-gray-600" />
                                </div>
                                <div class="text-left">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ wallet.currency_code }} Wallet</p>
                                    <p class="text-xs text-gray-500">{{ wallet.formatted_balance }} available</p>
                                </div>
                            </div>
                            <CheckCircle v-if="selectedSourceWallet?.id === wallet.id" class="h-5 w-5 text-blue-500" />
                        </button>
                    </div>
                </div>

                <!-- Recipient Currency Selection -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="mb-2 text-xs text-gray-500">Recipient receives in</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="wallet in recipientWallets"
                            :key="wallet.id"
                            @click="selectedTargetWallet = wallet"
                            class="flex-1 rounded-xl p-3 text-center transition-all"
                            :class="selectedTargetWallet?.id === wallet.id 
                                ? 'bg-blue-50 dark:bg-blue-900/20 ring-1 ring-blue-500' 
                                : 'bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700'"
                        >
                            <p class="font-medium text-gray-900 dark:text-white">{{ wallet.currency_code }}</p>
                            <p class="text-xs text-gray-500">{{ wallet.currency_symbol }}</p>
                        </button>
                    </div>
                </div>

                <!-- Transfer Details -->
                <div v-if="transferDetails" class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                    <div class="space-y-2">
                        <div v-if="transferDetails.is_cross_currency" class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Exchange rate</span>
                            <span class="font-medium text-gray-900 dark:text-white">
                                1 {{ transferDetails.source_currency }} = {{ transferDetails.rate }} {{ transferDetails.target_currency }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Recipient gets</span>
                            <span class="font-medium text-emerald-600">
                                {{ formattedConvertedAmount }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Transfer fee ({{ transferDetails.fee_percentage }}%)</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ formattedFee }}</span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold dark:border-gray-700">
                            <span class="text-gray-900 dark:text-white">Total to debit</span>
                            <span class="text-blue-600">{{ formattedTotal }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            Remaining balance: {{ transferDetails.source_symbol }} {{ transferDetails.remaining_balance.toFixed(2) }}
                        </div>
                    </div>
                </div>

                <!-- Error -->
                <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                    <div class="flex items-start gap-2">
                        <AlertCircle class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-600" />
                        <p class="text-sm text-red-700">{{ error }}</p>
                    </div>
                </div>

                <!-- Continue Button -->
                <button 
                    @click="createQuote"
                    :disabled="!canProceed || isLoading"
                    class="w-full rounded-xl bg-gray-900 py-3 font-semibold text-white transition-all hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-700 dark:hover:bg-gray-600"
                >
                    <Loader2 v-if="isLoading" class="mx-auto h-5 w-5 animate-spin" />
                    <span v-else>Continue</span>
                </button>
            </div>

            <!-- ==================== STEP 3: Confirm ==================== -->
            <div v-show="step === 3" class="space-y-4">
                <!-- RECIPIENT CARD (New - shows recipient details for verification) -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Transfer Details</h3>
                        <div class="flex items-center gap-1 text-xs text-gray-500">
                            <Clock class="h-3 w-3" />
                            <span :class="{ 'text-red-500': quoteExpiresIn < 10 }">
                                Rate locked for {{ quoteExpiresIn }}s
                            </span>
                        </div>
                    </div>
                    
                    <!-- Recipient Info Box -->
                    <div class="mb-4 rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-600 text-white">
                                {{ recipientInitials }}
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Recipient</p>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ recipientData?.name }}</p>
                                <p class="text-xs text-gray-500">{{ recipientData?.email }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                            <span class="text-sm text-gray-500">From</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedSourceWallet?.currency_code }} Wallet</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                            <span class="text-sm text-gray-500">Amount</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedSourceWallet?.currency_symbol }}{{ amount?.toFixed(2) }}</span>
                        </div>
                        <div v-if="transferDetails?.is_cross_currency" class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                            <span class="text-sm text-gray-500">Recipient gets</span>
                            <span class="text-sm font-medium text-emerald-600">{{ formattedConvertedAmount }}</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                            <span class="text-sm text-gray-500">Fee</span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ formattedFee }}</span>
                        </div>
                        <div class="flex justify-between pt-2">
                            <span class="font-semibold text-gray-900 dark:text-white">Total</span>
                            <span class="font-bold text-blue-600">{{ formattedTotal }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <Shield class="h-4 w-4" />
                        <span>Protected and encrypted transfer</span>
                    </div>
                    <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                        <Info class="h-3 w-3" />
                        <span>Once confirmed, this transfer cannot be reversed</span>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button 
                        @click="step = 2"
                        class="flex-1 rounded-xl border border-gray-300 py-3 font-medium transition-all hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        Back
                    </button>
                    <button 
                        @click="executeTransfer"
                        :disabled="isLoading || quoteExpiresIn === 0"
                        class="flex-1 rounded-xl bg-emerald-600 py-3 font-semibold text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <Loader2 v-if="isLoading" class="mx-auto h-5 w-5 animate-spin" />
                        <span v-else>Confirm & Send</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Smooth transitions */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 200ms;
}

/* Hide number input arrows */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    opacity: 0.5;
}

/* Better touch targets on mobile */
@media (max-width: 640px) {
    button, [role="button"] {
        min-height: 44px;
    }
}
</style>