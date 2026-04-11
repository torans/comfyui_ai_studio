import { useEffect, useMemo, useRef, useState, type ChangeEvent, type PointerEvent } from 'react';
import { ImagePlus, Loader2, Trash2, Upload, ZoomIn } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const STAGE_SIZE = 360;
const CROP_BOX_SIZE = 260;
const OUTPUT_SIZE = 1024;
const MIN_ZOOM = 1;
const MAX_ZOOM = 3;

type Offset = { x: number; y: number };

interface WorkflowThumbUploadFieldProps {
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
}

const resolveThumbPreviewUrl = (value?: string | null): string | null => {
    if (!value) {
        return null;
    }

    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('data:')) {
        return value;
    }

    return value.startsWith('/') ? value : `/${value}`;
};

const getXsrfToken = (): string => {
    const matched = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return matched ? decodeURIComponent(matched) : '';
};

const clamp = (value: number, min: number, max: number) => Math.min(Math.max(value, min), max);

const getImageMetrics = (
    imageSize: { width: number; height: number } | null,
    zoom: number,
    offset: Offset,
) => {
    if (!imageSize) {
        return null;
    }

    const baseScale = Math.max(CROP_BOX_SIZE / imageSize.width, CROP_BOX_SIZE / imageSize.height);
    const totalScale = baseScale * zoom;
    const width = imageSize.width * totalScale;
    const height = imageSize.height * totalScale;
    const maxOffsetX = Math.max(0, (width - CROP_BOX_SIZE) / 2);
    const maxOffsetY = Math.max(0, (height - CROP_BOX_SIZE) / 2);
    const cropLeft = (STAGE_SIZE - CROP_BOX_SIZE) / 2;
    const cropTop = (STAGE_SIZE - CROP_BOX_SIZE) / 2;

    return {
        width,
        height,
        totalScale,
        maxOffsetX,
        maxOffsetY,
        cropLeft,
        cropTop,
        left: STAGE_SIZE / 2 - width / 2 + offset.x,
        top: STAGE_SIZE / 2 - height / 2 + offset.y,
    };
};

const clampOffset = (nextOffset: Offset, imageSize: { width: number; height: number } | null, zoom: number): Offset => {
    const metrics = getImageMetrics(imageSize, zoom, { x: 0, y: 0 });
    if (!metrics) {
        return nextOffset;
    }

    return {
        x: clamp(nextOffset.x, -metrics.maxOffsetX, metrics.maxOffsetX),
        y: clamp(nextOffset.y, -metrics.maxOffsetY, metrics.maxOffsetY),
    };
};

