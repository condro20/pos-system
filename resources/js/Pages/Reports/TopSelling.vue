<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({ topProducts: Object, filters: Object });

// Menampung state filter dari URL
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const search = ref(props.filters?.search || '');
const sortField = ref(props.filters?.sort || 'total_qty');
const sortDirection = ref(props.filters?.direction || 'desc');

// Fungsi ambil data ke Backend sesuai nama route web.php Anda
const fetchData = () => {
    router.get(route('reports.top_selling'), {
        start_date: startDate.value,
        end_date: endDate.value,
        search: search.value,
        sort: sortField.value,
        direction: sortDirection.value
    }, { preserveState: true, preserveScroll: true, replace: true });
};

// Jeda pencarian otomatis saat mengetik (Debounce)
let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { fetchData(); }, 300);
});

// Trigger fetch langsung jika filter tanggal diubah
const applyDateFilter = () => fetchData();

// Fungsi klik Header Tabel
const sortBy = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
    fetchData();
};

const formatRp = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
</script>

<template>
    <Head title="Laporan Barang Terlaris" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan Peringkat Barang Terlaris</h2>
        </template>

        <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                
                <!-- TOOLBAR: FILTER TANGGAL & PENCARIAN -->
                <div class="flex flex-col md:flex-row justify-between gap-4 mb-6 bg-gray-50 p-4 rounded-lg border">
                    <div class="flex items-center gap-2 w-full md:w-auto">
                        <span class="font-semibold text-gray-600 mr-2 hidden md:inline">Periode:</span>
                        <input type="date" v-model="startDate" @change="applyDateFilter" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <span class="text-gray-500">s/d</span>
                        <input type="date" v-model="endDate" @change="applyDateFilter" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                    
                    <div class="relative w-full md:w-1/3">
                        <input v-model="search" type="text" placeholder="Cari nama barang atau barcode..." class="w-full rounded-md border-gray-300 shadow-sm pr-10 focus:border-indigo-500 focus:ring-indigo-500" />
                        <span v-if="search" @click="search = ''" class="absolute right-3 top-2.5 text-gray-400 cursor-pointer font-bold text-lg hover:text-red-500">&times;</span>
                    </div>
                </div>

                <!-- TABEL DATA -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b-2 text-gray-700">
                                <th class="p-3 text-center w-16">Rank</th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('barcode')">
                                    Barcode <span v-if="sortField === 'barcode'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('name')">
                                    Nama Produk <span v-if="sortField === 'name'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('category')">
                                    Kategori <span v-if="sortField === 'category'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-center cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('total_qty')">
                                    Kuantitas Terjual <span v-if="sortField === 'total_qty'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-right cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('total_revenue')">
                                    Total Omzet <span v-if="sortField === 'total_revenue'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(product, index) in topProducts.data" :key="product.id" class="border-b hover:bg-gray-50 transition">
                                <td class="p-3 text-center font-bold text-gray-500">
                                    #{{ (topProducts.current_page - 1) * topProducts.per_page + index + 1 }}
                                </td>
                                <td class="p-3 text-sm">{{ product.barcode || '-' }}</td>
                                <td class="p-3 font-semibold">{{ product.name }}</td>
                                <td class="p-3">{{ product.category?.name || '-' }}</td>
                                <td class="p-3 text-center font-bold" :class="product.total_qty > 0 ? 'text-green-600' : 'text-gray-400'">
                                    {{ parseFloat(product.total_qty) }} {{ product.unit }}
                                </td>
                                <td class="p-3 text-right font-bold text-indigo-600">
                                    {{ formatRp(product.total_revenue) }}
                                </td>
                            </tr>
                            <tr v-if="topProducts.data.length === 0">
                                <td colspan="6" class="p-6 text-center text-gray-500 font-medium">Tidak ada data penjualan barang pada rentang tanggal tersebut.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Komponen Pagination -->
                <Pagination :links="topProducts.links" />

            </div>
        </div>
    </AuthenticatedLayout>
</template>