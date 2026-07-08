<script lang="ts">
    import { scaleBand } from 'd3-scale';
    import { BarChart } from 'layerchart';
    import * as Chart from '@/components/ui/chart';
    import { cn } from '@/lib/utils';

    type Series = { key: string; label: string; color: string };

    let {
        data,
        x,
        series,
        stack = false,
        class: className = '',
    }: {
        data: Record<string, unknown>[];
        x: string;
        series: Series[];
        stack?: boolean;
        class?: string;
    } = $props();

    const config: Chart.ChartConfig = Object.fromEntries(
        series.map((s) => [s.key, { label: s.label, color: s.color }]),
    );
</script>

<Chart.Container {config} class={cn('h-[220px] w-full', className)}>
    <BarChart
        {data}
        {x}
        xScale={scaleBand().padding(0.3)}
        axis="x"
        rule={false}
        seriesLayout={stack ? 'stack' : 'group'}
        {series}
        props={{
            bars: { radius: 0, strokeWidth: 0 },
            xAxis: { tickLength: 0, format: (d: unknown) => String(d) },
        }}
    >
        {#snippet tooltip()}
            <Chart.Tooltip />
        {/snippet}
    </BarChart>
</Chart.Container>
