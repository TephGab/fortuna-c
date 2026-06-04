<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { ref, onMounted, computed } from 'vue';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    },
});

// ==================== LOCATION DETECTION ====================

// State for location detection
const isDetecting = ref(true);
const detectedCountry = ref<string | null>(null);
const selectedCountry = ref('US'); // Default to US

// List of available countries with currencies
const countriesList = ref([
    { code: 'US', name: 'United States', currency: 'USD', flag: '🇺🇸' },
    { code: 'GB', name: 'United Kingdom', currency: 'GBP', flag: '🇬🇧' },
    { code: 'CA', name: 'Canada', currency: 'CAD', flag: '🇨🇦' },
    { code: 'AU', name: 'Australia', currency: 'AUD', flag: '🇦🇺' },
    { code: 'DE', name: 'Germany', currency: 'EUR', flag: '🇩🇪' },
    { code: 'FR', name: 'France', currency: 'EUR', flag: '🇫🇷' },
    { code: 'IT', name: 'Italy', currency: 'EUR', flag: '🇮🇹' },
    { code: 'ES', name: 'Spain', currency: 'EUR', flag: '🇪🇸' },
    { code: 'JP', name: 'Japan', currency: 'JPY', flag: '🇯🇵' },
    { code: 'CN', name: 'China', currency: 'CNY', flag: '🇨🇳' },
    { code: 'IN', name: 'India', currency: 'INR', flag: '🇮🇳' },
    { code: 'BR', name: 'Brazil', currency: 'BRL', flag: '🇧🇷' },
    { code: 'MX', name: 'Mexico', currency: 'MXN', flag: '🇲🇽' },
    { code: 'DO', name: 'Dominican Republic', currency: 'DOP', flag: '🇩🇴' },
    { code: 'HT', name: 'Haiti', currency: 'HTG', flag: '🇭🇹' },
    { code: 'CH', name: 'Switzerland', currency: 'CHF', flag: '🇨🇭' },
    { code: 'SE', name: 'Sweden', currency: 'SEK', flag: '🇸🇪' },
    { code: 'NO', name: 'Norway', currency: 'NOK', flag: '🇳🇴' },
    { code: 'DK', name: 'Denmark', currency: 'DKK', flag: '🇩🇰' },
    { code: 'PL', name: 'Poland', currency: 'PLN', flag: '🇵🇱' },
    { code: 'TR', name: 'Turkey', currency: 'TRY', flag: '🇹🇷' },
    { code: 'KR', name: 'South Korea', currency: 'KRW', flag: '🇰🇷' },
    { code: 'SG', name: 'Singapore', currency: 'SGD', flag: '🇸🇬' },
    { code: 'HK', name: 'Hong Kong', currency: 'HKD', flag: '🇭🇰' },
    { code: 'NZ', name: 'New Zealand', currency: 'NZD', flag: '🇳🇿' },
    { code: 'TH', name: 'Thailand', currency: 'THB', flag: '🇹🇭' },
    { code: 'VN', name: 'Vietnam', currency: 'VND', flag: '🇻🇳' },
    { code: 'MY', name: 'Malaysia', currency: 'MYR', flag: '🇲🇾' },
    { code: 'PH', name: 'Philippines', currency: 'PHP', flag: '🇵🇭' },
    { code: 'ZA', name: 'South Africa', currency: 'ZAR', flag: '🇿🇦' },
    { code: 'NG', name: 'Nigeria', currency: 'NGN', flag: '🇳🇬' },
    { code: 'AE', name: 'UAE', currency: 'AED', flag: '🇦🇪' },
    { code: 'SA', name: 'Saudi Arabia', currency: 'SAR', flag: '🇸🇦' },
]);

// Get selected country details
const selectedCountryDetails = computed(() => {
    return countriesList.value.find(c => c.code === selectedCountry.value);
});

