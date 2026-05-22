<script setup lang="ts">
interface Props {
    type?: string | null;
    incomeLabel?: string;
    expenseLabel?: string;
    savingLabel?: string;
    unknownLabel?: string;
}

const props = withDefaults(defineProps<Props>(), {
    type: null,
    incomeLabel: 'Inkomen',
    expenseLabel: 'Uitgave',
    savingLabel: 'Sparen',
    unknownLabel: 'Onbekend',
});

const labelForType = () => {
    if (props.type === 'income') return props.incomeLabel;
    if (props.type === 'expense') return props.expenseLabel;
    if (props.type === 'saving') return props.savingLabel;
    return props.unknownLabel;
};
</script>

<template>
    <span
        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
        :class="
            type === 'income'
                ? 'bg-green-600/20 text-green-400'
                : type === 'saving'
                  ? 'bg-blue-600/20 text-blue-400'
                  : type === 'expense'
                    ? 'bg-red-600/20 text-red-400'
                    : 'bg-slate-700 text-slate-200'
        "
    >
        {{ labelForType() }}
    </span>
</template>
