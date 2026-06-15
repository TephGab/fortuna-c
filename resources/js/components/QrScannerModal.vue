<!-- resources/js/Components/QrScannerModal.vue -->

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 dark:bg-gray-900">
            <!-- Modal Header -->
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Scan QR Code') }}</h3>
                <button @click="close" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-800">
                    <X class="h-5 w-5 text-gray-500" />
                </button>
            </div>
            
            <!-- ==================== CAMERA SCANNER SECTION ==================== -->
            <div v-if="hasCamera" class="mb-4">
                <!-- Start Camera Button -->
                <button
                    v-if="!showCamera"
                    @click="startCamera"
                    class="mb-3 flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-3 text-white transition hover:bg-blue-700"
                >
                    <Camera class="h-5 w-5" />
                    <span>{{ t('Start Camera') }}</span>
                </button>
                
                <!-- Camera Preview with Scanning Overlay -->
                <div v-if="showCamera" class="relative overflow-hidden rounded-xl">
                    <!-- Camera Feed -->
                    <video ref="videoRef" class="h-96 w-full object-cover" autoplay playsinline muted></video>
                    
                    <!-- Scanning Overlay -->
                    <div class="absolute inset-0">
                        <div class="absolute inset-0 bg-black/50"></div>
                        
                        <!-- Scan Window -->
                        <div class="absolute left-1/2 top-1/2 h-64 w-64 -translate-x-1/2 -translate-y-1/2">
                            <div class="absolute inset-0 bg-transparent"></div>
                            
                            <!-- Corner borders -->
                            <div class="absolute left-0 top-0 h-8 w-8 border-l-4 border-t-4 border-emerald-500"></div>
                            <div class="absolute right-0 top-0 h-8 w-8 border-r-4 border-t-4 border-emerald-500"></div>
                            <div class="absolute bottom-0 left-0 h-8 w-8 border-b-4 border-l-4 border-emerald-500"></div>
                            <div class="absolute bottom-0 right-0 h-8 w-8 border-b-4 border-r-4 border-emerald-500"></div>
                            
                            <!-- Scanning line animation -->
                            <div class="absolute left-0 right-0 top-1/2 h-0.5 bg-emerald-500 shadow-lg animate-scan"></div>
                        </div>
                    </div>
                    
                    <!-- Top Bar with Controls -->
                    <div class="absolute left-0 right-0 top-3 flex items-center justify-between px-3">
                        <button
                            @click="switchCamera"
                            class="rounded-full bg-black/50 p-2 text-white backdrop-blur-sm transition hover:bg-black/70"
                            :disabled="isSwitchingCamera"
                            title="Switch Camera"
                        >
                            <RefreshCw class="h-5 w-5" />
                        </button>
                        
                        <div class="rounded-full bg-black/50 px-3 py-1 text-xs text-white backdrop-blur-sm">
                            {{ currentCameraMode === 'environment' ? '📷 Back' : '🤳 Front' }}
                        </div>
                        
                        <button
                            @click="stopCamera"
                            class="rounded-full bg-red-600/80 p-2 text-white backdrop-blur-sm transition hover:bg-red-700"
                            title="Stop Camera"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    
                    <!-- Status Text -->
                    <div class="absolute bottom-3 left-0 right-0 text-center">
                        <p class="text-sm text-white drop-shadow-lg">
                            {{ scanningStatus }}
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Divider -->
            <div v-if="hasCamera && !showCamera" class="relative my-4 text-center">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300 dark:border-gray-700"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="bg-white px-2 text-gray-500 dark:bg-gray-900 dark:text-gray-400">{{ t('or') }}</span>
                </div>
            </div>
            
            <!-- ==================== IMAGE UPLOAD SECTION ==================== -->
            <div class="rounded-xl border-2 border-dashed border-gray-300 p-8 text-center dark:border-gray-600">
                <QrCode class="mx-auto h-16 w-16 text-gray-400" />
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    {{ t('Upload a QR code image to pay') }}
                </p>
                <label class="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600">
                    <Upload class="h-4 w-4" />
                    {{ t('Choose Image') }}
                    <input 
                        type="file" 
                        accept="image/*" 
                        class="hidden" 
                        @change="handleFileUpload"
                        ref="fileInput"
                    />
                </label>
            </div>
            
            <p class="mt-4 text-center text-xs text-gray-500">
                {{ t('Make sure the QR code is clear and well-lit') }}
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { X, Camera, Upload, QrCode, RefreshCw } from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';
import { router } from '@inertiajs/vue3';
import jsQR from 'jsqr';

