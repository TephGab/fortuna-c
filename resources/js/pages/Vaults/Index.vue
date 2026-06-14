<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { 
    Plus, 
    TrendingUp, 
    Lock, 
    Unlock,
    Wallet,
    Target,
    ChevronRight,
    Eye,
    EyeOff,
    Percent,
    AlertCircle
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import ClientOnly from '@/components/ClientOnly.vue'
import { useTranslation } from '@/composables/useTranslation';
import CreateVaultModal from './CreateVaultModal.vue';
import DepositModal from './DepositModal.vue';
import WithdrawModal from './WithdrawModal.vue';

const { t } = useTranslation();

// ==================== PROPS ====================
const props = defineProps<{
    vaults: any[];
    stats: {
        formatted_total_value: string;
        formatted_total_interest: string;
        active_vaults: number;
        locked_vaults: number;
        matured_vaults: number;
    };
    wallets: any[];
    availableTypes: any;
}>();

// ==================== STATE ====================
const showBalance = ref(true);
const showCreateModal = ref(false);
const showDepositModal = ref(false);
const showWithdrawModal = ref(false);
const selectedVaultForAction = ref<any>(null);

// ==================== COMPUTED ====================
const activeVaults = computed(() => props.vaults.filter(v => v.status === 'active'));
const lockedVaults = computed(() => props.vaults.filter(v => v.status === 'locked'));
const maturedVaults = computed(() => props.vaults.filter(v => v.status === 'matured'));

// ==================== HELPER METHODS ====================
const getDisplayBalance = (vault: any) => {
    if (!showBalance.value) return '••••••';
    if (vault.balance === 0 && vault.interest_earned === 0) return '$0.00';
    return vault.formatted_balance;
};

const getDisplayInterest = (vault: any) => {
    if (!showBalance.value) return '••••••';
    if (vault.interest_earned === 0) return t('No interest yet');
    return `+${vault.formatted_interest_earned} earned`;
};

const getInterestColorClass = (vault: any) => {
    if (vault.interest_earned === 0) return 'text-gray-500 dark:text-gray-400';
    return 'text-emerald-600 dark:text-emerald-400';
};

// ==================== METHODS ====================
const toggleBalanceVisibility = () => {
    showBalance.value = !showBalance.value;
};

const openDepositModal = (vault: any) => {
    selectedVaultForAction.value = vault;
    showDepositModal.value = true;
};

const openWithdrawModal = (vault: any) => {
    selectedVaultForAction.value = vault;
    showWithdrawModal.value = true;
};

const viewVault = (vault: any) => {
    router.visit(`/vaults/${vault.id}`);
};

const handleVaultCreated = () => {
    showCreateModal.value = false;
    router.reload();
};

const handleDepositSuccess = () => {
    showDepositModal.value = false;
    selectedVaultForAction.value = null;
    router.reload();
};

const handleWithdrawSuccess = () => {
    showWithdrawModal.value = false;
    selectedVaultForAction.value = null;
    router.reload();
};

const closeDepositModal = () => {
    showDepositModal.value = false;
    selectedVaultForAction.value = null;
};

const closeWithdrawModal = () => {
    showWithdrawModal.value = false;
    selectedVaultForAction.value = null;
};
</script>

<template>
    <Head title="Vaults" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-y-auto p-4 sm:p-6">
        
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    {{ t('Vaults') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Save and grow your money with secure vaults') }}
                </p>
            </div>
            <button
                @click="showCreateModal = true"
                class="flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition-all hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600"
            >
                <Plus class="h-4 w-4" />
                {{ t('Create New Vault') }}
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Value Card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Total Value') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ showBalance ? stats.formatted_total_value : '••••••' }}
                        </p>
                    </div>
                    <div class="rounded-full bg-emerald-100 p-2.5 dark:bg-emerald-900/30">
                        <Wallet class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                </div>
                <button 
                    @click="toggleBalanceVisibility" 
                    class="mt-2 flex items-center gap-1.5 text-xs text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <Eye v-if="showBalance" class="h-3.5 w-3.5" />
                    <EyeOff v-else class="h-3.5 w-3.5" />
                    {{ showBalance ? t('Hide balance') : t('Show balance') }}
                </button>
            </div>

            <!-- Total Interest Card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Total Interest Earned') }}</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            {{ showBalance ? stats.formatted_total_interest : '••••••' }}
                        </p>
                    </div>
                    <div class="rounded-full bg-emerald-100 p-2.5 dark:bg-emerald-900/30">
                        <TrendingUp class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                </div>
            </div>

            <!-- Active Vaults Card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Active Vaults') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ stats.active_vaults }}
                        </p>
                    </div>
                    <div class="rounded-full bg-blue-100 p-2.5 dark:bg-blue-900/30">
                        <Target class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </div>

            <!-- Locked Vaults Card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Locked Vaults') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ stats.locked_vaults }}
                        </p>
                    </div>
                    <div class="rounded-full bg-amber-100 p-2.5 dark:bg-amber-900/30">
                        <Lock class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Vaults List -->
        <div class="space-y-4">
            <!-- Active Vaults Section -->
            <div v-if="activeVaults.length > 0" class="space-y-3">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Active Vaults') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="vault in activeVaults"
                        :key="vault.id"
                        @click="viewVault(vault)"
                        class="group cursor-pointer rounded-2xl border border-gray-200 bg-white p-5 transition-all hover:shadow-md dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl">{{ vault.icon || '💰' }}</span>
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ vault.name }}</h3>
                                    <p class="text-xs text-gray-500">{{ vault.type_config?.name }}</p>
                                </div>
                            </div>
                            <ChevronRight class="h-5 w-5 text-gray-400 transition-transform group-hover:translate-x-0.5" />
                        </div>

                        <div class="mt-4">
                            <!-- Balance Display - Shows $0.00 when empty -->
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ getDisplayBalance(vault) }}
                            </p>
                            <!-- Interest Display - Shows "No interest yet" when zero -->
                            <p :class="getInterestColorClass(vault)" class="text-sm">
                                {{ getDisplayInterest(vault) }}
                            </p>
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <div class="flex items-center gap-1 text-xs text-gray-500">
                                <Percent class="h-3 w-3" />
                                <span>{{ vault.interest_rate }}% APY</span>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    @click.stop="openDepositModal(vault)"
                                    class="rounded-lg px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    {{ t('Deposit') }}
                                </button>
                                <button
                                    @click.stop="openWithdrawModal(vault)"
                                    :disabled="vault.balance === 0 && vault.interest_earned === 0"
                                    class="rounded-lg px-3 py-1.5 text-xs font-medium border border-gray-200 transition disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                                >
                                    {{ t('Withdraw') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Locked Vaults Section -->
            <div v-if="lockedVaults.length > 0" class="space-y-3">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Locked Vaults') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="vault in lockedVaults"
                        :key="vault.id"
                        @click="viewVault(vault)"
                        class="group cursor-pointer rounded-2xl border border-gray-200 bg-white p-5 transition-all hover:shadow-md dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl">{{ vault.icon || '🔒' }}</span>
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ vault.name }}</h3>
                                    <p class="text-xs text-gray-500">{{ vault.type_config?.name }}</p>
                                </div>
                            </div>
                            <div :class="vault.type_color_class" class="rounded-full px-2 py-0.5 text-xs">
                                Locked
                            </div>
                        </div>

                        <div class="mt-4">
                            <!-- Balance Display - Shows $0.00 when empty -->
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ getDisplayBalance(vault) }}
                            </p>
                            <!-- Interest Display - Shows "No interest yet" when zero -->
                            <p :class="getInterestColorClass(vault)" class="text-sm">
                                {{ getDisplayInterest(vault) }}
                            </p>
                        </div>

                        <!-- Progress Bar (only show if vault has balance) -->
                        <div v-if="vault.balance > 0" class="mt-4">
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>Lock progress</span>
                                <span>{{ vault.progress_percentage || 0 }}%</span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="vault.progress_bar_color_class"
                                    :style="{ width: `${vault.progress_percentage || 0}%` }"
                                ></div>
                            </div>
                            <p v-if="vault.days_remaining_text" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                {{ vault.days_remaining_text }}
                            </p>
                        </div>

                        <!-- Empty vault message -->
                        <div v-else class="mt-4 rounded-lg bg-gray-50 p-3 text-center dark:bg-gray-800">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                💰 Add funds to start earning {{ vault.interest_rate }}% APY
                            </p>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button
                                @click.stop="openDepositModal(vault)"
                                class="rounded-lg px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300"
                            >
                                {{ t('Add Funds') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Matured Vaults Section -->
            <div v-if="maturedVaults.length > 0" class="space-y-3">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Matured Vaults') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="vault in maturedVaults"
                        :key="vault.id"
                        @click="viewVault(vault)"
                        class="group cursor-pointer rounded-2xl border border-gray-200 bg-white p-5 transition-all hover:shadow-md dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl">{{ vault.icon || '✨' }}</span>
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ vault.name }}</h3>
                                    <p class="text-xs text-gray-500">{{ vault.type_config?.name }}</p>
                                </div>
                            </div>
                            <div :class="vault.status_badge.color" class="rounded-full px-2 py-0.5 text-xs">
                                {{ vault.status_badge.text }}
                            </div>
                        </div>

                        <div class="mt-4">
                            <!-- Balance Display - Shows $0.00 when empty -->
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ getDisplayBalance(vault) }}
                            </p>
                            <!-- Interest Display - Shows "No interest yet" when zero -->
                            <p :class="getInterestColorClass(vault)" class="text-sm">
                                {{ getDisplayInterest(vault) }}
                            </p>
                        </div>

                        <!-- Interest summary for matured vaults with balance -->
                        <div v-if="vault.interest_earned > 0" class="mt-3 rounded-lg bg-emerald-50 p-2 text-center dark:bg-emerald-900/20">
                            <p class="text-xs text-emerald-700 dark:text-emerald-400">
                                🎉 Total interest earned: {{ vault.formatted_interest_earned }}
                            </p>
                        </div>

                        <div class="mt-4 flex justify-end gap-2">
                            <button
                                @click.stop="openWithdrawModal(vault)"
                                :disabled="vault.balance === 0 && vault.interest_earned === 0"
                                class="rounded-lg px-3 py-1.5 text-xs font-medium bg-emerald-600 text-white transition hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ vault.balance > 0 || vault.interest_earned > 0 ? t('Withdraw Funds') : t('Empty Vault') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="vaults.length === 0" class="rounded-2xl border border-gray-200 bg-white p-12 text-center dark:border-gray-800 dark:bg-gray-900">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <Wallet class="h-8 w-8 text-gray-400" />
                </div>
                <h3 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">{{ t('No vaults yet') }}</h3>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Create your first vault and start growing your savings') }}
                </p>
                <button
                    @click="showCreateModal = true"
                    class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600"
                >
                    <Plus class="h-4 w-4" />
                    {{ t('Create Vault') }}
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODALS WRAPPED WITH CLIENTONLY ==================== -->
    
    <!-- Create Vault Modal -->
    <ClientOnly>
        <CreateVaultModal
            :is-open="showCreateModal"
            :wallets="wallets"
            :available-types="availableTypes"
            @close="showCreateModal = false"
            @created="handleVaultCreated"
        />
    </ClientOnly>

    <!-- Deposit Modal -->
    <ClientOnly>
        <DepositModal
            v-if="selectedVaultForAction"
            :is-open="showDepositModal"
            :vault="selectedVaultForAction"
            :wallets="wallets"
            @close="closeDepositModal"
            @deposited="handleDepositSuccess"
        />
    </ClientOnly>

    <!-- Withdraw Modal -->
    <ClientOnly>
        <WithdrawModal
            v-if="selectedVaultForAction"
            :is-open="showWithdrawModal"
            :vault="selectedVaultForAction"
            @close="closeWithdrawModal"
            @withdrawn="handleWithdrawSuccess"
        />
    </ClientOnly>
</template>