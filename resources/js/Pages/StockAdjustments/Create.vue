<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ products: Array });

const form = useForm({
    product_id: '',
    physical_stock: 0,
    reason: '',
});

const selectedProduct = computed(() => {
    return props.products.find(p => p.id === form.product_id) || null;
});

// Menghitung selisih secara realtime di UI
const difference = computed(() => {
    if (!selectedProduct.value) return 0;
    return (form.physical_stock - selectedProduct.value.stock).toFixed(3);
});

const submit = () => form.post(route('stock-adjustments.store'));
</script>

<template>
    <Head title="Stok Opname" />
    <AuthenticatedLayout>
        <template #header><h2 class="font-semibold text-xl text-gray-800 leading-tight">Penyesuaian Stok (Opname)</h2></template>
        <div class="py-12 max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Pilih Produk</label>
                        <select v-model="form.product_id" class="mt-1 w-full rounded-md border-gray-300" required>
                            <option disabled value="">-- Pilih Barang --</option>
                            <option v-for="item in products" :key="item.id" :value="item.id">
                                {{ item.barcode ? `[${item.barcode}] ` : '' }}{{ item.name }}
                            </option>
                        </select>
                    </div>
                    
                    <div v-if="selectedProduct" class="p-4 bg-gray-50 border rounded-md">
                        <div class="flex justify-between items-center mb-4">
                            <span class="font-bold text-gray-700">Stok Sistem Saat Ini:</span>
                            <span class="text-xl font-bold">{{ selectedProduct.stock }} {{ selectedProduct.unit }}</span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 items-center">
                            <div>
                                <label class="block text-sm font-medium text-blue-700">Stok Fisik Sebenarnya</label>
                                <input v-model="form.physical_stock" type="number" step="0.01" class="mt-1 w-full rounded-md border-gray-300 border-blue-400 focus:ring-blue-500" required />
                            </div>
                            <div class="text-right pt-4">
                                <span class="text-sm font-medium text-gray-500">Selisih: </span>
                                <span :class="{'text-red-600': difference < 0, 'text-green-600': difference > 0, 'text-gray-800': difference == 0}" class="text-lg font-bold">
                                    {{ difference > 0 ? '+' : '' }}{{ difference }} {{ selectedProduct.unit }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedProduct">
                        <label class="block text-sm font-medium">Alasan Penyesuaian</label>
                        <input v-model="form.reason" type="text" placeholder="Contoh: Beras tumpah digigit tikus, Susut timbangan..." class="mt-1 w-full rounded-md border-gray-300" required />
                    </div>

                    <div class="flex justify-end gap-2 pt-4">
                        <Link :href="route('dashboard')" class="px-4 py-2 bg-gray-300 rounded-md">Batal</Link>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md" :disabled="form.processing || !form.product_id">Simpan Penyesuaian</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>