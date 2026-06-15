<script setup lang="ts">
/**
 * Send Money Component - Mobile First Design
 * Matches dashboard color scheme - No green, neutral grays only
 * 
 * Supports:
 * - Manual email entry
 * - Recent recipients
 * - QR code scanning (opens scanner modal)
 * - URL parameters for pre-fill (recipient_email, amount, description)
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
    TrendingUp,
    QrCode,
    Scan,
    Upload
} from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';
import QrScannerModal from '@/components/QrScannerModal.vue';

const { t } = useTranslation();

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

// Form data
const recipientEmail = ref('');
const recipientData = ref<any>(null);
const selectedSourceWallet = ref<any>(null);
const selectedTargetWallet = ref<any>(null);
const amount = ref<number | null>(null);
const transferDetails = ref<any>(null);
const prefilledDescription = ref<string | null>(null);

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

// ==================== URL PARAMETER PRE-FILL ====================
/**
 * Parse URL parameters to pre-fill the form when scanning a QR code
 * Supports parameters: recipient_email, amount, description
 */
const parseUrlParameters = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const prefillEmail = urlParams.get('recipient_email');
    const prefillAmount = urlParams.get('amount');
    const prefillDescription = urlParams.get('description');
    
    // Auto-fill recipient email if provided
    if (prefillEmail && prefillEmail !== 'null' && prefillEmail !== '') {
        console.log('Pre-filling recipient email:', prefillEmail);
        recipientEmail.value = prefillEmail;
        
        // Auto-find recipient after a short delay
        setTimeout(() => {
            findRecipient();
        }, 500);
    }
    
    // Auto-fill amount if provided
    if (prefillAmount && prefillAmount !== 'null' && prefillAmount !== '') {
        const parsedAmount = parseFloat(prefillAmount);
        if (!isNaN(parsedAmount) && parsedAmount > 0) {
            console.log('Pre-filling amount:', parsedAmount);
            amount.value = parsedAmount;
        }
    }
    
    // Store description if provided (for reference)
    if (prefillDescription && prefillDescription !== 'null' && prefillDescription !== '') {
        prefilledDescription.value = prefillDescription;
        console.log('Description from QR:', prefillDescription);
    }
};

// ==================== METHODS ====================

const goBack = () => {
    if (step.value > 1) {
        step.value--;
    } else {
        router.visit('/dashboard');
    }
};

const handleTouchStart = (e: TouchEvent) => {
    touchStartX.value = e.touches[0].clientX;
};

