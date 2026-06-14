<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Edit Vault</h2>
                <button @click="close" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-800">
                    <X class="h-5 w-5 text-gray-500" />
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Vault Name
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-xl border border-gray-300 p-3 focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        required
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Description
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-xl border border-gray-300 p-3 focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    ></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        @click="close"
                        class="flex-1 rounded-xl border border-gray-300 py-2.5 font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="flex-1 rounded-xl bg-gray-900 py-2.5 font-semibold text-white transition hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-700 dark:hover:bg-gray-600"
                    >
                        <Loader2 v-if="isSubmitting" class="mx-auto h-5 w-5 animate-spin" />
                        <span v-else>Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { X, Loader2 } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    isOpen: boolean;
    vault: any;
}>();

const emit = defineEmits(['close', 'updated']);

const form = ref({
    name: props.vault.name,
    description: props.vault.description || '',
});
const isSubmitting = ref(false);

const submit = async () => {
    isSubmitting.value = true;
    
    try {
        await router.put(`/vaults/${props.vault.id}`, {
            name: form.value.name,
            description: form.value.description,
        });
        
        emit('updated');
        close();
    } catch (err) {
        console.error('Update failed:', err);
    } finally {
        isSubmitting.value = false;
    }
};

const close = () => {
    form.value = { name: props.vault.name, description: props.vault.description || '' };
    emit('close');
};
</script>
