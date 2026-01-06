import { useRef, useState, useMemo, useEffect } from 'react';
import { Group, Mesh as ThreeMesh, Color, BufferAttribute, BufferGeometry, DoubleSide, Box3, Vector3 } from 'three';
import { useFrame } from '@react-three/fiber';
import { Html, useGLTF } from '@react-three/drei';
import { Episode } from '@/hooks/useEpisodesManager';
import { calculateHeatMapData, RegionData } from './HeatMapCalculator';

interface GeometryPack {
  geometry: BufferGeometry;
  center: Vector3;
  halfExtents: Vector3;
}

// Normalize a position vector into skull-local space [-1, 1]
const normalize = (v: Vector3, center: Vector3, halfExtents: Vector3): Vector3 => {
  return new Vector3(
    (v.x - center.x) / (halfExtents.x || 1e-6),
    (v.y - center.y) / (halfExtents.y || 1e-6),
    (v.z - center.z) / (halfExtents.z || 1e-6)
  );
};

// Map normalized 3D position to anatomical region
const getRegionFromNormalized = (nx: number, ny: number, nz: number): string => {
  // Vertex (top of head)
  if (ny > 0.65) return 'vertex';
  
  // Base (lower skull)
  if (ny < -0.35) return 'base';
  
  // Frontal (forehead/front)
  if (nz > 0.45) return 'frontal';
  
  // Occipital (back of head)
  if (nz < -0.45) return 'occipital';
  
  // Temporal and Parietal regions (sides)
  if (Math.abs(nx) > 0.55) {
    if (ny < 0.25) {
      return nx > 0 ? 'rightTemporal' : 'leftTemporal';
    }
    return nx > 0 ? 'rightParietal' : 'leftParietal';
  }
  
  return 'frontal'; // Default
};

interface SkullMeshProps {
  episodes: Episode[];
}

export const SkullMesh = ({ episodes }: SkullMeshProps) => {
  const groupRef = useRef<Group>(null);
  const [hoveredRegion, setHoveredRegion] = useState<RegionData | null>(null);
  const [hoverPosition, setHoverPosition] = useState<[number, number, number]>([0, 0, 0]);
  const [geometryPacks, setGeometryPacks] = useState<GeometryPack[]>([]);

  // Load GLTF skull model
  const { scene } = useGLTF('/models/skull.glb');

  // Calculate heat map data
  const heatMapData = useMemo(() => calculateHeatMapData(episodes), [episodes]);

  // Extract and process all geometries from the GLTF scene
  useEffect(() => {
    const packs: GeometryPack[] = [];
    
    scene.traverse((child) => {
      if ((child as ThreeMesh).isMesh) {
        const mesh = child as ThreeMesh;
        const sourceGeo = mesh.geometry as BufferGeometry;
        
        // Clone and convert to non-indexed for independent vertex colors
        const geo = sourceGeo.index ? sourceGeo.toNonIndexed() : sourceGeo.clone();
        
        // Compute bounding box for normalization
        geo.computeBoundingBox();
        const boundingBox = geo.boundingBox || new Box3();
        const center = boundingBox.getCenter(new Vector3());
        const size = boundingBox.getSize(new Vector3());
        const halfExtents = size.multiplyScalar(0.5);
        
        packs.push({ geometry: geo, center, halfExtents });
      }
    });
    
    setGeometryPacks(packs);
  }, [scene]);

  // Apply vertex colors to all geometries based on heatmap
  useEffect(() => {
    geometryPacks.forEach(({ geometry, center, halfExtents }) => {
      const positions = geometry.getAttribute('position');
      const count = positions.count;
      const colors = new Float32Array(count * 3);
      const tempVec = new Vector3();

      for (let i = 0; i < count; i++) {
        tempVec.set(
          positions.getX(i),
          positions.getY(i),
          positions.getZ(i)
        );
        
        // Normalize to skull-local space
        const normalized = normalize(tempVec, center, halfExtents);
        const region = getRegionFromNormalized(normalized.x, normalized.y, normalized.z);
        const regionData = heatMapData.get(region);
        
        if (regionData && regionData.count > 0) {
          const color = new Color(regionData.color);
          colors[i * 3] = color.r;
          colors[i * 3 + 1] = color.g;
          colors[i * 3 + 2] = color.b;
        } else {
          // Default gray for no data
          colors[i * 3] = 0.3;
          colors[i * 3 + 1] = 0.3;
          colors[i * 3 + 2] = 0.3;
        }
      }
      
      geometry.setAttribute('color', new BufferAttribute(colors, 3));
      geometry.computeVertexNormals();
    });
  }, [geometryPacks, heatMapData]);

  // Handle hover interactions
  const handlePointerMove = (event: any) => {
    event.stopPropagation();
    
    if (groupRef.current && geometryPacks.length > 0) {
      const point = event.point as Vector3;
      
      // Use the first geometry pack's normalization (assuming skull is centered)
      const { center, halfExtents } = geometryPacks[0];
      const normalized = normalize(point, center, halfExtents);
      const region = getRegionFromNormalized(normalized.x, normalized.y, normalized.z);
      const regionData = heatMapData.get(region);
      
      if (regionData && regionData.count > 0) {
        setHoveredRegion(regionData);
        setHoverPosition([point.x, point.y, point.z]);
      } else {
        setHoveredRegion(null);
      }
    }
  };

  const handlePointerOut = () => {
    setHoveredRegion(null);
  };

  // Gentle idle rotation
  useFrame(() => {
    if (groupRef.current) {
      groupRef.current.rotation.y += 0.001;
    }
  });

  return (
    <group
      ref={groupRef}
      onPointerMove={handlePointerMove}
      onPointerOut={handlePointerOut}
      scale={[1.5, 1.5, 1.5]}
      position={[0, -0.5, 0]}
    >
      {geometryPacks.map(({ geometry }, idx) => (
        <group key={idx}>
          {/* Colored heat map layer */}
          <mesh geometry={geometry}>
            <meshStandardMaterial
              vertexColors={true}
              transparent={true}
              opacity={0.6}
              side={DoubleSide}
              metalness={0.1}
              roughness={0.8}
            />
          </mesh>
          
          {/* Wireframe overlay */}
          <mesh geometry={geometry}>
            <meshStandardMaterial
              wireframe={true}
              color="#ffffff"
              transparent={true}
              opacity={0.35}
              polygonOffset={true}
              polygonOffsetFactor={1}
              polygonOffsetUnits={1}
            />
          </mesh>
        </group>
      ))}
      
      {hoveredRegion && (
        <Html position={hoverPosition} style={{ pointerEvents: 'none' }}>
          <div className="bg-popover border rounded-lg p-3 shadow-lg min-w-[200px] animate-fade-in">
            <h4 className="font-semibold text-sm text-popover-foreground">
              {hoveredRegion.displayName}
            </h4>
            <div className="text-xs space-y-1 mt-2 text-muted-foreground">
              <p>Episodes: <span className="text-popover-foreground font-medium">{hoveredRegion.count}</span></p>
              <p>Frequency: <span className="text-popover-foreground font-medium">{Math.round(hoveredRegion.percentage)}%</span></p>
              <p>Avg Intensity: <span className="text-popover-foreground font-medium">{hoveredRegion.avgIntensity}/10</span></p>
            </div>
          </div>
        </Html>
      )}
    </group>
  );
};

// Preload the model
useGLTF.preload('/models/skull.glb');
