<script setup>
import { ref, watch, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    products: {
        type: Array,
        default: () => []
    },

    movements: {
        type: Object,
        default: () => ({
            data: [],
            links: []
        })
    },

    selected_product: {
        type: Object,
        default: null
    },

    summary: {
        type: Object,
        default: () => ({
            opening_stock: 0,
            total_in: 0,
            total_out: 0,
            ending_stock: 0
        })
    },

    filters: {
        type: Object,
        default: () => ({})
    }
});


// =========================================================
// FILTER STATE
// =========================================================

const productId = ref(
    props.filters?.product_id || ''
);

const startDate = ref(
    props.filters?.start_date || ''
);

const endDate = ref(
    props.filters?.end_date || ''
);

const search = ref(
    props.filters?.search || ''
);

const sortField = ref(
    props.filters?.sort || 'date'
);

const sortDirection = ref(
    props.filters?.direction || 'desc'
);


// =========================================================
// DATA YANG AMAN UNTUK TEMPLATE
// =========================================================

const movementData = computed(() => {
    return props.movements?.data ?? [];
});

const movementLinks = computed(() => {
    return props.movements?.links ?? [];
});


// =========================================================
// FORMAT ANGKA
// =========================================================

const formatNumber = (value) => {
    const number = parseFloat(value ?? 0);

    return number.toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3
    });
};


// =========================================================
// FETCH DATA
// =========================================================

const fetchData = () => {

    router.get(
        route('reports.stock_card'),
        {
            product_id: productId.value,
            start_date: startDate.value,
            end_date: endDate.value,
            search: search.value,
            sort: sortField.value,
            direction: sortDirection.value
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true
        }
    );
};


// =========================================================
// SEARCH DEBOUNCE
// =========================================================

let searchTimeout;

watch(search, () => {

    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        fetchData();
    }, 300);
});


// =========================================================
// FILTER
// =========================================================

const applyFilter = () => {
    fetchData();
};


// =========================================================
// SORT
// =========================================================

const sortBy = (field) => {

    if (sortField.value === field) {

        sortDirection.value =
            sortDirection.value === 'asc'
                ? 'desc'
                : 'asc';

    } else {

        sortField.value = field;
        sortDirection.value = 'asc';
    }

    fetchData();
};
</script>


