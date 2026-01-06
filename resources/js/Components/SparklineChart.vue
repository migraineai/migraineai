<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        points: Array<Record<string, unknown>>;
        valueKey: string;
        stroke?: string;
    }>(),
    {
        stroke: '#5F9E86',
    }
);

const wrapper = ref<HTMLDivElement | null>(null);
const hoverIndex = ref<number | null>(null);
const hoverPos = ref<{ x: number; y: number } | null>(null);

const normalizedPoints = computed(() => {
    const safePoints = Array.isArray(props.points) ? props.points.filter((p) => p && typeof p === 'object') : [];
    if (!safePoints.length) {
        return [];
    }

    const values = safePoints
        .map((point) => {
            const raw = (point as Record<string, unknown>)[props.valueKey];
            return typeof raw === 'number' ? raw : null;
        })
        .filter((value): value is number => value !== null);

    const max = values.length ? Math.max(...values, 1) : 1;

    return safePoints.map((point, index) => {
        const raw = (point as Record<string, unknown>)[props.valueKey];
        const value = typeof raw === 'number' ? raw : 0;
        const x = (index / Math.max(safePoints.length - 1, 1)) * 100;
        const y = 100 - (value / max) * 100;
        return `${x},${y}`;
    });
});

const coords = computed(() =>
    normalizedPoints.value.map((p) => {
        const [x, y] = p.split(',').map(Number);
        return { x, y };
    })
);

function nearestIndex(clientX: number) {
    if (!wrapper.value || !coords.value.length) return null;
    const rect = wrapper.value.getBoundingClientRect();
    const percentX = ((clientX - rect.left) / rect.width) * 100;
    let bestIdx = 0;
    let bestDist = Infinity;
    coords.value.forEach((pt, i) => {
        const d = Math.abs(pt.x - percentX);
        if (d < bestDist) {
            bestDist = d;
            bestIdx = i;
        }
    });
    return bestIdx;
}

function handlePointerMove(e: PointerEvent) {
    const idx = nearestIndex(e.clientX);
    if (idx === null) {
        hoverIndex.value = null;
        hoverPos.value = null;
        return;
    }
    hoverIndex.value = idx;
    const pt = coords.value[idx];
    hoverPos.value = { x: pt.x, y: pt.y };
}

function handlePointerLeave() {
    hoverIndex.value = null;
    hoverPos.value = null;
}

onMounted(() => {
    // noop; wrapper ref is used for pointer calc
});
</script>

<template>
    <div
        ref="wrapper"
        class="sparkline-wrapper"
        @pointermove="handlePointerMove"
        @pointerleave="handlePointerLeave"
    >
        <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-16 w-full">
            <polyline
                v-if="normalizedPoints.length"
                :points="normalizedPoints.join(' ')"
                fill="none"
                :stroke="stroke"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
            <line v-else x1="0" y1="50" x2="100" y2="50" stroke="rgba(255,255,255,0.2)" stroke-width="2" />

            <line
                v-if="hoverPos"
                :x1="hoverPos.x"
                y1="8"
                :x2="hoverPos.x"
                y2="92"
                stroke="rgba(95,158,134,0.35)"
                stroke-width="0.6"
            />
            <circle
                v-if="hoverIndex !== null && coords[hoverIndex]"
                :cx="coords[hoverIndex].x"
                :cy="coords[hoverIndex].y"
                r="2"
                :fill="stroke"
                stroke="rgba(20,28,24,0.95)"
                stroke-width="0.6"
            />
        </svg>
        <div
            v-if="hoverIndex !== null && hoverPos"
            class="sparkline-tooltip"
            :style="{ left: `calc(${hoverPos.x}% + 8px)` }"
        >
            <span class="sparkline-tooltip-value">
                {{
                    points[hoverIndex] && typeof (points[hoverIndex] as Record<string, unknown>)[valueKey] === 'number'
                        ? (points[hoverIndex] as Record<string, unknown>)[valueKey]
                        : 'N/A'
                }}
            </span>
            <span class="sparkline-tooltip-date">
                {{ points[hoverIndex] && (points[hoverIndex] as Record<string, unknown>).date ? (points[hoverIndex] as Record<string, unknown>).date : '' }}
            </span>
        </div>
    </div>
</template>

<style scoped>
.sparkline-wrapper {
    position: relative;
}
.sparkline-tooltip {
    position: absolute;
    top: 6px;
    transform: translateX(-50%);
    display: inline-flex;
    flex-direction: column;
    gap: 2px;
    padding: 6px 8px;
    border-radius: 10px;
    background: rgba(18, 24, 22, 0.92);
    border: 1px solid rgba(95, 158, 134, 0.35);
    color: var(--color-text-primary);
    font-size: 0.75rem;
    pointer-events: none;
    box-shadow: 0 10px 26px -16px rgba(0, 0, 0, 0.55);
}
.sparkline-tooltip-value {
    font-weight: 600;
    color: #dff5ec;
}
.sparkline-tooltip-date {
    color: rgba(201, 211, 206, 0.7);
}
</style>
