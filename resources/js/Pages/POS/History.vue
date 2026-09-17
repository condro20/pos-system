<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    sales: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
        }),
    },

    filters: {
        type: Object,
        default: () => ({}),
    },
});

// =========================================================
// FILTER
// =========================================================

const search = ref(
    props.filters?.search || ''
);

const sortField = ref(
    props.filters?.sort || 'created_at'
);

const sortDirection = ref(
    props.filters?.direction || 'desc'
);


// =========================================================
// MODAL
// =========================================================

const showModal = ref(false);

const selectedData = ref(null);


// =========================================================
// FETCH DATA
// =========================================================

const fetchData = () => {

    router.get(
        route('pos.history'),
        {
            search: search.value.trim(),
            sort: sortField.value,
            direction: sortDirection.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );

};


// =========================================================
// SEARCH DEBOUNCE
// =========================================================

let searchTimeout = null;

watch(search, () => {

    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        fetchData();
    }, 300);

});


// =========================================================
// SORTING
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


// =========================================================
// OPEN DETAIL
// =========================================================

const openModal = (sale) => {

    selectedData.value = sale;

    showModal.value = true;

};


// =========================================================
// CLOSE DETAIL
// =========================================================

const closeModal = () => {

    showModal.value = false;

    selectedData.value = null;

};


// =========================================================
// PRINT INVOICE
// =========================================================

const printInvoice = () => {

    if (!selectedData.value?.id) {
        return;
    }

    const url = route(
        'pos.receipt',
        selectedData.value.id
    );

    window.open(
        url,
        '_blank',
        'noopener,noreferrer'
    );

};


// =========================================================
// FORMAT RUPIAH
// =========================================================

const formatRp = (value) => {

    return new Intl.NumberFormat(
        'id-ID',
        {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }
    ).format(
        Number(value) || 0
    );

};


// =========================================================
// FORMAT QTY
// =========================================================

const formatQty = (value) => {

    const number = Number(value) || 0;

    return new Intl.NumberFormat(
        'id-ID',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3,
        }
    ).format(number);

};


// =========================================================
// FORMAT DATE
// =========================================================

const formatDate = (dateString) => {

    if (!dateString) {
        return '-';
    }

    const date = new Date(dateString);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleDateString(
        'id-ID',
        {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }
    );

};

</script>


