<script setup lang="ts">
/**
 * Card Deposit Component
 * 
 * Features:
 * - Card preview appears IMMEDIATELY when card type is detected
 * - Redirects to success page after payment
 * - Full Stripe Elements integration
 * - Mobile-first responsive design
 */

import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { 
    ArrowLeft, 
    CreditCard,
    Lock,
    Shield,
    Loader2,
    CheckCircle,
    AlertCircle,
    Sparkles,
    Wallet,
    Zap,
    Smartphone,
    Fingerprint,
    X
} from 'lucide-vue-next';
import { loadStripe } from '@stripe/stripe-js';

// ==================== CONFIGURATION ====================

const STRIPE_PUBLISHABLE_KEY = import.meta.env.VITE_STRIPE_KEY || '';

// Card type styling - maps Stripe brand values to display
const CARD_STYLES: Record<string, { gradient: string; displayName: string }> = {
    visa: { gradient: 'from-blue-600 to-blue-800', displayName: 'Visa' },
    mastercard: { gradient: 'from-orange-500 to-red-600', displayName: 'Mastercard' },
    amex: { gradient: 'from-blue-700 to-blue-900', displayName: 'American Express' },
    discover: { gradient: 'from-orange-600 to-orange-800', displayName: 'Discover' },
    diners: { gradient: 'from-gray-700 to-gray-900', displayName: 'Diners Club' },
    jcb: { gradient: 'from-green-600 to-green-800', displayName: 'JCB' },
    unionpay: { gradient: 'from-red-600 to-red-800', displayName: 'UnionPay' },
};

// ==================== STATE MANAGEMENT ====================

const amount = ref<number | null>(null);
const isProcessing = ref(false);
const error = ref<string | null>(null);

// Stripe elements
let stripe: any = null;
let elements: any = null;
let cardNumberElement: any = null;
let cardExpiryElement: any = null;
let cardCvcElement: any = null;

// DOM refs
const cardNumberRef = ref<HTMLDivElement | null>(null);
const cardExpiryRef = ref<HTMLDivElement | null>(null);
const cardCvcRef = ref<HTMLDivElement | null>(null);

// Card state
const cardType = ref<string | null>(null);
const cardNumberComplete = ref(false);
const cardExpiryComplete = ref(false);
const cardCvcComplete = ref(false);
const showCardPreview = ref(false);

// ==================== COMPUTED ====================

const isValidAmount = computed(() => {
    return amount.value && amount.value >= 10 && amount.value <= 5000;
});

const formattedAmount = computed(() => {
    return amount.value ? `$${amount.value.toFixed(2)}` : '$0.00';
});

const feeAmount = computed(() => {
    return amount.value ? amount.value * 0.029 : 0;
});

const totalAmount = computed(() => {
    return amount.value ? amount.value * 1.029 : 0;
});

const isFormValid = computed(() => {
    return isValidAmount.value && 
           cardNumberComplete.value && 
           cardExpiryComplete.value && 
           cardCvcComplete.value && 
           !isProcessing.value;
});

const cardDetails = computed(() => {
    if (!cardType.value) {
        return { gradient: 'from-gray-700 to-gray-900', displayName: 'Card' };
    }
    const key = cardType.value.toLowerCase();
    return CARD_STYLES[key] || { gradient: 'from-gray-700 to-gray-900', displayName: cardType.value };
});

const cardGradient = computed(() => cardDetails.value.gradient);
const cardTypeName = computed(() => cardDetails.value.displayName);

// ==================== METHODS ====================

const goBack = () => {
    if (!isProcessing.value) {
        router.visit('/deposits');
    }
};

const getCsrfToken = (): string => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
};

const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(value);
};

