<script lang="ts" setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as THREE from 'three';
import type { BufferGeometry, Color } from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';

type HeatmapEpisode = {
    id: number | string;
    pain_location: string | null;
    intensity: number | null;
};

const REGION_LABELS = {
    frontal: 'Frontal (Forehead)',
    leftTemporal: 'Left Temporal (Side)',
    rightTemporal: 'Right Temporal (Side)',
    leftParietal: 'Left Parietal (Crown)',
    rightParietal: 'Right Parietal (Crown)',
    occipital: 'Occipital (Back)',
    vertex: 'Vertex (Top Center)',
    base: 'Base of Skull',
    face: 'Facial (Excluded)',
} as const;

type RegionKey = keyof typeof REGION_LABELS;

type RegionStat = {
    key: RegionKey;
    displayName: string;
    count: number;
    percentage: number;
    avgIntensity: number;
    color: Color;
    hex: string;
};

type GeometryPack = {
    geometry: BufferGeometry;
    mesh: THREE.Mesh<BufferGeometry, THREE.MeshStandardMaterial>;
    center: THREE.Vector3;
    halfExtents: THREE.Vector3;
};

const props = defineProps<{
    episodes: HeatmapEpisode[];
}>();

const containerRef = ref<HTMLDivElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const showDemo = ref(false);
const tooltip = ref<{ label: string; episodes: number; percentage: number; avgIntensity: number; x: number; y: number } | null>(null);

const hasRecordedData = computed(() => props.episodes.some((episode) => Boolean(episode.pain_location)));
const displayEpisodes = computed<HeatmapEpisode[]>(() => (showDemo.value ? generateDemoEpisodes() : props.episodes));
const regionStats = computed<Record<RegionKey, RegionStat>>(() => calculateRegionStats(displayEpisodes.value));
const sortedRegions = computed(() =>
    Object.values(regionStats.value)
        .sort((a, b) => b.count - a.count)
        .slice(0, 4)
);

watch(displayEpisodes, () => scheduleHeatmapUpdate(), { deep: true });
watch(regionStats, () => scheduleHeatmapUpdate(), { deep: true });

let renderer: THREE.WebGLRenderer | null = null;
let scene: THREE.Scene | null = null;
let camera: THREE.PerspectiveCamera | null = null;
let controls: OrbitControls | null = null;
let skullGroup: THREE.Group | null = null;
let animationFrame = 0;
let resizeObserver: ResizeObserver | null = null;
const geometryPacks: GeometryPack[] = [];
const heatMeshes: THREE.Mesh[] = [];
const wireframeMeshes: THREE.LineSegments[] = [];

const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();
const defaultColor = new THREE.Color(0x2f343c);
let pendingColorUpdate = false;

function toggleDemo() {
    showDemo.value = !showDemo.value;
    tooltip.value = null;
    scheduleHeatmapUpdate(true);
}

onMounted(() => {
    initThree();
});

onBeforeUnmount(() => {
    disposeThree();
});

function initThree() {
    if (!containerRef.value) {
        return;
    }

    const width = containerRef.value.clientWidth;
    const height = containerRef.value.clientHeight || 500;

    renderer = new THREE.WebGLRenderer({
        antialias: true,
        alpha: true,
        canvas: canvasRef.value ?? undefined,
    });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setSize(width, height);
    renderer.setClearColor(0x000000, 0);

    scene = new THREE.Scene();

    camera = new THREE.PerspectiveCamera(50, width / height, 0.1, 100);
    camera.position.set(0.2, 0.5, 6.4);
    scene.add(camera);

    const ambient = new THREE.AmbientLight(0xffffff, 0.7);
    scene.add(ambient);

    const keyLight = new THREE.DirectionalLight(0xffffff, 0.9);
    keyLight.position.set(4, 3, 5);
    scene.add(keyLight);

    const fillLight = new THREE.DirectionalLight(0xffffff, 0.4);
    fillLight.position.set(-5, -2, -4);
    scene.add(fillLight);

    skullGroup = new THREE.Group();
    scene.add(skullGroup);

    loadSkullModel();

    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.enablePan = false;
    controls.minDistance = 4;
    controls.maxDistance = 7.5;
    controls.target.set(0, 0.25, 0);
    controls.update();

    renderer.domElement.addEventListener('pointermove', handlePointerMove);
    renderer.domElement.addEventListener('pointerleave', handlePointerLeave);

    resizeObserver = new ResizeObserver(() => handleResize());
    resizeObserver.observe(containerRef.value);
    handleResize();

    scheduleHeatmapUpdate(true);
    animate();
}

