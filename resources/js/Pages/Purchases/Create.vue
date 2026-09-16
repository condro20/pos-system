<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ suppliers: Array, products: Array });

const form = useForm({
    supplier_id: '',
    items: []
});

const formatRp = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);

// Menambah baris kosong ke daftar belanja
const addItem = () => {
    form.items.push({ product_id: '', qty: 1, price: 0 });
};

// Menghapus baris dari daftar
const removeItem = (index) => {
    form.items.splice(index, 1);
};

// Auto-fill harga beli saat produk dipilih
const onProductChange = (index) => {
    const selectedProd = props.products.find(p => p.id === form.items[index].product_id);
    if (selectedProd) {
        form.items[index].price = selectedProd.purchase_price;
    }
};

const grandTotal = computed(() => {
    return form.items.reduce((total, item) => total + (item.qty * item.price), 0);
});

const submit = () => {
    if (form.items.length === 0) return alert('Pilih minimal 1 barang untuk di-restock!');
    form.post(route('purchases.store'));
};
</script>

<template>
    <Head title="Input Barang Masuk" />
    <AuthenticatedLayout>
        <template #header><h2 class="font-semibold text-xl text-gray-800 leading-tight">Input Barang Masuk (Restock)</h2></template>
        
        <div class="py-12 max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form @submit.prevent="submit" class="space-y-6">
                    
                    <!-- Pilih Supplier -->
                    <div class="w-full md:w-1/2">
                        <label class="block text-sm font-medium">Agen / Supplier</label>
                        <select v-model="form.supplier_id" class="mt-1 w-full rounded-md border-gray-300" required>
                            <option disabled value="">-- Pilih Supplier --</option>
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                        </select>
                        <p v-if="suppliers.length === 0" class="text-red-500 text-xs mt-1">Data Supplier kosong. Silakan isi tabel suppliers di database terlebih dahulu.</p>
                    </div>

                    <!-- Daftar Barang -->
                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-lg">Daftar Belanja</h3>
                            <button type="button" @click="addItem" class="bg-blue-100 text-blue-700 px-3 py-1 rounded hover:bg-blue-200 text-sm font-bold">
                                + Tambah Baris
                            </button>
                        </div>

                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="p-2 w-1/3">Produk</th>
                                    <th class="p-2 w-24">Kuantitas</th>
                                    <th class="p-2">Harga Beli (Satuan)</th>
                                    <th class="p-2 text-right">Subtotal</th>
                                    <th class="p-2 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in form.items" :key="index" class="border-b">
                                    <td class="p-2">
                                        <select v-model="item.product_id" @change="onProductChange(index)" class="w-full rounded-md border-gray-300 text-sm" required>
                                            <option disabled value="">Pilih Produk</option>
                                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                                        </select>
                                    </td>
                                    <td class="p-2">
                                        <input v-model="item.qty" type="number" step="0.01" min="0.01" class="w-full rounded-md border-gray-300 text-sm text-center" required />
                                    </td>
                                    <td class="p-2">
                                        <input v-model="item.price" type="number" min="0" class="w-full rounded-md border-gray-300 text-sm" required />
                                    </td>
                                    <td class="p-2 text-right font-bold text-gray-700">
                                        {{ formatRp(item.qty * item.price) }}
                                    </td>
                                    <td class="p-2 text-center">
                                        <button type="button" @click="removeItem(index)" class="text-red-500 hover:text-red-700 text-xl">&times;</button>
                                    </td>
                                </tr>
                                <tr v-if="form.items.length === 0">
                                    <td colspan="5" class="p-6 text-center text-gray-500 italic">Belum ada barang yang ditambahkan. Klik "Tambah Baris".</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Grand Total & Submit -->
                    <div class="flex flex-col md:flex-row justify-between items-center border-t pt-4">
                        <div class="text-2xl font-bold text-indigo-700">
                            Total: {{ formatRp(grandTotal) }}
                        </div>
                        <div class="flex gap-2 mt-4 md:mt-0">
                            <Link :href="route('purchases.index')" class="px-6 py-2 bg-gray-300 rounded-md font-bold">Batal</Link>
                            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-md font-bold" :disabled="form.processing || form.items.length === 0">
                                Simpan Pembelian
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>