<template>

    <Head title="Kartu Stok (Pergerakan Barang)" />

    <AuthenticatedLayout>

        <template #header>

            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Laporan Kartu Stok
            </h2>

        </template>


        <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">


                <!-- ================================================= -->
                <!-- FILTER -->
                <!-- ================================================= -->

                <div
                    class="flex flex-col md:flex-row justify-between gap-4 mb-6 bg-gray-50 p-4 rounded-lg border"
                >

                    <div
                        class="flex flex-col md:flex-row gap-4 w-full md:w-auto items-center"
                    >

                        <!-- PRODUK -->

                        <select
                            v-model="productId"
                            @change="applyFilter"
                            class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-full md:w-72 font-bold"
                        >

                            <option value="">
                                -- Pilih Produk Dulu --
                            </option>

                            <option
                                v-for="prod in products"
                                :key="prod.id"
                                :value="prod.id"
                            >

                                {{ prod.barcode ? '[' + prod.barcode + '] ' : '' }}
                                {{ prod.name }}

                            </option>

                        </select>


                        <!-- TANGGAL -->

                        <div
                            class="flex items-center gap-2 w-full md:w-auto"
                        >

                            <input
                                type="date"
                                v-model="startDate"
                                @change="applyFilter"
                                class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />

                            <span class="text-gray-500">
                                s/d
                            </span>

                            <input
                                type="date"
                                v-model="endDate"
                                @change="applyFilter"
                                class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />

                        </div>

                    </div>


                    <!-- SEARCH -->

                    <div class="relative w-full md:w-1/4">

                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari ref / tipe / keterangan..."
                            :disabled="!productId"
                            class="w-full rounded-md border-gray-300 shadow-sm pr-10 focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400"
                        />

                        <span
                            v-if="search"
                            @click="search = ''"
                            class="absolute right-3 top-2.5 text-gray-400 cursor-pointer font-bold text-lg hover:text-red-500"
                        >
                            &times;
                        </span>

                    </div>

                </div>


                <!-- ================================================= -->
                <!-- INFORMASI PRODUK -->
                <!-- ================================================= -->

                <div
                    v-if="selected_product"
                    class="mb-6"
                >

                    <div
                        class="flex flex-col md:flex-row md:items-center md:justify-between gap-3"
                    >

                        <div>

                            <div class="text-lg font-bold text-gray-800">
                                {{ selected_product.name }}
                            </div>

                            <div class="text-sm text-gray-500">

                                {{
                                    selected_product.barcode
                                        ? 'Barcode: ' + selected_product.barcode
                                        : ''
                                }}

                            </div>

                        </div>


                        <div class="text-right">

                            <span class="text-gray-500">
                                Stok sistem saat ini:
                            </span>

                            <strong
                                class="text-indigo-600 text-2xl ml-2 bg-indigo-50 px-3 py-1 rounded border border-indigo-200"
                            >

                                {{
                                    formatNumber(selected_product.stock)
                                }}

                                {{ selected_product.unit }}

                            </strong>

                        </div>

                    </div>

                </div>


                <!-- ================================================= -->
                <!-- SUMMARY CARDS -->
                <!-- ================================================= -->

                <div
                    v-if="productId"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6"
                >

                    <!-- SALDO AWAL -->

                    <div class="border rounded-lg p-4 bg-gray-50">

                        <div class="text-sm text-gray-500">
                            Saldo Awal
                        </div>

                        <div class="text-2xl font-bold text-gray-800 mt-1">

                            {{ formatNumber(summary.opening_stock) }}

                        </div>

                    </div>


                    <!-- TOTAL MASUK -->

                    <div class="border rounded-lg p-4 bg-green-50">

                        <div class="text-sm text-green-700">
                            Total Masuk
                        </div>

                        <div class="text-2xl font-bold text-green-700 mt-1">

                            +{{ formatNumber(summary.total_in) }}

                        </div>

                    </div>


                    <!-- TOTAL KELUAR -->

                    <div class="border rounded-lg p-4 bg-red-50">

                        <div class="text-sm text-red-700">
                            Total Keluar
                        </div>

                        <div class="text-2xl font-bold text-red-700 mt-1">

                            -{{ formatNumber(summary.total_out) }}

                        </div>

                    </div>


                    <!-- SALDO AKHIR -->

                    <div class="border rounded-lg p-4 bg-indigo-50">

                        <div class="text-sm text-indigo-700">
                            Saldo Akhir
                        </div>

                        <div class="text-2xl font-bold text-indigo-700 mt-1">

                            {{ formatNumber(summary.ending_stock) }}

                        </div>

                    </div>

                </div>


                <!-- ================================================= -->
                <!-- TABEL -->
                <!-- ================================================= -->

                <div class="overflow-x-auto">

                    <table class="w-full text-left border-collapse">

                        <thead>

                            <tr
                                class="bg-gray-100 border-b-2 text-gray-700"
                            >

                                <!-- WAKTU -->

                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('date')"
                                >

                                    Waktu

                                    <span
                                        v-if="sortField === 'date'"
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <!-- TIPE -->

                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('type')"
                                >

                                    Tipe Transaksi

                                    <span
                                        v-if="sortField === 'type'"
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <!-- REFERENSI -->

                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('reference')"
                                >

                                    No. Referensi

                                    <span
                                        v-if="sortField === 'reference'"
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <!-- KETERANGAN -->

                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('description')"
                                >

                                    Keterangan

                                    <span
                                        v-if="sortField === 'description'"
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <!-- MASUK -->

                                <th
                                    class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('in')"
                                >

                                    Masuk

                                    <span
                                        v-if="sortField === 'in'"
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <!-- KELUAR -->

                                <th
                                    class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('out')"
                                >

                                    Keluar

                                    <span
                                        v-if="sortField === 'out'"
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <!-- SALDO -->

                                <th
                                    class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('balance')"
                                >

                                    Saldo

                                    <span
                                        v-if="sortField === 'balance'"
                                        class="text-indigo-600"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>

                            </tr>

                        </thead>


                        <!-- ================================================= -->
                        <!-- ADA PRODUK -->
                        <!-- ================================================= -->

                        <tbody v-if="productId">

                            <!-- DATA -->

                            <tr
                                v-for="(item, index) in movementData"
                                :key="item.reference + '-' + item.date + '-' + index"
                                class="border-b hover:bg-gray-50 transition"
                            >

                                <!-- WAKTU -->

                                <td class="p-3 text-sm text-gray-600 whitespace-nowrap">

                                    {{ item.date_formatted }}

                                </td>


                                <!-- TIPE -->

                                <td class="p-3">

                                    <span
                                        class="px-2 py-1 text-xs font-bold rounded"
                                        :class="{
                                            'bg-green-100 text-green-700':
                                                item.type.includes('Pembelian'),

                                            'bg-blue-100 text-blue-700':
                                                item.type.includes('Penjualan'),

                                            'bg-gray-200 text-gray-800':
                                                item.type.includes('Opname'),

                                            'bg-indigo-100 text-indigo-700':
                                                item.type.includes('Saldo Awal')
                                        }"
                                    >

                                        {{ item.type }}

                                    </span>

                                </td>


                                <!-- REFERENSI -->

                                <td class="p-3 font-semibold text-gray-700">

                                    {{ item.reference }}

                                </td>


                                <!-- KETERANGAN -->

                                <td
                                    class="p-3 italic text-gray-600 text-sm"
                                >

                                    {{ item.description }}

                                </td>


                                <!-- MASUK -->

                                <td
                                    class="p-3 text-center font-bold text-green-600"
                                >

                                    {{
                                        item.in > 0
                                            ? '+' + formatNumber(item.in)
                                            : '-'
                                    }}

                                </td>


                                <!-- KELUAR -->

                                <td
                                    class="p-3 text-center font-bold text-red-600"
                                >

                                    {{
                                        item.out > 0
                                            ? '-' + formatNumber(item.out)
                                            : '-'
                                    }}

                                </td>


                                <!-- SALDO -->

                                <td
                                    class="p-3 text-center font-bold text-gray-800"
                                >

                                    {{ formatNumber(item.balance) }}

                                </td>

                            </tr>


                            <!-- ================================================= -->
                            <!-- KOSONG -->
                            <!-- ================================================= -->

                            <tr v-if="movementData.length === 0">

                                <td
                                    colspan="7"
                                    class="p-10 text-center text-gray-500 font-medium"
                                >

                                    Tidak ada pergerakan stok untuk produk
                                    ini pada rentang waktu yang dipilih.

                                </td>

                            </tr>

                        </tbody>


                        <!-- ================================================= -->
                        <!-- BELUM PILIH PRODUK -->
                        <!-- ================================================= -->

                        <tbody v-else>

                            <tr>

                                <td
                                    colspan="7"
                                    class="p-10 text-center text-gray-400 italic"
                                >

                                    Silakan pilih produk terlebih dahulu
                                    melalui dropdown di atas.

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>


                <!-- ================================================= -->
                <!-- PAGINATION -->
                <!-- ================================================= -->

                <Pagination
                    v-if="productId && movementLinks.length > 0"
                    :links="movementLinks"
                />

            </div>

        </div>

    </AuthenticatedLayout>

</template>