<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            revenue_today: 0,
            profit_today: 0,
            transactions_today: 0,
            total_products: 0,
            total_customers: 0,
            total_suppliers: 0,
        }),
    },

    top_selling_products: {
        type: Array,
        default: () => [],
    },

    low_stock_products: {
        type: Array,
        default: () => [],
    },

    chart_data: {
        type: Object,
        default: () => ({
            labels: [],
            data: [],
        }),
    },
});


// ==========================================
// FORMAT RUPIAH
// ==========================================

const formatRp = (value) => {

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value) || 0);

};


// ==========================================
// FORMAT QUANTITY
// ==========================================

const formatQty = (value) => {

    const number = Number(value) || 0;

    return new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 3,
    }).format(number);

};


// ==========================================
// KONFIGURASI GRAFIK
// ==========================================

const chartDataConfig = {
    labels: props.chart_data.labels || [],

    datasets: [
        {
            label: 'Omzet Penjualan (Rp)',

            backgroundColor: 'rgba(79, 70, 229, 0.12)',

            borderColor: '#4f46e5',

            pointBackgroundColor: '#4f46e5',

            pointBorderColor: '#ffffff',

            pointBorderWidth: 2,

            pointRadius: 4,

            pointHoverRadius: 6,

            data: props.chart_data.data || [],

            tension: 0.3,

            fill: true,
        },
    ],
};


const chartOptions = {

    responsive: true,

    maintainAspectRatio: false,

    interaction: {
        intersect: false,
        mode: 'index',
    },

    plugins: {

        legend: {
            display: false,
        },

        tooltip: {
            callbacks: {
                label: function (context) {

                    return ` ${formatRp(context.raw)}`;

                },
            },
        },

    },

    scales: {

        y: {

            beginAtZero: true,

            ticks: {
                callback: function (value) {
                    return formatRp(value);
                },
            },

        },

    },

};
</script>


