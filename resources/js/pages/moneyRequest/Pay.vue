<template>
    <Head :title="t('Pay Money Request')" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
        
        <!-- Header -->
        <div class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-950">
            <div class="flex items-center px-4 pt-4 pb-2">
                <button 
                    @click="router.visit('/dashboard')"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-white shadow-sm hover:bg-gray-100 dark:bg-gray-900 dark:hover:bg-gray-800"
                >
                    <ArrowLeft class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                </button>
                <h1 class="ml-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Pay Money Request') }}</h1>
            </div>
        </div>

        <div class="mx-auto max-w-lg p-4">
            
            <!-- Request Details Card -->
            <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <!-- Requester Info -->
                <div class="mb-6 flex items-center gap-4 border-b border-gray-100 pb-4 dark:border-gray-800">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-200 text-xl font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                        {{ requesterInitials }}
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Request from') }}</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ request.requester.name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ request.requester.email }}</p>
                    </div>
                </div>

                <!-- Amount -->
                <div class="mb-6 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Amount requested') }}</p>
                    <p class="text-4xl font-bold text-gray-900 dark:text-white">
                        {{ request.formatted_amount }}
                    </p>
                    <p v-if="request.description" class="mt-2 text-sm text-gray-500">
                        {{ t('For') }}: {{ request.description }}
                    </p>
                </div>

                <!-- Expiration Warning -->
                <div v-if="isExpiringSoon" class="mb-6 rounded-xl bg-amber-50 p-3 dark:bg-amber-900/20">
                    <div class="flex items-center gap-2">
                        <Clock class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                        <span class="text-sm text-amber-700 dark:text-amber-300">
                            {{ t('This request expires on') }} {{ formattedExpiryDate }}
                        </span>
                    </div>
                </div>

                <!-- Your QR Code (for the payer to scan - optional) -->
                <div v-if="request.qr_code" class="mb-6 text-center">
                    <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Scan to pay') }}</p>
                    <div class="inline-block rounded-xl bg-white p-2 shadow-sm">
                        <img :src="request.qr_code" alt="Payment QR Code" class="h-32 w-32 mx-auto" />
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Pay with') }}</h2>
                
                <form @submit.prevent="processPayment" class="space-y-5">
                    <!-- Amount (readonly, matches request) -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Amount') }}
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                                {{ request.currency_symbol }}
                            </span>
                            <input
                                :value="request.amount"
                                type="text"
                                readonly
                                disabled
                                class="w-full rounded-xl border border-gray-300 bg-gray-100 p-3 pl-8 text-lg font-semibold dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>
                    </div>

                    <!-- Select Wallet -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Select Wallet') }}
                        </label>
                        <select
                            v-model="selectedWalletId"
                            class="w-full rounded-xl border border-gray-300 p-3 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            required
                        >
                            <option value="">{{ t('Select a wallet') }}</option>
                            <option 
                                v-for="wallet in availableWallets" 
                                :key="wallet.id" 
                                :value="wallet.id"
                                :disabled="wallet.currency_code !== request.currency_code"
                            >
                                {{ wallet.currency_code }} - {{ wallet.formatted_balance }}
                                <span v-if="wallet.currency_code !== request.currency_code" class="text-xs text-red-500">
                                    ({{ t('wrong currency') }})
                                </span>
                            </option>
                        </select>
                        <p v-if="selectedWalletError" class="mt-1 text-xs text-red-500">
                            {{ selectedWalletError }}
                        </p>
                    </div>

                    <!-- Wallet Balance Info -->
                    <div v-if="selectedWallet" class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ t('Available balance') }}</span>
                            <span class="font-medium" :class="hasSufficientBalance ? 'text-emerald-600' : 'text-red-600'">
                                {{ selectedWallet.formatted_balance }}
                            </span>
                        </div>
                        <div v-if="!hasSufficientBalance" class="mt-2 text-xs text-red-500">
                            {{ t('Insufficient balance. Please select another wallet.') }}
                        </div>
                    </div>

                    <!-- Error Message -->
                    <div v-if="errorMessage" class="rounded-xl bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400">
                        {{ errorMessage }}
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-3 pt-2">
                        <button
                            type="button"
                            @click="router.visit('/dashboard')"
                            class="flex-1 rounded-xl border border-gray-300 py-2.5 font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                        >
                            {{ t('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="!isFormValid || isSubmitting"
                            class="flex-1 rounded-xl bg-emerald-600 py-2.5 font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <Loader2 v-if="isSubmitting" class="mx-auto h-5 w-5 animate-spin" />
                            <span v-else>{{ t('Pay Now') }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Info Note -->
            <div class="mt-6 text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ t('Payment will be sent instantly to the requester\'s wallet') }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { ArrowLeft, Clock, Loader2 } from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

// ==================== PROPS ====================
const props = defineProps<{
    request: {
        id: number;
        request_token: string;
        amount: number;
        formatted_amount: string;
        currency_code: string;
        currency_symbol: string;
        description: string | null;
        expires_at: string;
        qr_code?: string | null;
        requester: {
            id: number;
            name: string;
            email: string;
        };
    };
}>();

// ==================== STATE ====================
const selectedWalletId = ref<number | null>(null);
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);
const availableWallets = ref<any[]>([]);

// ==================== COMPUTED ====================
const requesterInitials = computed(() => {
    if (!props.request.requester.name) return '?';
    return props.request.requester.name
        .split(' ')
        .map(n => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
});

const formattedExpiryDate = computed(() => {
    const date = new Date(props.request.expires_at);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
});

const isExpiringSoon = computed(() => {
    const expiryDate = new Date(props.request.expires_at);
    const now = new Date();
    const daysLeft = Math.ceil((expiryDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    return daysLeft <= 3 && daysLeft > 0;
});

const selectedWallet = computed(() => {
    if (!selectedWalletId.value) return null;
    return availableWallets.value.find(w => w.id === selectedWalletId.value);
});

const selectedWalletError = computed(() => {
    if (!selectedWallet.value) return null;
    if (selectedWallet.value.currency_code !== props.request.currency_code) {
        return t('This wallet uses a different currency. Please select a wallet with {currency}', { currency: props.request.currency_code });
    }
    return null;
});

const hasSufficientBalance = computed(() => {
    if (!selectedWallet.value) return false;
    return selectedWallet.value.balance_float >= props.request.amount;
});

const isFormValid = computed(() => {
    return selectedWalletId.value !== null 
        && !selectedWalletError.value 
        && hasSufficientBalance.value;
});

// ==================== METHODS ====================
const fetchWallets = async () => {
    try {
        const response = await fetch('/api/user/wallets', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        
        if (response.ok) {
            const data = await response.json();
            availableWallets.value = data.wallets || [];
            
            // Auto-select first wallet that matches currency and has sufficient balance
            const defaultWallet = availableWallets.value.find(
                w => w.currency_code === props.request.currency_code && w.balance_float >= props.request.amount
            );
            if (defaultWallet) {
                selectedWalletId.value = defaultWallet.id;
            } else if (availableWallets.value.length > 0) {
                selectedWalletId.value = availableWallets.value[0].id;
            }
        }
    } catch (err) {
        console.error('Failed to fetch wallets:', err);
    }
};

const processPayment = async () => {
    if (!isFormValid.value) return;
    
    isSubmitting.value = true;
    errorMessage.value = null;
    
    try {
        await router.post(`/money-requests/pay/${props.request.request_token}`, {
            amount: props.request.amount,
            wallet_id: selectedWalletId.value,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                // Success - redirect to dashboard with success message
                router.visit('/dashboard');
            },
            onError: (errors) => {
                console.error('Payment errors:', errors);
                if (errors.error) {
                    errorMessage.value = errors.error;
                } else if (errors.amount) {
                    errorMessage.value = errors.amount;
                } else {
                    errorMessage.value = t('Payment failed. Please try again.');
                }
            }
        });
    } catch (err) {
        console.error('Payment failed:', err);
        errorMessage.value = t('Payment failed. Please try again.');
    } finally {
        isSubmitting.value = false;
    }
};

// ==================== LIFECYCLE ====================
onMounted(() => {
    fetchWallets();
});
</script>