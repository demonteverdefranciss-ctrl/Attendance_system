import {
    Chart as ChartJS,
    ArcElement,
    LineElement,
    BarElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';
import { Doughnut, Line, Bar } from 'react-chartjs-2';

ChartJS.register(
    ArcElement, LineElement, BarElement, PointElement,
    CategoryScale, LinearScale, Tooltip, Legend, Filler,
);

export { Doughnut, Line, Bar };

export function ChartCard({ title, children, height = 260 }) {
    return (
        <div className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <h3 className="mb-3 text-sm font-semibold text-gray-700">{title}</h3>
            <div style={{ height }}>{children}</div>
        </div>
    );
}

export const noAspect = { responsive: true, maintainAspectRatio: false };
