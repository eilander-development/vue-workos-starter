<script setup lang="ts">
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import VueApexCharts from 'vue3-apexcharts'

interface Props {
    yearlyExpensesChart: Object|Array
}

const props = defineProps<Props>()

const chartOptions = {
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
      enabled: false,
    },
    legend: {
      show: false,
    },
    tooltip: {
      shared: true,
      intersect: false,
      followCursor: false,
      theme: 'dark',
      style: {
        fontFamily: "Inter, sans-serif",
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
        },
    },
    fill: {
      opacity: 1,
    },
};

</script>

<template>
    <Card class="rounded-md shadow-xl">
        <CardHeader>
            <CardTitle>Inkomen vs uitgaven</CardTitle>
        </CardHeader>
        <CardContent>
            <VueApexCharts height="350" :options="chartOptions" :series="props.yearlyExpensesChart.series"/>
        </CardContent>
    </Card>
</template>
