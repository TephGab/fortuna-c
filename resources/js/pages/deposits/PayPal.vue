<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { 
    ArrowLeft, 
    Wallet, 
    Lock, 
    Shield, 
    Loader2, 
    AlertCircle, 
    Info,
    CheckCircle,
    X
} from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

// ==================== STATE ====================
const amount = ref<number | null>(null);
const isProcessing = ref(false);
const error = ref<string | null>(null);
const success = ref(false);

// ==================== COMPUTED ====================
const isValidAmount = computed(() => {
    return amount.value && amount.value >= 10 && amount.value <= 5000;
});

const feeAmount = computed(() => {
    return amount.value ? amount.value * 0.035 : 0;
});

const totalAmount = computed(() => {
    return amount.value ? amount.value * 1.035 : 0;
});

const formattedTotal = computed(() => {
    return `$${totalAmount.value.toFixed(2)}`;
});

const suggestedAmounts = [25, 50, 100, 250, 500];

// ==================== METHODS ====================
const goBack = () => {
    router.visit('/deposits');
};

const handleDeposit = async () => {
    if (!amount.value || amount.value < 10) {
        error.value = t('Minimum deposit amount is $10');
        return;
    }
    
    if (amount.value > 5000) {
        error.value = t('Maximum deposit amount is $5,000');
        return;
    }
    
    isProcessing.value = true;
    error.value = null;
    
    try {
        const response = await fetch('/deposits/paypal/create-order', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ amount: amount.value }),
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || t('Failed to create PayPal order'));
        }
        
        if (data.approval_url) {
            // Redirect to PayPal to complete the payment
            window.location.href = data.approval_url;
        } else {
            throw new Error(t('No approval URL received'));
        }
        
    } catch (err: any) {
        console.error('PayPal error:', err);
        error.value = err.message || t('Payment failed. Please try again.');
        isProcessing.value = false;
    }
};
</script>

<template>
    <Head :title="t('PayPal Deposit')" />

    <div class="flex h-full flex-1 flex-col overflow-y-auto p-4 sm:p-6">
        
        <!-- Header -->
        <div class="mb-6 flex items-center gap-4">
            <button 
                @click="goBack"
                class="group flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 shadow-sm transition-all hover:border-gray-300 hover:text-gray-900 hover:shadow dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" />
            </button>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ t('PayPal Deposit') }}</h1>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ t('Secure deposit with your PayPal account') }}</p>
            </div>
        </div>

        <!-- Success Overlay (if needed) -->
        <Transition
            enter-active-class="transition-all duration-500 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition-all duration-300 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="success" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                <div class="mx-4 w-full max-w-sm rounded-2xl bg-white p-8 text-center shadow-2xl dark:bg-gray-900">
                    <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                        <CheckCircle class="h-10 w-10 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <h2 class="mb-2 text-2xl font-bold text-emerald-900 dark:text-emerald-300">
                        {{ t('Deposit Successful!') }}
                    </h2>
                    <p class="mb-2 text-gray-600 dark:text-gray-300">
                        {{ formattedTotal }} {{ t('added to your wallet') }}
                    </p>
                    <p class="text-sm text-gray-500">{{ t('Redirecting to dashboard...') }}</p>
                </div>
            </div>
        </Transition>

        <!-- Main Content -->
        <div class="mx-auto w-full max-w-md">
            
            <!-- PayPal Badge -->
            <div class="mb-6 flex items-center justify-center gap-2 rounded-full bg-blue-50 px-4 py-2 dark:bg-blue-900/20">
                <Wallet class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                <span class="text-sm font-medium text-blue-700 dark:text-blue-300">{{ t('PayPal Checkout') }}</span>
            </div>

            <!-- Amount Card -->
            <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ t('Deposit Amount') }}
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-2xl font-semibold text-gray-400">$</span>
                    <input 
                        v-model.number="amount"
                        type="number"
                        step="10"
                        class="w-full rounded-xl border border-gray-200 p-4 pl-10 text-2xl font-semibold focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="0.00"
                    />
                </div>
                
                <!-- Suggested Amounts -->
                <div class="mt-4 flex flex-wrap gap-2">
                    <button 
                        v-for="suggested in suggestedAmounts" 
                        :key="suggested"
                        type="button"
                        @click="amount = suggested"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium transition-all hover:border-emerald-500 hover:bg-emerald-50 dark:border-gray-700 dark:hover:border-emerald-500 dark:hover:bg-emerald-900/20"
                    >
                        ${{ suggested }}
                    </button>
                </div>
                
                <!-- Limits Info -->
                <div class="mt-4 flex items-center gap-2 text-xs text-gray-500">
                    <AlertCircle class="h-3 w-3" />
                    <span>{{ t('Min $10 · Max $5,000') }}</span>
                </div>
            </div>

            <!-- Fee Information -->
            <div class="mb-6 rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/50">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">{{ t('Deposit amount') }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">${{ amount || 0 }}</span>
                </div>
                <div class="mt-2 flex justify-between text-sm">
                    <div class="flex items-center gap-1">
                        <span class="text-gray-600 dark:text-gray-400">{{ t('Processing fee') }} (3.5%)</span>
                        <Info class="h-3 w-3 text-gray-400" />
                    </div>
                    <span class="font-medium text-gray-900 dark:text-white">${{ feeAmount.toFixed(2) }}</span>
                </div>
                <div class="mt-3 border-t border-gray-200 pt-2 flex justify-between font-semibold dark:border-gray-700">
                    <span class="text-gray-900 dark:text-white">{{ t('Total charged') }}</span>
                    <span class="text-blue-600 dark:text-blue-400">${{ totalAmount.toFixed(2) }}</span>
                </div>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                <div class="flex items-start gap-3">
                    <AlertCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" />
                    <p class="flex-1 text-sm text-red-700 dark:text-red-400">{{ error }}</p>
                    <button @click="error = null" class="rounded-lg p-1 hover:bg-red-100 dark:hover:bg-red-800/50">
                        <X class="h-4 w-4 text-red-600" />
                    </button>
                </div>
            </div>

            <!-- Security Badges -->
            <div class="mb-6 flex items-center justify-center gap-4">
                <div class="flex items-center gap-1">
                    <Lock class="h-3 w-3 text-emerald-600" />
                    <span class="text-xs text-gray-600 dark:text-gray-400">256-bit SSL</span>
                </div>
                <div class="flex items-center gap-1">
                    <Shield class="h-3 w-3 text-emerald-600" />
                    <span class="text-xs text-gray-600 dark:text-gray-400">PCI Compliant</span>
                </div>
                <div class="flex items-center gap-1">
                    <Wallet class="h-3 w-3 text-blue-600" />
                    <span class="text-xs text-gray-600 dark:text-gray-400">PayPal Secure</span>
                </div>
            </div>

            <!-- Submit Button -->
            <button 
                @click="handleDeposit"
                :disabled="!isValidAmount || isProcessing"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#0070ba] py-4 text-lg font-semibold text-white transition-all hover:bg-[#003087] disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <Loader2 v-if="isProcessing" class="h-5 w-5 animate-spin" />
                <Wallet v-else class="h-5 w-5" />
                {{ isProcessing ? t('Processing...') : t('Pay with PayPal') }}
            </button>

            <p class="mt-4 text-center text-xs text-gray-500">
                {{ t('You will be redirected to PayPal to complete your payment.') }}
            </p>
        </div>
    </div>
</template>

<style scoped>
/* Smooth transitions (same as other deposit pages) */
button {
    min-height: 44px;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}
</style>