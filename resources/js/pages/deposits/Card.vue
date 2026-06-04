<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { 
    ArrowLeft, 
    CreditCard,
    Lock,
    Shield,
    Loader2
} from 'lucide-vue-next';

const amount = ref<number | null>(null);
const isProcessing = ref(false);
const error = ref<string | null>(null);

const goBack = () => {
    router.visit('/deposits');
};

const handleDeposit = async () => {
    if (!amount.value || amount.value < 10) {
        error.value = 'Minimum deposit amount is $10';
        return;
    }
    
    if (amount.value > 5000) {
        error.value = 'Maximum deposit amount is $5,000';
        return;
    }
    
    isProcessing.value = true;
    error.value = null;
    
    try {
        const response = await fetch('/deposits/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                amount: amount.value
            })
        });
        
        const data = await response.json();
        
        if (data.url) {
            // Redirect to Stripe Checkout
            window.location.href = data.url;
        } else {
            throw new Error('Failed to create checkout session');
        }
    } catch (err) {
        error.value = 'Something went wrong. Please try again.';
        isProcessing.value = false;
    }
};

const isValidAmount = computed(() => {
    return amount.value && amount.value >= 10 && amount.value <= 5000;
});

const suggestedAmounts = [50, 100, 250, 500];
</script>

<template>
    <Head title="Card Deposit" />

    <div class="flex h-full flex-1 flex-col overflow-y-auto rounded-xl p-4">
        
        <!-- Header -->
        <div class="mb-6 flex items-center gap-4">
            <button 
                @click="goBack"
                class="rounded-lg p-2 text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
            >
                <ArrowLeft class="h-5 w-5" />
            </button>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Credit / Debit Card</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Instant deposit via Stripe</p>
            </div>
        </div>

        <!-- Main content -->
        <div class="mx-auto w-full max-w-md">
            
            <!-- Card Preview -->
            <div class="mb-8 rounded-xl bg-gradient-to-br from-gray-800 to-gray-900 p-6 text-white shadow-lg">
                <div class="flex justify-between">
                    <CreditCard class="h-8 w-8" />
                    <span class="text-xs">Secure Payment</span>
                </div>
                <div class="mt-6">
                    <p class="text-lg font-mono tracking-wider">
                        •••• •••• •••• ••••
                    </p>
                </div>
                <div class="mt-4 flex justify-between">
                    <div>
                        <p class="text-xs text-gray-400">Cardholder</p>
                        <p class="text-sm font-medium uppercase">YOUR NAME</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Expires</p>
                        <p class="text-sm">MM/YY</p>
                    </div>
                </div>
            </div>

            <!-- Deposit Form -->
            <form @submit.prevent="handleDeposit" class="space-y-6">
                <!-- Amount Input -->
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Deposit Amount
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                        <input 
                            v-model.number="amount"
                            type="number"
                            step="10"
                            class="w-full rounded-lg border border-gray-300 p-3 pl-8 text-lg focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            placeholder="0.00"
                        />
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button 
                            v-for="suggested in suggestedAmounts" 
                            :key="suggested"
                            type="button"
                            @click="amount = suggested"
                            class="rounded-full border border-gray-300 px-3 py-1 text-sm transition-all hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
                        >
                            ${{ suggested }}
                        </button>
                    </div>
                </div>

                <!-- Fee Information -->
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900/50">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Deposit amount</span>
                        <span class="font-medium text-gray-900 dark:text-white">
                            ${{ amount || 0 }}
                        </span>
                    </div>
                    <div class="mt-2 flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Processing fee (2.9%)</span>
                        <span class="font-medium text-gray-900 dark:text-white">
                            ${{ (amount ? amount * 0.029 : 0).toFixed(2) }}
                        </span>
                    </div>
                    <div class="mt-3 border-t border-gray-200 pt-2 flex justify-between font-semibold dark:border-gray-700">
                        <span class="text-gray-900 dark:text-white">Total charged</span>
                        <span class="text-gray-900 dark:text-white">
                            ${{ (amount ? amount * 1.029 : 0).toFixed(2) }}
                        </span>
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="error" class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                    {{ error }}
                </div>

                <!-- Security Badges -->
                <div class="flex items-center justify-center gap-4">
                    <div class="flex items-center gap-1">
                        <Lock class="h-3 w-3 text-emerald-600" />
                        <span class="text-xs text-gray-600 dark:text-gray-400">256-bit SSL</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <Shield class="h-3 w-3 text-emerald-600" />
                        <span class="text-xs text-gray-600 dark:text-gray-400">PCI Compliant</span>
                    </div>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit"
                    :disabled="!isValidAmount || isProcessing"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-gray-900 py-3 font-medium text-white transition-all hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-700 dark:hover:bg-gray-600"
                >
                    <Loader2 v-if="isProcessing" class="h-4 w-4 animate-spin" />
                    {{ isProcessing ? 'Processing...' : `Deposit $${amount || 0}` }}
                </button>

                <p class="text-center text-xs text-gray-500">
                    You will be redirected to Stripe's secure payment page.<br>
                    We never store your card details.
                </p>
            </form>
        </div>
    </div>
</template>