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
  id: string;
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

// Multi-currency balances - Main account is first card
const currencies = ref<CurrencyBalance[]>([
  { id: '1', code: 'BRL', symbol: 'R$', amount: 153.77, flag: '🇧🇷', isMain: true },
  { id: '2', code: 'USD', symbol: '$', amount: 30.35, flag: '🇺🇸', isMain: false },
  { id: '3', code: 'EUR', symbol: '€', amount: 0.00, flag: '🇪🇺', isMain: false },
  { id: '4', code: 'GBP', symbol: '£', amount: 125.50, flag: '🇬🇧', isMain: false },
  { id: '5', code: 'JPY', symbol: '¥', amount: 5000, flag: '🇯🇵', isMain: false },
]);

const recentTransactions = ref<Transaction[]>([
  {
    id: 1,
    name: 'STEPHANE GABEAU',
    amount: 23.04,
    currency: 'BRL',
    symbol: 'R$',
    type: 'sent',
    date: 'Today'
  },
  {
    id: 2,
    name: 'Netflix Subscription',
    amount: 45.90,
    currency: 'BRL',
    symbol: 'R$',
    type: 'sent',
    date: 'Yesterday'
  },
  {
    id: 3,
    name: 'Freelance Payment',
    amount: 500.00,
    currency: 'BRL',
    symbol: 'R$',
    type: 'received',
    date: 'Feb 12, 2026'
  },
]);

const depositOptions = ref<DepositOption[]>([
  { id: 'bank', name: 'Bank Transfer', icon: Landmark, fee: 'Free', time: '1-3 days' },
  { id: 'card', name: 'Credit/Debit Card', icon: CreditCard, fee: '2.9%', time: 'Instant' },
  { id: 'wire', name: 'Wire Transfer', icon: Send, fee: '$15', time: 'Same day' },
]);

// ==================== COMPUTED PROPERTIES ====================
const totalBalance = computed(() => {
  return currencies.value.reduce((sum, curr) => sum + curr.amount, 0).toFixed(2);
});

