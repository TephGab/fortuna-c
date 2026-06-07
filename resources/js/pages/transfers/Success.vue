<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { 
    CheckCircle, 
    Download, 
    Share2, 
    Copy, 
    Check,
    Mail,
    Printer,
    ArrowLeft,
    Home,
    Receipt,
    Clock,
    User,
    Wallet,
    TrendingUp,
    Shield,
    ExternalLink
} from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

// ==================== PROPS ====================
const props = defineProps<{
    type: 'transfer' | 'deposit';
    amount: number;
    currency: string;
    currency_symbol: string;
    recipient_name?: string;
    recipient_email?: string;
    recipient_currency?: string;
    recipient_amount?: number;
    reference: string;
    fee: number;
    date: string;
    status: string;
}>();

// ==================== STATE ====================
const copied = ref(false);
const showShareOptions = ref(false);

// ==================== COMPUTED ====================
const formattedAmount = computed(() => {
    return `${props.currency_symbol} ${props.amount.toFixed(2)}`;
});

const formattedRecipientAmount = computed(() => {
    if (!props.recipient_amount) return '';
    const symbol = props.recipient_currency === 'USD' ? '$' : props.recipient_currency;
    return `${symbol} ${props.recipient_amount.toFixed(2)}`;
});

const formattedFee = computed(() => {
    return `${props.currency_symbol} ${props.fee.toFixed(2)}`;
});

const formattedDate = computed(() => {
    const date = new Date(props.date);
    return date.toLocaleString('en-US', {
        dateStyle: 'full',
        timeStyle: 'medium'
    });
});

const transactionId = computed(() => {
    return props.reference;
});

// ==================== METHODS ====================

/**
 * Navigate to dashboard
 */
const goToDashboard = () => {
    router.visit('/dashboard');
};

/**
 * Copy transaction details to clipboard
 */