export function WorkflowThumbUploadField({ value, onChange, disabled = false }: WorkflowThumbUploadFieldProps) {
    const inputRef = useRef<HTMLInputElement | null>(null);
    const imageRef = useRef<HTMLImageElement | null>(null);
    const dragStateRef = useRef<{ startX: number; startY: number; origin: Offset } | null>(null);

    const [dialogOpen, setDialogOpen] = useState(false);
    const [sourceUrl, setSourceUrl] = useState<string | null>(null);
    const [sourceFileName, setSourceFileName] = useState('workflow-thumb.png');
    const [imageSize, setImageSize] = useState<{ width: number; height: number } | null>(null);
    const [zoom, setZoom] = useState(1);
    const [offset, setOffset] = useState<Offset>({ x: 0, y: 0 });
    const [uploading, setUploading] = useState(false);

    const previewUrl = useMemo(() => resolveThumbPreviewUrl(value), [value]);
    const imageMetrics = useMemo(() => getImageMetrics(imageSize, zoom, offset), [imageSize, zoom, offset]);

    useEffect(() => {
        return () => {
            if (sourceUrl) {
                URL.revokeObjectURL(sourceUrl);
            }
        };
    }, [sourceUrl]);

    useEffect(() => {
        setOffset((current) => clampOffset(current, imageSize, zoom));
    }, [imageSize, zoom]);

    useEffect(() => {
        if (!dialogOpen) {
            dragStateRef.current = null;
        }
    }, [dialogOpen]);

    const resetCropper = (nextUrl: string, fileName: string) => {
        if (sourceUrl) {
            URL.revokeObjectURL(sourceUrl);
        }

        setSourceUrl(nextUrl);
        setSourceFileName(fileName);
        setImageSize(null);
        setZoom(1);
        setOffset({ x: 0, y: 0 });
        setDialogOpen(true);
    };

    const handleFileSelected = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            toast.error('请选择图片文件');
            return;
        }

        const objectUrl = URL.createObjectURL(file);
        const extension = file.name.split('.').pop() || 'png';
        resetCropper(objectUrl, `workflow-thumb.${extension}`);
    };

    const handlePointerDown = (event: PointerEvent<HTMLDivElement>) => {
        if (!imageMetrics) {
            return;
        }

        dragStateRef.current = {
            startX: event.clientX,
            startY: event.clientY,
            origin: offset,
        };

        event.currentTarget.setPointerCapture(event.pointerId);
    };

    const handlePointerMove = (event: PointerEvent<HTMLDivElement>) => {
        const dragState = dragStateRef.current;
        if (!dragState) {
            return;
        }

        const deltaX = event.clientX - dragState.startX;
        const deltaY = event.clientY - dragState.startY;
        setOffset(
            clampOffset(
                {
                    x: dragState.origin.x + deltaX,
                    y: dragState.origin.y + deltaY,
                },
                imageSize,
                zoom,
            ),
        );
    };

    const handlePointerUp = (event: PointerEvent<HTMLDivElement>) => {
        dragStateRef.current = null;
        event.currentTarget.releasePointerCapture(event.pointerId);
    };

    const uploadCroppedImage = async () => {
        if (!sourceUrl || !imageRef.current || !imageMetrics) {
            return;
        }

        try {
            setUploading(true);

            const canvas = document.createElement('canvas');
            canvas.width = OUTPUT_SIZE;
            canvas.height = OUTPUT_SIZE;

            const context = canvas.getContext('2d');
            if (!context) {
                throw new Error('浏览器不支持图片裁剪');
            }

            const sourceX = Math.max(0, (imageMetrics.cropLeft - imageMetrics.left) / imageMetrics.totalScale);
            const sourceY = Math.max(0, (imageMetrics.cropTop - imageMetrics.top) / imageMetrics.totalScale);
            const sourceSize = CROP_BOX_SIZE / imageMetrics.totalScale;

            context.drawImage(
                imageRef.current,
                sourceX,
                sourceY,
                sourceSize,
                sourceSize,
                0,
                0,
                OUTPUT_SIZE,
                OUTPUT_SIZE,
            );

            const blob = await new Promise<Blob>((resolve, reject) => {
                canvas.toBlob((generatedBlob) => {
                    if (!generatedBlob) {
                        reject(new Error('裁剪结果生成失败'));
                        return;
                    }

                    resolve(generatedBlob);
                }, 'image/png');
            });

            const formData = new FormData();
            formData.append('file', new File([blob], sourceFileName.replace(/\.[^.]+$/, '') + '.png', { type: 'image/png' }));

            const response = await fetch('/admin/uploads/images', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: formData,
            });

            const rawPayload = await response.text();
            let payload: Record<string, unknown> = {};

            if (rawPayload) {
                try {
                    payload = JSON.parse(rawPayload) as Record<string, unknown>;
                } catch {
                    payload = {};
                }
            }

            if (!response.ok) {
                throw new Error((typeof payload?.message === 'string' && payload.message) || '缩略图上传失败');
            }

            onChange(typeof payload.url === 'string' ? payload.url : '');
            setDialogOpen(false);
            toast.success('缩略图已更新');
        } catch (error) {
            const message = error instanceof Error ? error.message : '缩略图上传失败';
            toast.error(message);
        } finally {
            setUploading(false);
        }
    };

    return (
        <div className="grid gap-3">
            <Label>缩略图</Label>

            <div className="workflow-thumb-field">
                <div className="workflow-thumb-preview">
                    {previewUrl ? (
                        <img src={previewUrl} alt="工作流缩略图" className="workflow-thumb-preview-image" />
                    ) : (
                        <div className="workflow-thumb-empty">
                            <ImagePlus className="size-8" />
                            <div className="space-y-1">
                                <p className="font-medium">上传工作流缩略图</p>
                                <p className="text-xs text-muted-foreground">建议 1:1 正方形，支持上传后裁剪</p>
                            </div>
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <input
                        ref={inputRef}
                        type="file"
                        accept="image/png,image/jpeg,image/webp,image/gif,image/bmp"
                        className="hidden"
                        onChange={handleFileSelected}
                        disabled={disabled || uploading}
                    />
                    <Button type="button" variant="outline" onClick={() => inputRef.current?.click()} disabled={disabled || uploading}>
                        <Upload />
                        {previewUrl ? '更换缩略图' : '上传图片'}
                    </Button>
                    {previewUrl ? (
                        <Button type="button" variant="ghost" onClick={() => onChange('')} disabled={disabled || uploading}>
                            <Trash2 />
                            移除
                        </Button>
                    ) : null}
                </div>
            </div>

            <Dialog open={dialogOpen} onOpenChange={(open) => !uploading && setDialogOpen(open)}>
                <DialogContent className="w-auto">
                    <DialogHeader>
                        <DialogTitle>裁剪缩略图</DialogTitle>
                        <DialogDescription>固定 1:1 比例。拖动图片位置，滑动缩放，确认后会上传裁剪结果。</DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-6 md:grid-cols-[minmax(0,1fr)_240px]">
                        <div className="workflow-crop-stage-wrap">
                            <div
                                className="workflow-crop-stage"
                                onPointerDown={handlePointerDown}
                                onPointerMove={handlePointerMove}
                                onPointerUp={handlePointerUp}
                            >
                                {sourceUrl ? (
                                    <img
                                        ref={imageRef}
                                        src={sourceUrl}
                                        alt="待裁剪图片"
                                        className="workflow-crop-image"
                                        style={
                                            imageMetrics
                                                ? {
                                                      width: `${imageMetrics.width}px`,
                                                      height: `${imageMetrics.height}px`,
                                                      left: '50%',
                                                      top: '50%',
                                                      transform: `translate(calc(-50% + ${offset.x}px), calc(-50% + ${offset.y}px))`,
                                                  }
                                                : undefined
                                        }
                                        onLoad={(event) => {
                                            setImageSize({
                                                width: event.currentTarget.naturalWidth,
                                                height: event.currentTarget.naturalHeight,
                                            });
                                        }}
                                        draggable={false}
                                    />
                                ) : null}
                                <div className="workflow-crop-mask" aria-hidden="true" />
                                <div className="workflow-crop-frame" aria-hidden="true" />
                            </div>
                        </div>

                        <div className="grid gap-4">
                            <div className="workflow-crop-panel">
                                <span className="text-sm font-medium">预览</span>
                                <div className="workflow-crop-preview-card">
                                    {sourceUrl ? (
                                        <div className="workflow-crop-preview-window">
                                            <img
                                                src={sourceUrl}
                                                alt="缩略图预览"
                                                className="workflow-crop-image"
                                                style={
                                                    imageMetrics
                                                        ? {
                                                              width: `${imageMetrics.width}px`,
                                                              height: `${imageMetrics.height}px`,
                                                              left: '50%',
                                                              top: '50%',
                                                              transform: `translate(calc(-50% + ${offset.x}px), calc(-50% + ${offset.y}px))`,
                                                          }
                                                        : undefined
                                                }
                                                draggable={false}
                                            />
                                        </div>
                                    ) : null}
                                </div>
                            </div>

                            <div className="workflow-crop-panel">
                                <div className="flex items-center justify-between text-sm font-medium">
                                    <span>缩放</span>
                                    <span className="text-muted-foreground">{zoom.toFixed(2)}x</span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <ZoomIn className="size-4 text-muted-foreground" />
                                    <input
                                        type="range"
                                        min={MIN_ZOOM}
                                        max={MAX_ZOOM}
                                        step={0.01}
                                        value={zoom}
                                        onChange={(event) => setZoom(Number(event.target.value))}
                                        className="w-full"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setDialogOpen(false)} disabled={uploading}>
                            取消
                        </Button>
                        <Button type="button" onClick={uploadCroppedImage} disabled={uploading || !imageMetrics}>
                            {uploading ? <Loader2 className="animate-spin" /> : null}
                            {uploading ? '上传中...' : '确认并上传'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