function disposeThree() {
    if (resizeObserver && containerRef.value) {
        resizeObserver.unobserve(containerRef.value);
    }
    resizeObserver = null;

    if (animationFrame) {
        cancelAnimationFrame(animationFrame);
    }

    if (renderer) {
        renderer.domElement.removeEventListener('pointermove', handlePointerMove);
        renderer.domElement.removeEventListener('pointerleave', handlePointerLeave);
    }

    controls?.dispose();
    renderer?.dispose();
    clearSkullMeshes();

    scene = null;
    camera = null;
    controls = null;
    renderer = null;
    skullGroup = null;
}

function animate() {
    if (!renderer || !scene || !camera) {
        return;
    }
    animationFrame = requestAnimationFrame(animate);
    controls?.update();
    renderer.render(scene, camera);
}

function handleResize() {
    if (!renderer || !camera || !containerRef.value) {
        return;
    }
    const { clientWidth, clientHeight } = containerRef.value;
    renderer.setSize(clientWidth, clientHeight);
    camera.aspect = clientWidth / clientHeight;
    camera.updateProjectionMatrix();
}

function handlePointerMove(event: PointerEvent) {
    if (!renderer || !camera || !containerRef.value || !heatMeshes.length) {
        return;
    }

    const rect = renderer.domElement.getBoundingClientRect();
    pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
    pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

    raycaster.setFromCamera(pointer, camera);
    const intersects = raycaster.intersectObjects(heatMeshes, false);

    if (intersects.length === 0) {
        tooltip.value = null;
        return;
    }

    const hit = intersects[0];
    const mesh = hit.object as THREE.Mesh;
    const pack = geometryPacks.find((entry) => entry.mesh === mesh);
    if (!pack) {
        tooltip.value = null;
        return;
    }

    const localPoint = mesh.worldToLocal(hit.point.clone());
    const normalized = normalizePoint(localPoint, pack.center, pack.halfExtents);
    const region = determineRegion(normalized.x, normalized.y, normalized.z);
    if (region === 'face') {
        tooltip.value = null;
        return;
    }
    const data = regionStats.value[region];

    if (!data || data.count === 0) {
        tooltip.value = null;
        return;
    }

    const containerRect = containerRef.value.getBoundingClientRect();
    tooltip.value = {
        label: data.displayName,
        episodes: data.count,
        percentage: Math.round(data.percentage),
        avgIntensity: data.avgIntensity,
        x: event.clientX - containerRect.left,
        y: event.clientY - containerRect.top,
    };
}

function handlePointerLeave() {
    tooltip.value = null;
}

function scheduleHeatmapUpdate(immediate = false) {
    if (immediate) {
        applyHeatmapColors();
        return;
    }

    if (pendingColorUpdate) {
        return;
    }

    pendingColorUpdate = true;
    requestAnimationFrame(() => {
        applyHeatmapColors();
        pendingColorUpdate = false;
    });
}