const copyToClipboard = async () => {
    const text = `
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 ${t('TRANSACTION RECEIPT')}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

${t('Transaction ID')}: ${props.reference}
${t('Date & Time')}: ${formattedDate.value}
${t('Status')}: ${props.status}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 ${t('DETAILS')}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

${t('Amount')}: ${formattedAmount.value}
${t('Fee')}: ${formattedFee.value}
${props.type === 'transfer' ? `${t('Recipient')}: ${props.recipient_name} (${props.recipient_email})` : ''}
${props.type === 'transfer' && props.recipient_amount ? `${t('Recipient receives')}: ${formattedRecipientAmount.value}` : ''}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ ${t('Transaction completed successfully')}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

${t('Thank you for using Fortuna!')}
    `.trim();
    
    await navigator.clipboard.writeText(text);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

/**
 * Download receipt as HTML file
 */
const downloadReceipt = () => {
    const receiptHtml = `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>${t('Transaction Receipt')} - ${props.reference}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f5f5f5;
            padding: 40px 20px;
        }
        .receipt {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1e3a5f 0%, #0f2b44 100%);
            color: white;
            padding: 32px;
            text-align: center;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 16px;
        }
        .success-icon {
            width: 64px;
            height: 64px;
            background: #10b981;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .success-icon svg {
            width: 32px;
            height: 32px;
        }
        h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        .status {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .content {
            padding: 32px;
        }
        .section {
            margin-bottom: 24px;
        }
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .row:last-child {
            border-bottom: none;
        }
        .label {
            color: #6b7280;
            font-size: 14px;
        }
        .value {
            font-weight: 500;
            color: #1f2937;
        }
        .amount {
            font-size: 20px;
            font-weight: bold;
            color: #2563eb;
        }
        .footer {
            background: #f9fafb;
            padding: 24px 32px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            color: #9ca3af;
            font-size: 12px;
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="logo">Fortuna</div>
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h1>${props.type === 'transfer' ? t('Transfer Complete!') : t('Deposit Successful!')}</h1>
            <span class="status">${props.status}</span>
        </div>
        
        <div class="content">
            <div class="section">
                <div class="section-title">${t('Transaction Details')}</div>
                <div class="row">
                    <span class="label">${t('Transaction ID')}</span>
                    <span class="value">${props.reference}</span>
                </div>
                <div class="row">
                    <span class="label">${t('Date & Time')}</span>
                    <span class="value">${formattedDate.value}</span>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">${t('Payment Details')}</div>
                <div class="row">
                    <span class="label">${t('Amount')}</span>
                    <span class="value amount">${formattedAmount.value}</span>
                </div>
                <div class="row">
                    <span class="label">${t('Fee')}</span>
                    <span class="value">${formattedFee.value}</span>
                </div>
                ${props.type === 'transfer' ? `
                <div class="row">
                    <span class="label">${t('Recipient')}</span>
                    <span class="value">${props.recipient_name}</span>
                </div>
                <div class="row">
                    <span class="label">${t('Recipient Email')}</span>
                    <span class="value">${props.recipient_email}</span>
                </div>
                ` : ''}
                ${props.type === 'transfer' && props.recipient_amount ? `
                <div class="row">
                    <span class="label">${t('Recipient Receives')}</span>
                    <span class="value">${formattedRecipientAmount.value}</span>
                </div>
                ` : ''}
            </div>
        </div>
        
        <div class="footer">
            <p>${t('Thank you for using Fortuna')}</p>
            <p>${t('This is an electronic receipt - no signature required')}</p>
            <p>${t('Reference')}: ${props.reference}</p>
        </div>
    </div>
</body>
</html>`;
    
    const blob = new Blob([receiptHtml], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `receipt_${props.reference}.html`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
};

/**
 * Share receipt (Web Share API)
 */
const shareReceipt = async () => {
    const text = `${t('Transaction')} ${props.reference} ${t('completed successfully. Amount')}: ${formattedAmount.value}`;
    
    if (navigator.share) {
        try {
            await navigator.share({
                title: t('Transaction Receipt'),
                text: text,
                url: window.location.href,
            });
        } catch (err) {
            // User cancelled share
            console.log('Share cancelled');
        }
    } else {
        // Fallback - copy to clipboard
        await copyToClipboard();
        alert(t('Receipt copied to clipboard!'));
    }
};

/**
 * Print receipt
 */
const printReceipt = () => {
    window.print();
};

/**
 * Email receipt
 */
const emailReceipt = () => {
    const subject = `${t('Transaction Receipt')} - ${props.reference}`;
    const body = `
${t('Transaction Receipt')}
------------------
${t('Reference')}: ${props.reference}
${t('Date & Time')}: ${formattedDate.value}
${t('Amount')}: ${formattedAmount.value}
${t('Fee')}: ${formattedFee.value}
${props.type === 'transfer' ? `${t('Recipient')}: ${props.recipient_name} (${props.recipient_email})` : ''}
${t('Status')}: ${props.status}

${t('Thank you for using Fortuna!')}
    `.trim();
    
    window.location.href = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
};

/**
 * View transaction details
 */
const viewTransaction = () => {
    router.visit(`/transactions/${props.reference}`);
};

/**
 * Make another transfer
 */
const makeAnotherTransfer = () => {
    router.visit('/transfers');
};

/**
 * Make another deposit
 */
const makeAnotherDeposit = () => {
    router.visit('/deposits');
};
</script>

<template>
    <Head :title="t('Transaction Successful')" />

    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-950 dark:to-gray-900">
        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 sm:py-12">
            
            <!-- Success Card -->
            <div class="rounded-2xl bg-white p-6 shadow-lg dark:bg-gray-900 sm:p-8">
                
                <!-- Success Icon -->
                <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                    <CheckCircle class="h-10 w-10 text-emerald-600 dark:text-emerald-400" />
                </div>
                
                <!-- Title -->
                <h1 class="mb-2 text-center text-2xl font-bold text-gray-900 dark:text-white">
                    {{ type === 'transfer' ? t('Transfer Complete!') : t('Deposit Successful!') }}
                </h1>
                <p class="mb-6 text-center text-gray-500 dark:text-gray-400">
                    {{ t('Your transaction has been processed successfully') }}
                </p>
                
                <!-- Amount Highlight -->
                <div class="mb-6 rounded-xl bg-gray-50 p-4 text-center dark:bg-gray-800">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Amount') }}</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ formattedAmount }}
                    </p>
                    <p v-if="type === 'transfer' && recipient_amount" class="mt-1 text-sm text-emerald-600 dark:text-emerald-400">
                        {{ t('Recipient receives') }}: {{ formattedRecipientAmount }}
                    </p>
                </div>
                
                <!-- Transaction Details -->
                <div class="mb-6 space-y-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ t('Transaction ID') }}</span>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs text-gray-900 dark:text-white">{{ transactionId.slice(0, 20) }}...</span>
                            <button 
                                @click="copyToClipboard"
                                class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-800"
                                :title="t('Copy full ID')"
                            >
                                <Copy v-if="!copied" class="h-4 w-4 text-gray-400" />
                                <Check v-else class="h-4 w-4 text-emerald-600" />
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ t('Date & Time') }}</span>
                        <span class="text-sm text-gray-900 dark:text-white">{{ formattedDate }}</span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ t('Fee') }}</span>
                        <span class="text-sm text-gray-900 dark:text-white">{{ formattedFee }}</span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ t('Status') }}</span>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                            {{ status }}
                        </span>
                    </div>
                    
                    <div v-if="type === 'transfer'" class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ t('Recipient') }}</span>
                        <div class="text-right">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ recipient_name }}</p>
                            <p class="text-xs text-gray-500">{{ recipient_email }}</p>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="mb-6 grid gap-3 sm:grid-cols-2">
                    <button 
                        @click="downloadReceipt"
                        class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 py-2.5 text-sm font-medium transition-all hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        <Download class="h-4 w-4" />
                        {{ t('Download Receipt') }}
                    </button>
                    
                    <button 
                        @click="shareReceipt"
                        class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 py-2.5 text-sm font-medium transition-all hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        <Share2 class="h-4 w-4" />
                        {{ t('Share Receipt') }}
                    </button>
                    
                    <button 
                        @click="printReceipt"
                        class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 py-2.5 text-sm font-medium transition-all hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        <Printer class="h-4 w-4" />
                        {{ t('Print') }}
                    </button>
                    
                    <button 
                        @click="emailReceipt"
                        class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 py-2.5 text-sm font-medium transition-all hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        <Mail class="h-4 w-4" />
                        {{ t('Email Receipt') }}
                    </button>
                </div>
                
                <!-- Divider -->
                <div class="relative mb-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-200 dark:border-gray-800"></div>
                    </div>
                    <div class="relative flex justify-center text-xs">
                        <span class="bg-white px-2 text-gray-500 dark:bg-gray-900 dark:text-gray-400">{{ t('What would you like to do next?') }}</span>
                    </div>
                </div>
                
                <!-- Next Actions -->
                <div class="grid gap-3 sm:grid-cols-2">
                    <button 
                        @click="goToDashboard"
                        class="flex items-center justify-center gap-2 rounded-xl bg-gray-900 py-3 font-medium text-white transition-all hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600"
                    >
                        <Home class="h-5 w-5" />
                        {{ t('Go to Dashboard') }}
                    </button>
                    
                    <button 
                        @click="viewTransaction"
                        class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 py-3 font-medium transition-all hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        <Receipt class="h-5 w-5" />
                        {{ t('View Transaction') }}
                    </button>
                    
                    <button 
                        v-if="type === 'transfer'"
                        @click="makeAnotherTransfer"
                        class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 py-3 font-medium transition-all hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        <TrendingUp class="h-5 w-5" />
                        {{ t('Send Again') }}
                    </button>
                    
                    <button 
                        v-else
                        @click="makeAnotherDeposit"
                        class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 py-3 font-medium transition-all hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        <Wallet class="h-5 w-5" />
                        {{ t('Add More Funds') }}
                    </button>
                </div>
                
                <!-- Security Footer -->
                <div class="mt-6 text-center">
                    <div class="inline-flex items-center gap-2 text-xs text-gray-400">
                        <Shield class="h-3 w-3" />
                        <span>{{ t('This transaction is secured and verified') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .min-h-screen {
        background: white;
        padding: 0;
    }
    .rounded-2xl {
        box-shadow: none;
        border: none;
        padding: 0;
    }
    button {
        display: none;
    }
    .border-t, .border-gray-200 {
        border-color: #e5e7eb;
    }
}
</style>