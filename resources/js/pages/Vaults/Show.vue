<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { 
    ArrowLeft, 
    Lock, 
    Unlock, 
    TrendingUp,
    Calendar,
    Percent,
    Wallet,
    History,
    AlertCircle,
    MoreHorizontal,
    Edit2,
    Trash2,
    Download,
    Copy,
    Eye,
    EyeOff,
    Sparkles
} from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';
import ClientOnly from '@/components/ClientOnly.vue';
import DepositModal from './DepositModal.vue';
import WithdrawModal from './WithdrawModal.vue';
import EditVaultModal from './EditVaultModal.vue';

const { t } = useTranslation();

// ==================== PROPS ====================
const props = defineProps<{
    vault: {
        id: number;
        name: string;
        icon: string;
        type: string;
        status: string;
        description: string;
        created_at: string;
        formatted_balance: string;
        formatted_interest_earned: string;
        formatted_total_value: string;
        interest_rate: number;
        locked_until: string | null;
        matures_at: string | null;
        type_config: {
            name: string;
            lock_days: number;
            interest_rate: number;
            penalty: number;
            icon: string;
            color: string;
            description: string;
        };
        type_color_class: string;
        progress_bar_color_class: string;
        status_badge: {
            text: string;
            color: string;
            icon: string;
        };
        days_remaining: number | null;
        days_remaining_text: string | null;
        progress_percentage: number | null;
        is_locked: boolean;
        is_matured: boolean;
        can_early_withdraw: boolean;
        balance: number;
        interest_earned: number;
    };
    transactions: Array<{
        id: number;
        type: string;
        type_name: string;
        formatted_amount: string;
        formatted_amount_with_sign: string;
        formatted_balance_after: string;
        description: string;
        created_at: string;
        icon: string;
        color_class: string;
        bg_color_class: string;
    }>;
}>();

// ==================== COMPUTED ====================
const canWithdraw = computed(() => {
    if (props.vault.type === 'flexible') {
        return props.vault.balance > 0 || props.vault.interest_earned > 0;
    }
    return props.vault.is_matured || props.vault.can_early_withdraw;
});

const withdrawDisabledMessage = computed(() => {
    // Flexible vaults - no message
    if (props.vault.type === 'flexible') return null;
    
    // Matured vaults - can withdraw
    if (props.vault.is_matured) return null;
    
    // Locked vaults - show appropriate message
    const daysText = props.vault.days_remaining_text;
    
    if (!daysText) {
        return ' Funds are locked until maturity date';
    }
    
    if (daysText === 'Unlocks today!') {
        return ' Funds unlock today';
    }
    
    if (daysText === '1 day remaining') {
        return ' Funds are locked for 1 day';
    }
    
    // Extract number from "X days remaining"
    const match = daysText.match(/(\d+)/);
    if (match) {
        return ` Funds are locked for ${match[1]} days`;
    }
    
    return ' Funds are locked until maturity date';
});