<template>

    <Head title="Dashboard" />


    <AuthenticatedLayout>

        <template #header>

            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Dashboard Analitik
            </h2>

        </template>


        <div class="py-12">

            <div
                class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6"
            >


                <!-- ======================================
                     WELCOME
                ======================================= -->

                <div
                    class="bg-white overflow-hidden shadow-sm sm:rounded-lg"
                >

                    <div
                        class="p-6 text-gray-900 border-l-4 border-indigo-500"
                    >

                        Selamat bekerja,
                        <b>{{ $page.props.auth.user.name }}</b>!

                        Berikut ringkasan performa toko hari ini.

                    </div>

                </div>


                <!-- ======================================
                     KEY METRICS
                ======================================= -->

                <div
                    class="grid grid-cols-1 md:grid-cols-3 gap-6"
                >


                    <!-- OMZET -->

                    <div
                        class="bg-white rounded-lg shadow-sm p-6 border border-gray-100 flex items-center gap-4"
                    >

                        <div class="p-4 bg-green-100 rounded-full">

                            <svg
                                class="w-8 h-8 text-green-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />

                            </svg>

                        </div>


                        <div>

                            <p
                                class="text-sm font-semibold text-gray-500 uppercase"
                            >
                                Omzet Hari Ini
                            </p>

                            <h3
                                class="text-2xl font-bold text-gray-800"
                            >
                                {{ formatRp(summary.revenue_today) }}
                            </h3>

                        </div>

                    </div>


                    <!-- LABA -->

                    <div
                        class="bg-white rounded-lg shadow-sm p-6 border border-gray-100 flex items-center gap-4"
                    >

                        <div class="p-4 bg-blue-100 rounded-full">

                            <svg
                                class="w-8 h-8 text-blue-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                />

                            </svg>

                        </div>


                        <div>

                            <p
                                class="text-sm font-semibold text-gray-500 uppercase"
                            >
                                Laba Hari Ini
                            </p>

                            <h3
                                class="text-2xl font-bold text-gray-800"
                            >
                                {{ formatRp(summary.profit_today) }}
                            </h3>

                        </div>

                    </div>


                    <!-- TRANSAKSI -->

                    <div
                        class="bg-white rounded-lg shadow-sm p-6 border border-gray-100 flex items-center gap-4"
                    >

                        <div class="p-4 bg-purple-100 rounded-full">

                            <svg
                                class="w-8 h-8 text-purple-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
                                />

                            </svg>

                        </div>


                        <div>

                            <p
                                class="text-sm font-semibold text-gray-500 uppercase"
                            >
                                Total Transaksi
                            </p>

                            <h3
                                class="text-2xl font-bold text-gray-800"
                            >

                                {{ summary.transactions_today }}

                                <span
                                    class="text-sm font-normal text-gray-500"
                                >
                                    Nota
                                </span>

                            </h3>

                        </div>

                    </div>

                </div>


                <!-- ======================================
                     GRAFIK OMZET
                ======================================= -->

                <div
                    class="bg-white rounded-lg shadow-sm border border-gray-100 p-6"
                >

                    <div
                        class="flex justify-between items-center mb-4 border-b pb-2"
                    >

                        <div>

                            <h3
                                class="font-bold text-lg text-gray-800"
                            >
                                Tren Omzet 7 Hari Terakhir
                            </h3>

                            <p class="text-sm text-gray-500 mt-1">
                                Pergerakan omzet berdasarkan transaksi POS
                            </p>

                        </div>

                    </div>


                    <div class="h-72">

                        <Line
                            :data="chartDataConfig"
                            :options="chartOptions"
                        />

                    </div>

                </div>


                <!-- ======================================
                     PRODUK TERLARIS + STOK MENIPIS
                ======================================= -->

                <div
                    class="grid grid-cols-1 md:grid-cols-2 gap-6"
                >


                    <!-- ==================================
                         PRODUK TERLARIS
                    =================================== -->

                    <div
                        class="bg-white rounded-lg shadow-sm border border-gray-100 p-6"
                    >

                        <div
                            class="flex justify-between items-center mb-4 border-b pb-2"
                        >

                            <div>

                                <h3
                                    class="font-bold text-lg text-gray-800"
                                >
                                    Produk Terlaris
                                </h3>

                                <p
                                    class="text-sm text-gray-500 mt-1"
                                >
                                    Penjualan hari ini
                                </p>

                            </div>

                            <Link
                                :href="route('reports.top_selling')"
                                class="text-sm text-indigo-600 hover:underline"
                            >
                                Lihat Laporan
                            </Link>

                        </div>


                        <!-- ADA DATA -->

                        <div
                            v-if="top_selling_products.length > 0"
                            class="divide-y divide-gray-200"
                        >

                            <div
                                v-for="(product, index) in top_selling_products"
                                :key="product.id"
                                class="py-3 flex items-center justify-between"
                            >

                                <div class="flex items-center gap-3">

                                    <!-- RANK -->

                                    <div
                                        class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm"
                                    >
                                        {{ index + 1 }}
                                    </div>


                                    <!-- PRODUCT -->

                                    <div>

                                        <p
                                            class="font-semibold text-gray-800"
                                        >
                                            {{ product.name }}
                                        </p>

                                        <p
                                            class="text-xs text-gray-500"
                                        >
                                            Omzet:
                                            {{ formatRp(product.total_revenue) }}
                                        </p>

                                    </div>

                                </div>


                                <!-- QTY -->

                                <div class="text-right">

                                    <p
                                        class="font-bold text-gray-800"
                                    >
                                        {{ formatQty(product.total_qty) }}
                                    </p>

                                    <p
                                        class="text-xs text-gray-500"
                                    >
                                        {{ product.unit }}
                                    </p>

                                </div>

                            </div>

                        </div>


                        <!-- TIDAK ADA DATA -->

                        <div
                            v-else
                            class="text-center py-8 text-gray-500"
                        >

                            <svg
                                class="w-10 h-10 mx-auto mb-2 text-gray-300"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-3.5a1.5 1.5 0 00-1.5 1.5v0a1.5 1.5 0 01-1.5 1.5h-3A1.5 1.5 0 0110 14.5v0A1.5 1.5 0 008.5 13H4"
                                />

                            </svg>

                            Belum ada transaksi hari ini.

                        </div>

                    </div>


                    <!-- ==================================
                         STOK MENIPIS
                    =================================== -->

                    <div
                        class="bg-white rounded-lg shadow-sm border border-gray-100 p-6"
                    >

                        <div
                            class="flex justify-between items-center mb-4 border-b pb-2"
                        >

                            <h3
                                class="font-bold text-lg text-red-600 flex items-center gap-2"
                            >

                                <svg
                                    class="w-5 h-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >

                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3.733 1.732 3.733z"
                                    />

                                </svg>

                                Stok Hampir Habis

                            </h3>


                            <Link
                                :href="route('products.index')"
                                class="text-sm text-indigo-600 hover:underline"
                            >
                                Lihat Semua
                            </Link>

                        </div>


                        <!-- ADA DATA -->

                        <ul
                            v-if="low_stock_products.length > 0"
                            class="divide-y divide-gray-200"
                        >

                            <li
                                v-for="product in low_stock_products"
                                :key="product.id"
                                class="py-3 flex justify-between items-center"
                            >

                                <div>

                                    <p
                                        class="font-semibold text-gray-800"
                                    >
                                        {{ product.name }}
                                    </p>

                                    <p
                                        class="text-xs text-gray-500"
                                    >
                                        {{ product.barcode || '-' }}
                                    </p>

                                </div>


                                <span
                                    class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-bold"
                                >

                                    Sisa
                                    {{ formatQty(product.stock) }}
                                    {{ product.unit }}

                                </span>

                            </li>

                        </ul>


                        <!-- AMAN -->

                        <div
                            v-else
                            class="text-center py-8 text-gray-500 italic"
                        >

                            Semua stok produk dalam kondisi aman
                            (di atas 10).

                        </div>

                    </div>

                </div>


                <!-- ======================================
                     RINGKASAN SISTEM
                ======================================= -->

                <div
                    class="bg-white rounded-lg shadow-sm border border-gray-100 p-6"
                >

                    <h3
                        class="font-bold text-lg mb-4 border-b pb-2"
                    >
                        Ringkasan Sistem
                    </h3>


                    <div
                        class="grid grid-cols-1 md:grid-cols-3 gap-4"
                    >

                        <!-- PRODUK -->

                        <div
                            class="flex justify-between items-center p-4 hover:bg-gray-50 rounded-md border border-gray-100"
                        >

                            <span
                                class="font-medium text-gray-600"
                            >
                                Total Macam Produk
                            </span>

                            <span
                                class="font-bold text-xl text-gray-800"
                            >
                                {{ summary.total_products }}
                            </span>

                        </div>


                        <!-- CUSTOMER -->

                        <div
                            class="flex justify-between items-center p-4 hover:bg-gray-50 rounded-md border border-gray-100"
                        >

                            <span
                                class="font-medium text-gray-600"
                            >
                                Total Pelanggan
                            </span>

                            <span
                                class="font-bold text-xl text-gray-800"
                            >
                                {{ summary.total_customers }}
                            </span>

                        </div>


                        <!-- SUPPLIER -->

                        <div
                            class="flex justify-between items-center p-4 hover:bg-gray-50 rounded-md border border-gray-100"
                        >

                            <span
                                class="font-medium text-gray-600"
                            >
                                Total Supplier
                            </span>

                            <span
                                class="font-bold text-xl text-gray-800"
                            >
                                {{ summary.total_suppliers }}
                            </span>

                        </div>

                    </div>

                </div>


            </div>

        </div>

    </AuthenticatedLayout>

</template>