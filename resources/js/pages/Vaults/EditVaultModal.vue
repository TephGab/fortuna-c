<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Edit Vault') }}</h2>
                <button @click="close" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-800">
                    <X class="h-5 w-5 text-gray-500" />
                </button>
            </div>

            <!-- Step 1: Edit Form -->
            <div v-if="!showConfirmation" class="space-y-5">
                <form @submit.prevent="goToConfirmation">
                    <div class="space-y-5">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Vault Name') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-xl border border-gray-300 p-3 focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                required
                            />
                            <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Description') }}
                            </label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-xl border border-gray-300 p-3 focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                :placeholder="t('What is this vault for?')"
                            ></textarea>
                        </div>

                        <!-- Error message -->
                        <div v-if="errorMessage" class="rounded-xl bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400">
                            {{ errorMessage }}
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button
                                type="button"
                                @click="close"
                                class="flex-1 rounded-xl border border-gray-300 py-2.5 font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                            >
                                {{ t('Cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="!isFormValid"
                                class="flex-1 rounded-xl bg-emerald-600 py-2.5 font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ t('Continue') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Step 2: Confirmation Screen -->
            <div v-else class="space-y-5">
                <div class="rounded-xl bg-emerald-50 p-4 text-center dark:bg-emerald-900/20">
                    <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                        <Edit2 class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <p class="text-sm text-emerald-700 dark:text-emerald-300">{{ t('Please review your changes') }}</p>
                </div>

                <!-- Confirmation Details -->
                <div class="space-y-3 rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                    <div class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                        <span class="text-gray-600">{{ t('Vault Name') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ form.name }}</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-200 pb-2 dark:border-gray-700">
                        <span class="text-gray-600">{{ t('Description') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ form.description || t('No description') }}</span>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        @click="showConfirmation = false"
                        class="flex-1 rounded-xl border border-gray-300 py-2.5 font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        {{ t('Back') }}
                    </button>
                    <button
                        type="button"
                        @click="submit"
                        :disabled="isSubmitting"
                        class="flex-1 rounded-xl bg-emerald-600 py-2.5 font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <Loader2 v-if="isSubmitting" class="mx-auto h-5 w-5 animate-spin" />
                        <span v-else>{{ t('Confirm Changes') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { X, Loader2, Edit2 } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import { useTranslation } from '@/composables/useTranslation';

const { t } = useTranslation();

const props = defineProps<{
    isOpen: boolean;
    vault: {
        id: number;
        name: string;
        description?: string | null;
    };
}>();

const emit = defineEmits(['close', 'updated']);

// ==================== STATE ====================
const form = ref({
    name: '',
    description: '',
});
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);
const errors = ref<Record<string, string>>({});
const showConfirmation = ref(false);

// ==================== COMPUTED ====================
const isFormValid = computed(() => {
    return form.value.name && form.value.name.trim().length >= 3;
});

// ==================== METHODS ====================
const goToConfirmation = () => {
    if (!isFormValid.value) return;
    showConfirmation.value = true;
};

const resetForm = () => {
    form.value = {
        name: props.vault.name || '',
        description: props.vault.description || '',
    };
    errors.value = {};
    errorMessage.value = null;
    showConfirmation.value = false;
    isSubmitting.value = false;
};

// ==================== WATCHERS ====================
watch(() => props.isOpen, (open) => {
    if (open) {
        resetForm();
    }
});

// ==================== SUBMIT ====================
const submit = async () => {
    if (!isFormValid.value) {
        errors.value = { name: t('Vault name is required') };
        return;
    }
    
    isSubmitting.value = true;
    errorMessage.value = null;
    errors.value = {};
    
    try {
        await router.put(`/vaults/${props.vault.id}`, {
            name: form.value.name,
            description: form.value.description,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                emit('updated');
                close();
            },
            onError: (backendErrors) => {
                console.error('Update errors:', backendErrors);
                errors.value = backendErrors;
                if (backendErrors.error) {
                    errorMessage.value = backendErrors.error;
                } else {
                    errorMessage.value = t('Failed to update vault. Please try again.');
                }
                showConfirmation.value = false;
            }
        });
    } catch (err) {
        console.error('Update failed:', err);
        errorMessage.value = t('Failed to update vault. Please try again.');
        showConfirmation.value = false;
    } finally {
        isSubmitting.value = false;
    }
};

const close = () => {
    resetForm();
    emit('close');
};
</script>