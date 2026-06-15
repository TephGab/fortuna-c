<template>
    <Head title="Request Money" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-y-auto p-4 sm:p-6">
        <div class="mx-auto w-full max-w-2xl">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Request Money') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Share your QR code to receive payments') }}
                </p>
            </div>

            <!-- QR Code Card -->
            <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Your Payment QR Code') }}</h2>
                
                <!-- QR Code Display -->
                <div class="flex justify-center">
                    <div class="relative rounded-xl bg-white p-4 shadow-lg">
                        <img 
                            v-if="qrCodeUrl" 
                            :src="qrCodeUrl" 
                            alt="QR Code" 
                            class="mx-auto" 
                            style="width: 250px; height: 250px;" 
                        />
                        <div v-else class="flex h-64 w-64 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                            <QrCode class="h-16 w-16 text-gray-400" />
                        </div>
                        <!-- Logo Watermark (optional visual) -->
                        <div v-if="qrCodeUrl" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <div class="rounded-full bg-white p-1 shadow-sm">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-900 text-xs font-bold text-white dark:bg-gray-700">
                                    FC
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Email Display -->
                <p class="mt-4 text-sm font-medium text-gray-900 dark:text-white">
                    {{ user.email }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ amount && amount > 0 ? t('Requesting {currency}{amount}', { currency: currency.symbol, amount: amount.toFixed(2) }) : t('Flexible amount - payer decides') }}
                </p>
                
                <!-- Action Buttons -->
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        @click="copyEmail"
                        class="flex items-center gap-2 rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        <Copy class="h-4 w-4" />
                        {{ t('Copy Email') }}
                    </button>
                    <button
                        @click="downloadQRCode"
                        :disabled="isDownloading || !qrCodeUrl"
                        class="flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
                    >
                        <Loader2 v-if="isDownloading" class="h-4 w-4 animate-spin" />
                        <Download v-else class="h-4 w-4" />
                        {{ t('Download QR Code') }}
                    </button>
                </div>
            </div>

            <!-- Amount Input Card (Moved Below) -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Set Amount (Optional)') }}</h2>
                <p class="mb-4 text-sm text-gray-500">
                    {{ t('Add a specific amount to your QR code') }}
                </p>
                
                <div class="flex gap-3">
                    <div class="relative flex-1">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">{{ currency.symbol }}</span>
                        <input
                            v-model.number="amount"
                            type="number"
                            step="10"
                            min="0"
                            class="w-full rounded-xl border border-gray-300 p-3 pl-8 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            placeholder="0.00"
                        />
                    </div>
                    <button
                        @click="generateQRCode"
                        :disabled="isGenerating"
                        class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <Loader2 v-if="isGenerating" class="h-4 w-4 animate-spin" />
                        <span v-else>{{ t('Generate QR Code') }}</span>
                    </button>
                </div>
                
                <!-- Quick Amount Buttons -->
                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="suggested in [10, 25, 50, 100, 250, 500]"
                        :key="suggested"
                        @click="setQuickAmount(suggested)"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs transition hover:border-emerald-500 hover:bg-emerald-50 dark:border-gray-700 dark:hover:border-emerald-600 dark:hover:bg-emerald-900/20"
                    >
                        {{ currency.symbol }}{{ suggested }}
                    </button>
                    <button
                        v-if="amount && amount > 0"
                        @click="clearAmount"
                        class="rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-600 transition hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
                    >
                        {{ t('Clear Amount') }}
                    </button>
                </div>
                
                <p class="mt-4 text-xs text-gray-400">
                    💡 {{ t('Leave empty for flexible amount, or enter a specific amount that will be pre-filled when scanned') }}
                </p>
            </div>

            <!-- How it works -->
            <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('How it works') }}</h2>
                <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                    <div class="flex items-start gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-900 text-xs font-bold text-white dark:bg-gray-700">1</span>
                        <p>Enter an amount (optional) and click Generate QR Code</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-900 text-xs font-bold text-white dark:bg-gray-700">2</span>
                        <p>Share your QR code with someone via message, email, or print</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-900 text-xs font-bold text-white dark:bg-gray-700">3</span>
                        <p>They scan the QR code using their camera or the app scanner</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-900 text-xs font-bold text-white dark:bg-gray-700">4</span>
                        <p>If you set an amount, it will be pre-filled automatically</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-900 text-xs font-bold text-white dark:bg-gray-700">5</span>
                        <p>They confirm and send payment instantly to your wallet</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Component -->
    <div 
        v-if="toast.show" 
        class="fixed bottom-4 left-1/2 z-50 -translate-x-1/2 transform animate-slideUp rounded-xl px-4 py-3 shadow-lg"
        :class="toast.type === 'success' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'"
    >
        <div class="flex items-center gap-2">
            <CheckCircle v-if="toast.type === 'success'" class="h-5 w-5" />
            <AlertCircle v-else class="h-5 w-5" />
            <span class="text-sm font-medium">{{ toast.message }}</span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import { Copy, Download, Loader2, QrCode, CheckCircle, AlertCircle } from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
    };
    currency: {
        code: string;
        symbol: string;
    };
}>();

