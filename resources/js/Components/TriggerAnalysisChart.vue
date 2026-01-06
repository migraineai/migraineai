<script lang="ts" setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type BreakdownEntry = {
    label: string;
    count: number;
    color: string;
};

const props = defineProps<{
    breakdown: BreakdownEntry[];
    total: number;
    label?: string;
    subtitle?: string;
}>();

const canvas = ref<HTMLCanvasElement | null>(null);
const canvasWrapper = ref<HTMLDivElement | null>(null);
const tooltip = ref<{ label: string; count: number; percent: number; x: number; y: number } | null>(null);
const segments = ref<
    Array<{
        start: number;
        end: number;
        entry: BreakdownEntry;
        percent: number;
    }>
>([]);
const hasData = computed(() => props.breakdown.length > 0 && props.total > 0);
const legendEntries = computed(() =>
    props.breakdown.map((entry) => ({
        ...entry,
        percent: props.total ? (entry.count / props.total) * 100 : 0,
    }))
);
let resizeObserver: ResizeObserver | null = null;

const RING_WIDTH = 34;
const START_OFFSET = -Math.PI / 2;

function getCanvasSize(): number | null {
    if (!canvasWrapper.value) {
        return null;
    }

    const rect = canvasWrapper.value.getBoundingClientRect();
    const available = Math.min(rect.width, rect.height);
    if (!available) {
        return null;
    }
    return available;
}

function drawTriggerChart() {
    if (!canvas.value || !canvasWrapper.value) {
        return;
    }

    const size = getCanvasSize();
    if (!size) {
        return;
    }

    const ratio = window.devicePixelRatio || 1;
    const canvasWidth = size * ratio;
    canvas.value.width = canvasWidth;
    canvas.value.height = canvasWidth;
    canvas.value.style.width = `${size}px`;
    canvas.value.style.height = `${size}px`;

    const ctx = canvas.value.getContext('2d');
    if (!ctx) {
        return;
    }

    ctx.clearRect(0, 0, canvasWidth, canvasWidth);

    if (!props.total) {
        segments.value = [];
        return;
    }

    const radius = (canvasWidth - RING_WIDTH * ratio) / 2;
    const center = canvasWidth / 2;
    const lineWidth = RING_WIDTH * ratio;

    ctx.lineWidth = lineWidth;
    ctx.lineCap = 'round';
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.08)';
    ctx.beginPath();
    ctx.arc(center, center, radius, 0, Math.PI * 2);
    ctx.stroke();

    const newSegments: typeof segments.value = [];
    let currentNormalized = 0;

    props.breakdown.forEach((entry) => {
        if (!entry.count) {
            return;
        }
        const portion = entry.count / props.total;
        const percent = portion * 100;
        const sweep = portion * Math.PI * 2;
        const startAngle = START_OFFSET + currentNormalized;
        const endAngle = startAngle + sweep;

        ctx.strokeStyle = entry.color;
        ctx.beginPath();
        ctx.arc(center, center, radius, startAngle, endAngle);
        ctx.stroke();

        newSegments.push({
            start: currentNormalized,
            end: currentNormalized + sweep,
            entry,
            percent,
        });

        currentNormalized += sweep;
    });

    segments.value = newSegments;
}

function handlePointerMove(event: PointerEvent) {
    if (!canvas.value || !segments.value.length || !canvasWrapper.value) {
        tooltip.value = null;
        return;
    }

    const rect = canvas.value.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const dx = x - centerX;
    const dy = y - centerY;
    const distance = Math.sqrt(dx * dx + dy * dy);
    const outerRadius = rect.width / 2;
    const innerRadius = outerRadius - RING_WIDTH;

    if (distance < innerRadius || distance > outerRadius) {
        tooltip.value = null;
        return;
    }

    const angle = Math.atan2(dy, dx);
    const normalized = (angle - START_OFFSET + Math.PI * 2) % (Math.PI * 2);

    const match = segments.value.find((segment, index) => {
        if (index === segments.value.length - 1) {
            return normalized >= segment.start && normalized <= segment.end + 0.00001;
        }
        return normalized >= segment.start && normalized < segment.end;
    });

    if (!match) {
        tooltip.value = null;
        return;
    }

    tooltip.value = {
        label: match.entry.label,
        count: match.entry.count,
        percent: Math.round(match.percent),
        x: x + 8,
        y: y - 8,
    };
}

function handlePointerLeave() {
    tooltip.value = null;
}

function setupObservers() {
    if (!canvasWrapper.value) {
        return;
    }
    resizeObserver?.disconnect();
    resizeObserver = new ResizeObserver(() => {
        drawTriggerChart();
    });
    resizeObserver.observe(canvasWrapper.value);
}