function applyHeatmapColors() {
    if (!geometryPacks.length) {
        return;
    }

    const vector = new THREE.Vector3();

    geometryPacks.forEach((pack) => {
        const positions = pack.geometry.getAttribute('position') as THREE.BufferAttribute;
        const colors = pack.geometry.getAttribute('color') as THREE.BufferAttribute;

        for (let i = 0; i < positions.count; i += 1) {
            vector.set(positions.getX(i), positions.getY(i), positions.getZ(i));
            const normalized = normalizePoint(vector, pack.center, pack.halfExtents);
            const region = determineRegion(normalized.x, normalized.y, normalized.z);
            const stat = regionStats.value[region];
            const color = stat && stat.count > 0 ? stat.color : defaultColor;
            colors.setXYZ(i, color.r, color.g, color.b);
        }

        colors.needsUpdate = true;
    });
}

function normalizePoint(point: THREE.Vector3, center: THREE.Vector3, halfExtents: THREE.Vector3): THREE.Vector3 {
    return new THREE.Vector3(
        (point.x - center.x) / (halfExtents.x || 1e-6),
        (point.y - center.y) / (halfExtents.y || 1e-6),
        (point.z - center.z) / (halfExtents.z || 1e-6)
    );
}

function loadSkullModel() {
    if (!skullGroup) {
        return;
    }

    const loader = new GLTFLoader();
    loader.load(
        '/models/skull.glb',
        (gltf) => {
            clearSkullMeshes();
            gltf.scene.traverse((child) => {
                const mesh = child as THREE.Mesh;
                if (mesh?.isMesh && mesh.geometry) {
                    createGeometryPack(mesh.geometry as BufferGeometry);
                }
            });

            if (!geometryPacks.length) {
                buildFallbackSkull();
            } else {
                scheduleHeatmapUpdate(true);
            }
        },
        undefined,
        () => {
            buildFallbackSkull();
        }
    );
}

function buildFallbackSkull() {
    clearSkullMeshes();
    createGeometryPack(createFallbackGeometry());
    scheduleHeatmapUpdate(true);
}

function clearSkullMeshes() {
    geometryPacks.splice(0, geometryPacks.length);

    heatMeshes.splice(0, heatMeshes.length).forEach((mesh) => {
        skullGroup?.remove(mesh);
        mesh.geometry.dispose();
        (mesh.material as THREE.Material).dispose();
    });

    wireframeMeshes.splice(0, wireframeMeshes.length).forEach((wire) => {
        skullGroup?.remove(wire);
        wire.geometry.dispose();
        (wire.material as THREE.Material).dispose();
    });
}

function createGeometryPack(source: BufferGeometry) {
    if (!skullGroup) {
        return;
    }

    const geometry = source.index ? source.toNonIndexed() : source.clone();
    geometry.computeVertexNormals();
    geometry.computeBoundingBox();

    const boundingBox = geometry.boundingBox ?? new THREE.Box3();
    const center = boundingBox.getCenter(new THREE.Vector3());
    const halfExtents = boundingBox.getSize(new THREE.Vector3()).multiplyScalar(0.5);

    const positions = geometry.getAttribute('position');
    const colorAttribute = new THREE.Float32BufferAttribute(new Array(positions.count * 3).fill(0), 3);
    geometry.setAttribute('color', colorAttribute);

    const heatMaterial = new THREE.MeshStandardMaterial({
        vertexColors: true,
        transparent: true,
        opacity: 0.72,
        metalness: 0.1,
        roughness: 0.85,
    });

    const heatMesh = new THREE.Mesh(geometry, heatMaterial);
    heatMesh.castShadow = false;
    heatMesh.receiveShadow = false;
    skullGroup.add(heatMesh);

    const wireframe = new THREE.LineSegments(
        new THREE.WireframeGeometry(geometry.clone()),
        new THREE.LineBasicMaterial({
            color: 0xffffff,
            transparent: true,
            opacity: 0.3,
        })
    );
    skullGroup.add(wireframe);

    geometryPacks.push({ geometry, mesh: heatMesh, center, halfExtents });
    heatMeshes.push(heatMesh);
    wireframeMeshes.push(wireframe);
}

function createFallbackGeometry(): BufferGeometry {
    const geometry = new THREE.SphereGeometry(1, 128, 128);
    geometry.scale(0.9, 1.05, 1.2);
    geometry.rotateY(Math.PI);
    return geometry;
}

