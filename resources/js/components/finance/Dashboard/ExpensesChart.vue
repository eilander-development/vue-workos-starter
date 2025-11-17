<script setup lang="ts">
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import VueApexCharts from 'vue3-apexcharts'

interface Props {
    monthyExpensesChart: Array
}

const props = defineProps<Props>()

const chartOptions = {
    chart: {
        type: 'bar',
        height: 350,
        stacked: true,
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
      shared: false,
      intersect: true,
      followCursor: true,
      theme: 'dark',
      style: {
        fontFamily: "Inter, sans-serif",
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
        type: 'datetime',
        categories: props.monthyExpensesChart.months,
        labels: {
            show: true,
            style: {
                colors: 'var(--color-gray-300)',
                fontFamily: "Inter, sans-serif",
            },
            format: 'dd',
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
                colors: 'var(--color-gray-300)',
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
  <Card class="w-full">
    <CardHeader>
        <CardTitle>Monthly Income/Expenses</CardTitle>
    </CardHeader>
    <CardContent>
        <VueApexCharts height="350" :options="chartOptions" :series="props.monthyExpensesChart.series"/>
    </CardContent>
</Card>
</template>
