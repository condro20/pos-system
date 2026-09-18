<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
    categories: Array
});

const form = useForm({
    category_id: '',
    barcode: '',
    name: '',
    unit: 'kg',
    purchase_price: 0,
    selling_price: 0,
});

const submit = () => {
    form.post(route('products.store'));
};
</script>

<template>
    <Head title="Tambah Produk" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Tambah Produk
            </h2>
        </template>

        <div class="py-12 max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">

                <form
                    @submit.prevent="submit"
                    class="space-y-4"
                >

                    <div>
                        <label class="block text-sm font-medium">
                            Kategori
                        </label>

                        <select
                            v-model="form.category_id"
                            class="mt-1 w-full rounded-md border-gray-300"
                            required
                        >
                            <option disabled value="">
                                Pilih Kategori
                            </option>

                            <option
                                v-for="cat in categories"
                                :key="cat.id"
                                :value="cat.id"
                            >
                                {{ cat.name }}
                            </option>
                        </select>

                        <div
                            v-if="form.errors.category_id"
                            class="text-sm text-red-600 mt-1"
                        >
                            {{ form.errors.category_id }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">

                        <div>
                            <label class="block text-sm font-medium">
                                Barcode (Opsional)
                            </label>

                            <input
                                v-model="form.barcode"
                                type="text"
                                class="mt-1 w-full rounded-md border-gray-300"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium">
                                Nama Produk
                            </label>

                            <input
                                v-model="form.name"
                                type="text"
                                class="mt-1 w-full rounded-md border-gray-300"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium">
                                Satuan
                            </label>

                            <input
                                v-model="form.unit"
                                type="text"
                                placeholder="kg, liter, pcs"
                                class="mt-1 w-full rounded-md border-gray-300"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium">
                                Harga Beli (HPP)
                            </label>

                            <input
                                v-model="form.purchase_price"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1 w-full rounded-md border-gray-300"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium">
                                Harga Jual
                            </label>

                            <input
                                v-model="form.selling_price"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1 w-full rounded-md border-gray-300"
                                required
                            />
                        </div>

                    </div>

                    <div class="p-3 bg-blue-50 text-blue-700 rounded-md text-sm">
                        Stok awal produk dibuat <strong>0</strong>.
                        Untuk memasukkan stok awal, gunakan menu
                        <strong>Stock Adjustment</strong> agar tercatat
                        dalam histori stok.
                    </div>

                    <div class="flex justify-end gap-2 pt-4">

                        <Link
                            :href="route('products.index')"
                            class="px-4 py-2 bg-gray-300 rounded-md"
                        >
                            Batal
                        </Link>

                        <button
                            type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md"
                            :disabled="form.processing"
                        >
                            Simpan
                        </button>

                    </div>

                </form>

            </div>
        </div>
    </AuthenticatedLayout>
</template>