const initializeStripe = async () => {
    try {
        if (!STRIPE_PUBLISHABLE_KEY) {
            error.value = 'Payment system configuration error.';
            console.error('Stripe key missing');
            return;
        }
        
        stripe = await loadStripe(STRIPE_PUBLISHABLE_KEY);
        
        if (!stripe) {
            throw new Error('Failed to load Stripe');
        }
        
        elements = stripe.elements();
        
        const elementStyle = {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#1f2937',
                    '::placeholder': {
                        color: '#9ca3af',
                    },
                },
                invalid: {
                    color: '#dc2626',
                },
            },
        };
        
        cardNumberElement = elements.create('cardNumber', elementStyle);
        cardExpiryElement = elements.create('cardExpiry', elementStyle);
        cardCvcElement = elements.create('cardCvc', elementStyle);
        
        // Mount Card Number with detection
        if (cardNumberRef.value) {
            cardNumberElement.mount(cardNumberRef.value);
            
            cardNumberElement.on('change', (event: any) => {
                console.log('Stripe event:', event); // Debug log
                
                // Update card type as soon as Stripe detects it
                if (event.brand && event.brand !== 'unknown') {
                    cardType.value = event.brand;
                    // Show preview when we have a card type and at least 1 digit
                    showCardPreview.value = true;
                    console.log('Card detected:', event.brand);
                } else if (event.value && event.value.length === 0) {
                    // Reset when field is empty
                    cardType.value = null;
                    showCardPreview.value = false;
                }
                
                cardNumberComplete.value = event.complete;
                
                if (event.error) {
                    error.value = event.error.message;
                } else {
                    error.value = null;
                }
            });
        }
        
        // Mount Expiry
        if (cardExpiryRef.value) {
            cardExpiryElement.mount(cardExpiryRef.value);
            cardExpiryElement.on('change', (event: any) => {
                cardExpiryComplete.value = event.complete;
                if (event.error && !event.complete) {
                    error.value = event.error.message;
                }
            });
        }
        
        // Mount CVC
        if (cardCvcRef.value) {
            cardCvcElement.mount(cardCvcRef.value);
            cardCvcElement.on('change', (event: any) => {
                cardCvcComplete.value = event.complete;
                if (event.error && !event.complete) {
                    error.value = event.error.message;
                }
            });
        }
        
    } catch (err) {
        console.error('Stripe init failed:', err);
        error.value = 'Payment system unavailable. Please refresh.';
    }
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
    
    if (!stripe || !cardNumberElement) {
        error.value = 'Payment system not ready. Please refresh.';
        return;
    }
    
    isProcessing.value = true;
    error.value = null;
    
    try {
        // Create payment intent
        const response = await fetch('/api/deposits/create-payment-intent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ amount: amount.value }),
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Failed to create payment');
        }
        
        // Confirm payment with Stripe
        const { error: stripeError, paymentIntent } = await stripe.confirmCardPayment(
            data.clientSecret,
            {
                payment_method: {
                    card: cardNumberElement,
                },
            }
        );
        
        if (stripeError) {
            throw new Error(stripeError.message);
        }
        
        if (paymentIntent?.status === 'succeeded') {
            // Confirm with backend
            const confirmResponse = await fetch('/api/deposits/confirm-payment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    payment_intent_id: paymentIntent.id,
                    amount: amount.value,
                }),
            });
            
            if (confirmResponse.ok) {
                // Redirect to success page with amount
                router.visit(`/deposits/success?amount=${amount.value}&session_id=${paymentIntent.id}`);
            } else {
                throw new Error('Failed to update wallet. Please contact support.');
            }
        }
        
    } catch (err: any) {
        console.error('Payment failed:', err);
        error.value = err.message || 'Payment failed. Please try again.';
        isProcessing.value = false;
    }
};

// ==================== LIFECYCLE ====================

onMounted(() => {
    initializeStripe();
});

onUnmounted(() => {
    if (cardNumberElement) cardNumberElement.destroy();
    if (cardExpiryElement) cardExpiryElement.destroy();
    if (cardCvcElement) cardCvcElement.destroy();
});
</script>

