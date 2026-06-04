<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { 
  Plus, 
  Send, 
  TrendingUp, 
  Clock, 
  ChevronRight,
  ChevronLeft,
  ArrowUpRight,
  ArrowDownRight,
  Eye,
  EyeOff,
  Landmark,
  CreditCard,
  DollarSign
} from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

// ==================== PROPS ====================
// Receive real data from Laravel controller
const props = defineProps<{
    wallets: Array<{
        id: number;
        currency_code: string;
        currency_symbol: string;
        balance: number;
        currency_flag: string;
        is_default: boolean;
    }>;
    recentTransactions: Array<{
        id: number;
        type: string;
        amount: number;
        currency_code: string;
        currency_symbol: string;
        description: string;
        created_at: string;
        status: string;
    }>;
    totalBalance: number;
    mainCurrency: {
        code: string;
        symbol: string;
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
  flag: string;
  isMain: boolean;
}

interface Transaction {
  id: number;
  name: string;
  amount: number;
  currency: string;
  symbol: string;
  type: 'sent' | 'received';
  date: string;
}

interface DepositOption {
  id: string;
  name: string;
  icon: any;
  fee: string;
  time: string;
}

// ==================== STATE MANAGEMENT ====================
const showBalance = ref(true);
const scrollContainer = ref<HTMLElement | null>(null);
const showLeftArrow = ref(false);
const showRightArrow = ref(true);

// Transform wallets data from props to component format
const currencies = ref<CurrencyBalance[]>([]);

// Transform transactions from props to component format
const recentTransactions = ref<Transaction[]>([]);

// Deposit options (can also come from backend or stay static)
const depositOptions = ref<DepositOption[]>([
  { id: 'bank', name: 'Bank Transfer', icon: Landmark, fee: 'Free', time: '1-3 days' },
  { id: 'card', name: 'Credit/Debit Card', icon: CreditCard, fee: '2.9%', time: 'Instant' },
  { id: 'wire', name: 'Wire Transfer', icon: Send, fee: '$15', time: 'Same day' },
]);

// ==================== COMPUTED PROPERTIES ====================
const totalBalanceDisplay = computed(() => {
  return props.totalBalance.toFixed(2);
});

const mainCurrency = computed(() => {
  return {
    symbol: props.mainCurrency?.symbol || '$',
    code: props.mainCurrency?.code || 'USD'
  };
});

// ==================== METHODS ====================
const toggleBalanceVisibility = () => {
  showBalance.value = !showBalance.value;
};

const formatAmount = (amount: number, symbol: string) => {
  return `${symbol} ${amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const formatCompactAmount = (amount: number, symbol: string) => {
  if (amount >= 1000) {
    return `${symbol} ${(amount / 1000).toFixed(1)}k`;
  }
  return `${symbol} ${amount.toFixed(2)}`;
};

const getTransactionColor = (type: string) => {
  return type === 'received' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
};

const getTransactionIcon = (type: string) => {
  return type === 'received' ? ArrowDownRight : ArrowUpRight;
};

const getTransactionDisplayName = (transaction: Transaction) => {
  // If it's a deposit
  if (transaction.name === 'Deposit') {
    return 'Deposit';
  }
  // If it's a transfer
  if (transaction.name.startsWith('Transfer')) {
    return transaction.name;
  }
  return transaction.name;
};

const addNewCurrency = () => {
  router.visit('/currencies/add');
};

const handleDeposit = (option: DepositOption) => {
  if (option.id === 'card') {
    router.visit('/deposits/card');
  } else {
    console.log('Deposit with:', option.name);
  }
};

const goToDepositOptions = () => {
  router.visit('/deposits');
};

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

const handleResize = () => {
  checkScrollButtons();
};

// Format date to relative time (Today, Yesterday, or date)
const formatRelativeDate = (dateString: string): string => {
  const date = new Date(dateString);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);
  
  if (date.toDateString() === today.toDateString()) {
    return 'Today';
  } else if (date.toDateString() === yesterday.toDateString()) {
    return 'Yesterday';
  } else {
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  }
};

// Process real data on mount
onMounted(() => {
  // Transform wallets data
  currencies.value = props.wallets.map(wallet => ({
    id: wallet.id,
    code: wallet.currency_code,
    symbol: wallet.currency_symbol,
    amount: wallet.balance,
    flag: wallet.currency_flag,
    isMain: wallet.is_default,
  }));
  
  // Transform transactions data
  recentTransactions.value = props.recentTransactions.map(tx => ({
    id: tx.id,
    name: tx.description || (tx.type === 'deposit' ? 'Deposit' : 'Transfer'),
    amount: tx.amount,
    currency: tx.currency_code,
    symbol: tx.currency_symbol,
    type: tx.type === 'deposit' || tx.type === 'transfer_received' ? 'received' : 'sent',
    date: formatRelativeDate(tx.created_at),
  }));
  
  // Set up scroll listeners
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
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">

        <!-- =====================================================
             HEADER SECTION
        ====================================================== -->
        <div class="mb-2">

            <!-- Top row: Earn badge + Add currency button -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-2 dark:bg-emerald-900/20">
                    <TrendingUp class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                    <span class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Earn R$250</span>
                </div>
                <button 
                    @click="addNewCurrency"
                    class="flex items-center gap-2 rounded-full bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition-all hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    <Plus class="h-4 w-4" />
                    <span>Add currency</span>
                </button>
            </div>

            <!-- Total balance + visibility toggle -->
            <div class="mb-6">
                <p class="mb-1 text-sm text-gray-500 dark:text-gray-400">Total balance</p>
                <div class="flex items-center gap-3">
                    <h1 class="truncate text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">
                        {{ showBalance ? formatAmount(Number(totalBalanceDisplay), mainCurrency.symbol) : '••••••' }}
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

            <!-- Quick action buttons -->
            <div class="grid grid-cols-3 gap-4">
                <button class="flex flex-col items-center gap-2 rounded-xl border border-sidebar-border/70 bg-white py-3 transition-all hover:shadow-md dark:border-sidebar-border dark:bg-gray-900">
                    <Send class="h-6 w-6 text-gray-700 dark:text-gray-300" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Send</span>
                </button>
                <button @click="goToDepositOptions" class="flex flex-col items-center gap-2 rounded-xl border border-sidebar-border/70 bg-white py-3 transition-all hover:shadow-md dark:border-sidebar-border dark:bg-gray-900">
                    <Plus class="h-6 w-6 text-gray-700 dark:text-gray-300" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Add money</span>
                </button>
                <button class="flex flex-col items-center gap-2 rounded-xl border border-sidebar-border/70 bg-white py-3 transition-all hover:shadow-md dark:border-sidebar-border dark:bg-gray-900">
                    <CreditCard class="h-6 w-6 text-gray-700 dark:text-gray-300" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Request</span>
                </button>
            </div>
        </div>

        <!-- =====================================================
             CURRENCY CARDS - Horizontal scrollable row
        ====================================================== -->
        <div class="mb-4">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Your balances</h2>
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
                    <!-- Currency cards from real data -->
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
                                        {{ currency.isMain ? 'Main account' : currency.code }}
                                    </p>
                                </div>
                                <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white">
                                    {{ showBalance ? formatCompactAmount(currency.amount, currency.symbol) : '••••' }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Available balance
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
                                Send
                            </button>
                            <button 
                                v-if="currency.isMain"
                                class="flex-1 rounded-lg bg-gray-700 py-2 text-sm font-medium text-white transition-all hover:bg-gray-600 dark:bg-gray-600 dark:hover:bg-gray-500"
                            >
                                Manage
                            </button>
                        </div>
                    </div>

                    <!-- Add new currency card -->
                    <div 
                        @click="addNewCurrency"
                        class="min-w-[280px] flex-shrink-0 cursor-pointer rounded-xl border-2 border-dashed border-gray-300 bg-white p-5 text-center transition-all hover:border-gray-400 hover:shadow-md dark:border-gray-600 dark:bg-gray-900 dark:hover:border-gray-500"
                    >
                        <div class="flex h-full flex-col items-center justify-center gap-3">
                            <div class="rounded-full bg-gray-100 p-3 dark:bg-gray-800">
                                <Plus class="h-6 w-6 text-gray-600 dark:text-gray-400" />
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Add new currency</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Open account in minutes</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =====================================================
             ADD FUNDS — Deposit options row
        ====================================================== -->
        <div class="mb-4">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Add funds</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div 
                    v-for="option in depositOptions" 
                    :key="option.id"
                    @click="handleDeposit(option)"
                    class="flex cursor-pointer items-center justify-between rounded-xl border border-sidebar-border/70 bg-white p-4 transition-all hover:shadow-md dark:border-sidebar-border dark:bg-gray-900"
                >
                    <div class="flex items-center gap-3">
                        <component :is="option.icon" class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ option.name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Fee: {{ option.fee }}</p>
                        </div>
                    </div>
                    <ChevronRight class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                </div>
            </div>
        </div>

        <!-- =====================================================
             RECENT TRANSACTIONS
        ====================================================== -->
        <div>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Transactions</h2>
                <button class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                    See all
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
                            <p class="font-medium text-gray-900 dark:text-white">{{ getTransactionDisplayName(transaction) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ transaction.date }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p :class="['font-semibold', getTransactionColor(transaction.type)]">
                            {{ transaction.type === 'received' ? '+' : '-' }} {{ transaction.symbol }} {{ transaction.amount.toFixed(2) }}
                        </p>
                    </div>
                </div>
                
                <!-- Empty state -->
                <div v-if="recentTransactions.length === 0" class="rounded-xl border border-sidebar-border/70 bg-white p-8 text-center dark:border-sidebar-border dark:bg-gray-900">
                    <p class="text-gray-500 dark:text-gray-400">No transactions yet</p>
                    <button @click="goToDepositOptions" class="mt-2 text-sm text-blue-600 hover:underline">Make your first deposit</button>
                </div>
            </div>
        </div>

    </div>
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