// ==================== STATE ====================
const amount = ref<number | null>(null);
const qrCodeUrl = ref<string | null>(null);
const isGenerating = ref(false);
const isDownloading = ref(false);

// Toast notification state
const toast = reactive({
    show: false,
    message: '',
    type: 'success' as 'success' | 'error',
    timeout: null as ReturnType<typeof setTimeout> | null,
});

// ==================== TOAST METHODS ====================
const showToast = (message: string, type: 'success' | 'error' = 'success') => {
    if (toast.timeout) clearTimeout(toast.timeout);
    
    toast.message = message;
    toast.type = type;
    toast.show = true;
    
    toast.timeout = setTimeout(() => {
        toast.show = false;
    }, 3000);
};

// ==================== QR CODE METHODS ====================
const setQuickAmount = (value: number) => {
    amount.value = value;
    generateQRCode();
};

const clearAmount = () => {
    amount.value = null;
    generateQRCode();
};

const copyEmail = () => {
    navigator.clipboard.writeText(props.user.email);
    showToast(t('Email copied to clipboard!'), 'success');
};

const generateQRCode = async () => {
    isGenerating.value = true;
    
    try {
        const params = new URLSearchParams();
        if (amount.value && amount.value > 0) {
            params.append('amount', amount.value.toString());
            params.append('currency', props.currency.code);
        }
        
        const response = await fetch(`/money-requests/generate-qr?${params.toString()}`);
        const data = await response.json();
        
        if (response.ok) {
            qrCodeUrl.value = data.qr_code;
            if (amount.value && amount.value > 0) {
                showToast(t('QR code generated with amount {currency}{amount}', { 
                    currency: props.currency.symbol, 
                    amount: amount.value.toFixed(2) 
                }), 'success');
            } else {
                showToast(t('QR code generated successfully'), 'success');
            }
        } else {
            showToast(t('Failed to generate QR code'), 'error');
        }
    } catch (error) {
        console.error('Generation failed:', error);
        showToast(t('Failed to generate QR code'), 'error');
    } finally {
        isGenerating.value = false;
    }
};

const downloadQRCode = async () => {
    if (!qrCodeUrl.value) return;
    
    isDownloading.value = true;
    
    try {
        const params = new URLSearchParams();
        if (amount.value && amount.value > 0) {
            params.append('amount', amount.value.toString());
            params.append('currency', props.currency.code);
        }
        
        const response = await fetch(`/money-requests/download-qr?${params.toString()}`);
        const blob = await response.blob();
        const blobUrl = URL.createObjectURL(blob);
        
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = amount.value && amount.value > 0 ? `payment-${amount.value}.png` : 'payment-qr.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        URL.revokeObjectURL(blobUrl);
        showToast(t('QR code downloaded successfully'), 'success');
    } catch (error) {
        console.error('Download failed:', error);
        showToast(t('Failed to download QR code'), 'error');
    } finally {
        isDownloading.value = false;
    }
};

// Generate initial QR code (without amount)
generateQRCode();
</script>

<style scoped>
@keyframes slideUp {
    from {
        opacity: 0;
        transform: translate(-50%, 100%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, 0);
    }
}

.animate-slideUp {
    animation: slideUp 0.3s ease-out;
}
</style>