const { t } = useTranslation();

// ============================================================================
// PROPS & EMITS
// ============================================================================

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits(['close', 'decoded']);

// ============================================================================
// STATE VARIABLES
// ============================================================================

const videoRef = ref<HTMLVideoElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const showCamera = ref(false);
const hasCamera = ref(false);
const isSwitchingCamera = ref(false);
const currentCameraMode = ref<'environment' | 'user'>('environment');
const scanningStatus = ref('Position QR code in frame');
let stream: MediaStream | null = null;
let animationId: number | null = null;
let lastScanTime = 0;
const SCAN_INTERVAL = 100; // Scan every 100ms

// ============================================================================
// MODAL CONTROLS
// ============================================================================

const close = () => {
    stopCamera();
    emit('close');
};

// ============================================================================
// CAMERA SCANNING METHODS
// ============================================================================

/**
 * Get camera constraints
 */
const getCameraConstraints = () => {
    return {
        video: {
            facingMode: currentCameraMode.value,
            width: { ideal: 1280 },
            height: { ideal: 720 }
        }
    };
};

/**
 * Start the camera with current mode
 */
const startCamera = async () => {
    try {
        scanningStatus.value = 'Requesting camera access...';
        
        const constraints = getCameraConstraints();
        console.log('Camera constraints:', constraints);
        
        stream = await navigator.mediaDevices.getUserMedia(constraints);
        
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            await videoRef.value.play();
            showCamera.value = true;
            scanningStatus.value = 'Position QR code in the frame...';
            startScanning();
        }
    } catch (err) {
        console.error('Camera error:', err);
        
        // Fallback: Try simpler constraints
        try {
            scanningStatus.value = 'Trying default camera...';
            const fallbackConstraints = { video: true };
            stream = await navigator.mediaDevices.getUserMedia(fallbackConstraints);
            
            if (videoRef.value) {
                videoRef.value.srcObject = stream;
                await videoRef.value.play();
                showCamera.value = true;
                scanningStatus.value = 'Position QR code in the frame...';
                startScanning();
            }
        } catch (fallbackErr) {
            console.error('Fallback camera error:', fallbackErr);
            scanningStatus.value = 'Unable to access camera';
            alert(t('Unable to access camera. Please check permissions.'));
        }
    }
};

/**
 * Switch between front and back camera
 */
const switchCamera = async () => {
    if (isSwitchingCamera.value) return;
    
    isSwitchingCamera.value = true;
    scanningStatus.value = 'Switching camera...';
    
    const newMode = currentCameraMode.value === 'environment' ? 'user' : 'environment';
    
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
    
    try {
        const constraints = { video: { facingMode: newMode } };
        stream = await navigator.mediaDevices.getUserMedia(constraints);
        
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            await videoRef.value.play();
            currentCameraMode.value = newMode;
            scanningStatus.value = 'Position QR code in the frame...';
        }
    } catch (err) {
        console.error('Switch camera error:', err);
        scanningStatus.value = 'Failed to switch camera';
        
        try {
            const defaultConstraints = { video: true };
            stream = await navigator.mediaDevices.getUserMedia(defaultConstraints);
            if (videoRef.value) {
                videoRef.value.srcObject = stream;
                await videoRef.value.play();
                scanningStatus.value = 'Position QR code in the frame...';
            }
        } catch (recoverErr) {
            scanningStatus.value = 'Camera unavailable';
            showCamera.value = false;
        }
    } finally {
        isSwitchingCamera.value = false;
    }
};

/**
 * Stop the camera and clean up resources
 */
const stopCamera = () => {
    scanningStatus.value = 'Camera stopped';
    
    if (animationId) {
        cancelAnimationFrame(animationId);
        animationId = null;
    }
    
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
    
    showCamera.value = false;
};

