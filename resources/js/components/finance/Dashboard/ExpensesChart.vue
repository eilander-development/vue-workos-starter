<script setup lang="ts">
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import VueApexCharts from 'vue3-apexcharts'
import { computed, onMounted, ref } from 'vue';

interface Props {
    yearlyExpensesChart: object|Array;
}

const props = defineProps<Props>()

const visibleSeries = ref<any[]>((props.yearlyExpensesChart as any)?.series ?? []);
const isLoading = ref(false);

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('nl-NL', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

const chartOptions = computed(() => ({
    chart: {
        type: 'bar',
        height: 350,
        stacked: false,
        toolbar: {
            show: false
        },
        zoom: {
            enabled: false
        }
    },
    dataLabels: {
      enabled: true,
      formatter: (value: number) => formatCurrency(value),
      style: {
          fontSize: '11px',
          fontWeight: 600,
      },
    },
    legend: {
      show: true,
      position: 'top',
      horizontalAlign: 'right',
    },
    tooltip: {
      shared: true,
      intersect: false,
      followCursor: false,
      theme: 'dark',
      style: {
        fontFamily: "Inter, sans-serif",
      },
      y: {
        formatter: (value: number) => formatCurrency(value),
      },
        x: {
            show: true,
            format: 'MMM',
        },
      onDatasetHover: {
        highlightDataSeries: false,
      },
    },
    plotOptions: {
        bar: {
            horizontal: false,
            columnWidth: '35%',
            borderRadius: 5,
            borderRadiusApplication: 'end'
        },
    },
    stroke: {
        show: true,
        width: 5,
        colors: ['transparent']
    },
    states: {
      hover: {
        filter: {
          type: "darken",
          value: 1,
        },
      },
    },
    grid: {
      show: false,
    },
    xaxis: {
        floating: false,
        labels: {
            show: true,
            style: {
                colors: 'var(--color-gray-400)',
                fontFamily: "Inter, sans-serif",
            },
            format: 'MMM',
        },
        tooltip: {
            enabled: false,
        },
        crosshairs: {
            show: false,
        },
        axisBorder: {
            show: false,
        },
        axisTicks: {
            show: false,
        },
    },
    yaxis: {
        labels: {
            show: true,
            style: {
                colors: 'var(--color-gray-400)',
                fontFamily: "Inter, sans-serif",
            },
            formatter: (value: number) => formatCurrency(value),
        },
    },
    fill: {
      opacity: 1,
    },
    colors: ['#10b981', '#ef4444'],
}));

const loadYearlySeries = async () => {
    isLoading.value = true;
    try {
        const year = new Date().getFullYear();
        const response = await fetch(`/dashboard/yearly-expenses-chart?year=${year}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        visibleSeries.value = data?.series ?? [];
    } finally {
        isLoading.value = false;
    }
};

onMounted(loadYearlySeries);

</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardHeader>
            <CardTitle>Inkomen vs uitgaven</CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="isLoading" class="text-sm text-muted-foreground">Grafiek laden...</div>
            <VueApexCharts height="350" :options="chartOptions" :series="visibleSeries"/>
        </CardContent>
    </Card>
</template>
