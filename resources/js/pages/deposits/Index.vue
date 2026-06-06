<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { 
    CreditCard, 
    Landmark, 
    Send, 
    ArrowLeft, 
    ChevronRight, 
    Zap, 
    Clock, 
    Wallet, 
    Building2 
} from 'lucide-vue-next';

const depositMethods = [
    {
        id: 'card',
        name: 'Credit / Debit Card',
        description: 'Instant deposit via Stripe',
        icon: CreditCard,
        fee: '2.9%',
        processingTime: 'Instant',
        badge: 'Popular',
        gradient: 'from-violet-500 to-indigo-500',
        iconBg: 'bg-violet-50 dark:bg-violet-500/10',
        iconColor: 'text-violet-600 dark:text-violet-400',
        badgeColor: 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
    },
    {
        id: 'paypal',
        name: 'PayPal',
        description: 'Secure deposit with PayPal balance or linked cards',
        icon: Wallet,
        fee: '3.5%',
        processingTime: 'Instant',
        badge: 'Popular',
        gradient: 'from-blue-500 to-sky-500',
        iconBg: 'bg-blue-50 dark:bg-blue-500/10',
        iconColor: 'text-blue-600 dark:text-blue-400',
        badgeColor: 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
    },
    {
        id: 'authorized-agent',
        name: 'Authorized Agent',
        description: 'Deposit cash at any authorized agent location',
        icon: Building2,
        fee: '1.5%',
        processingTime: 'Same day',
        badge: 'Cash',
        gradient: 'from-emerald-500 to-teal-500',
        iconBg: 'bg-emerald-50 dark:bg-emerald-500/10',
        iconColor: 'text-emerald-600 dark:text-emerald-400',
        badgeColor: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
    },
    // Bank Transfer - Commented for now
    // {
    //     id: 'bank-transfer',
    //     name: 'Bank Transfer',
    //     description: 'Direct from your bank account',
    //     icon: Landmark,
    //     fee: 'Free',
    //     processingTime: '1–3 days',
    //     badge: 'No fees',
    //     gradient: 'from-emerald-500 to-teal-500',
    //     iconBg: 'bg-emerald-50 dark:bg-emerald-500/10',
    //     iconColor: 'text-emerald-600 dark:text-emerald-400',
    //     badgeColor: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
    // },
    // Wire Transfer - Commented for now
    // {
    //     id: 'wire-transfer',
    //     name: 'Wire Transfer',
    //     description: 'For large amounts',
    //     icon: Send,
    //     fee: '$15',
    //     processingTime: 'Same day',
    //     badge: 'Large amounts',
    //     gradient: 'from-amber-500 to-orange-500',
    //     iconBg: 'bg-amber-50 dark:bg-amber-500/10',
    //     iconColor: 'text-amber-600 dark:text-amber-400',
    //     badgeColor: 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
    // }
];

const goBack = () => router.visit('/dashboard');

const selectMethod = (methodId: string) => {
    if (methodId === 'card') {
        router.visit('/deposits/card');
    } else if (methodId === 'paypal') {
        router.visit('/deposits/paypal');
    } else if (methodId === 'authorized-agent') {
        router.visit('/deposits/authorized-agent');
    }
};
</script>

<template>
    <Head title="Add Money" />

    <div class="flex h-full flex-1 flex-col overflow-y-auto p-4 sm:p-6">

        <!-- Header -->
        <div class="mb-8 flex items-center gap-4">
            <button
                @click="goBack"
                class="group flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 shadow-sm transition-all hover:border-gray-300 hover:text-gray-900 hover:shadow dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" />
            </button>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Add Money</h1>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Choose how you'd like to deposit funds</p>
            </div>
        </div>

        <!-- Method Cards -->
        <div class="flex flex-col gap-3">
            <button
                v-for="method in depositMethods"
                :key="method.id"
                @click="selectMethod(method.id)"
                class="group relative w-full overflow-hidden rounded-2xl border border-gray-200/80 bg-white text-left shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg dark:border-gray-700/60 dark:bg-gray-800/60"
            >
                <!-- Gradient top bar -->
                <div :class="`absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r opacity-0 transition-opacity duration-200 group-hover:opacity-100 ${method.gradient}`" />

                <div class="flex items-center gap-4 p-5">
                    <!-- Icon -->
                    <div :class="`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl transition-transform duration-200 group-hover:scale-105 ${method.iconBg}`">
                        <component :is="method.icon" :class="`h-5 w-5 ${method.iconColor}`" />
                    </div>

                    <!-- Content -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-900 dark:text-white">{{ method.name }}</span>
                            <span :class="`rounded-full px-2 py-0.5 text-xs font-medium ${method.badgeColor}`">
                                {{ method.badge }}
                            </span>
                        </div>
                        <p class="mt-0.5 truncate text-sm text-gray-500 dark:text-gray-400">{{ method.description }}</p>

                        <!-- Meta -->
                        <div class="mt-2.5 flex items-center gap-4">
                            <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                                <Zap class="h-3 w-3" />
                                <span>{{ method.fee }}</span>
                            </div>
                            <div class="h-3 w-px bg-gray-200 dark:bg-gray-700" />
                            <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                                <Clock class="h-3 w-3" />
                                <span>{{ method.processingTime }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Arrow -->
                    <ChevronRight class="h-4 w-4 shrink-0 text-gray-300 transition-all duration-200 group-hover:translate-x-0.5 group-hover:text-gray-500 dark:text-gray-600 dark:group-hover:text-gray-400" />
                </div>
            </button>
        </div>

        <!-- Footer note -->
        <p class="mt-6 text-center text-xs text-gray-400 dark:text-gray-600">
            All transactions are encrypted and secure
        </p>
    </div>
</template>