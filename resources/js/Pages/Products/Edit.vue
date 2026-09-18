<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    product: Object,
    categories: Array
});

const form = useForm({
    category_id: props.product.category_id,
    barcode: props.product.barcode || '',
    name: props.product.name,
    unit: props.product.unit,
    purchase_price: props.product.purchase_price,
    selling_price: props.product.selling_price,
});

const submit = () => {
    form.put(
        route('products.update', props.product.id)
    );
};
</script>

<template>
    <Head title="Edit Produk" />

    <AuthenticatedLayout>

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Produk
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
                            <option
                                v-for="cat in categories"
                                :key="cat.id"
                                :value="cat.id"
                            >
                                {{ cat.name }}
                            </option>
                        </select>

                    </div>

                    <div class="grid grid-cols-2 gap-4">

                        <div>

                            <label class="block text-sm font-medium">
                                Barcode
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
                                class="mt-1 w-full rounded-md border-gray-300"
                                required
                            />

                        </div>

                        <div>

                            <label class="block text-sm font-medium">
                                Stok Saat Ini
                            </label>

                            <input
                                :value="product.stock"
                                type="text"
                                class="mt-1 w-full rounded-md border-gray-300 bg-gray-100"
                                readonly
                            />

                            <p class="text-xs text-gray-500 mt-1">
                                Stok hanya dapat diubah melalui
                                Purchase, POS, atau Stock Adjustment.
                            </p>

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
                            Update
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </AuthenticatedLayout>
</template>