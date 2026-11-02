<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import {ref} from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});

const app_url = ref(import.meta.env.VITE_APP_URL);
const app_env = ref(import.meta.env.VITE_APP_ENV);
const app_url_prod = ref(import.meta.env.VITE_APP_URL_PRODUCTION);
function handleImageError() {
    document.getElementById('screenshot-container')?.classList.add('!hidden');
    document.getElementById('docs-card')?.classList.add('!row-span-1');
    document.getElementById('docs-card-content')?.classList.add('!flex-row');
    document.getElementById('background')?.classList.add('!hidden');
}


</script>

<template>

    <div>
        <Head title="Welcome" />
        <div class="relative w-full h-screen bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
            <!-- Imagen de fondo -->
            <img id="background"
                class="absolute inset-0 w-full h-full object-cover z-0"
                :src="app_env == 'production' ? `${app_url}/images-resources/img-ingles.webp`:'/images-resources/img-ingles.webp'"
                alt="afuera" />



            <!-- Contenido sobre la imagen -->
            <div class="relative z-10 flex flex-col items-center justify-center h-full bg-black/50 text-white">
                <div class="space-y-4 text-center">
                    <h1 class="text-4xl font-bold">Welcome to BlackPool</h1>
                    <p class="text-lg">Learn English with us!</p>
                </div>

                <div class="mt-6 flex space-x-4">
                    <!-- Botón de Log in -->
                    <Link v-if="canLogin"
                          :href="route('login')"
                          class="px-6 py-3 bg-white text-black rounded-md shadow-lg hover:bg-gray-200">
                        Log in
                    </Link>
                    <!-- Botón de Register -->
                    <Link v-if="canRegister"
                          :href="route('register')"
                          class="px-6 py-3 bg-white text-black rounded-md shadow-lg hover:bg-gray-200">
                        Register
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