<template>
    <Head title="Secure Deposit | Credit/Debit Card" />

    <div class="h-full w-full overflow-hidden bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-950 dark:to-gray-900">
        <div class="mx-auto h-full max-w-7xl overflow-y-auto px-4 py-6 sm:px-6 lg:px-8">
            
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <button 
                    @click="goBack"
                    :disabled="isProcessing"
                    class="rounded-xl p-2 text-gray-500 transition-all hover:bg-gray-200 disabled:opacity-50 dark:text-gray-400 dark:hover:bg-gray-800"
                >
                    <ArrowLeft class="h-5 w-5" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Add Funds</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Secure deposit via credit or debit card</p>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="grid gap-6 lg:grid-cols-2 lg:gap-8">
                
                <!-- LEFT COLUMN -->
                <div class="space-y-6">
                    
                    <!-- Amount Card -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Deposit Amount</label>
                        
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-2xl font-semibold text-gray-400">$</span>
                            <input 
                                v-model.number="amount"
                                type="number"
                                step="10"
                                class="w-full rounded-xl border border-gray-300 p-4 pl-10 text-2xl font-semibold focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                placeholder="0.00"
                            />
                        </div>
                        
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button 
                                v-for="suggested in [25, 50, 100, 250, 500, 1000]" 
                                :key="suggested"
                                type="button"
                                @click="amount = suggested"
                                class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium transition-all hover:border-emerald-500 hover:bg-emerald-50 dark:border-gray-700 dark:hover:border-emerald-500 dark:hover:bg-emerald-900/20"
                            >
                                ${{ suggested }}
                            </button>
                        </div>
                        
                        <div class="mt-4 flex flex-wrap gap-4 text-xs text-gray-500">
                            <div class="flex items-center gap-1"><AlertCircle class="h-3 w-3" /> Min: $10</div>
                            <div class="flex items-center gap-1"><AlertCircle class="h-3 w-3" /> Max: $5,000</div>
                            <div class="flex items-center gap-1"><Zap class="h-3 w-3" /> Instant</div>
                        </div>
                    </div>

                    <!-- CARD PREVIEW - Shows immediately when card type detected -->
                    <div 
                        v-if="showCardPreview && cardType"
                        class="transform rounded-2xl bg-gradient-to-br p-6 text-white shadow-xl transition-all duration-500"
                        :class="cardGradient"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex gap-2">
                                <div class="h-8 w-12 rounded-md bg-yellow-400/30 backdrop-blur-sm"></div>
                                <div class="h-8 w-8 rounded-full bg-gray-400/30"></div>
                            </div>
                            <span class="text-sm font-semibold uppercase opacity-80">{{ cardTypeName }}</span>
                        </div>
                        
                        <div class="mt-6">
                            <p class="font-mono text-lg tracking-wider opacity-80">•••• •••• •••• ••••</p>
                        </div>
                        
                        <div class="mt-4 flex justify-between">
                            <div>
                                <p class="text-xs opacity-70">Cardholder</p>
                                <p class="text-sm font-medium uppercase tracking-wide">YOUR NAME</p>
                            </div>
                            <div>
                                <p class="text-xs opacity-70">Expires</p>
                                <p class="text-sm font-medium">{{ cardExpiryComplete ? '**/**' : 'MM/YY' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card Info Form -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Card Information</h3>
                            <Fingerprint class="h-5 w-5 text-gray-400" />
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Card Number</label>
                                <div ref="cardNumberRef" class="rounded-xl border border-gray-300 bg-white p-3 dark:border-gray-700 dark:bg-gray-800"></div>
                                <div v-if="cardType && !cardNumberComplete" class="mt-1 text-right text-xs text-emerald-600 dark:text-emerald-400">
                                    ✓ {{ cardTypeName }} detected
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Expiry Date</label>
                                    <div ref="cardExpiryRef" class="rounded-xl border border-gray-300 bg-white p-3 dark:border-gray-700 dark:bg-gray-800"></div>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">CVC / CVV</label>
                                    <div ref="cardCvcRef" class="rounded-xl border border-gray-300 bg-white p-3 dark:border-gray-700 dark:bg-gray-800"></div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-center gap-1 text-xs text-gray-400">
                                <Lock class="h-3 w-3" />
                                <span>Your card info is encrypted</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Error Message -->
                    <div v-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                        <div class="flex items-start gap-3">
                            <AlertCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" />
                            <p class="flex-1 text-sm text-red-700 dark:text-red-400">{{ error }}</p>
                            <button @click="error = null" class="rounded-lg p-1 hover:bg-red-100 dark:hover:bg-red-800/50">
                                <X class="h-4 w-4 text-red-600" />
                            </button>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button 
                        @click="handleDeposit"
                        :disabled="!isFormValid"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-gray-900 to-gray-800 px-6 py-4 text-lg font-semibold text-white transition-all hover:from-gray-800 hover:to-gray-700 disabled:opacity-50 disabled:cursor-not-allowed dark:from-gray-700 dark:to-gray-600"
                    >
                        <Loader2 v-if="isProcessing" class="h-5 w-5 animate-spin" />
                        <Zap v-else class="h-5 w-5" />
                        {{ isProcessing ? 'Processing...' : `Deposit ${formattedAmount}` }}
                    </button>
                </div>
                
                <!-- RIGHT COLUMN -->
                <div class="hidden space-y-6 lg:block">
                    
                    <div class="sticky top-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="mb-4 flex items-center gap-2 font-semibold text-gray-900 dark:text-white">
                            <Wallet class="h-5 w-5" />
                            Payment Summary
                        </h3>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Deposit amount</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(amount || 0) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Processing fee (2.9%)</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(feeAmount) }}</span>
                            </div>
                            <div class="border-t border-gray-200 pt-3 dark:border-gray-700">
                                <div class="flex justify-between font-semibold">
                                    <span class="text-gray-900 dark:text-white">Total charged</span>
                                    <span class="text-emerald-600 dark:text-emerald-400">{{ formatCurrency(totalAmount) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="mb-4 flex items-center gap-2 font-semibold text-gray-900 dark:text-white">
                            <Sparkles class="h-5 w-5" />
                            Why deposit with us?
                        </h3>
                        
                        <div class="space-y-3">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 rounded-full bg-emerald-100 p-1.5 dark:bg-emerald-900/30">
                                    <Zap class="h-3 w-3 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">Instant deposits</p>
                                    <p class="text-xs text-gray-500">Funds available immediately</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 rounded-full bg-emerald-100 p-1.5 dark:bg-emerald-900/30">
                                    <Shield class="h-3 w-3 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">Bank-level security</p>
                                    <p class="text-xs text-gray-500">PCI DSS Level 1 compliant</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 rounded-full bg-emerald-100 p-1.5 dark:bg-emerald-900/30">
                                    <Smartphone class="h-3 w-3 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">Mobile optimized</p>
                                    <p class="text-xs text-gray-500">Seamless on all devices</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
* {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

*::-webkit-scrollbar {
    display: none;
}

button {
    min-height: 44px;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

:deep(.StripeElement) {
    width: 100%;
    background: transparent;
}

:deep(.StripeElement--focus) {
    outline: none;
}

:deep(.StripeElement--invalid) {
    border-color: #ef4444;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    opacity: 0.5;
}

@media (max-width: 768px) {
    input, .StripeElement {
        font-size: 16px;
    }
}
</style>