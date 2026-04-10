import { useEffect, useState } from "react";

export function useVideoPoster(src: string): string | null {
  const [poster, setPoster] = useState<string | null>(null);

  useEffect(() => {
    if (!src) {
      setPoster(null);
      return;
    }

    let cancelled = false;
    const video = document.createElement("video");
    video.src = src;
    video.muted = true;
    video.playsInline = true;
    video.preload = "auto";
    video.crossOrigin = "anonymous";

    const capture = () => {
      if (cancelled || video.videoWidth <= 0 || video.videoHeight <= 0) {
        return;
      }

      try {
        const canvas = document.createElement("canvas");
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const context = canvas.getContext("2d");
        if (!context) return;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        if (!cancelled) {
          setPoster(canvas.toDataURL("image/jpeg", 0.82));
        }
      } catch {
        if (!cancelled) {
          setPoster(null);
        }
      }
    };

    const handleLoadedData = () => {
      if (video.readyState >= 2) {
        const targetTime = Math.min(0.05, Number.isFinite(video.duration) ? Math.max(video.duration / 10, 0.01) : 0.05);
        try {
          video.currentTime = targetTime;
        } catch {
          capture();
        }
      }
    };

    const handleSeeked = () => capture();

    video.addEventListener("loadeddata", handleLoadedData);
    video.addEventListener("seeked", handleSeeked);
    video.load();

    return () => {
      cancelled = true;
      video.removeEventListener("loadeddata", handleLoadedData);
      video.removeEventListener("seeked", handleSeeked);
      video.pause();
      video.src = "";
    };
  }, [src]);

  return poster;
}
