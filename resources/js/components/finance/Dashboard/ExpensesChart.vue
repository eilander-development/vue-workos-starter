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
            borderRadius: 2,
            borderRadiusApplication: 'end', // 'around', 'end'
            borderRadiusWhenStacked: 'last', // 'all', 'last'
            dataLabels: {
                total: {
                    enabled: false,
                }
            }
        },
    },
    states: {
      hover: {
        filter: {
          type: "darken",
          value: 1,
        },
      },
    },
    stroke: {
      show: false,
      width: 0,
      colors: ["transparent"],
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
            <CardTitle>Income vs Expenses</CardTitle>
        </CardHeader>
        <CardContent>
            <VueApexCharts height="350" :options="chartOptions" :series="props.yearlyExpensesChart.series"/>
        </CardContent>
    </Card>
</template>