const handleTouchEnd = (e: TouchEvent) => {
    if (!touchStartX.value) return;
    const deltaX = e.changedTouches[0].clientX - touchStartX.value;
    
    if (Math.abs(deltaX) > 50) {
        if (deltaX > 0 && step.value > 1) {
            step.value--;
        } else if (deltaX < 0 && step.value < 3 && canProceed) {
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
        error.value = t('Please enter an email address');
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
            throw new Error(data.error || t('Recipient not found'));
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
            throw new Error(data.error || t('Failed to calculate transfer'));
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
            throw new Error(data.error || t('Failed to create quote'));
        }
        
        quoteId.value = data.quote_id;
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
            throw new Error(data.error || t('Transfer failed'));
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

// ==================== QR SCANNER HANDLERS ====================

const openScanner = () => {
    showScanner.value = true;
};

const handleQrDecoded = (data: { type: string; email: string; amount?: number; description?: string }) => {
    if (data.type === 'user' && data.email) {
        recipientEmail.value = data.email;
        
        if (data.amount) {
            amount.value = data.amount;
        }
        
        if (data.description) {
            prefilledDescription.value = data.description;
        }
        
        findRecipient();
    }
};

// ==================== LIFECYCLE ====================

onUnmounted(() => {
    const container = document.querySelector('.send-money-container');
    if (container) {
        container.removeEventListener('touchstart', handleTouchStart);
        container.removeEventListener('touchend', handleTouchEnd);
    }
});

watch([selectedSourceWallet, amount, selectedTargetWallet], () => {
    if (selectedSourceWallet.value && amount.value && amount.value >= 1 && selectedTargetWallet.value) {
        calculateTransfer();
    }
});

onMounted(() => {
    const defaultWallet = props.wallets.find(w => w.is_default);
    if (defaultWallet) {
        selectedSourceWallet.value = defaultWallet;
    }
    
    // Parse URL parameters for QR code pre-fill
    parseUrlParameters();
    
    const container = document.querySelector('.send-money-container');
    if (container) {
        container.addEventListener('touchstart', handleTouchStart);
        container.addEventListener('touchend', handleTouchEnd);
    }
});
</script>

<template>
    <Head :title="t('Send Money')" />

    <div class="send-money-container min-h-screen bg-gray-50 dark:bg-gray-950">
        
        <!-- Header -->
        <div class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-950">
            <div class="flex items-center justify-between px-4 pt-4 pb-2">
                <button 
                    @click="goBack"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-white shadow-sm hover:bg-gray-100 dark:bg-gray-900 dark:hover:bg-gray-800"
                >
                    <ArrowLeft class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                </button>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Send Money') }}</h1>
                <button 
                    @click="openScanner"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-white shadow-sm hover:bg-gray-100 dark:bg-gray-900 dark:hover:bg-gray-800"
                >
                    <Scan class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                </button>
            </div>
            
            <!-- Progress Bar -->
            <div class="px-4 pb-3">
                <div class="h-[3px] w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                    <div 
                        class="h-full rounded-full bg-gray-500 transition-all duration-300 dark:bg-gray-500"
                        :style="{ width: `${progressPercent}%` }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-4 py-4">
            
            <!-- STEP 1: Recipient -->
            <div v-show="step === 1" class="animate-fadeIn">
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Who are you sending to?') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Enter their email address or scan QR code') }}</p>
                </div>
                
                <div class="relative mb-6">
                    <Mail class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input 
                        v-model="recipientEmail"
                        type="email"
                        class="h-14 w-full rounded-xl border border-gray-200 bg-white pl-12 pr-4 text-lg focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:ring-gray-500/20"
                        :placeholder="t('friend@example.com')"
                        @keyup.enter="findRecipient"
                    />
                </div>
                
                <button 
                    @click="findRecipient"
                    :disabled="isLoading || !recipientEmail"
                    class="mb-8 h-14 w-full rounded-xl bg-gray-900 font-semibold text-white transition-all active:scale-95 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-700 dark:hover:bg-gray-600"
                >
                    <Loader2 v-if="isLoading" class="mx-auto h-5 w-5 animate-spin" />
                    <span v-else>{{ t('Continue') }}</span>
                </button>

                <div v-if="recentRecipients.length > 0">
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ t('Recent') }}</p>
                    <div class="space-y-2">
                        <button
                            v-for="recip in recentRecipients"
                            :key="recip.id"
                            @click="selectRecentRecipient(recip)"
                            class="flex w-full items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 transition-all active:scale-98 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800"
                        >
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-200 text-base font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                {{ recip.avatar }}
                            </div>
                            <div class="flex-1 text-left">
                                <p class="font-medium text-gray-900 dark:text-white">{{ recip.name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ recip.email }}</p>
                            </div>
                            <ChevronRight class="h-5 w-5 text-gray-400" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Amount -->
            <div v-show="step === 2" class="animate-fadeIn">
                <div class="mb-4">
                    <h2 class="text-xl font-bold text-gray-500 dark:text-gray-400">{{ t('How much?') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Enter the amount to send') }}</p>
                </div>
                
                <!-- Recipient Summary -->
                <div class="mb-6 flex items-center justify-between rounded-xl bg-gray-100 p-4 dark:bg-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-700 text-white dark:bg-gray-600">
                            <User class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Sending to') }}</p>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ recipientData?.name }}</p>
                        </div>
                    </div>
                    <button @click="step = 1" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300">{{ t('Change') }}</button>
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
                            class="h-16 w-full rounded-xl border border-gray-200 bg-white pl-12 pr-4 text-2xl font-semibold focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            :placeholder="t('0')"
                        />
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button 
                            v-for="suggested in [10, 25, 50, 100]"
                            :key="suggested"
                            @click="amount = suggested"
                            class="flex-1 rounded-lg border border-gray-200 bg-white py-2 text-sm transition-all active:scale-95 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800"
                        >
                            {{ selectedSourceWallet?.currency_symbol }}{{ suggested }}
                        </button>
                    </div>
                </div>

                <!-- From Wallet -->
                <div class="mb-4">
                    <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">{{ t('From') }}</p>
                    <div class="space-y-2">
                        <button
                            v-for="wallet in sourceWallets"
                            :key="wallet.id"
                            @click="selectedSourceWallet = wallet"
                            class="flex w-full items-center justify-between rounded-xl border border-gray-200 bg-white p-4 transition-all active:scale-98 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800"
                            :class="selectedSourceWallet?.id === wallet.id ? 'ring-2 ring-gray-900 dark:ring-gray-500' : ''"
                        >
                            <div class="flex items-center gap-3">
                                <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-800">
                                    <CircleDollarSign class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ wallet.currency_code }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ wallet.formatted_balance }}</p>
                                </div>
                            </div>
                            <CheckCircle v-if="selectedSourceWallet?.id === wallet.id" class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                        </button>
                    </div>
                </div>

                <!-- Transfer Summary -->
                <div v-if="transferDetails" class="mb-6 rounded-xl bg-gray-100 p-4 dark:bg-gray-800">
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ t('Recipient gets') }}</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ formattedConvertedAmount || formattedTotal }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ t('Fee') }} ({{ transferDetails.fee_percentage }}%)</span>
                            <span class="text-gray-700 dark:text-gray-300">{{ formattedFee }}</span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-bold dark:border-gray-700">
                            <span class="text-gray-900 dark:text-white">{{ t('Total to pay') }}</span>
                            <span class="text-gray-900 dark:text-white">{{ formattedTotal }}</span>
                        </div>
                    </div>
                </div>

                <button 
                    @click="createQuote"
                    :disabled="!canProceed || isLoading"
                    class="h-14 w-full rounded-xl bg-gray-900 font-semibold text-white transition-all active:scale-95 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-700 dark:hover:bg-gray-600"
                >
                    <Loader2 v-if="isLoading" class="mx-auto h-5 w-5 animate-spin" />
                    <span v-else>{{ t('Continue') }}</span>
                </button>
            </div>

            <!-- STEP 3: Confirm -->
            <div v-show="step === 3" class="animate-fadeIn">
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-gray-500 dark:text-gray-400">{{ t('Confirm transfer') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Review details before sending') }}</p>
                </div>
                
                <!-- Transfer Details Card -->
                <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <!-- Recipient -->
                    <div class="mb-4 flex items-center gap-3 border-b border-gray-100 pb-4 dark:border-gray-800">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-200 text-base font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            {{ recipientInitials }}
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Recipient') }}</p>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ recipientData?.name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ recipientData?.email }}</p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ t('From') }}</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ selectedSourceWallet?.currency_code }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ t('Amount') }}</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ selectedSourceWallet?.currency_symbol }}{{ amount?.toFixed(2) }}</span>
                        </div>
                        <div v-if="transferDetails?.is_cross_currency" class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ t('Recipient gets') }}</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ formattedConvertedAmount }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ t('Fee') }}</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ formattedFee }}</span>
                        </div>
                        <div class="flex justify-between border-t border-gray-100 pt-3 text-lg font-bold dark:border-gray-800">
                            <span class="text-gray-900 dark:text-white">{{ t('Total') }}</span>
                            <span class="text-gray-900 dark:text-white">{{ formattedTotal }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Warning -->
                <div class="mb-6 flex items-center gap-2 rounded-xl bg-gray-100 p-3 dark:bg-gray-800">
                    <Shield class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ t('This transfer cannot be reversed once confirmed') }}</span>
                </div>

                <div class="flex gap-3">
                    <button 
                        @click="step = 2"
                        class="h-14 flex-1 rounded-xl border border-gray-300 font-medium transition-all active:scale-95 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        {{ t('Back') }}
                    </button>
                    <button 
                        @click="executeTransfer"
                        :disabled="isLoading"
                        class="h-14 flex-1 rounded-xl bg-gray-900 font-semibold text-white transition-all active:scale-95 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-700 dark:hover:bg-gray-600"
                    >
                        <Loader2 v-if="isLoading" class="mx-auto h-5 w-5 animate-spin" />
                        <span v-else>{{ t('Send') }} {{ formattedTotal }}</span>
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
                <p class="text-xs text-gray-400">← {{ t('Swipe back') }} • {{ t('Continue') }} →</p>
            </div>
        </div>
    </div>

    <!-- QR Scanner Modal Component -->
    <QrScannerModal 
        :is-open="showScanner"
        @close="showScanner = false"
        @decoded="handleQrDecoded"
    />
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

button, [role="button"] {
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}
</style>