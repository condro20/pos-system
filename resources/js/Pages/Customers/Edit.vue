<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ customer: Object });

const form = useForm({
    name: props.customer.name,
    phone: props.customer.phone || '',
    address: props.customer.address || ''
});

const submit = () => {
    form.put(route('customers.update', props.customer.id));
};
</script>

<template>
    <Head title="Edit Pelanggan" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800">Edit Pelanggan</h2>
        </template>
        <div class="py-12 max-w-lg mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm rounded-lg">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Nama Pelanggan</label>
                        <input v-model="form.name" type="text" class="mt-1 w-full rounded border-gray-300" required autofocus />
                    </div>
                    <div>
                        <label class="block text-sm font-medium">No. Telepon</label>
                        <input v-model="form.phone" type="text" class="mt-1 w-full rounded border-gray-300" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Alamat</label>
                        <textarea v-model="form.address" class="mt-1 w-full rounded border-gray-300"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-4">
                        <Link :href="route('customers.index')" class="px-4 py-2 bg-gray-300 rounded font-bold">Batal</Link>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded font-bold" :disabled="form.processing">
                            Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>