/**
 * Enhanced QR code scanning - processes every frame for better detection
 */
const startScanning = () => {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    
    const scan = () => {
        if (!videoRef.value || !showCamera.value || !videoRef.value.videoWidth || !videoRef.value.videoHeight) {
            animationId = requestAnimationFrame(scan);
            return;
        }
        
        const video = videoRef.value;
        
        // Set canvas size to match video
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        if (ctx) {
            // Draw current video frame to canvas
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Get image data
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            
            // Try to decode QR code
            const code = jsQR(imageData.data, canvas.width, canvas.height, {
                inversionAttempts: "attemptBoth", // Try both normal and inverted colors
            });
            
            if (code) {
                scanningStatus.value = 'QR Code detected! Redirecting...';
                console.log('QR Code detected:', code.data);
                processDecodedData(code.data);
                stopCamera();
                close();
                return;
            }
        }
        
        // Continue scanning
        animationId = requestAnimationFrame(scan);
    };
    
    scan();
};

// ============================================================================
// IMAGE UPLOAD SCANNING
// ============================================================================

const handleFileUpload = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;
    
    const file = input.files[0];
    const img = new Image();
    const imageUrl = URL.createObjectURL(file);
    
    scanningStatus.value = 'Processing image...';
    
    img.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext('2d');
        
        if (ctx) {
            ctx.drawImage(img, 0, 0, img.width, img.height);
            const imageData = ctx.getImageData(0, 0, img.width, img.height);
            const code = jsQR(imageData.data, img.width, img.height, {
                inversionAttempts: "attemptBoth",
            });
            
            if (code) {
                scanningStatus.value = 'QR Code found! Redirecting...';
                processDecodedData(code.data);
                close();
            } else {
                scanningStatus.value = 'No QR code found in image';
                alert(t('No QR code found in the image. Please try another image.'));
            }
        }
        
        URL.revokeObjectURL(imageUrl);
    };
    
    img.onerror = () => {
        scanningStatus.value = 'Failed to load image';
        alert(t('Failed to load image. Please try another file.'));
        URL.revokeObjectURL(imageUrl);
    };
    
    img.src = imageUrl;
};

// ============================================================================
// QR CODE DATA PROCESSING
// ============================================================================

const processDecodedData = (data: string) => {
    console.log('Decoded QR data:', data);
    
    let recipientEmail = '';
    let amount: number | null = null;
    let description = '';
    
    try {
        const qrData = JSON.parse(data);
        
        if (qrData.email) recipientEmail = qrData.email;
        else if (qrData.user_email) recipientEmail = qrData.user_email;
        else if (qrData.requester_email) recipientEmail = qrData.requester_email;
        
        if (qrData.amount && qrData.amount > 0) amount = qrData.amount;
        if (qrData.description) description = qrData.description;
        
    } catch (err) {
        const emailMatch = data.match(/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/);
        if (emailMatch) recipientEmail = emailMatch[1];
    }
    
    if (recipientEmail) {
        const params = new URLSearchParams();
        params.append('recipient_email', recipientEmail);
        if (amount) params.append('amount', amount.toString());
        if (description) params.append('description', description);
        
        emit('decoded', { type: 'user', email: recipientEmail, amount, description });
        router.visit(`/transfers?${params.toString()}`);
        return;
    }
    
    alert(t('Invalid QR code. Please scan a valid payment QR code containing an email address.'));
};

// ============================================================================
// LIFECYCLE HOOKS
// ============================================================================

onMounted(() => {
    navigator.mediaDevices.enumerateDevices()
        .then(devices => {
            hasCamera.value = devices.some(device => device.kind === 'videoinput');
            console.log('Camera detected:', hasCamera.value);
        })
        .catch(() => {
            hasCamera.value = false;
            console.log('Could not enumerate devices');
        });
});

onUnmounted(() => {
    stopCamera();
});
</script>

<style scoped>
@keyframes scan {
    0% {
        top: 0%;
    }
    50% {
        top: 100%;
    }
    100% {
        top: 0%;
    }
}

.animate-scan {
    animation: scan 2s ease-in-out infinite;
}

button {
    cursor: pointer;
    min-width: 36px;
    min-height: 36px;
}
</style>