const formatDate = (dateString: string | null | undefined) => {
    if (!dateString) return '—';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

// ==================== STATE ====================
const showDepositModal = ref(false);
const showWithdrawModal = ref(false);
const showEditModal = ref(false);
const showMenu = ref(false);
const showBalance = ref(true);

// ==================== METHODS ====================
const toggleBalanceVisibility = () => {
    showBalance.value = !showBalance.value;
};

const goBack = () => {
    router.visit('/vaults');
};

const copyVaultId = () => {
    navigator.clipboard.writeText(props.vault.id.toString());
};

const handleDepositSuccess = () => {
    showDepositModal.value = false;
    router.reload();
};

const handleWithdrawSuccess = () => {
    showWithdrawModal.value = false;
    router.reload();
};

const handleVaultUpdated = () => {
    showEditModal.value = false;
    router.reload();
};

const closeVault = async () => {
    if (confirm(t('Are you sure you want to close this vault? This action cannot be undone.'))) {
        router.delete(`/vaults/${props.vault.id}`);
    }
};
</script>

<template>
    <Head :title="vault.name" />

    <div class="flex h-full flex-1 flex-col overflow-y-auto p-4 sm:p-6">
        
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button
                    @click="goBack"
                    class="rounded-xl p-2 text-gray-500 transition-all hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800"
                >
                    <ArrowLeft class="h-5 w-5" />
                </button>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-3xl">{{ vault.icon || '💰' }}</span>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ vault.name }}</h1>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">{{ vault.description || t('No description') }}</p>
                </div>
            </div>
            
            <div class="relative">
                <button
                    @click="showMenu = !showMenu"
                    class="rounded-xl p-2 text-gray-500 transition-all hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800"
                >
                    <MoreHorizontal class="h-5 w-5" />
                </button>
                
                <div v-if="showMenu" class="absolute right-0 mt-2 w-48 rounded-xl border border-gray-200 bg-white py-2 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                    <button
                        @click="showEditModal = true; showMenu = false"
                        class="flex w-full items-center gap-2 px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        <Edit2 class="h-4 w-4" />
                        {{ t('Edit Vault') }}
                    </button>
                    <button
                        @click="copyVaultId"
                        class="flex w-full items-center gap-2 px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                        <Copy class="h-4 w-4" />
                        {{ t('Copy Vault ID') }}
                    </button>
                    <button
                        v-if="vault.balance === 0 && vault.interest_earned === 0"
                        @click="closeVault"
                        class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:text-red-400"
                    >
                        <Trash2 class="h-4 w-4" />
                        {{ t('Close Vault') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Vault Stats -->
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Value Card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">{{ t('Total Value') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ showBalance ? vault.formatted_total_value : '••••••' }}
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

            <!-- Interest Earned Card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">{{ t('Interest Earned') }}</p>
                <p class="mt-1 text-2xl font-bold" :class="vault.interest_earned > 0 ? 'text-emerald-600' : 'text-gray-500'">
                    {{ showBalance ? (vault.interest_earned > 0 ? vault.formatted_interest_earned : '$0.00') : '••••••' }}
                </p>
                <p v-if="vault.interest_earned === 0 && vault.is_locked" class="mt-1 text-xs text-gray-400">
                    {{ t('Interest will start accruing daily') }}
                </p>
            </div>

            <!-- Interest Rate Card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">{{ t('Interest Rate') }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ vault.interest_rate }}% APY
                        </p>
                    </div>
                    <div class="rounded-full bg-blue-100 p-2.5 dark:bg-blue-900/30">
                        <Percent class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </div>

            <!-- Status Card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">{{ t('Status') }}</p>
                        <div class="mt-1 flex items-center gap-2">
                            <div :class="vault.status_badge.color" class="rounded-full px-2 py-0.5 text-xs">
                                {{ vault.status_badge.text }}
                            </div>
                        </div>
                    </div>
                    <div class="rounded-full bg-gray-100 p-2.5 dark:bg-gray-800">
                        <component :is="vault.status_badge.icon === 'Lock' ? Lock : (vault.status_badge.icon === 'Sparkles' ? Sparkles : TrendingUp)" class="h-5 w-5" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Lock Progress (for locked vaults) - Only show if vault has balance -->
        <div v-if="vault.type !== 'flexible' && vault.balance > 0" class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <Calendar class="h-5 w-5 text-gray-500" />
                    <span class="font-medium">{{ t('Lock Progress') }}</span>
                </div>
                <span class="text-sm text-gray-500">{{ vault.progress_percentage || 0 }}% {{ t('complete') }}</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                    class="h-full rounded-full transition-all"
                    :class="vault.progress_bar_color_class"
                    :style="{ width: `${vault.progress_percentage || 0}%` }"
                ></div>
            </div>
            <p v-if="vault.days_remaining_text" class="mt-3 text-sm" :class="vault.days_remaining === 0 ? 'text-emerald-600' : 'text-amber-600'">
                {{ vault.days_remaining_text }}
            </p>
        </div>

        <!-- Empty Locked Vault Message -->
        <div v-if="vault.type !== 'flexible' && vault.balance === 0 && vault.interest_earned === 0" class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center gap-3 text-center">
                <AlertCircle class="h-8 w-8 text-amber-500" />
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">{{ t('No funds in this vault yet') }}</p>
                    <p class="text-sm text-gray-500">{{ t('Add funds to start earning') }} {{ vault.interest_rate }}% APY</p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-6 flex flex-col gap-3">
            <div class="flex gap-3">
                <button
                    @click="showDepositModal = true"
                    class="flex-1 rounded-xl bg-emerald-600 py-3 font-semibold text-white transition hover:bg-emerald-700"
                >
                    {{ t('Add Funds') }}
                </button>
                <button
                    @click="showWithdrawModal = true"
                    :disabled="!canWithdraw"
                    class="flex-1 rounded-xl border border-gray-300 py-3 font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                >
                    {{ t('Withdraw') }}
                </button>
            </div>
            
            <!-- Withdraw disabled message for locked vaults -->
            <div v-if="!canWithdraw && withdrawDisabledMessage" class="text-center">
                <p class="text-xs text-amber-600 dark:text-amber-400">
                    🔒 {{ withdrawDisabledMessage }}
                </p>
            </div>
        </div>

        <!-- Vault Info Card -->
        <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Vault Details') }}</h2>
            <div class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ t('Vault Type') }}</span>
                    <span class="font-medium">{{ vault.type_config.name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ t('Lock Period') }}</span>
                    <span class="font-medium">{{ vault.type_config.lock_days }} {{ t('days') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ t('Early Withdrawal Penalty') }}</span>
                    <span class="font-medium text-amber-600">{{ vault.type_config.penalty }}%</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ t('Created') }}</span>
                    <span class="font-medium">{{ formatDate(vault.created_at) }}</span>
                </div>
                <div v-if="vault.locked_until" class="flex justify-between">
                    <span class="text-gray-500">{{ t('Unlocks On') }}</span>
                    <span class="font-medium">{{ formatDate(vault.locked_until) }}</span>
                </div>
                <div v-if="vault.matures_at" class="flex justify-between">
                    <span class="text-gray-500">{{ t('Matures On') }}</span>
                    <span class="font-medium">{{ formatDate(vault.matures_at) }}</span>
                </div>
            </div>
        </div>

        <!-- Transaction History -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Transaction History') }}</h2>
                <History class="h-5 w-5 text-gray-400" />
            </div>
            
            <div class="space-y-3">
                <div
                    v-for="transaction in transactions"
                    :key="transaction.id"
                    class="flex items-center justify-between border-b border-gray-100 pb-3 last:border-0 dark:border-gray-800"
                >
                    <div class="flex items-center gap-3">
                        <span class="text-xl">{{ transaction.icon }}</span>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ transaction.type_name }}</p>
                            <p class="text-xs text-gray-500">{{ formatDate(transaction.created_at) }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p :class="['font-semibold', transaction.color_class]">
                            {{ transaction.formatted_amount_with_sign }}
                        </p>
                        <p class="text-xs text-gray-500">{{ t('Balance') }}: {{ transaction.formatted_balance_after }}</p>
                    </div>
                </div>
                
                <div v-if="transactions.length === 0" class="py-8 text-center text-gray-500">
                    {{ t('No transactions yet') }}
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== MODALS ==================== -->
    
    <!-- Deposit Modal -->
    <ClientOnly>
        <DepositModal
            :is-open="showDepositModal"
            :vault="vault"
            :wallets="[]"
            @close="showDepositModal = false"
            @deposited="handleDepositSuccess"
        />
    </ClientOnly>

    <!-- Withdraw Modal -->
    <ClientOnly>
        <WithdrawModal
            :is-open="showWithdrawModal"
            :vault="vault"
            @close="showWithdrawModal = false"
            @withdrawn="handleWithdrawSuccess"
        />
    </ClientOnly>

    <!-- Edit Vault Modal -->
    <ClientOnly>
        <EditVaultModal
            :is-open="showEditModal"
            :vault="vault"
            @close="showEditModal = false"
            @updated="handleVaultUpdated"
        />
    </ClientOnly>
</template>