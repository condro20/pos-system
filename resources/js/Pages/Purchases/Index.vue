<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    purchases: {
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
        route('purchases.index'),
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
// SEARCH
// =========================================================

let searchTimeout = null;

watch(search, () => {

    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        fetchData();
    }, 300);

});


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


// =========================================================
// OPEN MODAL
// =========================================================

const openModal = (purchase) => {

    selectedData.value = purchase;

    showModal.value = true;

};


// =========================================================
// CLOSE MODAL
// =========================================================

const closeModal = () => {

    showModal.value = false;

    selectedData.value = null;

};


// =========================================================
// PRINT PO
// =========================================================

const printPurchase = () => {

    if (!selectedData.value?.id) {
        return;
    }

    const url = route(
        'purchases.print',
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

    <Head title="Riwayat Pembelian" />

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
                        Riwayat Pembelian
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Riwayat barang masuk dan Purchase Order
                    </p>

                </div>


                <Link
                    :href="route('purchases.create')"
                    class="inline-flex items-center justify-center bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 font-bold shadow-sm transition"
                >
                    + Input Pembelian
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
                        placeholder="Cari No. PO atau Nama Supplier..."
                        class="w-full rounded-md border-gray-300 shadow-sm pr-10 focus:border-indigo-500 focus:ring-indigo-500"
                    />

                    <button
                        v-if="search"
                        type="button"
                        @click="search = ''"
                        class="absolute right-3 top-2.5 text-gray-400 hover:text-red-500 font-bold text-lg"
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

                                    No. PO

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
                                    @click="sortBy('supplier')"
                                >

                                    Supplier / Agen

                                    <span
                                        v-if="sortField === 'supplier'"
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

                                    Total Pembelian

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
                                v-for="item in purchases.data"
                                :key="item.id"
                                class="border-b hover:bg-gray-50 transition"
                            >

                                <td
                                    class="p-3 text-sm text-gray-600"
                                >
                                    {{
                                        formatDate(
                                            item.created_at
                                        )
                                    }}
                                </td>


                                <!-- CLICKABLE PO -->

                                <td class="p-3">

                                    <button
                                        type="button"
                                        @click="openModal(item)"
                                        class="font-semibold text-indigo-700 hover:text-indigo-900 hover:underline transition"
                                        :title="'Lihat detail ' + item.invoice_no"
                                    >
                                        {{ item.invoice_no }}
                                    </button>

                                </td>


                                <td class="p-3">

                                    {{
                                        item.supplier?.name
                                        || '-'
                                    }}

                                </td>


                                <td
                                    class="p-3 text-right font-bold text-red-600"
                                >
                                    {{
                                        formatRp(
                                            item.grand_total
                                        )
                                    }}
                                </td>

                            </tr>


                            <tr
                                v-if="purchases.data.length === 0"
                            >

                                <td
                                    colspan="4"
                                    class="p-10 text-center text-gray-500 font-medium"
                                >
                                    Belum ada riwayat pembelian.
                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>


                <!-- PAGINATION -->

                <Pagination
                    :links="purchases.links"
                />

            </div>

        </div>

    </AuthenticatedLayout>


    <!-- ================================================= -->
    <!-- MODAL DETAIL PO -->
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
                        Detail Purchase Order
                    </h3>

                    <p
                        class="text-sm text-indigo-600 font-semibold mt-1"
                    >
                        {{ selectedData.invoice_no }}
                    </p>

                </div>


                <button
                    type="button"
                    @click="closeModal"
                    class="text-gray-500 hover:text-red-500 font-bold text-2xl"
                >
                    &times;
                </button>

            </div>


            <!-- BODY -->

            <div
                class="p-6 max-h-[70vh] overflow-y-auto"
            >

                <!-- INFORMATION -->

                <div
                    class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6"
                >

                    <div>

                        <p class="text-xs text-gray-500 uppercase">
                            Supplier
                        </p>

                        <p class="font-semibold text-gray-800">
                            {{
                                selectedData.supplier?.name
                                || '-'
                            }}
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


                    <div>

                        <p class="text-xs text-gray-500 uppercase">
                            Input Oleh
                        </p>

                        <p class="font-semibold text-gray-800">
                            {{
                                selectedData.user?.name
                                || '-'
                            }}
                        </p>

                    </div>

                </div>


                <!-- ITEMS -->

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
                                    Harga Beli
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
                                    selectedData.purchase_details &&
                                    selectedData.purchase_details.length > 0
                                "
                            >

                                <tr
                                    v-for="detail in selectedData.purchase_details"
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
                                                detail.price
                                            )
                                        }}

                                    </td>


                                    <td
                                        class="p-3 text-right font-bold text-green-600"
                                    >

                                        +{{
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
                                    Data barang masuk tidak ditemukan.
                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>


                <!-- TOTAL -->

                <div
                    class="mt-6 flex justify-end"
                >

                    <div class="text-right">

                        <p
                            class="text-sm text-gray-500"
                        >
                            Total Pembelian
                        </p>

                        <p
                            class="text-3xl font-bold text-indigo-700"
                        >
                            {{
                                formatRp(
                                    selectedData.grand_total
                                )
                            }}
                        </p>

                    </div>

                </div>

            </div>


            <!-- FOOTER -->

            <div
                class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-3"
            >

                <button
                    type="button"
                    @click="printPurchase"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded shadow font-bold transition"
                >
                    🖨 Cetak PO
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