function calculateRegionStats(episodes: HeatmapEpisode[]): Record<RegionKey, RegionStat> {
    const baseCounts: Record<RegionKey, { count: number; totalIntensity: number }> = Object.keys(REGION_LABELS).reduce(
        (acc, key) => {
            acc[key as RegionKey] = { count: 0, totalIntensity: 0 };
            return acc;
        },
        {} as Record<RegionKey, { count: number; totalIntensity: number }>
    );

    episodes.forEach((episode) => {
        const targetRegions = expandPainLocation(episode.pain_location);
        if (!targetRegions.length) {
            return;
        }

        const intensity = typeof episode.intensity === 'number' ? episode.intensity : 0;
        targetRegions.forEach((region) => {
            baseCounts[region].count += 1;
            baseCounts[region].totalIntensity += intensity;
        });
    });

    const maxCount = Math.max(
        1,
        ...Object.values(baseCounts).map((entry) => entry.count)
    );

    return Object.entries(baseCounts).reduce((acc, [key, value]) => {
        const percentage = value.count > 0 ? (value.count / maxCount) * 100 : 0;
        const avgIntensity = value.count > 0 ? value.totalIntensity / value.count : 0;
        const color = getHeatColor(percentage);
        acc[key as RegionKey] = {
            key: key as RegionKey,
            displayName: REGION_LABELS[key as RegionKey],
            count: value.count,
            percentage,
            avgIntensity: Math.round(avgIntensity * 10) / 10,
            color,
            hex: `#${color.getHexString()}`,
        };
        return acc;
    }, {} as Record<RegionKey, RegionStat>);
}

function expandPainLocation(value: string | null): RegionKey[] {
    if (!value) {
        return [];
    }

    const trimmed = value.trim();
    if (!trimmed) {
        return [];
    }

    const normalized = trimmed.toLowerCase();

    if (normalized.startsWith('{') || normalized.startsWith('[')) {
        try {
            const parsed = JSON.parse(trimmed);
            return normalizeStructuredLocation(parsed);
        } catch {
            // Fall through to simple text mapping
        }
    }

    const sanitized = normalized.replace(/[\\[\\]\\(\\)]/g, ' ');
    const hasLeft = /\\bleft\\b/.test(sanitized) || /\\b(l)\\b/.test(sanitized);
    const hasRight = /\\bright\\b/.test(sanitized) || /\\b(r)\\b/.test(sanitized);
    const hasBothSides = /\\bboth\\b/.test(sanitized) || /\\bbilateral\\b/.test(sanitized) || sanitized.includes('sides');

    const mentionsVertex = /vertex/.test(sanitized) || sanitized.includes('top of the head') || sanitized.includes('top of head') || (/\\btop\\b/.test(sanitized) && /\\bhead\\b/.test(sanitized)) || sanitized === 'top';
    const mentionsTemporal = /temporal/.test(sanitized) || /temple/.test(sanitized) || /\\bside/.test(sanitized);
    const mentionsParietal = /parietal/.test(sanitized) || /crown/.test(sanitized);
    const mentionsFrontal = /frontal/.test(sanitized) || /forehead/.test(sanitized) || /\\bfront\\b/.test(sanitized);
    const mentionsOccipital = /occipital/.test(sanitized) || sanitized.includes('back of head') || /\\bback\\b/.test(sanitized) || /rear/.test(sanitized);
    const mentionsBase = sanitized.includes('base of skull') || /\\bbase\\b/.test(sanitized) || /\\bneck\\b/.test(sanitized);

    const regions = new Set<RegionKey>();

    const addSideRegions = (left: RegionKey, right: RegionKey) => {
        if (hasLeft) {
            regions.add(left);
        }
        if (hasRight) {
            regions.add(right);
        }
        if ((!hasLeft && !hasRight && !hasBothSides) || hasBothSides) {
            regions.add(left);
            regions.add(right);
        }
    };

    if (mentionsVertex) {
        regions.add('vertex');
    }

    if (mentionsTemporal) {
        addSideRegions('leftTemporal', 'rightTemporal');
    }

    if (mentionsParietal) {
        addSideRegions('leftParietal', 'rightParietal');
    }

    if (mentionsFrontal) {
        regions.add('frontal');
    }

    if (mentionsOccipital || mentionsBase) {
        regions.add('occipital');
    }

    if (mentionsBase || normalized.includes('base of skull') || normalized.includes('skull base')) {
        regions.add('base');
    }

    if (regions.size) {
        return Array.from(regions);
    }

    if (normalized === 'left') {
        return ['leftTemporal', 'leftParietal'];
    }
    if (normalized === 'right') {
        return ['rightTemporal', 'rightParietal'];
    }
    if (normalized === 'bilateral') {
        return ['leftTemporal', 'rightTemporal', 'leftParietal', 'rightParietal'];
    }
    if (normalized === 'frontal') {
        return ['frontal'];
    }
    if (normalized === 'occipital') {
        return ['occipital', 'base'];
    }
    if (normalized === 'other') {
        return ['vertex'];
    }

    return [];
}

