<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({ sales: Object, summary: Object, filters: Object });

// Menampung state filter dari URL
const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const search = ref(props.filters?.search || '');
const sortField = ref(props.filters?.sort || 'created_at');
const sortDirection = ref(props.filters?.direction || 'desc');

// Fungsi ambil data ke Backend
const fetchData = () => {
    router.get(route('reports.sales'), {
        start_date: startDate.value,
        end_date: endDate.value,
        search: search.value,
        sort: sortField.value,
        direction: sortDirection.value
    }, { preserveState: true, preserveScroll: true, replace: true });
};

// Jeda pencarian otomatis (Debounce) untuk teks pencarian
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

// Logika Modal Detail
const showModal = ref(false);
const selectedData = ref(null);
const openModal = (data) => {
    selectedData.value = data;
    showModal.value = true;
};

// Helper Format Uang
const formatRp = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
</script>

<template>
    <Head title="Laporan Penjualan" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan Penjualan & Laba</h2>
        </template>

        <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- SUMMARY CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-indigo-500">
                    <p class="text-sm font-medium text-gray-500">Total Transaksi</p>
                    <p class="text-3xl font-bold text-gray-800">{{ summary.transactions }} <span class="text-base font-normal">Nota</span></p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-green-500">
                    <p class="text-sm font-medium text-gray-500">Omzet / Pendapatan Kotor</p>
                    <p class="text-3xl font-bold text-gray-800">{{ formatRp(summary.revenue) }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-blue-500">
                    <p class="text-sm font-medium text-gray-500">Total Laba Bersih</p>
                    <p class="text-3xl font-bold text-gray-800">{{ formatRp(summary.profit) }}</p>
                </div>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <!-- TOOLBAR: FILTER TANGGAL & PENCARIAN -->
                <div class="flex flex-col md:flex-row justify-between gap-4 mb-6">
                    <div class="flex items-center gap-2 w-full md:w-auto">
                        <input type="date" v-model="startDate" @change="applyDateFilter" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="text-gray-500">s/d</span>
                        <input type="date" v-model="endDate" @change="applyDateFilter" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    
                    <div class="relative w-full md:w-1/3">
                        <input v-model="search" type="text" placeholder="Cari No. Invoice atau Kasir..." class="w-full rounded-md border-gray-300 shadow-sm pr-10 focus:border-indigo-500 focus:ring-indigo-500" />
                        <span v-if="search" @click="search = ''" class="absolute right-3 top-2.5 text-gray-400 cursor-pointer font-bold text-lg hover:text-red-500">&times;</span>
                    </div>
                </div>

                <!-- TABEL DATA -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 border-b-2 text-gray-700">
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('created_at')">
                                    Tanggal <span v-if="sortField === 'created_at'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('invoice_no')">
                                    No. Invoice <span v-if="sortField === 'invoice_no'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('cashier')">
                                    Kasir <span v-if="sortField === 'cashier'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('payment_method')">
                                    Pembayaran <span v-if="sortField === 'payment_method'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-right cursor-pointer hover:bg-gray-200 transition select-none" @click="sortBy('grand_total')">
                                    Omzet <span v-if="sortField === 'grand_total'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-right cursor-pointer hover:bg-gray-200 transition select-none text-green-700" @click="sortBy('profit')">
                                    Laba Bersih <span v-if="sortField === 'profit'" class="text-indigo-600">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sale in sales.data" :key="sale.id" class="border-b hover:bg-gray-50 transition">
                                <td class="p-3 text-sm text-gray-600">{{ sale.date }}</td>
                                <td class="p-3 font-semibold">{{ sale.invoice_no }}</td>
                                <td class="p-3">{{ sale.cashier }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-1 text-xs font-bold rounded" :class="sale.payment_method === 'Tunai' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'">
                                        {{ sale.payment_method }}
                                    </span>
                                </td>
                                <td class="p-3 text-right font-bold">{{ formatRp(sale.grand_total) }}</td>
                                <td class="p-3 text-right font-bold text-green-600">{{ formatRp(sale.profit) }}</td>
                                <td class="p-3 text-center space-x-2">
                                    <button @click="openModal(sale)" class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-sm font-bold hover:bg-blue-200 transition">
                                        Detail
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="sales.data.length === 0">
                                <td colspan="7" class="p-6 text-center text-gray-500 font-medium">Tidak ada data penjualan pada rentang tanggal/pencarian ini.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Komponen Pagination -->
                <Pagination :links="sales.links" />

            </div>
        </div>
    </AuthenticatedLayout>

    <!-- MODAL DETAIL TRANSAKSI -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl overflow-hidden">
            <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-lg text-gray-800">Detail Invoice: {{ selectedData.invoice_no }}</h3>
                <button @click="showModal = false" class="text-gray-500 hover:text-red-500 font-bold text-xl">&times;</button>
            </div>
            
            <div class="p-6 max-h-[60vh] overflow-y-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-100 border-b-2">
                        <tr>
                            <th class="p-2">Produk</th>
                            <th class="p-2 text-right">Harga Jual</th>
                            <th class="p-2 text-right">Kuantitas</th>
                            <th class="p-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="selectedData?.sale_details && selectedData.sale_details.length > 0">
                            <tr v-for="detail in selectedData.sale_details" :key="detail.id" class="border-b">
                                <td class="p-2">{{ detail.product?.name || 'Produk Dihapus' }}</td>
                                <td class="p-2 text-right">{{ formatRp(detail.selling_price) }}</td>
                                <td class="p-2 text-right">{{ parseFloat(detail.quantity) }} {{ detail.product?.unit || '' }}</td>
                                <td class="p-2 text-right font-bold">{{ formatRp(detail.subtotal) }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                
                <div class="mt-4 flex justify-between items-end">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Laba Bersih Transaksi Ini:</p>
                        <p class="text-xl font-bold text-green-600">{{ formatRp(selectedData.profit) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500 mb-1">Omzet / Total Belanja:</p>
                        <p class="text-2xl font-bold text-indigo-600">{{ formatRp(selectedData.grand_total) }}</p>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t bg-gray-50 text-right">
                <button @click="showModal = false" class="bg-gray-800 hover:bg-gray-900 text-white px-6 py-2 rounded shadow font-bold transition">Tutup</button>
            </div>
        </div>
    </div>
</template>