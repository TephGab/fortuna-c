<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Scan QR Code') }}</h3>
                <button @click="close" class="rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-gray-800">
                    <X class="h-5 w-5 text-gray-500" />
                </button>
            </div>
            
            <!-- Camera Option -->
            <div v-if="hasCamera" class="mb-4">
                <button
                    @click="startCamera"
                    class="mb-3 flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 py-3 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                >
                    <Camera class="h-5 w-5" />
                    <span>{{ t('Use Camera') }}</span>
                </button>
                
                <div v-if="showCamera" class="relative overflow-hidden rounded-xl">
                    <video ref="videoRef" class="h-64 w-full object-cover" autoplay playsinline></video>
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-xl border-2 border-blue-500">
                        <div class="h-48 w-48 rounded-lg border-2 border-blue-500"></div>
                    </div>
                    <button
                        @click="captureFromCamera"
                        class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-blue-600 p-3 text-white shadow-lg"
                    >
                        <Camera class="h-6 w-6" />
                    </button>
                </div>
            </div>
            
            <div v-if="hasCamera && showCamera" class="relative my-4 text-center">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300 dark:border-gray-700"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="bg-white px-2 text-gray-500 dark:bg-gray-900 dark:text-gray-400">{{ t('or') }}</span>
                </div>
            </div>
            
            <!-- Upload Option -->
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
import { X, Camera, Upload, QrCode } from 'lucide-vue-next';
import { useTranslation } from '@/composables/useTranslation';
import { router } from '@inertiajs/vue3';
import jsQR from 'jsqr';

const { t } = useTranslation();

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits(['close', 'decoded']);

const videoRef = ref<HTMLVideoElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const showCamera = ref(false);
const hasCamera = ref(false);
let stream: MediaStream | null = null;
let animationId: number | null = null;

const close = () => {
    stopCamera();
    emit('close');
};

const startCamera = async () => {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            await videoRef.value.play();
            showCamera.value = true;
            startScanning();
        }
    } catch (err) {
        console.error('Camera error:', err);
        alert(t('Unable to access camera'));
    }
};

const stopCamera = () => {
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

const startScanning = () => {
    const scan = () => {
        if (!videoRef.value || !showCamera.value) return;
        
        const canvas = document.createElement('canvas');
        const video = videoRef.value;
        
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            if (ctx) {
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, canvas.width, canvas.height);
                if (code) {
                    console.log('QR Code detected:', code.data);
                    processDecodedData(code.data);
                    stopCamera();
                    close();
                }
            }
        }
        
        animationId = requestAnimationFrame(scan);
    };
    
    scan();
};

const captureFromCamera = () => {
    if (!videoRef.value) return;
    
    const canvas = document.createElement('canvas');
    const video = videoRef.value;
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    
    if (ctx) {
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, canvas.width, canvas.height);
        
        if (code) {
            processDecodedData(code.data);
            stopCamera();
            close();
        } else {
            alert(t('No QR code found. Please try again.'));
        }
    }
};

const handleFileUpload = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;
    
    const file = input.files[0];
    const img = new Image();
    const imageUrl = URL.createObjectURL(file);
    
    img.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext('2d');
        
        if (ctx) {
            ctx.drawImage(img, 0, 0, img.width, img.height);
            const imageData = ctx.getImageData(0, 0, img.width, img.height);
            const code = jsQR(imageData.data, img.width, img.height);
            
            if (code) {
                console.log('QR Code from image:', code.data);
                processDecodedData(code.data);
                close();
            } else {
                alert(t('No QR code found in the image. Please try another image.'));
            }
        }
        
        URL.revokeObjectURL(imageUrl);
    };
    
    img.onerror = () => {
        alert(t('Failed to load image. Please try another file.'));
        URL.revokeObjectURL(imageUrl);
    };
    
    img.src = imageUrl;
};

/**
 * Process decoded QR code data
 * Expected formats:
 * 1. Plain email: user@example.com
 * 2. Any text containing an email
 */
const processDecodedData = (data: string) => {
    console.log('Processing decoded data:', data);
    console.log('Data type:', typeof data);
    console.log('Data length:', data.length);
    
    // Clean the data - remove any weird characters
    let cleanData = data.trim();
    
    // Try to extract email using regex
    const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/;
    const emailMatch = cleanData.match(emailRegex);
    
    if (emailMatch && emailMatch[0]) {
        const email = emailMatch[0];
        console.log('Extracted email:', email);
        
        // Build URL with email parameter
        const params = new URLSearchParams();
        params.append('recipient_email', email);
        
        // Redirect to send money page
        router.visit(`/transfers?${params.toString()}`);
        return;
    }
    
    // If no email found, show error
    console.error('No email found in QR code:', cleanData);
    alert(t('Invalid QR code. Please scan a valid payment QR code containing an email address.'));
};

onMounted(() => {
    navigator.mediaDevices.enumerateDevices()
        .then(devices => {
            hasCamera.value = devices.some(device => device.kind === 'videoinput');
        })
        .catch(() => {
            hasCamera.value = false;
        });
});

onUnmounted(() => {
    stopCamera();
});
</script>