// Get currency symbol for display
const getCurrencySymbol = (currencyCode: string) => {
    const symbols: Record<string, string> = {
        'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥', 'CNY': '¥',
        'INR': '₹', 'BRL': 'R$', 'MXN': '$', 'CAD': '$', 'AUD': '$',
        'DOP': 'RD$', 'HTG': 'G', 'CHF': 'CHF', 'SEK': 'kr', 'NOK': 'kr',
        'DKK': 'kr', 'PLN': 'zł', 'TRY': '₺', 'KRW': '₩', 'SGD': 'S$',
        'HKD': 'HK$', 'NZD': 'NZ$', 'THB': '฿', 'VND': '₫', 'MYR': 'RM',
        'PHP': '₱', 'ZAR': 'R', 'NGN': '₦', 'AED': 'د.إ', 'SAR': '﷼',
    };
    return symbols[currencyCode] || currencyCode;
};

// Auto-detect user location
const detectLocation = async () => {
    isDetecting.value = true;
    
    const services = [
        {
            url: 'https://ipapi.co/json/',
            parser: (data: any) => ({ country: data.country_code })
        },
        {
            url: 'https://ip-api.com/json/',
            parser: (data: any) => ({ country: data.countryCode })
        },
    ];
    
    for (const service of services) {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);
            
            const response = await fetch(service.url, { signal: controller.signal });
            clearTimeout(timeoutId);
            
            const data = await response.json();
            const countryCode = service.parser(data).country;
            
            // Validate country code exists in our list
            if (countryCode && countriesList.value.some(c => c.code === countryCode)) {
                selectedCountry.value = countryCode;
                detectedCountry.value = countryCode;
                break;
            }
        } catch (error) {
            console.warn(`Location detection failed for ${service.url}:`, error);
            continue;
        }
    }
    
    isDetecting.value = false;
};

// Run detection on mount
onMounted(() => {
    detectLocation();
});
</script>

<template>
    <Head title="Register" />

    <!-- Location Detection Status Bar -->
    <div 
        v-if="isDetecting" 
        class="mb-4 flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-300"
    >
        <Spinner class="h-4 w-4" />
        <span>Detecting your location for better experience...</span>
    </div>

    <!-- Detected Location Info -->
    <div 
        v-if="!isDetecting && detectedCountry && selectedCountry === detectedCountry" 
        class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-950/50 dark:text-green-300"
    >
        <span class="text-lg">{{ selectedCountryDetails?.flag }}</span>
        <span>We detected you're in <strong>{{ selectedCountryDetails?.name }}</strong></span>
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <!-- Name Field -->
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Full name"
                />
                <InputError :message="errors.name" />
            </div>

            <!-- Email Field -->
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <!-- Country Selection Field (NEW) -->
            <div class="grid gap-2">
                <Label for="country">Country of residence</Label>
                <select
                    id="country"
                    name="country"
                    v-model="selectedCountry"
                    required
                    :tabindex="3"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="" disabled>Select your country</option>
                    <option 
                        v-for="country in countriesList" 
                        :key="country.code"
                        :value="country.code"
                    >
                        {{ country.flag }} {{ country.name }} ({{ country.currency }})
                    </option>
                </select>
                <InputError :message="errors.country" />
                
                <!-- Selected Currency Info -->
                <div v-if="selectedCountryDetails" class="mt-2 rounded-lg bg-muted/50 p-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">{{ selectedCountryDetails.flag }}</span>
                            <div>
                                <p class="text-sm font-medium">
                                    {{ selectedCountryDetails.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Your main account will be in {{ selectedCountryDetails.currency }}
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-primary">
                                {{ getCurrencySymbol(selectedCountryDetails.currency) }}
                            </p>
                            <p class="text-xs text-muted-foreground">{{ selectedCountryDetails.currency }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Password Field -->
            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <!-- Confirm Password Field -->
            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="5"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <!-- Hidden field to ensure country is submitted -->
            <input type="hidden" name="country" :value="selectedCountry" />

            <!-- Submit Button -->
            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="6"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="7"
                >Log in</TextLink
            >
        </div>
    </Form>
</template>





<!-- <script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    },
});
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Full name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="6"
                >Log in</TextLink
            >
        </div>
    </Form>
</template> -->
