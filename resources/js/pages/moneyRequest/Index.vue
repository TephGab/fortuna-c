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
                
                <div class="flex justify-center">
                    <div class="rounded-xl bg-white p-4 shadow-lg">
                        <img 
                            v-if="qrCode" 
                            :src="qrCode" 
                            alt="QR Code" 
                            class="mx-auto" 
                            style="width: 250px; height: 250px;" 
                        />
                        <div v-else class="h-64 w-64 animate-pulse rounded-xl bg-gray-200 dark:bg-gray-700"></div>
                    </div>
                </div>
                
                <p class="mt-4 text-sm text-gray-500">
                    {{ t('Share this QR code with anyone to receive payments') }}
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
                        :disabled="isDownloading"
                        class="flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
                    >
                        <Loader2 v-if="isDownloading" class="h-4 w-4 animate-spin" />
                        <Download v-else class="h-4 w-4" />
                        {{ t('Download QR Code') }}
                    </button>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('How it works') }}</h2>
                <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                    <p>1. Share your QR code or email with someone</p>
                    <p>2. They scan the QR code or enter your email in Send Money</p>
                    <p>3. They enter the amount and send payment</p>
                    <p>4. Money is instantly deposited to your default wallet</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Copy, Download, Loader2 } from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const props = defineProps<{
    qrCode: string;
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

const isDownloading = ref(false);

const copyEmail = () => {
    navigator.clipboard.writeText(props.user.email);
    alert(t('Email copied to clipboard!'));
};

const downloadQRCode = async () => {
    isDownloading.value = true;
    
    try {
        // Fetch the image as a blob
        const response = await fetch(props.qrCode);
        const blob = await response.blob();
        
        // Create a blob URL
        const blobUrl = URL.createObjectURL(blob);
        
        // Create download link
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = 'payment-qr-code.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Clean up the blob URL
        URL.revokeObjectURL(blobUrl);
        
    } catch (error) {
        console.error('Download failed:', error);
        alert(t('Failed to download QR code. Please try again.'));
    } finally {
        isDownloading.value = false;
    }
};
</script>