<script setup>
import { ref } from 'vue';

const props = defineProps({
    show: Boolean,
    title: { type: String, default: 'Konfirmasi' },
    message: String,
    isPrompt: { type: Boolean, default: false }, // True jika butuh input text (seperti prompt browser)
    placeholder: { type: String, default: '' }
});

const emit = defineEmits(['close', 'confirm']);
const inputValue = ref('');

const handleConfirm = () => {
    emit('confirm', props.isPrompt ? inputValue.value : true);
    inputValue.value = '';
};

const handleClose = () => {
    emit('close');
    inputValue.value = '';
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4 transition-opacity">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100">
            <!-- Header -->
            <div class="px-6 py-4 bg-gray-50 border-b flex justify-between items-center">
                <h3 class="font-bold text-lg text-gray-800">{{ title }}</h3>
                <button @click="handleClose" class="text-gray-400 hover:text-red-500 font-bold text-xl">&times;</button>
            </div>
            
            <!-- Content -->
            <div class="p-6">
                <p class="text-gray-600 mb-4 text-sm leading-relaxed">{{ message }}</p>
                
                <!-- Input field jika tipe prompt -->
                <div v-if="isPrompt">
                    <input 
                        type="text" 
                        v-model="inputValue" 
                        :placeholder="placeholder"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        @keyup.enter="handleConfirm"
                        autofocus
                    >
                </div>
            </div>
            
            <!-- Footer Buttons -->
            <div class="px-6 py-3 bg-gray-50 border-t flex justify-end space-x-3">
                <button @click="handleClose" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 font-medium text-sm hover:bg-gray-100 transition">
                    Batal
                </button>
                <button @click="handleConfirm" class="px-4 py-2 bg-indigo-600 border border-transparent rounded-lg text-white font-semibold text-sm hover:bg-indigo-700 shadow-sm transition">
                    OK
                </button>
            </div>
        </div>
    </div>
</template>