<template>
    <Head title="Request Money" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-y-auto p-4 sm:p-6">
        <div class="mx-auto w-full max-w-2xl">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Request Money') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Share your QR code or create a payment request link') }}
                </p>
            </div>

            <!-- QR Code Card -->
            <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Your QR Code') }}</h2>
                
                <div class="flex justify-center">
                    <div class="rounded-xl bg-white p-4 shadow-lg">
                        <img v-if="qrCode" :src="qrCode" alt="QR Code" class="mx-auto" style="width: 300px; height: 300px;" />
                        <div v-else class="h-64 w-64 animate-pulse rounded-xl bg-gray-200 dark:bg-gray-700"></div>
                    </div>
                </div>
                
                <p class="mt-4 text-sm text-gray-500">
                    {{ t('Scan this QR code to pay me') }}
                </p>
                
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        @click="copyQrLink"
                        class="flex items-center gap-2 rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        <Copy class="h-4 w-4" />
                        {{ t('Copy Link') }}
                    </button>
                    <button
                        @click="shareQrCode"
                        class="flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                    >
                        <Share2 class="h-4 w-4" />
                        {{ t('Share') }}
                    </button>
                    <button
                        v-if="qrCode"
                        @click="downloadQRCode"
                        class="flex items-center gap-2 rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        <Download class="h-4 w-4" />
                        {{ t('Download') }}
                    </button>
                </div>
            </div>

            <!-- Create Money Request Form -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Create Payment Request') }}</h2>
                
                <form @submit.prevent="createRequest" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Amount') }} ({{ currency.symbol }})
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">{{ currency.symbol }}</span>
                            <input
                                v-model="form.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="w-full rounded-xl border border-gray-300 p-3 pl-8 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                :placeholder="`0.00 ${currency.code}`"
                                @input="validateAmount"
                            />
                        </div>
                        <p v-if="errors.amount" class="mt-1 text-xs text-red-500">{{ errors.amount }}</p>
                    </div>
                    
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Description') }} ({{ t('optional') }})
                        </label>
                        <input
                            v-model="form.description"
                            type="text"
                            class="w-full rounded-xl border border-gray-300 p-3 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            :placeholder="t('e.g., Dinner, Rent, Coffee')"
                        />
                    </div>
                    
                    <button
                        type="submit"
                        :disabled="isSubmitting || !isFormValid"
                        class="w-full rounded-xl bg-emerald-600 py-3 font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <Loader2 v-if="isSubmitting" class="mx-auto h-5 w-5 animate-spin" />
                        <span v-else>{{ t('Generate Request Link') }}</span>
                    </button>
                </form>
                
                <!-- Generated Link -->
                <div v-if="generatedLink" class="mt-4 rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                    <p class="mb-2 text-sm font-medium text-gray-900 dark:text-white">{{ t('Your request link') }}</p>
                    <div class="flex items-center gap-2">
                        <input
                            :value="generatedLink"
                            type="text"
                            readonly
                            class="flex-1 rounded-lg border border-gray-300 bg-white p-2 text-sm dark:border-gray-700 dark:bg-gray-900"
                        />
                        <button
                            @click="copyLink"
                            class="rounded-lg p-2 text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700"
                        >
                            <Copy class="h-5 w-5" />
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">
                        {{ t('Share this link with anyone to request payment') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Copy, Share2, Loader2, Download } from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const props = defineProps<{
    qrCode: string;
    requestId: string;
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

const form = ref({
    amount: null as number | null,
    description: '',
});
const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});
const generatedLink = ref('');

// Validation
const isFormValid = computed(() => {
    return form.value.amount && form.value.amount > 0;
});

const validateAmount = () => {
    if (form.value.amount && form.value.amount <= 0) {
        errors.value.amount = t('Amount must be greater than 0');
    } else if (form.value.amount && form.value.amount > 10000) {
        errors.value.amount = t('Maximum amount is $10,000');
    } else {
        delete errors.value.amount;
    }
};

const createRequest = async () => {
    if (!form.value.amount || form.value.amount <= 0) {
        errors.value = { amount: t('Please enter a valid amount') };
        return;
    }
    
    isSubmitting.value = true;
    errors.value = {};
    
    try {
        const response = await fetch('/money-requests/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                amount: parseFloat(form.value.amount.toString()),
                description: form.value.description,
            }),
        });
        
        const data = await response.json();
        
        if (response.ok) {
            generatedLink.value = data.request_url;
            form.value.description = '';
            form.value.amount = null;
        } else {
            errors.value = data.errors || { general: t('Failed to create request') };
        }
    } catch (err) {
        console.error('Error:', err);
        errors.value = { general: t('Failed to create request') };
    } finally {
        isSubmitting.value = false;
    }
};

const copyQrLink = () => {
    const link = `${window.location.origin}/money-requests/pay/${props.requestId}`;
    navigator.clipboard.writeText(link);
    alert(t('Link copied to clipboard!'));
};

const shareQrCode = async () => {
    const link = `${window.location.origin}/money-requests/pay/${props.requestId}`;
    
    if (navigator.share) {
        try {
            await navigator.share({
                title: t('Money Request'),
                text: t('Send me money'),
                url: link,
            });
        } catch (err) {
            console.log('Share cancelled');
        }
    } else {
        copyQrLink();
    }
};

const downloadQRCode = () => {
    const link = document.createElement('a');
    link.download = 'qr-code.png';
    link.href = props.qrCode;
    link.click();
};

const copyLink = () => {
    navigator.clipboard.writeText(generatedLink.value);
    alert(t('Link copied to clipboard!'));
};
</script>