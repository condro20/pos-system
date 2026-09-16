<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const form = useForm({ name: '', phone: '', address: '' });
const submit = () => form.post(route('suppliers.store'));
</script>
<template>
    <Head title="Tambah Supplier" />
    <AuthenticatedLayout>
        <template #header><h2 class="font-semibold text-xl text-gray-800">Tambah Supplier</h2></template>
        <div class="py-12 max-w-lg mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm rounded-lg">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm">Nama Supplier / Agen</label>
                        <input v-model="form.name" type="text" class="w-full rounded border-gray-300" required />
                    </div>
                    <div>
                        <label class="block text-sm">No. Telepon</label>
                        <input v-model="form.phone" type="text" class="w-full rounded border-gray-300" />
                    </div>
                    <div>
                        <label class="block text-sm">Alamat</label>
                        <textarea v-model="form.address" class="w-full rounded border-gray-300"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Link :href="route('suppliers.index')" class="px-4 py-2 bg-gray-300 rounded">Batal</Link>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>