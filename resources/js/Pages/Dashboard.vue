<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Line } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend } from 'chart.js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

const props = defineProps({
    summary: Object,
    low_stock_products: Array,
    chart_data: Object, // Props baru
});

const formatRp = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);

// Konfigurasi Grafik
const chartDataConfig = {
    labels: props.chart_data.labels,
    datasets: [{
        label: 'Omzet Penjualan (Rp)',
        backgroundColor: '#4f46e5', // Warna Indigo Tailwind
        borderColor: '#4f46e5',
        data: props.chart_data.data,
        tension: 0.3, // Membuat kurva melengkung halus
        fill: true,
    }]
};

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } }
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard Analitik</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Pesan Selamat Datang -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 border-l-4 border-indigo-500">
                        Selamat bekerja, <b>{{ $page.props.auth.user.name }}</b>! Berikut ringkasan performa toko hari ini.
                    </div>
                </div>

                <!-- Row 1: Key Metrics (Hari Ini) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Omzet -->
                    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100 flex items-center gap-4">
                        <div class="p-4 bg-green-100 rounded-full">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-500 uppercase">Omzet Hari Ini</p>
                            <h3 class="text-2xl font-bold text-gray-800">{{ formatRp(summary.revenue_today) }}</h3>
                        </div>
                    </div>

                    <!-- Laba -->
                    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100 flex items-center gap-4">
                        <div class="p-4 bg-blue-100 rounded-full">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-500 uppercase">Laba Hari Ini</p>
                            <h3 class="text-2xl font-bold text-gray-800">{{ formatRp(summary.profit_today) }}</h3>
                        </div>
                    </div>

                    <!-- Transaksi -->
                    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-100 flex items-center gap-4">
                        <div class="p-4 bg-purple-100 rounded-full">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-500 uppercase">Total Transaksi</p>
                            <h3 class="text-2xl font-bold text-gray-800">{{ summary.transactions_today }} <span class="text-sm font-normal text-gray-500">Nota</span></h3>
                        </div>
                    </div>
                </div>

                <!-- Row Extra: GRAFIK PENJUALAN 7 HARI TERAKHIR -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <h3 class="font-bold text-lg mb-4 text-gray-800 border-b pb-2">Tren Omzet 7 Hari Terakhir</h3>
                    <div class="h-64">
                        <Line :data="chartDataConfig" :options="chartOptions" />
                    </div>
                </div>

                <!-- Row 2: Informasi Tambahan -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Peringatan Stok Menipis -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                        <div class="flex justify-between items-center mb-4 border-b pb-2">
                            <h3 class="font-bold text-lg text-red-600 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                Stok Hampir Habis
                            </h3>
                            <Link :href="route('products.index')" class="text-sm text-indigo-600 hover:underline">Lihat Semua</Link>
                        </div>
                        
                        <ul v-if="low_stock_products.length > 0" class="divide-y divide-gray-200">
                            <li v-for="product in low_stock_products" :key="product.id" class="py-3 flex justify-between items-center">
                                <div>
                                    <p class="font-semibold text-gray-800">{{ product.name }}</p>
                                    <p class="text-xs text-gray-500">{{ product.barcode || '-' }}</p>
                                </div>
                                <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-bold">
                                    Sisa {{ product.stock }} {{ product.unit }}
                                </span>
                            </li>
                        </ul>
                        <div v-else class="text-center py-6 text-gray-500 italic">
                            Semua stok produk dalam kondisi aman (Di atas 10).
                        </div>
                    </div>

                    <!-- Data Master Overview -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                        <h3 class="font-bold text-lg mb-4 border-b pb-2">Ringkasan Sistem</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center p-3 hover:bg-gray-50 rounded-md border border-gray-100">
                                <span class="font-medium text-gray-600">Total Macam Produk</span>
                                <span class="font-bold text-xl">{{ summary.total_products }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 hover:bg-gray-50 rounded-md border border-gray-100">
                                <span class="font-medium text-gray-600">Total Pelanggan Terdaftar</span>
                                <span class="font-bold text-xl">{{ summary.total_customers }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 hover:bg-gray-50 rounded-md border border-gray-100">
                                <span class="font-medium text-gray-600">Total Agen/Supplier</span>
                                <span class="font-bold text-xl">{{ summary.total_suppliers }}</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>