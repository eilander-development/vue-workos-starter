<script setup lang="ts">
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import VueApexCharts from 'vue3-apexcharts'

const chartSeries = [
{
    name: 'Income',
    data: [3900, 3900, 3900, 3900, 4200, 3900, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    color: "oklch(79.2% 0.209 151.711)"
}, 
{
    name: 'Expenses',
    data: [3500, 3600, 3789, 4100, 3456, 3850, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    color: "oklch(70.4% 0.191 22.216)"
},
];
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
      shared: true,
      intersect: false,
      theme: 'dark',
      style: {
        fontFamily: "Inter, sans-serif",
      },
    },
    responsive: [{
        breakpoint: 480,
        options: {
            legend: {
                position: 'bottom',
                offsetX: -10,
                offsetY: 0
            }
        }
    }],
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
      show: true,
      width: 0,
      colors: ["transparent"],
    },
    grid: {
      show: false,
      strokeDashArray: 4,
      padding: {
        left: 2,
        right: 2,
        top: -14
      },
    },
    xaxis: {
      floating: false,
      type: 'datetime',
      categories: daysOfMonth(),
      labels: {
        show: true,
        style: {
          colors: 'var(--color-gray-300)',
          fontFamily: "Inter, sans-serif",
        },
        format: 'dd',
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
      show: false,
    },
    fill: {
      opacity: 1,
    },
};

function daysOfMonth(): Array<string> {
    const days: Array<string> = [];
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const numDays = new Date(year, month + 1, 0).getDate();

    for (let day = 1; day <= numDays; day++) {
        const dayString = day.toString().padStart(2, '0');
        const monthString = (month + 1).toString().padStart(2, '0');
        days.push(`${year}-${monthString}-${dayString}`);
    }

    return days;
}


</script>

<template>
  <Card class="w-full">
    <CardHeader>
        <CardTitle>Monthly Income/Expenses</CardTitle>
    </CardHeader>
    <CardContent>
        <VueApexCharts height="350" :options="chartOptions" :series="chartSeries"/>
    </CardContent>
</Card>
</template>
