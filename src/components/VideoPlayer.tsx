import { useEffect, useRef } from "react";
import { useVideoPoster } from "../hooks/useVideoPoster";

type VideoPlayerProps = {
  src: string;
  autoPlay?: boolean;
  className?: string;
  compact?: boolean;
  stopPropagation?: boolean;
};

export default function VideoPlayer({
  src,
  autoPlay = false,
  className,
  compact = false,
  stopPropagation = false,
}: VideoPlayerProps) {
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const poster = useVideoPoster(src);

  useEffect(() => {
    if (!videoRef.current) return;
    videoRef.current.currentTime = 0;
    if (autoPlay) {
      void videoRef.current.play().catch(() => {});
    } else {
      videoRef.current.pause();
    }
  }, [src, autoPlay]);

  return (
    <div
      className={`video-player native ${compact ? "compact" : ""} ${className ?? ""}`.trim()}
      onClick={stopPropagation ? (event) => event.stopPropagation() : undefined}
    >
      <div className="video-player-frame">
        <video
          ref={videoRef}
          src={src}
          controls
          playsInline
          preload="metadata"
          autoPlay={autoPlay}
          poster={poster ?? undefined}
        />
      </div>
    </div>
  );
}