function normalizeStructuredLocation(payload: unknown): RegionKey[] {
    if (!payload || typeof payload !== 'object') {
        return [];
    }

    const result = new Set<RegionKey>();
    const maybeDetailed = (payload as { detailed?: Record<string, boolean>; regions?: string[]; allOver?: boolean }) ?? {};

    if (maybeDetailed.allOver) {
        Object.keys(REGION_LABELS).forEach((key) => result.add(key as RegionKey));
    }

    if (maybeDetailed.detailed) {
        Object.entries(maybeDetailed.detailed).forEach(([region, hasValue]) => {
            if (hasValue && region in REGION_LABELS) {
                result.add(region as RegionKey);
            }
        });
    }

    if (Array.isArray(maybeDetailed.regions)) {
        maybeDetailed.regions.forEach((region) => {
            expandPainLocation(region as string).forEach((entry) => result.add(entry));
        });
    }

    return Array.from(result);
}

function getHeatColor(percentage: number): Color {
    if (percentage >= 75) return new THREE.Color(0xff0000);
    if (percentage >= 50) return new THREE.Color(0xff3300);
    if (percentage >= 25) return new THREE.Color(0xff6600);
    if (percentage >= 10) return new THREE.Color(0xff9900);
    return new THREE.Color(0xffcc00);
}

