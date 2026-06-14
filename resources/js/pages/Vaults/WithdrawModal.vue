<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Withdraw from {{ vault.name }}</h2>
                <button @click="close" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-800">
                    <X class="h-5 w-5 text-gray-500" />
                </button>
            </div>

            <div class="mb-4 rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Available balance</span>
                    <span class="font-semibold">{{ vault.formatted_balance }}</span>
                </div>
                <div class="mt-2 flex justify-between text-sm">
                    <span class="text-gray-600">Interest earned</span>
                    <span class="font-semibold text-emerald-600">{{ vault.formatted_interest_earned }}</span>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Withdraw Amount
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                        <input
                            v-model.number="amount"
                            type="number"
                            step="10"
                            min="1"
                            :max="maxWithdraw"
                            class="w-full rounded-xl border border-gray-300 p-3 pl-8 text-lg font-semibold focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            placeholder="0.00"
                        />
                    </div>
                    <button
                        type="button"
                        @click="amount = maxWithdraw"
                        class="mt-2 text-xs text-emerald-600 hover:underline"
                    >
                        Withdraw all (${{ maxWithdraw.toFixed(2) }})
                    </button>
                </div>

                <!-- Penalty Warning -->
                <div v-if="isEarlyWithdrawal && amount && amount > 0" class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                    <div class="flex items-start gap-2">
                        <AlertCircle class="h-5 w-5 text-amber-600" />
                        <div>
                            <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Early Withdrawal Penalty</p>
                            <p class="text-xs text-amber-700 dark:text-amber-400">
                                Withdrawing before the lock period ends will incur a {{ vault.type_config?.penalty }}% penalty.
                                You will receive approximately ${{ estimatedAmount.toFixed(2) }} after penalty.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Amount to withdraw</span>
                        <span class="font-medium">${{ amount || 0 }}</span>
                    </div>
                    <div v-if="penaltyAmount > 0" class="mt-2 flex justify-between text-sm">
                        <span class="text-gray-600">Penalty ({{ vault.type_config?.penalty }}%)</span>
                        <span class="font-medium text-red-600">-${{ penaltyAmount.toFixed(2) }}</span>
                    </div>
                    <div class="mt-3 border-t border-gray-200 pt-2 flex justify-between font-semibold dark:border-gray-700">
                        <span class="text-gray-900">You'll receive</span>
                        <span class="text-emerald-600">${{ estimatedAmount.toFixed(2) }}</span>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        @click="close"
                        class="flex-1 rounded-xl border border-gray-300 py-2.5 font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting || !amount || amount < 1 || amount > maxWithdraw"
                        class="flex-1 rounded-xl bg-amber-600 py-2.5 font-semibold text-white transition hover:bg-amber-700 disabled:opacity-50"
                    >
                        <Loader2 v-if="isSubmitting" class="mx-auto h-5 w-5 animate-spin" />
                        <span v-else>Withdraw</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { X, AlertCircle, Loader2 } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    isOpen: boolean;
    vault: any;
}>();

const emit = defineEmits(['close', 'withdrawn']);

const amount = ref<number | null>(null);
const isSubmitting = ref(false);

const maxWithdraw = computed(() => {
    const total = (props.vault.balance + props.vault.interest_earned) / 100;
    return Math.max(0, total);
});

const isEarlyWithdrawal = computed(() => {
    return props.vault.type !== 'flexible' && props.vault.status === 'locked' && !props.vault.is_matured;
});

const penaltyAmount = computed(() => {
    if (!isEarlyWithdrawal.value || !amount.value) return 0;
    const penaltyPercent = props.vault.type_config?.penalty || 0;
    return amount.value * (penaltyPercent / 100);
});

const estimatedAmount = computed(() => {
    return (amount.value || 0) - penaltyAmount.value;
});

const submit = async () => {
    if (!amount.value || amount.value < 1) return;
    
    isSubmitting.value = true;
    
    try {
        await router.post(`/vaults/${props.vault.id}/withdraw`, {
            amount: amount.value,
        });
        
        emit('withdrawn');
        close();
    } catch (err) {
        console.error('Withdrawal failed:', err);
    } finally {
        isSubmitting.value = false;
    }
};

const close = () => {
    amount.value = null;
    emit('close');
};
</script>