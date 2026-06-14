<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Deposit to {{ vault.name }}</h2>
                <button @click="close" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-800">
                    <X class="h-5 w-5 text-gray-500" />
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Deposit Amount
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                        <input
                            v-model.number="amount"
                            type="number"
                            step="10"
                            min="1"
                            class="w-full rounded-xl border border-gray-300 p-3 pl-8 text-lg font-semibold focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            placeholder="0.00"
                        />
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="suggested in [10, 25, 50, 100, 250]"
                            :key="suggested"
                            type="button"
                            @click="amount = suggested"
                            class="rounded-lg border border-gray-200 px-3 py-1 text-xs transition-all hover:border-emerald-500 dark:border-gray-700"
                        >
                            ${{ suggested }}
                        </button>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Source Wallet
                    </label>
                    <select
                        v-model="selectedWalletId"
                        class="w-full rounded-xl border border-gray-300 p-3 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option v-for="wallet in wallets" :key="wallet.id" :value="wallet.id">
                            {{ wallet.currency_code }} - {{ wallet.formatted_balance }}
                        </option>
                    </select>
                </div>

                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">New vault balance</span>
                        <span class="font-medium text-emerald-600">
                            ${{ (vault.balance / 100 + (amount || 0)).toFixed(2) }}
                        </span>
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
                        :disabled="isSubmitting || !amount || amount < 1"
                        class="flex-1 rounded-xl bg-emerald-600 py-2.5 font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <Loader2 v-if="isSubmitting" class="mx-auto h-5 w-5 animate-spin" />
                        <span v-else>Deposit ${{ amount || 0 }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { X, Loader2 } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    isOpen: boolean;
    vault: any;
    wallets: any[];
}>();

const emit = defineEmits(['close', 'deposited']);

const amount = ref<number | null>(null);
const selectedWalletId = ref<string | null>(null);
const isSubmitting = ref(false);

const submit = async () => {
    if (!amount.value || amount.value < 1) return;
    
    isSubmitting.value = true;
    
    try {
        await router.post(`/vaults/${props.vault.id}/deposit`, {
            amount: amount.value,
            wallet_id: selectedWalletId.value,
        });
        
        emit('deposited');
        close();
    } catch (err) {
        console.error('Deposit failed:', err);
    } finally {
        isSubmitting.value = false;
    }
};

const close = () => {
    amount.value = null;
    emit('close');
};
</script>