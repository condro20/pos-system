<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const form = useForm({ name: '' });
const submit = () => form.post(route('categories.store'));
</script>

<template>
    <Head title="Tambah Kategori" />
    <AuthenticatedLayout>
        <template #header><h2 class="font-semibold text-xl text-gray-800 leading-tight">Tambah Kategori</h2></template>
        <div class="py-12 max-w-lg mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Nama Kategori</label>
                        <input v-model="form.name" type="text" class="mt-1 w-full rounded-md border-gray-300" required autofocus />
                        <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                    </div>
                    <div class="flex justify-end gap-2 pt-4">
                        <Link :href="route('categories.index')" class="px-4 py-2 bg-gray-300 rounded-md">Batal</Link>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md" :disabled="form.processing">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>