function determineRegion(nx: number, ny: number, nz: number): RegionKey {
    const absX = Math.abs(nx);
    const isLeft = nx < 0;  // Left is negative X

    // Vertex (top of head) - ONLY the very highest Y values (crown/top center)
    if (ny >= 0.70) {
        return 'vertex';
    }

    // Base of skull (bottom/neck area) - lowest Y values
    if (ny <= -0.25) {
        return 'base';
    }

    // FACE EXCLUSION - ALL areas below eye level
    // This includes: orbital, peri-orbital, retro-orbital, nose, cheek, jaw, teeth
    // CRITICAL: No heatmap below eyes
    const isFaceRegion = nz >= 0.15 && ny <= 0.30;
    if (isFaceRegion) {
        return 'face';
    }

    // OCCIPITAL (BACK of head) - NEGATIVE Z values
    // Back of head region - should be large area
    if (nz <= -0.25) {
        // Pure back of head region
        if (absX <= 0.45) {
            return ny <= -0.08 ? 'base' : 'occipital';
        }
        // Back sides - still occipital if not too high
        if (ny <= 0.25) {
            return 'occipital';
        }
    }

    // PARIETAL - Upper sides and upper-back (crown area)
    // Top sides of the head, smaller region than temporal
    const isUpperParietal = ny >= 0.50 && absX >= 0.30;
    if (isUpperParietal) {
        return isLeft ? 'leftParietal' : 'rightParietal';
    }

    // TEMPORAL - LARGE side regions (around ear area)
    // This is the PRIMARY side region - should be large like in reference (63.5%)
    const isTemporalSide = absX >= 0.30;
    const isTemporalHeight = ny >= 0.05 && ny <= 0.50;
    
    if (isTemporalSide && isTemporalHeight) {
        // Avoid very front (face) and very back (occipital) at this height
        if (nz >= -0.20 && nz <= 0.10) {
            return isLeft ? 'leftTemporal' : 'rightTemporal';
        }
    }

    // Extended temporal for wider coverage
    if (absX >= 0.35 && ny >= 0.00 && ny <= 0.55) {
        // Back temporal transitions
        if (nz <= -0.15 && ny <= 0.20) {
            return 'occipital';
        }
        // Front temporal - avoid face zone
        if (nz <= 0.10) {
            return isLeft ? 'leftTemporal' : 'rightTemporal';
        }
    }

    // FRONTAL (FOREHEAD) - Front upper area ABOVE eyes
    // Must be clearly above eye level (ny >= 0.35)
    if (nz >= 0.15 && ny >= 0.35) {
        if (absX <= 0.40) {
            return 'frontal';
        }
    }

    // Central very upper area defaults to vertex or parietal
    if (ny >= 0.60) {
        return absX >= 0.25 ? (isLeft ? 'leftParietal' : 'rightParietal') : 'vertex';
    }

    // Central back area defaults to occipital
    if (nz <= -0.20 && absX <= 0.35) {
        return 'occipital';
    }

    // Front-facing upper areas default to frontal
    if (nz >= 0.10 && ny >= 0.35 && absX <= 0.35) {
        return 'frontal';
    }

    // Default fallback based on position
    if (ny >= 0.20) {
        // High areas
        if (absX >= 0.30) {
            return isLeft ? 'leftTemporal' : 'rightTemporal';
        }
        return nz >= 0.10 ? 'frontal' : 'occipital';
    }

    return 'base';
}

function generateDemoEpisodes(): HeatmapEpisode[] {
    return [
        { id: 'demo-1', pain_location: 'frontal', intensity: 8 },
        { id: 'demo-2', pain_location: 'frontal', intensity: 7 },
        { id: 'demo-3', pain_location: 'frontal', intensity: 9 },
        { id: 'demo-4', pain_location: 'left', intensity: 6 },
        { id: 'demo-5', pain_location: 'left', intensity: 7 },
        { id: 'demo-6', pain_location: 'right', intensity: 5 },
        { id: 'demo-7', pain_location: 'right', intensity: 6 },
        { id: 'demo-8', pain_location: 'occipital', intensity: 8 },
        { id: 'demo-9', pain_location: 'occipital', intensity: 7 },
        { id: 'demo-10', pain_location: 'bilateral', intensity: 6 },
        { id: 'demo-11', pain_location: 'bilateral', intensity: 7 },
        { id: 'demo-12', pain_location: 'frontal', intensity: 8 },
    ];
}
</script>