onMounted(() => {
    setupObservers();
    drawTriggerChart();
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    resizeObserver = null;
});

watch(
    () => props.breakdown,
    () => {
        drawTriggerChart();
    },
    { deep: true }
);

watch(
    () => props.total,
    () => {
        drawTriggerChart();
    }
);
</script>

<template>
    <div class="trigger-chart-wrapper">
        <div class="trigger-chart-header">
            <p class="trigger-chart-label">{{ label ?? 'Trigger Breakdown' }}</p>
        </div>
        <div v-if="hasData" class="trigger-chart-body">
            <div
                class="trigger-chart-canvas-wrapper"
                ref="canvasWrapper"
                @pointermove="handlePointerMove"
                @pointerleave="handlePointerLeave"
            >
                <canvas ref="canvas" class="trigger-chart-canvas"></canvas>
                <div class="trigger-chart-center">
                    <p class="trigger-chart-total">{{ total }}</p>
                    <p class="trigger-chart-subtitle">{{ subtitle ?? 'trigger mentions' }}</p>
                </div>
                <div
                    v-if="tooltip"
                    class="trigger-chart-tooltip"
                    :style="{ left: `${tooltip.x}px`, top: `${tooltip.y}px` }"
                >
                    <p class="trigger-chart-tooltip-label">{{ tooltip.label }}</p>
                    <p class="trigger-chart-tooltip-value">{{ tooltip.count }} • {{ tooltip.percent }}%</p>
                </div>
            </div>
            <ul class="trigger-chart-legend">
                <li v-for="item in legendEntries" :key="item.label" class="trigger-chart-legend-item">
                    <span
                        class="trigger-chart-legend-dot"
                        :style="{ backgroundColor: item.color || '#7dc2a3' }"
                    ></span>
                    <div>
                        <p class="trigger-chart-legend-name">{{ item.label }}</p>
                        <p class="trigger-chart-legend-meta">{{ item.count }} • {{ Math.round(item.percent) }}%</p>
                    </div>
                </li>
            </ul>
        </div>
        <div v-else class="trigger-chart-empty">
            <div class="analysis-donut-ring">
                <span>Log more triggers</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.trigger-chart-wrapper {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.trigger-chart-header {
    text-align: left;
}

.trigger-chart-label {
    margin: 0;
    font-size: 0.95rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(201, 211, 206, 0.7);
}

.trigger-chart-body {
    display: grid;
    gap: 24px;
    grid-template-columns: minmax(260px, 42%) minmax(220px, 58%);
    align-items: center;
}

.trigger-chart-canvas-wrapper {
    position: relative;
    width: min(320px, 100%);
    aspect-ratio: 1 / 1;
    display: grid;
    place-items: center;
}

.trigger-chart-canvas {
    width: 100%;
    height: 100%;
    display: block;
}

.trigger-chart-center {
    position: absolute;
    width: 50%;
    aspect-ratio: 1 / 1;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(5, 8, 10, 0.65), rgba(5, 8, 10, 0.85));
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    text-align: center;
    color: rgba(201, 211, 206, 0.75);
}

.trigger-chart-total {
    font-size: 2.3rem;
    font-weight: 700;
    margin: 0;
    color: var(--color-text-primary);
}

.trigger-chart-subtitle {
    margin: 0;
    font-size: 0.7rem;
    letter-spacing: 0.25em;
    text-transform: uppercase;
}

.trigger-chart-tooltip {
    position: absolute;
    top: 0;
    left: 0;
    transform: translate(-50%, -100%);
    background: rgba(6, 10, 14, 0.92);
    border-radius: 10px;
    padding: 0.6rem 0.85rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.35);
    pointer-events: none;
    min-width: 140px;
}

.trigger-chart-tooltip-label {
    margin: 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
}

.trigger-chart-tooltip-value {
    margin: 4px 0 0;
    font-size: 0.8rem;
    color: rgba(148, 248, 213, 0.95);
}

.trigger-chart-legend {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.trigger-chart-legend-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.trigger-chart-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    box-shadow: 0 0 12px rgba(0, 0, 0, 0.25);
}

.trigger-chart-legend-name {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.85);
}

.trigger-chart-legend-meta {
    margin: 0;
    font-size: 0.78rem;
    color: rgba(148, 248, 213, 0.85);
}

.trigger-chart-empty {
    display: flex;
    justify-content: center;
    padding-top: 8px;
}

@media (max-width: 1024px) {
    .trigger-chart-body {
        grid-template-columns: minmax(260px, 1fr);
    }
}

@media (max-width: 640px) {
    .trigger-chart-body {
        grid-template-columns: 1fr;
    }

    .trigger-chart-wrapper {
        gap: 20px;
    }
}
</style>
