<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },
});

/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
*/
const form = useForm({
    product_id: '',
    physical_stock: 0,
    reason: '',
});

/*
|--------------------------------------------------------------------------
| Selected Product
|--------------------------------------------------------------------------
*/
const selectedProduct = computed(() => {
    const productId = Number(form.product_id);

    if (!productId) {
        return null;
    }

    return (
        props.products.find(
            (product) =>
                Number(product.id) === productId
        ) || null
    );
});

/*
|--------------------------------------------------------------------------
| System Stock
|--------------------------------------------------------------------------
*/
const systemStock = computed(() => {
    if (!selectedProduct.value) {
        return 0;
    }

    const stock = Number(
        selectedProduct.value.stock
    );

    return Number.isFinite(stock)
        ? stock
        : 0;
});

/*
|--------------------------------------------------------------------------
| Physical Stock
|--------------------------------------------------------------------------
*/
const physicalStock = computed(() => {
    const value = Number(
        form.physical_stock
    );

    if (!Number.isFinite(value) || value < 0) {
        return 0;
    }

    return value;
});

/*
|--------------------------------------------------------------------------
| Difference
|--------------------------------------------------------------------------
*/
const difference = computed(() => {
    return (
        physicalStock.value -
        systemStock.value
    );
});

/*
|--------------------------------------------------------------------------
| Formatting
|--------------------------------------------------------------------------
*/
const formatQty = (value) => {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return '0';
    }

    return number.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    });
};

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/
const submit = () => {
    if (!form.product_id) {
        return;
    }

    if (
        Number(form.physical_stock) < 0 ||
        !Number.isFinite(
            Number(form.physical_stock)
        )
    ) {
        return;
    }

    form.post(
        route('stock-adjustments.store'),
        {
            preserveScroll: true,
        }
    );
};
</script>

<template>
    <Head title="Stok Opname" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="font-semibold text-xl text-gray-800 leading-tight"
            >
                Penyesuaian Stok (Opname)
            </h2>
        </template>

        <div
            class="py-12 max-w-2xl mx-auto sm:px-6 lg:px-8"
        >
            <div
                class="bg-white p-6 shadow-sm sm:rounded-lg"
            >
                <form
                    @submit.prevent="submit"
                    class="space-y-5"
                >

                    <!-- Produk -->
                    <div>
                        <label
                            for="product_id"
                            class="block text-sm font-medium text-gray-700"
                        >
                            Pilih Produk
                        </label>

                        <select
                            id="product_id"
                            v-model="form.product_id"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option
                                disabled
                                value=""
                            >
                                -- Pilih Barang --
                            </option>

                            <option
                                v-for="item in products"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{
                                    item.barcode
                                        ? `[${item.barcode}] `
                                        : ''
                                }}{{ item.name }}
                            </option>
                        </select>

                        <p
                            v-if="
                                form.errors.product_id
                            "
                            class="mt-1 text-sm text-red-600"
                        >
                            {{
                                form.errors.product_id
                            }}
                        </p>
                    </div>

                    <!-- Stock Information -->
                    <div
                        v-if="selectedProduct"
                        class="p-4 bg-gray-50 border rounded-md"
                    >

                        <!-- System Stock -->
                        <div
                            class="flex justify-between items-center mb-5"
                        >
                            <div>
                                <span
                                    class="block text-sm text-gray-500"
                                >
                                    Stok Sistem Saat Ini
                                </span>

                                <span
                                    class="text-2xl font-bold text-gray-800"
                                >
                                    {{
                                        formatQty(
                                            systemStock
                                        )
                                    }}
                                    {{
                                        selectedProduct.unit
                                    }}
                                </span>
                            </div>

                            <div
                                class="text-right"
                            >
                                <span
                                    class="text-xs text-gray-400"
                                >
                                    Data dari database
                                </span>
                            </div>
                        </div>

                        <!-- Physical Stock -->
                        <div
                            class="grid grid-cols-1 md:grid-cols-2 gap-5 items-end"
                        >

                            <div>
                                <label
                                    for="physical_stock"
                                    class="block text-sm font-medium text-blue-700"
                                >
                                    Stok Fisik Sebenarnya
                                </label>

                                <input
                                    id="physical_stock"
                                    v-model.number="
                                        form.physical_stock
                                    "
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    inputmode="decimal"
                                    class="mt-1 w-full rounded-md border-gray-300 border-blue-400 focus:border-blue-500 focus:ring-blue-500"
                                    required
                                />

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Maksimal 3 angka
                                    desimal.
                                </p>

                                <p
                                    v-if="
                                        form.errors.physical_stock
                                    "
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{
                                        form.errors
                                            .physical_stock
                                    }}
                                </p>
                            </div>

                            <!-- Difference -->
                            <div
                                class="text-right"
                            >
                                <span
                                    class="block text-sm font-medium text-gray-500"
                                >
                                    Selisih
                                </span>

                                <span
                                    class="text-2xl font-bold"
                                    :class="{
                                        'text-red-600':
                                            difference < 0,
                                        'text-green-600':
                                            difference > 0,
                                        'text-gray-800':
                                            difference === 0,
                                    }"
                                >
                                    {{
                                        difference > 0
                                            ? '+'
                                            : ''
                                    }}{{
                                        formatQty(
                                            difference
                                        )
                                    }}
                                    {{
                                        selectedProduct.unit
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Reason -->
                    <div
                        v-if="selectedProduct"
                    >
                        <label
                            for="reason"
                            class="block text-sm font-medium text-gray-700"
                        >
                            Alasan Penyesuaian
                        </label>

                        <textarea
                            id="reason"
                            v-model="form.reason"
                            rows="3"
                            maxlength="255"
                            placeholder="Contoh: Barang rusak, barang hilang, selisih opname, tumpah, koreksi stok..."
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        ></textarea>

                        <div
                            class="flex justify-between mt-1"
                        >
                            <p
                                class="text-xs text-gray-500"
                            >
                                Jelaskan alasan
                                perubahan stok.
                            </p>

                            <p
                                class="text-xs text-gray-400"
                            >
                                {{
                                    form.reason.length
                                }}/255
                            </p>
                        </div>

                        <p
                            v-if="form.errors.reason"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{
                                form.errors.reason
                            }}
                        </p>
                    </div>

                    <!-- Buttons -->
                    <div
                        class="flex justify-end gap-2 pt-4 border-t"
                    >
                        <Link
                            :href="
                                route(
                                    'stock-adjustments.index'
                                )
                            "
                            class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 transition"
                        >
                            Batal
                        </Link>

                        <button
                            type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="
                                form.processing ||
                                !form.product_id ||
                                form.physical_stock === '' ||
                                Number(
                                    form.physical_stock
                                ) < 0
                            "
                        >
                            {{
                                form.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan Penyesuaian'
                            }}
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>