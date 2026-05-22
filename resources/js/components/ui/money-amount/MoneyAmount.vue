<script setup lang="ts">
interface Props {
    amount: number | string;
    highlightPositive?: boolean;
    locale?: string;
    currency?: string;
}

const props = withDefaults(defineProps<Props>(), {
    highlightPositive: false,
    locale: 'nl-NL',
    currency: 'EUR',
});
</script>

<template>
    <span :class="highlightPositive && Number(props.amount) > 0 ? 'rounded-md bg-green-800 p-2 py-1 text-white' : ''">
        {{
            new Intl.NumberFormat(props.locale, {
                style: 'currency',
                currency: props.currency,
            }).format(Number(props.amount ?? 0))
        }}
    </span>
</template>
