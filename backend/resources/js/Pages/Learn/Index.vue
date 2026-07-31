<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import WordChat from '@/Components/WordChat.vue';

defineProps({
    prefill: { type: String, default: '' },
});
</script>

<template>
    <Head title="Learn" />
    <AppLayout title="Learn" heading="Learn" chat-page>
        <WordChat :prefill="prefill" />
    </AppLayout>
</template>
