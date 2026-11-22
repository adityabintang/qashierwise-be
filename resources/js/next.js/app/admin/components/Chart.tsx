import ApexCharts from "apexcharts";
import { useEffect, useRef } from "react";

/* Thin imperative wrapper around ApexCharts (already a project dependency). */
export function Chart({ options }: { options: ApexCharts.ApexOptions }) {
  const ref = useRef<HTMLDivElement>(null);
  const chartRef = useRef<ApexCharts | null>(null);

  useEffect(() => {
    if (!ref.current) return;
    const chart = new ApexCharts(ref.current, options);
    chartRef.current = chart;
    chart.render();
    return () => {
      chart.destroy();
      chartRef.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    chartRef.current?.updateOptions(options, true, true);
  }, [options]);

  return <div ref={ref} />;
}
