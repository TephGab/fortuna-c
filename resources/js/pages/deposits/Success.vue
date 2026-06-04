<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle } from 'lucide-vue-next';

defineProps<{
    amount: number;
    sessionId: string;
}>();

const goToDashboard = () => {
    router.visit('/dashboard');
};
</script>

<template>
    <div class="flex h-full flex-1 flex-col items-center justify-center rounded-xl p-4">
        <div class="text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                <CheckCircle class="h-8 w-8 text-green-600 dark:text-green-400" />
            </div>
            <h1 class="mb-2 text-2xl font-bold text-gray-900 dark:text-white">Deposit Successful!</h1>
            <p class="mb-6 text-gray-600 dark:text-gray-400">
                ${{ amount }} has been added to your wallet.
            </p>
            <button 
                @click="goToDashboard"
                class="rounded-lg bg-gray-900 px-6 py-2 font-medium text-white transition-all hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600"
            >
                Go to Dashboard
            </button>
        </div>
    </div>
</template>