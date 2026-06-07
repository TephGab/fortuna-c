<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { 
    ArrowLeft, 
    Building2,
    MapPin,
    Phone,
    Clock,
    AlertCircle,
    ExternalLink,
    Shield,
    Loader2
} from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const showNearestAgent = ref(false);
const userLocation = ref<string | null>(null);
const isLocating = ref(false);

const goBack = () => {
    router.visit('/deposits');
};

// Mock authorized agents data
const authorizedAgents = [
    {
        id: 1,
        name: 'Fortuna Authorized Center - Downtown',
        address: '123 Main Street, Suite 101, Downtown',
        distance: '0.3 miles',
        hours: '9:00 AM - 7:00 PM',
        phone: '+1 (555) 123-4567',
        rating: 4.8,
        verified: true,
    },
    {
        id: 2,
        name: 'Fortuna Authorized Center - Westside',
        address: '456 West Avenue, Westside Mall',
        distance: '1.2 miles',
        hours: '10:00 AM - 8:00 PM',
        phone: '+1 (555) 234-5678',
        rating: 4.6,
        verified: true,
    },
    {
        id: 3,
        name: 'Fortuna Authorized Center - Eastside',
        address: '789 East Boulevard, Eastside Plaza',
        distance: '2.5 miles',
        hours: '9:00 AM - 6:00 PM',
        phone: '+1 (555) 345-6789',
        rating: 4.9,
        verified: true,
    },
];

const findNearestAgent = () => {
    isLocating.value = true;
    // Simulate geolocation
    setTimeout(() => {
        userLocation.value = 'Current Location Detected';
        showNearestAgent.value = true;
        isLocating.value = false;
    }, 1500);
};
</script>

<template>
    <Head :title="t('Authorized Agent Deposit')" />

    <div class="flex h-full flex-1 flex-col overflow-y-auto p-4 sm:p-6">
        
        <!-- Header -->
        <div class="mb-6 flex items-center gap-4">
            <button 
                @click="goBack"
                class="group flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 shadow-sm transition-all hover:border-gray-300 hover:text-gray-900 hover:shadow dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" />
            </button>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ t('Authorized Agent Deposit') }}</h1>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ t('Deposit cash at any authorized Fortuna location') }}</p>
            </div>
        </div>

        <!-- Trust Badge -->
        <div class="mb-6 flex items-center justify-center gap-2 rounded-full bg-emerald-50 px-4 py-2 dark:bg-emerald-900/20">
            <Shield class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
            <span class="text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ t('100% Secure & Verified Agents') }}</span>
        </div>

        <!-- How It Works -->
        <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('How it works') }}</h2>
            <div class="space-y-4">
                <div class="flex gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">1</div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ t('Find an authorized center') }}</p>
                        <p class="text-sm text-gray-500">{{ t('Use our locator to find your nearest Fortuna Authorized Center') }}</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">2</div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ t('Visit with cash and ID') }}</p>
                        <p class="text-sm text-gray-500">{{ t('Bring your government-issued ID and the cash amount') }}</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">3</div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ t('Provide your registered email/phone') }}</p>
                        <p class="text-sm text-gray-500">{{ t('The authorized agent will verify and process your deposit') }}</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">4</div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ t('Funds credited instantly') }}</p>
                        <p class="text-sm text-gray-500">{{ t('Your wallet will be updated within minutes') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fee & Limits Info -->
        <div class="mb-6 rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/50">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">{{ t('Service fee') }}</span>
                <span class="font-medium text-gray-900 dark:text-white">1.5%</span>
            </div>
            <div class="mt-2 flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">{{ t('Minimum deposit') }}</span>
                <span class="font-medium text-gray-900 dark:text-white">$20</span>
            </div>
            <div class="mt-2 flex items-center justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">{{ t('Maximum deposit') }}</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ t('$5,000 per day') }}</span>
            </div>
        </div>

        <!-- Find Nearest Authorized Agent Button -->
        <button 
            @click="findNearestAgent"
            :disabled="isLocating"
            class="mb-6 flex w-full items-center justify-center gap-2 rounded-xl bg-gray-900 py-3 font-medium text-white transition-all hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-700 dark:hover:bg-gray-600"
        >
            <MapPin v-if="!isLocating" class="h-5 w-5" />
            <Loader2 v-else class="h-5 w-5 animate-spin" />
            {{ isLocating ? t('Locating nearest authorized center...') : t('Find nearest authorized center') }}
        </button>

        <!-- Nearest Authorized Agents List -->
        <div v-if="showNearestAgent" class="space-y-3 animate-fadeIn">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Authorized Centers Near You') }}</h3>
                <span class="text-xs text-emerald-600 dark:text-emerald-400">✓ {{ t('Verified by Fortuna') }}</span>
            </div>
            <div 
                v-for="agent in authorizedAgents" 
                :key="agent.id"
                class="rounded-xl border border-gray-200 bg-white p-4 transition-all hover:shadow-md dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-start justify-between">
                    <div class="flex gap-3">
                        <div class="rounded-lg bg-emerald-100 p-2 dark:bg-emerald-900/20">
                            <Building2 class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-gray-900 dark:text-white">{{ agent.name }}</p>
                                <span class="rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                    {{ t('Authorized') }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">{{ agent.address }}</p>
                            <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-400">
                                <span class="flex items-center gap-1">
                                    <MapPin class="h-3 w-3" />
                                    {{ agent.distance }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Clock class="h-3 w-3" />
                                    {{ agent.hours }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Phone class="h-3 w-3" />
                                    {{ agent.phone }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Shield class="h-3 w-3 text-emerald-500" />
                                    {{ t('Verified') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <ExternalLink class="h-4 w-4 text-gray-400" />
                </div>
            </div>
        </div>

        <!-- Important Note -->
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
            <div class="flex items-start gap-3">
                <AlertCircle class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                <div>
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">{{ t('Important Information') }}</p>
                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                        {{ t('Always carry a valid government-issued ID. Deposits are processed within 15-30 minutes during business hours. Only use Fortuna Authorized Centers for secure transactions.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Contact Support -->
        <div class="mt-4 text-center">
            <a href="#" class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                {{ t('Need help finding an authorized center?') }}
            </a>
        </div>
    </div>
</template>

<style scoped>
.animate-fadeIn {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>