<template>

    <Head title="Riwayat Kasir" />

    <AuthenticatedLayout>

        <!-- ================================================= -->
        <!-- HEADER -->
        <!-- ================================================= -->

        <template #header>

            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
            >

                <div>

                    <h2
                        class="font-semibold text-xl text-gray-800 leading-tight"
                    >
                        Riwayat Transaksi Kasir
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Riwayat transaksi penjualan
                    </p>

                </div>


                <Link
                    :href="route('pos.index')"
                    class="inline-flex items-center justify-center bg-gray-700 text-white px-4 py-2 rounded-md hover:bg-gray-800 font-bold transition"
                >
                    &laquo; Kembali ke Kasir
                </Link>

            </div>

        </template>


        <!-- ================================================= -->
        <!-- CONTENT -->
        <!-- ================================================= -->

        <div
            class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8"
        >

            <div
                class="bg-white p-6 shadow-sm sm:rounded-lg"
            >

                <!-- SEARCH -->

                <div
                    class="relative w-full md:w-1/3 mb-6"
                >

                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari No. Invoice atau Nama Kasir..."
                        class="w-full rounded-md border-gray-300 shadow-sm pr-10 focus:border-indigo-500 focus:ring-indigo-500"
                    />

                    <button
                        v-if="search"
                        type="button"
                        @click="search = ''"
                        class="absolute right-3 top-2.5 text-gray-400 hover:text-red-500 font-bold text-lg"
                        aria-label="Hapus pencarian"
                    >
                        &times;
                    </button>

                </div>


                <!-- TABLE -->

                <div class="overflow-x-auto">

                    <table
                        class="w-full text-left border-collapse"
                    >

                        <thead>

                            <tr
                                class="bg-gray-100 border-b-2 text-gray-700"
                            >

                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('created_at')"
                                >

                                    Tanggal & Jam

                                    <span
                                        v-if="sortField === 'created_at'"
                                        class="text-indigo-600 ml-1"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('invoice_no')"
                                >

                                    No. Invoice

                                    <span
                                        v-if="sortField === 'invoice_no'"
                                        class="text-indigo-600 ml-1"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('cashier')"
                                >

                                    Kasir

                                    <span
                                        v-if="sortField === 'cashier'"
                                        class="text-indigo-600 ml-1"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <th
                                    class="p-3 cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('payment_method')"
                                >

                                    Pembayaran

                                    <span
                                        v-if="sortField === 'payment_method'"
                                        class="text-indigo-600 ml-1"
                                    >
                                        {{
                                            sortDirection === 'asc'
                                                ? '↑'
                                                : '↓'
                                        }}
                                    </span>

                                </th>


                                <th
                                    class="p-3 text-right cursor-pointer hover:bg-gray-200 transition select-none"
                                    @click="sortBy('grand_total')"
                                >

                                    Total Belanja

                                    <span
                                        v-if="sortField === 'grand_total'"
                                        class="text-indigo-600 ml-1"
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


                        <tbody>

                            <tr
                                v-for="sale in sales.data"
                                :key="sale.id"
                                class="border-b hover:bg-gray-50 transition"
                            >

                                <td
                                    class="p-3 text-sm text-gray-600"
                                >
                                    {{ formatDate(sale.created_at) }}
                                </td>


                                <!-- CLICKABLE INVOICE -->

                                <td class="p-3">

                                    <button
                                        type="button"
                                        @click="openModal(sale)"
                                        class="font-semibold text-indigo-700 hover:text-indigo-900 hover:underline transition"
                                        :title="'Lihat detail ' + sale.invoice_no"
                                    >
                                        {{ sale.invoice_no }}
                                    </button>

                                </td>


                                <td class="p-3">

                                    {{
                                        sale.user?.name
                                        || 'Kasir Dihapus'
                                    }}

                                </td>


                                <td class="p-3">

                                    <span
                                        class="px-2 py-1 text-xs font-bold rounded"
                                        :class="
                                            sale.payment_method === 'Cash'
                                                || sale.payment_method === 'Tunai'
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-blue-100 text-blue-700'
                                        "
                                    >
                                        {{ sale.payment_method }}
                                    </span>

                                </td>


                                <td
                                    class="p-3 text-right font-bold text-indigo-600"
                                >
                                    {{ formatRp(sale.grand_total) }}
                                </td>

                            </tr>


                            <tr
                                v-if="sales.data.length === 0"
                            >

                                <td
                                    colspan="5"
                                    class="p-10 text-center text-gray-500 font-medium"
                                >

                                    Belum ada riwayat transaksi.

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>


                <!-- PAGINATION -->

                <Pagination
                    :links="sales.links"
                />

            </div>

        </div>

    </AuthenticatedLayout>


    <!-- ================================================= -->
    <!-- MODAL DETAIL INVOICE -->
    <!-- ================================================= -->

    <div
        v-if="showModal && selectedData"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
        @click.self="closeModal"
    >

        <div
            class="bg-white rounded-lg shadow-xl w-full max-w-4xl overflow-hidden"
        >

            <!-- HEADER -->

            <div
                class="px-6 py-4 border-b flex justify-between items-center bg-gray-50"
            >

                <div>

                    <h3
                        class="font-bold text-lg text-gray-800"
                    >
                        Detail Invoice
                    </h3>

                    <button
                        type="button"
                        @click="closeModal"
                        class="text-sm text-indigo-600 font-bold hover:underline mt-1"
                    >
                        {{ selectedData.invoice_no }}
                    </button>

                </div>


                <button
                    type="button"
                    @click="closeModal"
                    class="text-gray-500 hover:text-red-500 font-bold text-2xl"
                    aria-label="Tutup"
                >
                    &times;
                </button>

            </div>


            <!-- BODY -->

            <div
                class="p-6 max-h-[70vh] overflow-y-auto"
            >

                <!-- INFO -->

                <div
                    class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6"
                >

                    <div>

                        <p class="text-xs text-gray-500 uppercase">
                            Kasir
                        </p>

                        <p class="font-semibold text-gray-800">
                            {{
                                selectedData.user?.name
                                || 'Kasir Dihapus'
                            }}
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-gray-500 uppercase">
                            Pelanggan
                        </p>

                        <p class="font-semibold text-gray-800">
                            {{
                                selectedData.customer?.name
                                || '-'
                            }}
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-gray-500 uppercase">
                            Pembayaran
                        </p>

                        <p class="font-semibold text-gray-800">
                            {{ selectedData.payment_method }}
                        </p>

                    </div>


                    <div>

                        <p class="text-xs text-gray-500 uppercase">
                            Tanggal
                        </p>

                        <p class="font-semibold text-gray-800">
                            {{
                                formatDate(
                                    selectedData.created_at
                                )
                            }}
                        </p>

                    </div>

                </div>


                <!-- ITEM TABLE -->

                <div
                    class="border rounded-lg overflow-hidden"
                >

                    <table
                        class="w-full text-left border-collapse"
                    >

                        <thead
                            class="bg-gray-100 border-b-2"
                        >

                            <tr>

                                <th class="p-3">
                                    Produk
                                </th>

                                <th class="p-3">
                                    Satuan
                                </th>

                                <th class="p-3 text-right">
                                    Harga
                                </th>

                                <th class="p-3 text-right">
                                    Qty
                                </th>

                                <th class="p-3 text-right">
                                    Subtotal
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <template
                                v-if="
                                    selectedData.sale_details &&
                                    selectedData.sale_details.length > 0
                                "
                            >

                                <tr
                                    v-for="detail in selectedData.sale_details"
                                    :key="detail.id"
                                    class="border-b"
                                >

                                    <td class="p-3 font-medium">

                                        {{
                                            detail.product?.name
                                            || 'Produk Dihapus'
                                        }}

                                    </td>


                                    <td class="p-3 text-gray-600">

                                        {{
                                            detail.product?.unit
                                            || '-'
                                        }}

                                    </td>


                                    <td class="p-3 text-right">

                                        {{
                                            formatRp(
                                                detail.selling_price
                                            )
                                        }}

                                    </td>


                                    <td
                                        class="p-3 text-right font-bold text-red-600"
                                    >

                                        -{{
                                            formatQty(
                                                detail.quantity
                                            )
                                        }}

                                    </td>


                                    <td
                                        class="p-3 text-right font-bold"
                                    >

                                        {{
                                            formatRp(
                                                detail.subtotal
                                            )
                                        }}

                                    </td>

                                </tr>

                            </template>


                            <tr v-else>

                                <td
                                    colspan="5"
                                    class="p-6 text-center text-red-500 font-bold"
                                >
                                    Data detail transaksi tidak ditemukan.
                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>


                <!-- TOTAL -->

                <div
                    class="mt-6 flex justify-end"
                >

                    <div
                        class="text-right space-y-1 min-w-[250px]"
                    >

                        <div
                            class="flex justify-between gap-8 text-sm text-gray-600"
                        >

                            <span>
                                Subtotal
                            </span>

                            <span>
                                {{
                                    formatRp(
                                        selectedData.subtotal
                                    )
                                }}
                            </span>

                        </div>


                        <div
                            class="flex justify-between gap-8 text-sm text-gray-600"
                        >

                            <span>
                                Diskon
                            </span>

                            <span>
                                {{
                                    formatRp(
                                        selectedData.discount
                                    )
                                }}
                            </span>

                        </div>


                        <div
                            class="flex justify-between gap-8 pt-2 border-t"
                        >

                            <span class="font-bold">
                                Total
                            </span>

                            <span
                                class="text-2xl font-bold text-indigo-700"
                            >
                                {{
                                    formatRp(
                                        selectedData.grand_total
                                    )
                                }}
                            </span>

                        </div>

                    </div>

                </div>

            </div>


            <!-- FOOTER -->

            <div
                class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-3"
            >

                <button
                    type="button"
                    @click="printInvoice"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded shadow font-bold transition"
                >
                    🖨 Cetak Invoice
                </button>


                <button
                    type="button"
                    @click="closeModal"
                    class="bg-gray-800 hover:bg-gray-900 text-white px-5 py-2 rounded shadow font-bold transition"
                >
                    Tutup
                </button>

            </div>

        </div>

    </div>

</template>