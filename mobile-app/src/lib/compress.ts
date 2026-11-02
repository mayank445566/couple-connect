import { ImageManipulator, SaveFormat } from 'expo-image-manipulator';

const MAX_SIDE = 1600;

/**
 * Phone photos are often 4 to 12 MB. Shrinking to 1600 px and JPEG 78% keeps them sharp on a phone
 * screen at about 300 to 600 KB, so they upload quickly on mobile data and stay under host upload limits.
 * If anything fails, the original file is sent instead.
 */
export async function shrinkPhoto(file: { uri: string; name: string; type: string; width?: number; height?: number }) {
  if (file.type === 'image/gif') return file;
  try {
    const ctx = ImageManipulator.manipulate(file.uri);
    const w = file.width ?? 0;
    const h = file.height ?? 0;
    if (!w || !h || Math.max(w, h) > MAX_SIDE) ctx.resize(w >= h ? { width: Math.min(w || MAX_SIDE, MAX_SIDE) } : { height: MAX_SIDE });
    const img = await ctx.renderAsync();
    const out = await img.saveAsync({ compress: 0.78, format: SaveFormat.JPEG });
    return { uri: out.uri, name: file.name.replace(/\.[a-z0-9]+$/i, '') + '.jpg', type: 'image/jpeg' };
  } catch {
    return file;
  }
}
