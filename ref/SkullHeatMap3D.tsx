import { Suspense, useState } from 'react';
import { Canvas } from '@react-three/fiber';
import { OrbitControls, PerspectiveCamera } from '@react-three/drei';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Episode } from '@/hooks/useEpisodesManager';
import { SkullMesh } from './skull/SkullMesh';
import { Loader2, Sparkles } from 'lucide-react';

interface SkullHeatMap3DProps {
  episodes: Episode[];
}

// Generate test episodes for demonstration
const generateTestEpisodes = (): Episode[] => {
  return [
    { id: 'test-1', pain_location: { regions: ['frontal'], allOver: false, detailed: { frontal: true } }, intensity: 8, date: new Date().toISOString() },
    { id: 'test-2', pain_location: { regions: ['frontal'], allOver: false, detailed: { frontal: true } }, intensity: 7, date: new Date().toISOString() },
    { id: 'test-3', pain_location: { regions: ['frontal'], allOver: false, detailed: { frontal: true } }, intensity: 9, date: new Date().toISOString() },
    { id: 'test-4', pain_location: { regions: ['leftTemporal'], allOver: false, detailed: { leftTemporal: true } }, intensity: 6, date: new Date().toISOString() },
    { id: 'test-5', pain_location: { regions: ['leftTemporal'], allOver: false, detailed: { leftTemporal: true } }, intensity: 7, date: new Date().toISOString() },
    { id: 'test-6', pain_location: { regions: ['leftTemporal'], allOver: false, detailed: { leftTemporal: true } }, intensity: 8, date: new Date().toISOString() },
    { id: 'test-7', pain_location: { regions: ['rightParietal'], allOver: false, detailed: { rightParietal: true } }, intensity: 5, date: new Date().toISOString() },
    { id: 'test-8', pain_location: { regions: ['rightParietal'], allOver: false, detailed: { rightParietal: true } }, intensity: 6, date: new Date().toISOString() },
    { id: 'test-9', pain_location: { regions: ['occipital'], allOver: false, detailed: { occipital: true } }, intensity: 8, date: new Date().toISOString() },
    { id: 'test-10', pain_location: { regions: ['occipital'], allOver: false, detailed: { occipital: true } }, intensity: 7, date: new Date().toISOString() },
    { id: 'test-11', pain_location: { regions: ['rightTemporal'], allOver: false, detailed: { rightTemporal: true } }, intensity: 6, date: new Date().toISOString() },
    { id: 'test-12', pain_location: { regions: ['vertex'], allOver: false, detailed: { vertex: true } }, intensity: 9, date: new Date().toISOString() },
    { id: 'test-13', pain_location: { regions: ['leftParietal'], allOver: false, detailed: { leftParietal: true } }, intensity: 7, date: new Date().toISOString() },
  ] as Episode[];
};

export const SkullHeatMap3D = ({ episodes }: SkullHeatMap3DProps) => {
  const [showTestData, setShowTestData] = useState(false);
  const hasData = episodes.some(ep => ep.pain_location?.detailed && Object.keys(ep.pain_location.detailed).length > 0);
  const displayEpisodes = showTestData ? generateTestEpisodes() : episodes;

  return (
    <Card className="w-full">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          3D Pain Location Heat Map
        </CardTitle>
        <CardDescription>
          Interactive visualization showing pain frequency across skull regions. 
          Drag to rotate, scroll to zoom, hover for details.
        </CardDescription>
      </CardHeader>
      <CardContent>
        {!hasData && !showTestData ? (
          <div className="flex flex-col items-center justify-center h-[500px] text-center space-y-4">
            <div className="text-muted-foreground">
              <p className="text-lg font-medium">No Pain Location Data Yet</p>
              <p className="text-sm mt-2">
                Log episodes with detailed anatomical locations to see your personal heat map
              </p>
            </div>
            {/* <Button onClick={() => setShowTestData(true)} variant="outline" className="gap-2">
              <Sparkles className="h-4 w-4" />
              View Demo Heat Map
            </Button> */}
          </div>
        ) : (
          <div className="space-y-4">
            {showTestData && (
              <div className="p-3 bg-muted/50 rounded-lg flex items-center justify-between">
                {/* <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Sparkles className="h-4 w-4" />
                  <span>Viewing demo data</span>
                </div>
                <Button onClick={() => setShowTestData(false)} variant="ghost" size="sm">
                  Clear Demo
                </Button> */}
              </div>
            )}
            <div className="h-[500px] w-full rounded-lg border bg-muted/20">
              <Suspense fallback={<LoadingFallback />}>
                <Canvas>
                  <PerspectiveCamera makeDefault position={[0, 0, 6]} fov={50} />
                  
                  {/* Lighting */}
                  <ambientLight intensity={0.5} />
                  <directionalLight position={[5, 5, 5]} intensity={1} />
                  <directionalLight position={[-5, -5, -5]} intensity={0.5} />
                  <pointLight position={[0, 0, 5]} intensity={0.3} />
                  
                  {/* Controls */}
                  <OrbitControls 
                    enableZoom={true}
                    enablePan={true}
                    enableDamping
                    dampingFactor={0.05}
                    minDistance={3}
                    maxDistance={10}
                    autoRotate={false}
                  />
                  
                  {/* Skull with heat map */}
                  <SkullMesh episodes={displayEpisodes} />
                </Canvas>
              </Suspense>
            </div>
            
            {/* Legend */}
            <div className="flex items-center justify-center gap-6 text-sm">
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 rounded" style={{ backgroundColor: '#ff0000' }} />
                <span className="text-muted-foreground">High (75-100%)</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 rounded" style={{ backgroundColor: '#ff6600' }} />
                <span className="text-muted-foreground">Medium (25-75%)</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 rounded" style={{ backgroundColor: '#ffcc00' }} />
                <span className="text-muted-foreground">Low (&lt;25%)</span>
              </div>
            </div>
            
            {/* Instructions */}
            <p className="text-xs text-center text-muted-foreground">
              🖱️ Drag to rotate • 🔍 Scroll to zoom • 👆 Hover regions for details
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  );
};

const LoadingFallback = () => (
  <div className="flex items-center justify-center h-full">
    <Loader2 className="h-8 w-8 animate-spin text-primary" />
  </div>
);
