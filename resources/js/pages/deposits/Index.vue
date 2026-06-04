<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CreditCard, Landmark, Send, ArrowLeft } from 'lucide-vue-next';

const depositMethods = [
    {
        id: 'card',
        name: 'Credit / Debit Card',
        description: 'Instant deposit via Stripe',
        icon: CreditCard,
        fee: '2.9%',
        processingTime: 'Instant'
    },
    {
        id: 'bank-transfer',
        name: 'Bank Transfer',
        description: 'Direct from your bank account',
        icon: Landmark,
        fee: 'Free',
        processingTime: '1-3 days'
    },
    {
        id: 'wire-transfer',
        name: 'Wire Transfer',
        description: 'For large amounts',
        icon: Send,
        fee: '$15',
        processingTime: 'Same day'
    }
];

const goBack = () => {
    router.visit('/dashboard');
};

const selectMethod = (methodId: string) => {
    if (methodId === 'card') {
        router.visit('/deposits/card');
    }
    // Add other methods later
};
</script>

<template>
    <Head title="Add Money" />

    <div class="flex h-full flex-1 flex-col overflow-y-auto rounded-xl p-4">
        <div class="mb-6 flex items-center gap-4">
            <button @click="goBack" class="rounded-lg p-2 text-gray-600 hover:bg-gray-100 dark:text-gray-400">
                <ArrowLeft class="h-5 w-5" />
            </button>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Add Money</h1>
                <p class="mt-1 text-sm text-gray-500">Choose how you want to deposit funds</p>
            </div>
        </div>

        <div class="space-y-4">
            <div 
                v-for="method in depositMethods" 
                :key="method.id"
                @click="selectMethod(method.id)"
                class="cursor-pointer rounded-xl border border-gray-200 bg-white p-5 transition-all hover:shadow-md dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-start justify-between">
                    <div class="flex gap-4">
                        <div class="rounded-xl bg-gray-100 p-3 dark:bg-gray-800">
                            <component :is="method.icon" class="h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ method.name }}</h3>
                            <p class="text-sm text-gray-500">{{ method.description }}</p>
                            <div class="mt-2 flex gap-4 text-xs">
                                <span class="text-gray-500">Fee: {{ method.fee }}</span>
                                <span class="text-gray-500">Processing: {{ method.processingTime }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>