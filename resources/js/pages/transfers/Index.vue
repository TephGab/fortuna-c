<script setup lang="ts">
/**
 * Send Money Component - Mobile First Design
 * 
 * Features:
 * - Native mobile app feel
 * - Bottom sheet style step progress
 * - Swipe to navigate between steps
 * - Large touch targets
 * - Smooth animations
 */

import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { 
    ArrowLeft, 
    Send, 
    User, 
    Mail, 
    CheckCircle,
    AlertCircle,
    Loader2,
    ChevronRight,
    Wallet,
    Clock,
    Shield,
    Smartphone,
    CircleDollarSign,
    Info,
    DollarSign,
    X,
    ChevronLeft
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
const quoteExpiresIn = ref(0);
let quoteTimer: NodeJS.Timeout | null = null;

// Form data
const recipientEmail = ref('');
const recipientData = ref<any>(null);
const selectedSourceWallet = ref<any>(null);
const selectedTargetWallet = ref<any>(null);
const amount = ref<number | null>(null);
const transferDetails = ref<any>(null);

// Touch/swipe for mobile
const touchStartX = ref<number | null>(null);

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

const progressPercent = computed(() => {
    return (step.value / 3) * 100;
});

// ==================== METHODS ====================

const goBack = () => {
    if (step.value > 1) {
        step.value--;
    } else {
        router.visit('/dashboard');
    }
};

// Swipe handlers for mobile
const handleTouchStart = (e: TouchEvent) => {
    touchStartX.value = e.touches[0].clientX;
};

const handleTouchEnd = (e: TouchEvent) => {
    if (!touchStartX.value) return;
    const deltaX = e.changedTouches[0].clientX - touchStartX.value;
    
    if (Math.abs(deltaX) > 50) {
        if (deltaX > 0 && step.value > 1) {
            // Swipe right - go back
            step.value--;
        } else if (deltaX < 0 && step.value < 3 && canProceed) {
            // Swipe left - go next
            if (step.value === 1 && recipientData.value) {
                step.value++;
            } else if (step.value === 2 && canProceed.value) {
                createQuote();
            }
        }
    }
    touchStartX.value = null;
};

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
        selectedTargetWallet.value = data.default_currency;
        step.value = 2;
        
    } catch (err: any) {
        error.value = err.message;
    } finally {
        isLoading.value = false;
    }
};

const selectRecentRecipient = (recip: any) => {
    recipientEmail.value = recip.email;
    findRecipient();
};

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

// Clean up
onUnmounted(() => {
    if (quoteTimer) clearInterval(quoteTimer);
    const container = document.querySelector('.send-money-container');
    if (container) {
        container.removeEventListener('touchstart', handleTouchStart);
        container.removeEventListener('touchend', handleTouchEnd);
    }
});

// Watchers
watch([selectedSourceWallet, amount, selectedTargetWallet], () => {
    if (selectedSourceWallet.value && amount.value && amount.value >= 1 && selectedTargetWallet.value) {
        calculateTransfer();
    }
});

// Mount
onMounted(() => {
    const defaultWallet = props.wallets.find(w => w.is_default);
    if (defaultWallet) {
        selectedSourceWallet.value = defaultWallet;
    }
    
    const container = document.querySelector('.send-money-container');
    if (container) {
        container.addEventListener('touchstart', handleTouchStart);
        container.addEventListener('touchend', handleTouchEnd);
    }
});
</script>