const mainCurrency = computed(() => {
  return currencies.value.find(c => c.isMain) || currencies.value[0];
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

const addNewCurrency = () => {
  console.log('Add new currency');
};

const handleDeposit = (option: DepositOption) => {
  console.log('Deposit with:', option.name);
};

// Add money button to go to deposit options
const goToDepositOptions = () => {
    console.log('Navigating to deposits...'); 
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

onMounted(() => {
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
             - Earn banner (left) + Add currency button (right)
             - Total balance with eye toggle
             - Quick action buttons: Send / Add Money / Request
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
                        {{ showBalance ? formatAmount(Number(totalBalance), mainCurrency?.symbol || 'R$') : '••••••' }}
                    </h1>
                    <!--
                        FIX: Eye icon was text-gray-500 with no dark override — nearly invisible in dark mode.
                        Added dark:text-gray-400 so it stays visible against dark card backgrounds.
                    -->
                    <button 
                        @click="toggleBalanceVisibility"
                        class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                    >
                        <Eye v-if="!showBalance" class="h-5 w-5" />
                        <EyeOff v-else class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Quick action buttons: Send / Add money / Request -->
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
             CURRENCY CARDS — Horizontal scrollable row
             
             FIX (main card ring): Removed `ring-2 ring-emerald-500/50`
             which was casting too much visual shadow/weight in light mode.
             Replaced with a softer `ring-1 ring-emerald-400/30` so the
             main account card is subtly distinguished without looking heavy.

             FIX (Manage button): Removed from non-main cards entirely.
             Only the main BRL card keeps "Send + Manage".
             Other currency cards only show "Send".
             A "Manage currencies" button will be added separately later.

             FIX (Manage button color): In light mode bg-gray-900 was too dark
             and felt harsh. Changed to bg-gray-700 for light mode, keeping
             dark:bg-gray-600 for dark mode — both are readable but less heavy.
        ====================================================== -->
        <div class="mb-4">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Your balances</h2>
                <div class="flex gap-2">
                    <!-- Left scroll arrow — disabled when already at the start -->
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
                    <!-- Right scroll arrow — disabled when already at the end -->
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

            <!-- Scrollable card track -->
            <div class="relative">
                <div 
                    ref="scrollContainer"
                    class="scrollbar-hide flex gap-4 overflow-x-auto pb-4"
                    style="scrollbar-width: none; -ms-overflow-style: none;"
                >
                    <!-- Currency card loop -->
                    <div 
                        v-for="currency in currencies" 
                        :key="currency.id"
                        class="min-w-[280px] flex-shrink-0 rounded-xl border border-sidebar-border/70 bg-white p-5 transition-all hover:shadow-md dark:border-sidebar-border dark:bg-gray-900"
                        :class="{
                            /*
                                FIX: was ring-2 ring-emerald-500/50 which looked heavy (almost like a shadow) in light mode.
                                ring-1 with lower opacity (30%) is enough to signal 'this is the main account'
                                without overwhelming the card visually.
                            */
                            'ring-1 ring-emerald-400/30 dark:ring-emerald-400/30': currency.isMain
                        }"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-2xl">{{ currency.flag }}</span>
                                    <!--
                                        FIX: was text-gray-600 dark:text-gray-400.
                                        In dark mode gray-600 (#4b5563) is too dim on gray-900 cards.
                                        Changed base to text-gray-500 and explicit dark:text-gray-400.
                                    -->
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ currency.isMain ? 'Main account' : currency.code }}
                                    </p>
                                </div>
                                <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white">
                                    {{ showBalance ? formatCompactAmount(currency.amount, currency.symbol) : '••••' }}
                                </p>
                                <!--
                                    FIX: was text-gray-500 dark:text-gray-400.
                                    text-gray-500 in dark mode on gray-900 is borderline — bumped to dark:text-gray-400 explicitly.
                                -->
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Available balance
                                </p>
                            </div>
                            <div class="rounded-full bg-gray-100 p-2 dark:bg-gray-800">
                                <DollarSign class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                            </div>
                        </div>

                        <!--
                            CHANGE: Button layout is now conditional.

                            MAIN ACCOUNT CARD → shows both "Send" and "Manage"
                                - "Manage" button: changed from bg-gray-900 (too dark in light mode)
                                  to bg-gray-700 in light / dark:bg-gray-600 in dark mode.
                                  This is softer while still being a filled CTA.

                            OTHER CURRENCY CARDS → shows only "Send" (full width)
                                - "Manage" removed; a separate "Manage currencies" button will come later.
                        -->
                        <div class="mt-4 flex gap-2">

                            <!-- Send button — shown on all cards -->
                            <button 
                                :class="[
                                    'rounded-lg border border-sidebar-border/70 py-2 text-sm font-medium transition-all hover:bg-gray-50 dark:border-sidebar-border dark:hover:bg-gray-800',
                                    currency.isMain ? 'flex-1' : 'w-full'
                                ]"
                            >
                                Send
                            </button>

                            <!-- Manage button — ONLY on main account card -->
                            <button 
                                v-if="currency.isMain"
                                class="flex-1 rounded-lg bg-gray-700 py-2 text-sm font-medium text-white transition-all hover:bg-gray-600 dark:bg-gray-600 dark:hover:bg-gray-500"
                            >
                                Manage
                            </button>

                        </div>
                    </div>

                    <!--
                        Add new currency card — dashed border placeholder.
                        FIX: border was border-sidebar-border/70 which can resolve to near-invisible in dark mode.
                        Replaced with explicit border-gray-300 dark:border-gray-600 for reliable contrast on both themes.
                    -->
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
                                <!--
                                    FIX: was text-gray-500 dark:text-gray-400.
                                    Explicit dark:text-gray-400 ensures this subtitle is readable in dark mode.
                                -->
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Open account in minutes</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =====================================================
             ADD FUNDS — Deposit options row
             
             FIX: ChevronRight was hardcoded text-gray-400 with no dark override.
             Added dark:text-gray-500 to keep it visible but muted in dark mode.

             FIX: Deposit option fee/time sub-labels were text-gray-500 with no dark override.
             Added explicit dark:text-gray-400 for better contrast.
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
                            <!--
                                FIX: was text-gray-500 only. In dark mode on gray-900 this is borderline.
                                Added explicit dark:text-gray-400 for reliable legibility.
                            -->
                            <p class="text-xs text-gray-500 dark:text-gray-400">Fee: {{ option.fee }}</p>
                        </div>
                    </div>
                    <!--
                        FIX: was text-gray-400 only, no dark override.
                        dark:text-gray-500 keeps the chevron visible but subtle in dark mode.
                    -->
                    <ChevronRight class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                </div>
            </div>
        </div>

        <!-- =====================================================
             RECENT TRANSACTIONS

             FIX: Transaction icon backgrounds (sent/received) had no dark override.
             Added dark:bg-red-900/20 and dark:bg-green-900/20 so the icon
             badge doesn't disappear on dark card surfaces.

             FIX: Transaction name was text-gray-900 with no dark override.
             Added dark:text-white to stay readable.

             FIX: Transaction date was text-gray-500 with no dark override.
             Added dark:text-gray-400.

             FIX: Amount colors now include dark mode variants:
             getTransactionColor() returns both light and dark classes.
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
                        <!--
                            FIX: icon badge backgrounds had no dark override.
                            In dark mode the light bg-red-50 / bg-green-50 would look
                            washed out against the dark card. Added dark:bg variants.
                        -->
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
                            <!-- FIX: Added dark:text-white — was missing dark override -->
                            <p class="font-medium text-gray-900 dark:text-white">{{ transaction.name }}</p>
                            <!-- FIX: Added explicit dark:text-gray-400 for date sub-label -->
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ transaction.date }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <!--
                            Amount: getTransactionColor() now returns both light + dark classes.
                            e.g. 'text-green-600 dark:text-green-400' or 'text-red-600 dark:text-red-400'
                        -->
                        <p :class="['font-semibold', getTransactionColor(transaction.type)]">
                            {{ transaction.type === 'received' ? '+' : '-' }} {{ transaction.symbol }} {{ transaction.amount.toFixed(2) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
/* Hide scrollbar track while keeping scroll functionality */
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}

/* Smooth easing for all transitions */
.transition-all {
  transition-property: all;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 200ms;
}

/* Momentum-based touch scrolling on iOS + smooth scroll on all */
.scrollbar-hide {
  -webkit-overflow-scrolling: touch;
  scroll-behavior: smooth;
}
</style>