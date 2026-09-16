<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({ roles: Array });

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_id: ''
});

const submit = () => form.post(route('users.store'));
</script>

<template>
    <Head title="Tambah Pengguna" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800">Tambah Pengguna Baru</h2>
        </template>
        <div class="py-12 max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm rounded-lg">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium">Nama Lengkap</label>
                            <input v-model="form.name" type="text" class="mt-1 w-full rounded border-gray-300" required />
                            <div v-if="form.errors.name" class="text-red-500 text-xs">{{ form.errors.name }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Jabatan (Role)</label>
                            <select v-model="form.role_id" class="mt-1 w-full rounded border-gray-300" required>
                                <option disabled value="">Pilih Jabatan</option>
                                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name.toUpperCase() }}</option>
                            </select>
                            <div v-if="form.errors.role_id" class="text-red-500 text-xs">{{ form.errors.role_id }}</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Alamat Email (Untuk Login)</label>
                        <input v-model="form.email" type="email" class="mt-1 w-full rounded border-gray-300" required />
                        <div v-if="form.errors.email" class="text-red-500 text-xs">{{ form.errors.email }}</div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t pt-4">
                        <div>
                            <label class="block text-sm font-medium">Password Baru</label>
                            <input v-model="form.password" type="password" class="mt-1 w-full rounded border-gray-300" required />
                            <div v-if="form.errors.password" class="text-red-500 text-xs">{{ form.errors.password }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Konfirmasi Password</label>
                            <input v-model="form.password_confirmation" type="password" class="mt-1 w-full rounded border-gray-300" required />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4">
                        <Link :href="route('users.index')" class="px-4 py-2 bg-gray-300 rounded font-bold">Batal</Link>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded font-bold" :disabled="form.processing">Simpan User</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>