<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import {ref} from 'vue';

const app_url = ref(import.meta.env.VITE_APP_URL);
const app_env = ref(import.meta.env.VITE_APP_ENV);
</script>

<template>
    <div class="flex justify-center items-center w-auto">
        <img
            class="w-full max-w-7xl h-auto rounded-lg shadow-lg object-cover hover:scale-105 transition-transform duration-300"
            :src="app_env == 'production' ? `${app_url}/images-resources/img-ingles3.webp`:'/images-resources/img-ingles3.webp'"

            alt="Imagen">
    </div>
</template>


