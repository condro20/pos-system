<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ userEdit: Object, roles: Array });

const form = useForm({
    name: props.userEdit.name,
    email: props.userEdit.email,
    password: '', // Dikosongkan agar tidak perlu diisi jika tidak ingin ubah password
    password_confirmation: '',
    role_id: props.userEdit.role_id
});

const submit = () => form.put(route('users.update', props.userEdit.id));
</script>

<template>
    <Head title="Edit Pengguna" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800">Edit Data Pengguna</h2>
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
                                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name.toUpperCase() }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Alamat Email</label>
                        <input v-model="form.email" type="email" class="mt-1 w-full rounded border-gray-300" required />
                        <div v-if="form.errors.email" class="text-red-500 text-xs">{{ form.errors.email }}</div>
                    </div>

                    <div class="border-t pt-4 mt-4 bg-gray-50 p-4 rounded">
                        <p class="text-xs text-gray-500 mb-4 font-bold">Biarkan kosong jika tidak ingin mengubah password.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium">Password Baru</label>
                                <input v-model="form.password" type="password" class="mt-1 w-full rounded border-gray-300" />
                                <div v-if="form.errors.password" class="text-red-500 text-xs">{{ form.errors.password }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium">Konfirmasi Password Baru</label>
                                <input v-model="form.password_confirmation" type="password" class="mt-1 w-full rounded border-gray-300" />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4">
                        <Link :href="route('users.index')" class="px-4 py-2 bg-gray-300 rounded font-bold">Batal</Link>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded font-bold" :disabled="form.processing">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>