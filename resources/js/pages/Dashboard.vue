<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { 
  Plus, Send, TrendingUp, Clock, ChevronRight, ChevronLeft,
  ArrowUpRight, ArrowDownRight, ArrowRight, Eye, EyeOff, Landmark, CreditCard, DollarSign, Wallet,
  Lock, Unlock, Sparkles, Percent
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AddCurrencyModal from '@/components/AddCurrencyModal.vue';
import ClientOnly from '@/components/ClientOnly.vue';
import { useTranslation } from '@/composables/useTranslation';
import CreateVaultModal from '@/pages/Vaults/CreateVaultModal.vue';
import { dashboard } from '@/routes';

const { t } = useTranslation();

// ==================== PROPS ====================
const props = defineProps<{
    wallets: Array<{
        id: number;
        currency_code: string;
        currency_symbol: string;
        balance: number;
        formatted_balance?: string;
        currency_flag: string;
        is_default: boolean;
    }>;
    recentTransactions: Array<{
        id: number;
        type: string;
        amount: number;
        amount_display: string;
        currency_code: string;
        currency_symbol: string;
        name: string;
        date_display: string;
        status: string;
    }>;
    vaults?: Array<{
        id: number;
        name: string;
        icon: string;
        type: string;
        status: string;
        formatted_balance: string;
        formatted_interest_earned: string;
        interest_rate: number;
        progress_percentage: number | null;
        days_remaining_text: string | null;
        type_config: {
            name: string;
            lock_days: number;
            interest_rate: number;
            icon: string;
        };
    }>;
    totalBalance: number;
    mainCurrency: {
        code: string;
        symbol: string;
        formatted_balance?: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
               title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

// ==================== TYPES & INTERFACES ====================
interface CurrencyBalance {
  id: number;
  code: string;
  symbol: string;
  amount: number;
  formattedAmount: string;
  flag: string;
  isMain: boolean;
}

interface Transaction {
  id: number;
  name: string;
  amountDisplay: string;
  type: 'sent' | 'received';
  date: string;
}

interface QuickAction {
  id: string;
  nameKey: string;
  icon: any;
  action: () => void;
}

// ==================== STATE MANAGEMENT ====================
const showBalance = ref(true);
const scrollContainer = ref<HTMLElement | null>(null);
const showLeftArrow = ref(false);
const showRightArrow = ref(true);
const showAddCurrencyModal = ref(false);
const showCreateVaultModal = ref(false);

const currencies = ref<CurrencyBalance[]>([]);
const recentTransactions = ref<Transaction[]>([]);

// Quick actions - replaces add funds section
const quickActions = ref<QuickAction[]>([
  { 
    id: 'card', 
    nameKey: 'Credit/Debit Card', 
    icon: CreditCard, 
    action: () => router.visit('/deposits/card') 
  },
  { 
    id: 'paypal', 
    nameKey: 'PayPal', 
    icon: Wallet, 
    action: () => router.visit('/deposits/paypal') 
  },
]);

// ==================== COMPUTED ====================
const totalBalanceDisplay = computed(() => props.totalBalance.toFixed(2));

const mainCurrency = computed(() => ({
  symbol: props.mainCurrency?.symbol || '$',
  code: props.mainCurrency?.code || 'USD',
  formattedBalance: props.mainCurrency?.formatted_balance || `$${props.totalBalance.toFixed(2)}`
}));

// Vaults computed
const hasVaults = computed(() => (props.vaults?.length || 0) > 0);
const displayVaults = computed(() => (props.vaults || []).slice(0, 2));
const remainingVaultsCount = computed(() => (props.vaults?.length || 0) - 2);

// Get vault status icon
const getVaultStatusIcon = (vault: any) => {
    if (vault.status === 'locked') return Lock;
    if (vault.status === 'matured') return Sparkles;
    return TrendingUp;
};

// Get vault status color
const getVaultStatusColor = (vault: any) => {
    if (vault.status === 'locked') return 'text-amber-600 dark:text-amber-400';
    if (vault.status === 'matured') return 'text-emerald-600 dark:text-emerald-400';
    return 'text-blue-600 dark:text-blue-400';
};

// ==================== METHODS ====================
const toggleBalanceVisibility = () => { showBalance.value = !showBalance.value; };

const getTransactionColor = (type: string) => {
    if (type === 'received') return 'text-green-600 dark:text-green-400';
    if (type === 'sent') return 'text-red-600 dark:text-red-400';
    if (type === 'neutral') return 'text-gray-600 dark:text-gray-400';
    return 'text-gray-600 dark:text-gray-400';
};

const getTransactionIcon = (type: string) => {
    if (type === 'received') return ArrowDownRight;
    if (type === 'sent') return ArrowUpRight;
    if (type === 'neutral') return ArrowRight;
    return ArrowRight;
};
// const getTransactionColor = (type: string) => type === 'received' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
// const getTransactionIcon = (type: string) => type === 'received' ? ArrowDownRight : ArrowUpRight;

const openAddCurrencyModal = () => { showAddCurrencyModal.value = true; };
const openCreateVaultModal = () => { showCreateVaultModal.value = true; };
const viewVault = (vaultId: number) => { router.visit(`/vaults/${vaultId}`); };
const viewAllVaults = () => { router.visit('/vaults'); };

const onCurrencyAdded = (newWallet: any) => {
  currencies.value.push({
    id: newWallet.id,
    code: newWallet.currency_code,
    symbol: newWallet.currency_symbol,
    amount: newWallet.balance,
    formattedAmount: newWallet.formatted_balance,
    flag: newWallet.currency_flag,
    isMain: false,
  });
};

const onVaultCreated = () => {
  showCreateVaultModal.value = false;
  router.reload();
};

const goToDepositOptions = () => router.visit('/deposits');

const goToMoneyRequests = () => router.visit('/money-requests');

// ==================== SCROLL HELPERS ====================
const scroll = (direction: 'left' | 'right') => {
  if (scrollContainer.value) {
    const scrollAmount = 300;
    const newScrollLeft = scrollContainer.value.scrollLeft + (direction === 'left' ? -scrollAmount : scrollAmount);
    scrollContainer.value.scrollTo({ left: newScrollLeft, behavior: 'smooth' });
  }
};

const checkScrollButtons = () => {
  if (scrollContainer.value) {
    const scrollLeft = scrollContainer.value.scrollLeft;
    const maxScroll = scrollContainer.value.scrollWidth - scrollContainer.value.clientWidth;
    showLeftArrow.value = scrollLeft > 10;
    showRightArrow.value = scrollLeft < maxScroll - 10;
  }
};

const handleResize = () => { checkScrollButtons(); };

// ==================== LIFECYCLE ====================
onMounted(() => {
  // Sort wallets: main account first, then alphabetical by currency code
  const sortedWallets = [...props.wallets].sort((a, b) => {
    if (a.is_default) return -1;
    if (b.is_default) return 1;
    return a.currency_code.localeCompare(b.currency_code);
  });

  currencies.value = sortedWallets.map(wallet => ({
    id: wallet.id,
    code: wallet.currency_code,
    symbol: wallet.currency_symbol,
    amount: wallet.balance,
    formattedAmount: wallet.formatted_balance || `${wallet.currency_symbol} ${wallet.balance.toFixed(2)}`,
    flag: wallet.currency_flag,
    isMain: wallet.is_default,
  }));

  recentTransactions.value = props.recentTransactions.map(tx => ({
    id: tx.id,
    name: tx.name,
    amountDisplay: tx.amount_display,
    type: tx.type as 'sent' | 'received',
    date: tx.date_display,
  }));

  if (scrollContainer.value) {
    scrollContainer.value.addEventListener('scroll', checkScrollButtons);
    checkScrollButtons();
  }

  window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
  if (scrollContainer.value) {
    scrollContainer.value.removeEventListener('scroll', checkScrollButtons);
  }
  window.removeEventListener('resize', handleResize);
});
</script>

<template>
    <Head :title="t('Dashboard')" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">

        <div class="mb-2">

            <!-- Top row -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-2 dark:bg-emerald-900/20">
                    <TrendingUp class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                    <span class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Earn R$250</span>
                </div>
                <button 
                    @click="openAddCurrencyModal"
                    class="flex items-center gap-2 rounded-full bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition-all hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    <Plus class="h-4 w-4" />
                    <span>{{ t('Add currency') }}</span>
                </button>
            </div>

            <!-- Total balance -->
            <div class="mb-6">
                <p class="mb-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Total balance') }}</p>
                <div class="flex items-center gap-3">
                    <h1 class="truncate text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">
                        {{ showBalance ? mainCurrency.formattedBalance : '••••••' }}
                    </h1>
                    <button 
                        @click="toggleBalanceVisibility"
                        class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                    >
                        <Eye v-if="!showBalance" class="h-5 w-5" />
                        <EyeOff v-else class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="grid grid-cols-3 gap-4">
                <button @click="router.visit('/transfers')" class="flex flex-col items-center gap-2 rounded-xl border border-sidebar-border/70 bg-white py-3 transition-all hover:shadow-md dark:border-sidebar-border dark:bg-gray-900">
                    <Send class="h-6 w-6 text-gray-700 dark:text-gray-300" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Send') }}</span>
                </button>
                <button @click="goToDepositOptions" class="flex flex-col items-center gap-2 rounded-xl border border-sidebar-border/70 bg-white py-3 transition-all hover:shadow-md dark:border-sidebar-border dark:bg-gray-900">
                    <Plus class="h-6 w-6 text-gray-700 dark:text-gray-300" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Add money') }}</span>
                </button>
                <button @click="goToMoneyRequests" class="flex flex-col items-center gap-2 rounded-xl border border-sidebar-border/70 bg-white py-3 transition-all hover:shadow-md dark:border-sidebar-border dark:bg-gray-900">
                    <CreditCard class="h-6 w-6 text-gray-700 dark:text-gray-300" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Request') }}</span>
                </button>
            </div>
        </div>

        <!-- Currency cards -->
        <div class="mb-4">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Your balances') }}</h2>
                <div class="flex gap-2">
                    <button 
                        @click="scroll('left')" 
                        :disabled="!showLeftArrow"
                        :class="[
                            'rounded-lg border p-2 transition-all',
                            showLeftArrow 
                                ? 'border-sidebar-border/70 bg-white text-gray-700 hover:bg-gray-100 dark:border-sidebar-border dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' 
                                : 'cursor-not-allowed border-gray-200 bg-gray-50 text-gray-400 opacity-50 dark:border-gray-800 dark:bg-gray-900/50'
                        ]"
                    >
                        <ChevronLeft class="h-5 w-5" />
                    </button>
                    <button 
                        @click="scroll('right')" 
                        :disabled="!showRightArrow"
                        :class="[
                            'rounded-lg border p-2 transition-all',
                            showRightArrow 
                                ? 'border-sidebar-border/70 bg-white text-gray-700 hover:bg-gray-100 dark:border-sidebar-border dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' 
                                : 'cursor-not-allowed border-gray-200 bg-gray-50 text-gray-400 opacity-50 dark:border-gray-800 dark:bg-gray-900/50'
                        ]"
                    >
                        <ChevronRight class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <div class="relative">
                <div 
                    ref="scrollContainer"
                    class="scrollbar-hide flex gap-4 overflow-x-auto pb-4"
                    style="scrollbar-width: none; -ms-overflow-style: none;"
                >
                    <div 
                        v-for="currency in currencies" 
                        :key="currency.id"
                        class="min-w-[280px] flex-shrink-0 rounded-xl border border-sidebar-border/70 bg-white p-5 transition-all hover:shadow-md dark:border-sidebar-border dark:bg-gray-900"
                        :class="{ 'ring-1 ring-emerald-400/30 dark:ring-emerald-400/30': currency.isMain }"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-2xl">{{ currency.flag || '🌍' }}</span>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ currency.isMain ? t('Main account') : currency.code }}
                                    </p>
                                </div>
                                <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white">
                                    {{ showBalance ? currency.formattedAmount : '••••' }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ t('Available balance') }}
                                </p>
                            </div>
                            <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-800">
                                <DollarSign class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                            </div>
                        </div>

                        <div class="mt-4 flex gap-2">
                            <button 
                                :class="[
                                    'rounded-lg border border-sidebar-border/70 py-2 text-sm font-medium transition-all hover:bg-gray-50 dark:border-sidebar-border dark:hover:bg-gray-800',
                                    currency.isMain ? 'flex-1' : 'w-full'
                                ]"
                            >
                                {{ t('Send') }}
                            </button>
                            <button 
                                v-if="currency.isMain"
                                class="flex-1 rounded-lg bg-gray-700 py-2 text-sm font-medium text-white transition-all hover:bg-gray-600 dark:bg-gray-600 dark:hover:bg-gray-500"
                            >
                                {{ t('Manage') }}
                            </button>
                        </div>
                    </div>

                    <!-- Add new currency card (dashed) – this now also opens the modal -->
                    <div 
                        @click="openAddCurrencyModal"
                        class="min-w-[280px] flex-shrink-0 cursor-pointer rounded-xl border-2 border-dashed border-gray-300 bg-white p-5 text-center transition-all hover:border-gray-400 hover:shadow-md dark:border-gray-600 dark:bg-gray-900 dark:hover:border-gray-500"
                    >
                        <div class="flex h-full flex-col items-center justify-center gap-3">
                            <div class="rounded-full bg-gray-100 p-3 dark:bg-gray-800">
                                <Plus class="h-6 w-6 text-gray-600 dark:text-gray-400" />
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ t('Add new currency') }}</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Open account in minutes') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== VAULTS PREVIEW SECTION (ADDED - NOTHING BROKEN) ==================== -->
        
        <!-- If user has vaults - show preview -->
        <div v-if="hasVaults" class="mb-4">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Vaults') }}</h2>
                <button 
                    @click="viewAllVaults"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    {{ t('View all') }} 
                    <span v-if="remainingVaultsCount > 0" class="ml-1 rounded-full bg-gray-200 px-1.5 py-0.5 text-xs dark:bg-gray-700">
                        +{{ remainingVaultsCount }}
                    </span>
                </button>
            </div>
            
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Vault Cards Preview (max 2) -->
                <div 
                    v-for="vault in displayVaults" 
                    :key="vault.id"
                    @click="viewVault(vault.id)"
                    class="group cursor-pointer rounded-xl border border-sidebar-border/70 bg-white p-4 transition-all hover:shadow-md dark:border-sidebar-border dark:bg-gray-900"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">{{ vault.icon || '💰' }}</span>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ vault.name }}</h3>
                                <p class="text-xs text-gray-500">{{ vault.type_config?.name }}</p>
                            </div>
                        </div>
                        <component 
                            :is="getVaultStatusIcon(vault)" 
                            :class="['h-4 w-4', getVaultStatusColor(vault)]" 
                        />
                    </div>

                    <div class="mt-3">
                        <p class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ showBalance ? vault.formatted_balance : '••••••' }}
                        </p>
                        <p class="text-xs" :class="vault.formatted_interest_earned !== '$0.00' ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500'">
                            +{{ vault.formatted_interest_earned }} earned
                        </p>
                    </div>

                    <div class="mt-3 flex items-center justify-between">
                        <div class="flex items-center gap-1 text-xs text-gray-500">
                            <Percent class="h-3 w-3" />
                            <span>{{ vault.interest_rate }}% APY</span>
                        </div>
                        <div v-if="vault.days_remaining_text" class="flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                            <Clock class="h-3 w-3" />
                            <span>{{ vault.days_remaining_text }}</span>
                        </div>
                    </div>

                    <!-- Mini progress bar for locked vaults -->
                    <div v-if="vault.status === 'locked' && vault.progress_percentage" class="mt-3">
                        <div class="h-1 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div 
                                class="h-full rounded-full bg-amber-500 transition-all"
                                :style="{ width: `${vault.progress_percentage}%` }"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- "Add New Vault" Card -->
                <div 
                    @click="openCreateVaultModal"
                    class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-gray-300 bg-white p-4 text-center transition-all hover:border-gray-400 hover:shadow-md dark:border-gray-600 dark:bg-gray-900 dark:hover:border-gray-500"
                >
                    <div class="rounded-full bg-gray-100 p-3 dark:bg-gray-800">
                        <Plus class="h-6 w-6 text-gray-600 dark:text-gray-400" />
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ t('Create New Vault') }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Save and earn interest') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- If user has NO vaults - show empty state with preview card -->
        <div v-else class="mb-4">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Vaults') }}</h2>
                <button 
                    @click="openCreateVaultModal"
                    class="text-sm font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
                >
                    {{ t('Create one') }}
                </button>
            </div>
            
            <!-- Preview / Empty State Card -->
            <div 
                @click="openCreateVaultModal"
                class="cursor-pointer rounded-xl border border-sidebar-border/70 bg-gradient-to-r from-emerald-50 to-blue-50 p-5 transition-all hover:shadow-md dark:from-emerald-950/20 dark:to-blue-950/20 dark:border-sidebar-border"
            >
                <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-left">
                    <div class="rounded-full bg-white p-3 shadow-sm dark:bg-gray-800">
                        <Lock class="h-8 w-8 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Grow your savings') }}</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ t('Create locked vaults to earn up to 7% APY on your savings. Choose from 30, 90, 180, or 365-day terms.') }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="rounded-full bg-white px-2 py-1 text-xs font-medium text-gray-700 shadow-sm dark:bg-gray-800 dark:text-gray-300">30 days → 2% APY</span>
                            <span class="rounded-full bg-white px-2 py-1 text-xs font-medium text-gray-700 shadow-sm dark:bg-gray-800 dark:text-gray-300">90 days → 3.5% APY</span>
                            <span class="rounded-full bg-white px-2 py-1 text-xs font-medium text-gray-700 shadow-sm dark:bg-gray-800 dark:text-gray-300">180 days → 5% APY</span>
                            <span class="rounded-full bg-white px-2 py-1 text-xs font-medium text-gray-700 shadow-sm dark:bg-gray-800 dark:text-gray-300">365 days → 7% APY</span>
                        </div>
                    </div>
                    <button class="mt-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 sm:mt-0">
                        {{ t('Get Started') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section (replaces Add funds) -->
        <!-- <div class="mb-4">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Quick actions') }}</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div 
                    v-for="action in quickActions" 
                    :key="action.id"
                    @click="action.action"
                    class="flex cursor-pointer items-center justify-between rounded-xl border border-sidebar-border/70 bg-white p-4 transition-all hover:shadow-md dark:border-sidebar-border dark:bg-gray-900"
                >
                    <div class="flex items-center gap-3">
                        <component :is="action.icon" class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ t(action.nameKey) }}</p>
                        </div>
                    </div>
                    <ChevronRight class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                </div>
            </div>
        </div> -->

        <!-- Transactions -->
        <div>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Transactions') }}</h2>
                <button class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                    {{ t('See all') }}
                </button>
            </div>
            <div class="space-y-2">
                <div 
                    v-for="transaction in recentTransactions" 
                    :key="transaction.id"
                    class="flex items-center justify-between rounded-xl border border-sidebar-border/70 bg-white p-4 transition-all hover:shadow-md dark:border-sidebar-border dark:bg-gray-900"
                >
                    <div class="flex items-center gap-3">
                        <div :class="[
                            'rounded-full p-2',
                            transaction.type === 'received' 
                                ? 'bg-green-50 dark:bg-green-900/20' 
                                : 'bg-red-50 dark:bg-red-900/20'
                        ]">
                            <component 
                                :is="getTransactionIcon(transaction.type)" 
                                :class="getTransactionColor(transaction.type)" 
                                class="h-4 w-4" 
                            />
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ transaction.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ transaction.date }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p :class="['font-semibold', getTransactionColor(transaction.type)]">
                            {{ transaction.amountDisplay }}
                        </p>
                    </div>
                </div>

                <div v-if="recentTransactions.length === 0" class="rounded-xl border border-sidebar-border/70 bg-white p-8 text-center dark:border-sidebar-border dark:bg-gray-900">
                    <p class="text-gray-500 dark:text-gray-400">{{ t('No transactions yet') }}</p>
                    <button @click="goToDepositOptions" class="mt-2 text-sm text-blue-600 hover:underline dark:text-blue-400">
                        {{ t('Make your first deposit') }}
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- Add Currency Modal -->
    <ClientOnly>
        <AddCurrencyModal
            :is-open="showAddCurrencyModal"
            @close="showAddCurrencyModal = false"
            @added="onCurrencyAdded"
        />
    </ClientOnly>

    <!-- Create Vault Modal -->
    <ClientOnly>
        <CreateVaultModal
            :is-open="showCreateVaultModal"
            :wallets="wallets"
            :available-types="{}"
            @close="showCreateVaultModal = false"
            @created="onVaultCreated"
        />
    </ClientOnly>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.transition-all {
  transition-property: all;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 200ms;
}
.scrollbar-hide {
  -webkit-overflow-scrolling: touch;
  scroll-behavior: smooth;
}
</style>