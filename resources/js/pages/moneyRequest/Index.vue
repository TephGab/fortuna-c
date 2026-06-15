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
                
                <!-- Amount Input (Optional) -->
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Amount (Optional)') }}
                    </label>
                    <div class="flex gap-2">
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
                            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
                        >
                            <Loader2 v-if="isGenerating" class="h-4 w-4 animate-spin" />
                            <span v-else>{{ t('Generate') }}</span>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ t('Leave empty for flexible amount, or enter a specific amount') }}
                    </p>
                </div>
                
                <!-- QR Code Display -->
                <div class="flex justify-center">
                    <div class="rounded-xl bg-white p-4 shadow-lg">
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
                    </div>
                </div>
                
                <p class="mt-4 text-sm text-gray-500">
                    {{ amount && amount > 0 ? t('Requesting ${amount}', { amount: amount }) : t('Flexible amount - payer decides') }}
                </p>
                <p class="mt-1 text-xs text-gray-400">
                    {{ user.email }}
                </p>
                
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

            <!-- How it works -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('How it works') }}</h2>
                <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                    <p>1. Enter an amount (optional) and click Generate</p>
                    <p>2. Share your QR code with someone</p>
                    <p>3. They scan the QR code</p>
                    <p>4. If you set an amount, it will be pre-filled automatically</p>
                    <p>5. They confirm and send payment instantly</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Copy, Download, Loader2, QrCode } from 'lucide-vue-next';
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

const amount = ref<number | null>(null);
const qrCodeUrl = ref<string | null>(null);
const isGenerating = ref(false);
const isDownloading = ref(false);

const copyEmail = () => {
    navigator.clipboard.writeText(props.user.email);
    alert(t('Email copied to clipboard!'));
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
        } else {
            alert(t('Failed to generate QR code'));
        }
    } catch (error) {
        console.error('Generation failed:', error);
        alert(t('Failed to generate QR code'));
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
    } catch (error) {
        console.error('Download failed:', error);
        alert(t('Failed to download QR code'));
    } finally {
        isDownloading.value = false;
    }
};

// Generate initial QR code (without amount)
generateQRCode();
</script>