import { Episode } from '@/hooks/useEpisodesManager';

export interface RegionData {
  region: string;
  displayName: string;
  count: number;
  percentage: number;
  avgIntensity: number;
  color: number;
  opacity: number;
}

export const ANATOMICAL_REGIONS = {
  frontal: 'Frontal (Forehead)',
  leftTemporal: 'Left Temporal (Side)',
  rightTemporal: 'Right Temporal (Side)',
  leftParietal: 'Left Parietal (Top-Left)',
  rightParietal: 'Right Parietal (Top-Right)',
  occipital: 'Occipital (Back)',
  vertex: 'Vertex (Crown)',
  base: 'Base of Skull',
};

export const calculateHeatMapData = (episodes: Episode[]): Map<string, RegionData> => {
  const regionCounts = new Map<string, { count: number; totalIntensity: number }>();
  
  // Initialize all regions
  Object.keys(ANATOMICAL_REGIONS).forEach(region => {
    regionCounts.set(region, { count: 0, totalIntensity: 0 });
  });

  // Count episodes per region
  episodes.forEach(episode => {
    if (!episode.pain_location) return;

    const painLoc = typeof episode.pain_location === 'string' 
      ? JSON.parse(episode.pain_location) 
      : episode.pain_location;

    // Handle detailed regions
    if (painLoc.detailed) {
      Object.entries(painLoc.detailed).forEach(([region, hasIt]) => {
        if (hasIt && regionCounts.has(region)) {
          const data = regionCounts.get(region)!;
          data.count += 1;
          data.totalIntensity += episode.intensity || 0;
        }
      });
    }
    
    // Fallback: Map simple regions to detailed
    if (painLoc.regions && Array.isArray(painLoc.regions)) {
      painLoc.regions.forEach((simpleRegion: string) => {
        const mapped = mapSimpleToDetailed(simpleRegion);
        mapped.forEach(detailedRegion => {
          if (regionCounts.has(detailedRegion)) {
            const data = regionCounts.get(detailedRegion)!;
            data.count += 1;
            data.totalIntensity += episode.intensity || 0;
          }
        });
      });
    }
  });

  // Calculate percentages and colors
  const maxCount = Math.max(...Array.from(regionCounts.values()).map(d => d.count), 1);
  const result = new Map<string, RegionData>();

  regionCounts.forEach((data, region) => {
    const percentage = maxCount > 0 ? (data.count / maxCount) * 100 : 0;
    const avgIntensity = data.count > 0 ? data.totalIntensity / data.count : 0;
    
    result.set(region, {
      region,
      displayName: ANATOMICAL_REGIONS[region as keyof typeof ANATOMICAL_REGIONS],
      count: data.count,
      percentage,
      avgIntensity: Math.round(avgIntensity * 10) / 10,
      color: getHeatColor(percentage),
      opacity: getHeatOpacity(percentage),
    });
  });

  return result;
};

const mapSimpleToDetailed = (simpleRegion: string): string[] => {
  const mapping: Record<string, string[]> = {
    front: ['frontal'],
    back: ['occipital', 'base'],
    left: ['leftTemporal', 'leftParietal'],
    right: ['rightTemporal', 'rightParietal'],
  };
  return mapping[simpleRegion] || [];
};

const getHeatColor = (percentage: number): number => {
  // Red (high) to Yellow (low) gradient
  if (percentage >= 75) return 0xff0000; // Bright red
  if (percentage >= 50) return 0xff3300; // Red-orange
  if (percentage >= 25) return 0xff6600; // Orange
  if (percentage >= 10) return 0xff9900; // Orange-yellow
  return 0xffcc00; // Yellow
};

const getHeatOpacity = (percentage: number): number => {
  if (percentage >= 75) return 0.9;
  if (percentage >= 50) return 0.7;
  if (percentage >= 25) return 0.5;
  if (percentage >= 10) return 0.3;
  return 0.15;
};