<template>
    <Head title="Send Money" />

    <div class="send-money-container min-h-screen bg-gray-50 dark:bg-gray-950">
        
        <!-- Header - Mobile First -->
        <div class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-950">
            <div class="flex items-center justify-between px-4 pt-4 pb-2">
                <button 
                    @click="goBack"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-sm dark:bg-gray-900"
                >
                    <ArrowLeft class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                </button>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Send Money</h1>
                <div class="w-10"></div>
            </div>
            
            <!-- Progress Bar -->
            <div class="px-4 pb-3">
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                    <div 
                        class="h-full rounded-full bg-blue-600 transition-all duration-300"
                        :style="{ width: `${progressPercent}%` }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Main Content - Card Swipe Style -->
        <div class="px-4 py-4">
            
            <!-- STEP 1: Recipient -->
            <div v-show="step === 1" class="animate-fadeIn">
                <!-- Title -->
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Who are you sending to?</h2>
                    <p class="mt-1 text-sm text-gray-500">Enter their email address</p>
                </div>
                
                <!-- Search Input -->
                <div class="mb-6">
                    <div class="relative">
                        <Mail class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                        <input 
                            v-model="recipientEmail"
                            type="email"
                            class="h-14 w-full rounded-xl border border-gray-200 pl-12 pr-4 text-lg focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            placeholder="friend@example.com"
                            @keyup.enter="findRecipient"
                        />
                    </div>
                </div>
                
                <button 
                    @click="findRecipient"
                    :disabled="isLoading || !recipientEmail"
                    class="mb-8 h-14 w-full rounded-xl bg-blue-600 font-semibold text-white transition-all active:scale-95 disabled:opacity-50"
                >
                    <Loader2 v-if="isLoading" class="mx-auto h-5 w-5 animate-spin" />
                    <span v-else>Continue</span>
                </button>

                <!-- Recent Recipients -->
                <div v-if="recentRecipients.length > 0">
                    <p class="mb-3 text-sm text-gray-500">Recent</p>
                    <div class="space-y-2">
                        <button
                            v-for="recip in recentRecipients"
                            :key="recip.id"
                            @click="selectRecentRecipient(recip)"
                            class="flex w-full items-center gap-3 rounded-xl bg-white p-3 transition-all active:scale-98 dark:bg-gray-900"
                        >
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-600 text-base font-medium text-white">
                                {{ recip.avatar }}
                            </div>
                            <div class="flex-1 text-left">
                                <p class="font-medium text-gray-900 dark:text-white">{{ recip.name }}</p>
                                <p class="text-sm text-gray-500">{{ recip.email }}</p>
                            </div>
                            <ChevronRight class="h-5 w-5 text-gray-400" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Amount -->
            <div v-show="step === 2" class="animate-fadeIn">
                <!-- Title -->
                <div class="mb-4">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">How much?</h2>
                    <p class="mt-1 text-sm text-gray-500">Enter the amount to send</p>
                </div>
                
                <!-- Recipient Summary -->
                <div class="mb-6 flex items-center justify-between rounded-xl bg-blue-50 p-4 dark:bg-blue-950/30">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-500 text-white">
                            <User class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-xs text-blue-600 dark:text-blue-400">Sending to</p>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ recipientData?.name }}</p>
                        </div>
                    </div>
                    <button @click="step = 1" class="text-sm text-blue-600">Change</button>
                </div>
                
                <!-- Amount Input -->
                <div class="mb-6">
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-2xl font-semibold text-gray-400">
                            {{ selectedSourceWallet?.currency_symbol }}
                        </span>
                        <input 
                            v-model.number="amount"
                            type="number"
                            step="0.01"
                            min="1"
                            class="h-16 w-full rounded-xl border border-gray-200 pl-12 pr-4 text-2xl font-semibold focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            placeholder="0"
                        />
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button 
                            v-for="suggested in [10, 25, 50, 100]"
                            :key="suggested"
                            @click="amount = suggested"
                            class="flex-1 rounded-lg border border-gray-200 py-2 text-sm transition-all active:scale-95 dark:border-gray-700"
                        >
                            {{ selectedSourceWallet?.currency_symbol }}{{ suggested }}
                        </button>
                    </div>
                </div>

                <!-- From Wallet -->
                <div class="mb-4">
                    <p class="mb-2 text-sm text-gray-500">From</p>
                    <div class="space-y-2">
                        <button
                            v-for="wallet in sourceWallets"
                            :key="wallet.id"
                            @click="selectedSourceWallet = wallet"
                            class="flex w-full items-center justify-between rounded-xl bg-white p-4 transition-all active:scale-98 dark:bg-gray-900"
                            :class="selectedSourceWallet?.id === wallet.id ? 'ring-2 ring-blue-500' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-800">
                                    <CircleDollarSign class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="font-medium">{{ wallet.currency_code }}</p>
                                    <p class="text-sm text-gray-500">{{ wallet.formatted_balance }}</p>
                                </div>
                            </div>
                            <CheckCircle v-if="selectedSourceWallet?.id === wallet.id" class="h-5 w-5 text-blue-500" />
                        </button>
                    </div>
                </div>

                <!-- Transfer Summary -->
                <div v-if="transferDetails" class="mb-6 rounded-xl bg-gray-100 p-4 dark:bg-gray-800">
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Recipient gets</span>
                            <span class="font-semibold text-emerald-600">{{ formattedConvertedAmount || formattedTotal }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Fee ({{ transferDetails.fee_percentage }}%)</span>
                            <span class="text-gray-700">{{ formattedFee }}</span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-bold dark:border-gray-700">
                            <span>Total to pay</span>
                            <span class="text-blue-600">{{ formattedTotal }}</span>
                        </div>
                    </div>
                </div>

                <button 
                    @click="createQuote"
                    :disabled="!canProceed || isLoading"
                    class="h-14 w-full rounded-xl bg-blue-600 font-semibold text-white transition-all active:scale-95 disabled:opacity-50"
                >
                    <Loader2 v-if="isLoading" class="mx-auto h-5 w-5 animate-spin" />
                    <span v-else>Continue</span>
                </button>
            </div>

            <!-- STEP 3: Confirm -->
            <div v-show="step === 3" class="animate-fadeIn">
                <!-- Title -->
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Confirm transfer</h2>
                    <p class="mt-1 text-sm text-gray-500">Review details before sending</p>
                </div>
                
                <!-- Rate Lock Timer -->
                <div class="mb-4 flex items-center justify-between rounded-xl bg-amber-50 p-3 dark:bg-amber-950/30">
                    <div class="flex items-center gap-2">
                        <Clock class="h-4 w-4 text-amber-600" />
                        <span class="text-sm text-amber-700 dark:text-amber-400">Rate locked for</span>
                    </div>
                    <span class="font-mono text-lg font-bold text-amber-700 dark:text-amber-400">{{ quoteExpiresIn }}s</span>
                </div>
                
                <!-- Transfer Details Card -->
                <div class="mb-6 rounded-xl bg-white p-5 dark:bg-gray-900">
                    <!-- Recipient -->
                    <div class="mb-4 flex items-center gap-3 border-b border-gray-100 pb-4 dark:border-gray-800">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-600 text-white">
                            {{ recipientInitials }}
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Recipient</p>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ recipientData?.name }}</p>
                            <p class="text-sm text-gray-500">{{ recipientData?.email }}</p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-500">From</span>
                            <span class="font-medium">{{ selectedSourceWallet?.currency_code }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Amount</span>
                            <span class="font-medium">{{ selectedSourceWallet?.currency_symbol }}{{ amount?.toFixed(2) }}</span>
                        </div>
                        <div v-if="transferDetails?.is_cross_currency" class="flex justify-between">
                            <span class="text-gray-500">Recipient gets</span>
                            <span class="font-medium text-emerald-600">{{ formattedConvertedAmount }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Fee</span>
                            <span class="font-medium">{{ formattedFee }}</span>
                        </div>
                        <div class="flex justify-between border-t border-gray-100 pt-3 text-lg font-bold dark:border-gray-800">
                            <span>Total</span>
                            <span class="text-blue-600">{{ formattedTotal }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Warning -->
                <div class="mb-6 flex items-center gap-2 rounded-xl bg-gray-100 p-3 dark:bg-gray-800">
                    <Shield class="h-5 w-5 text-emerald-600" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">This transfer cannot be reversed once confirmed</span>
                </div>

                <div class="flex gap-3">
                    <button 
                        @click="step = 2"
                        class="h-14 flex-1 rounded-xl border border-gray-300 font-medium transition-all active:scale-95 dark:border-gray-700"
                    >
                        Back
                    </button>
                    <button 
                        @click="executeTransfer"
                        :disabled="isLoading || quoteExpiresIn === 0"
                        class="h-14 flex-1 rounded-xl bg-emerald-600 font-semibold text-white transition-all active:scale-95 disabled:opacity-50"
                    >
                        <Loader2 v-if="isLoading" class="mx-auto h-5 w-5 animate-spin" />
                        <span v-else>Send {{ formattedTotal }}</span>
                    </button>
                </div>
            </div>
            
            <!-- Error Display -->
            <div v-if="error" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                <div class="flex items-start gap-2">
                    <AlertCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600" />
                    <p class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
                    <button @click="error = null" class="ml-auto rounded-lg p-1 hover:bg-red-100">
                        <X class="h-4 w-4 text-red-600" />
                    </button>
                </div>
            </div>
            
            <!-- Swipe Hint -->
            <div v-if="step < 3 && step === 1 && !recipientData" class="mt-6 text-center">
                <p class="text-xs text-gray-400">← Swipe back • Continue →</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-fadeIn {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.active\:scale-98:active {
    transform: scale(0.98);
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    opacity: 0.5;
}

/* Mobile touch optimizations */
button, [role="button"] {
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}
</style>