<template>
    <article class="analytics-panel analytics-panel--wide">
        <header class="analytics-panel-header">
            <div>
                <h3>3D Pain Location Heat Map</h3>
                <p>Interactive visualization showing where you experience pain most often.</p>
            </div>
            <!-- <button type="button" class="analytics-outline-button" @click="toggleDemo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-4" fill="none">
                    <path
                        d="M12 5v14m7-7H5"
                        :stroke="showDemo ? '#f87171' : 'currentColor'"
                        stroke-width="1.5"
                        stroke-linecap="round"
                    />
                </svg>
                {{ showDemo ? 'Clear Demo' : 'View Demo Heat Map' }}
            </button> -->
        </header>

        <div v-if="!hasRecordedData && !showDemo" class="analytics-heatmap-placeholder">
            <p>No pain location data yet</p>
            <span>Log episodes with detailed locations to unlock your personal heat map.</span>
        </div>

        <div v-else class="analytics-heatmap-3d">
            <div ref="containerRef" class="heatmap-viewer">
                <canvas ref="canvasRef" class="heatmap-canvas"></canvas>
                <div
                    v-if="tooltip"
                    class="heatmap-tooltip"
                    :style="{ left: `${tooltip.x}px`, top: `${tooltip.y}px` }"
                >
                    <p class="heatmap-tooltip-title">{{ tooltip.label }}</p>
                    <p class="heatmap-tooltip-line">{{ tooltip.episodes }} episode{{ tooltip.episodes === 1 ? '' : 's' }}</p>
                    <p class="heatmap-tooltip-line">Frequency: {{ tooltip.percentage }}%</p>
                    <p class="heatmap-tooltip-line">Avg intensity: {{ tooltip.avgIntensity }}/10</p>
                </div>
                <div v-if="showDemo" class="heatmap-demo-pill">
                    Viewing demo data
                </div>
            </div>
            <div class="heatmap-meta">
                <p class="heatmap-meta-title">Top regions</p>
                <ul class="heatmap-meta-list">
                    <li v-for="region in sortedRegions" :key="region.key" class="heatmap-meta-item">
                        <span class="heatmap-meta-dot" :style="{ backgroundColor: region.hex }"></span>
                        <div>
                            <p class="heatmap-meta-label">{{ region.displayName }}</p>
                            <p class="heatmap-meta-subtitle">
                                {{ region.count }} episode{{ region.count === 1 ? '' : 's' }} 
                                <!-- •
                                {{ Math.round(region.percentage) }}%  -->
                            </p>
                        </div>
                    </li>
                </ul>
                <p class="heatmap-meta-hint">🖱️ Drag to rotate • 🔍 Scroll to zoom • 👆 Hover to inspect</p>
            </div>
        </div>
    </article>
</template>

<style scoped>
.analytics-heatmap-3d {
    display: grid;
    gap: 1.25rem;
}

@media (min-width: 1024px) {
    .analytics-heatmap-3d {
        grid-template-columns: minmax(0, 2.5fr) minmax(0, 1fr);
        align-items: stretch;
    }
}

.heatmap-viewer {
    position: relative;
    width: 100%;
    height: 420px;
    border-radius: 1.25rem;
    background: radial-gradient(circle at top, rgba(255, 255, 255, 0.08), rgba(12, 18, 24, 0.9));
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.heatmap-canvas {
    width: 100%;
    height: 100%;
    display: block;
}

.heatmap-tooltip {
    position: absolute;
    background: rgba(6, 10, 14, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 0.75rem;
    padding: 0.75rem;
    min-width: 180px;
    pointer-events: none;
    transform: translate(12px, -12px);
    box-shadow: 0 8px 20px rgba(5, 5, 5, 0.4);
}

.heatmap-tooltip-title {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 0.35rem;
}

.heatmap-tooltip-line {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.7);
    margin: 0.1rem 0;
}

.heatmap-demo-pill {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(250, 204, 21, 0.12);
    color: #facc15;
    border: 1px solid rgba(250, 204, 21, 0.3);
    border-radius: 999px;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.heatmap-meta {
    background: rgba(11, 17, 24, 0.7);
    border-radius: 1.25rem;
    border: 1px solid rgba(255, 255, 255, 0.04);
    padding: 1.25rem;
}

.heatmap-meta-title {
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.heatmap-meta-list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.heatmap-meta-item {
    display: flex;
    gap: 0.65rem;
    align-items: flex-start;
}

.heatmap-meta-dot {
    width: 0.65rem;
    height: 0.65rem;
    border-radius: 999px;
    margin-top: 0.2rem;
}

.heatmap-meta-label {
    font-size: 0.85rem;
}

.heatmap-meta-subtitle {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.65);
}

.heatmap-meta-hint {
    font-size: 0.75rem;
    margin-top: 1rem;
    color: rgba(255, 255, 255, 0.55);
}
</style>
